<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of the {@see metrics_manager} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring;

use ArrayAccess;
use core\di;
use core\exception\coding_exception;
use core\hook\manager as hook_manager;
use core_cache\data_source_interface as cache_data_source_interface;
use core_cache\definition as cache_definition;
use dml_exception;
use Exception;
use IteratorAggregate;
use tool_monitoring\exceptions\metric_not_found;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\metrics_cache;
use Traversable;

/**
 * Linchpin of the monitoring API and container for all registered metrics.
 *
 * Registers new {@see metric}s picked up by the {@see metric_collection} hook and provides access to registered ones.
 *
 * Emulates an associative array of {@see registered_metric} instances. Provides subscript-access to specific registered metrics by
 * their qualified name. (See the {@see self::offsetExists `offsetExists`} and {@see self::offsetGet `offsetGet`} methods.)
 * **NOTE**: Access is read-only. Metrics cannot be added to or removed from the manager directly.
 *
 * ```
 * $manager = new metrics_manager();
 * if (isset($manager['my_metric']) { // This works.
 *     $metric = $manager['my_metric']; // This also works.
 *     unset($manager['my_metric']); // Error!
 * }
 * $manager['my_metric'] = $something; // Error!
 * ```
 *
 * Iterating over a manager instance yields registered metrics indexed by their qualified name.
 * (See {@see self::getIterator `getIterator`}.)
 * Filters can be set on a manager instance with the {@see self::filter `filter`} method.
 *
 * ```
 * $metrics = new metrics_manager();
 * foreach ($metrics as $qname => $metric) {
 *     // Now `$qname` is a string and `$metric` is a `registered_metric` object.
 * }
 * // Filter out disabled metrics:
 * $metrics->filter(enabled: true);
 * // This now only yields enabled metrics with the 'foo' tag:
 * foreach ($metrics->filter(tagnames: ['foo']) as $qname => $metric) {
 *     // Do something.
 * }
 * ```
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class metrics_manager implements ArrayAccess, IteratorAggregate, cache_data_source_interface {
    /** @var self Instance of the class for the cache data source implementation. */
    private static self $cachedatasource;

    /** @var array<string, registered_metric> Internal store of all registered metrics indexed by their qualified name. */
    private array $metrics = [];

    /** @var array<string, metric_tag> Current tags filter; tags indexed by normalized name. */
    private array $filtertags = [];

    /** @var bool|null Current enabled state filter. */
    private bool|null $filterenabled = null;

    /**
     * Constructor without additional logic.
     *
     * @param metric_collection $collection Metric collection to manage; defaults to a new empty collection.
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    public function __construct(
        /** @var metric_collection Metric collection to manage; defaults to a new empty collection. */
        public readonly metric_collection $collection = new metric_collection()
    ) {}

    /**
     * Dispatches the managed {@see self::$collection `collection`} hook allowing callbacks to add metrics.
     *
     * @link https://moodledev.io/docs/apis/core/hooks#hook-emitter Documentation: Hook emitter
     *
     * @return $this Same instance.
     */
    public function dispatch_hook(): self {
        di::get(hook_manager::class)->dispatch($this->collection);
        return $this;
    }

    /**
     * Sets filters on the metrics iterator.
     *
     * @param bool|null $enabled If `true`, the instance will yield only enabled metrics; if `false`, it yields only disabled ones;
     *                           passing `null` (default) disables this filter.
     * @param string[] $tagnames Names of tags to filter by. Only metrics that carry all the specified tags will be yielded.
     *                           Names will be normalized before looking up the tags. An empty array (default) disables this filter.
     * @return $this Same instance.
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found At least one of the provided `$tagnames` does not match any existing metric tag.
     */
    public function filter(bool|null $enabled = null, array $tagnames = []): self {
        $this->filterenabled = $enabled;
        $this->filtertags = metric_tag::get_all_with_names(...$tagnames);
        return $this;
    }

    /**
     * Efficiently synchronizes the managed metric collection with the database.
     *
     * Ensures that a corresponding entry in the database exists for every unique metric in the collection (per qualified name).
     * Optionally deletes every database entry that does not correspond to any metric in the collection.
     *
     * This function issues exactly three `SELECT`, one `DELETE` (optional), and one `INSERT` queries.
     *
     * @param bool $collect If `true` (default), calls the {@see self::dispatch_hook `dispatch_hook`} method first.
     *                      **WARNING**: Failing to collect relevant metrics first will cause data loss if `$delete` is `true`.
     * @param bool $delete If `true`, deletes every database entry that does not correspond to any metric in the collection, and
     *                     triggers individual deletion events for all deleted database records.
     * @return $this Same instance.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function sync(bool $collect = true, bool $delete = false): self {
        global $DB, $USER;
        if ($collect) {
            $this->dispatch_hook();
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            // Get a `registered_metric` instance for every metric we collected.
            // Some may have no DB record and thus a `null` ID; those will need to be inserted.
            $this->metrics = registered_metric::get_for_metrics(...iterator_to_array($this->collection));
            // Prepare records for insertion and remember the existing IDs of collected-and-registered metrics.
            $existingids = [];
            $toinsert = [];
            $currenttime = time();
            foreach ($this->metrics as $qname => $metric) {
                if (is_null($metric->id)) {
                    $metric->timecreated = $currenttime;
                    $metric->timemodified = $currenttime;
                    $metric->usermodified = $USER->id;
                    $toinsert[$qname] = $metric->to_db();
                } else {
                    $existingids[] = $metric->id;
                }
            }
            // Insert in bulk.
            if ($toinsert) {
                $DB->insert_records(registered_metric::TABLE, $toinsert);
            }
            // Fetch all records that we did not get before.
            // These should only be newly inserted ones (if any) and orphans (without a collected metric).
            [$notexistingsql, $notexistingparams] = $DB->get_in_or_equal($existingids, equal: false, onemptyitems: null);
            $sqlqname = $DB->sql_concat_join(separator: "'_'", elements: ['component', 'name']);
            $otherids = $DB->get_records_select_menu(
                table:  registered_metric::TABLE,
                select: "id $notexistingsql",
                params: $notexistingparams,
                fields: "$sqlqname AS qname, id",
            );
            // Assign the newly inserted IDs and remove them from the array.
            foreach (array_keys($toinsert) as $qname) {
                $this->metrics[$qname]->id = $otherids[$qname];
                unset($otherids[$qname]);
            }
            // At this point `$other` should only contain orphan IDs.
            if ($delete) {
                [$oprphansql, $orphanparams] = $DB->get_in_or_equal($otherids, onemptyitems: null);
                $DB->delete_records_select(registered_metric::TABLE, "id $oprphansql", $orphanparams);
                // TODO: Trigger individual deletion events here.
            }
            $transaction->allow_commit();
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            if (!empty($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($e);
            }
            throw $e;
        }
        // @codeCoverageIgnoreEnd
        metrics_cache::set(...$this->metrics);
        return $this;
    }

    /**
     * Returns a registered metric by its qualified name.
     *
     * Attempts to get it from the {@see self::$metrics `registeredmetrics`} array first, then from the cache second,
     * and only queries the DB as a last resort through {@see self::load_for_cache `load_for_cache`}.
     *
     * **NOTE**: This method does explicit `null`-caching for those names that are not in the DB. This means if a name was not
     * found in the DB, that fact will be cached. Subsequent calls with the same name will no longer check the DB until the cache
     * is cleared.
     *
     * @param string $qualifiedname Qualified name of the metric to return.
     * @return registered_metric|null The metric with the given qualified name, or `null` if it is not found/registered.
     * @throws coding_exception
     */
    private function get_metric(string $qualifiedname): registered_metric|null {
        if ($this->metrics) {
            return $this->metrics[$qualifiedname] ?? null;
        }
        return metrics_cache::get($qualifiedname);
    }

    /**
     * Produces registered metrics for the managed collection.
     *
     * If filters were set via the {@see self::filter `filter`} method, yields only those metrics that match the filter criteria.
     *
     * @return Traversable<string, registered_metric> Registered metrics indexed by their qualified name.
     * @throws coding_exception
     */
    #[\Override]
    public function getIterator(): Traversable {
        if (!$this->metrics) {
            $this->dispatch_hook();
            $qnames = [];
            foreach ($this->collection as $metric) {
                $qnames[] = registered_metric::get_qualified_name($metric::get_component(), $metric::get_name());
            }
            $this->metrics = array_filter(metrics_cache::get_many(...$qnames));
        }
        foreach ($this->metrics as $qname => $metric) {
            if (!is_null($this->filterenabled) && $metric->enabled !== $this->filterenabled) {
                continue;
            }
            if (array_diff_key($this->filtertags, $metric->tags)) {
                continue;
            }
            yield $qname => $metric;
        }
    }

    /**
     * Checks whether a metric with the given qualified name is registered.
     *
     * @param string $offset Qualified name of the metric to check.
     * @return bool `true` if the metric is registered, `false` otherwise.
     * @throws coding_exception
     */
    #[\Override]
    public function offsetExists(mixed $offset): bool {
        return !is_null($this->get_metric($offset));
    }

    /**
     * Returns the registered metric with the given qualified name.
     *
     * @param string $offset Qualified name of the metric to return.
     * @return registered_metric Metric with the given qualified name.
     * @throws coding_exception
     * @throws metric_not_found No metric with the given qualified name is registered.
     */
    #[\Override]
    public function offsetGet(mixed $offset): registered_metric {
        if ($metric = $this->get_metric($offset)) {
            return $metric;
        }
        throw new metric_not_found($offset);
    }

    /**
     * Always throws an exception because the managed metrics are read-only.
     *
     * @param mixed $offset Ignored
     * @param mixed $value Ignored
     * @throws coding_exception
     */
    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void {
        throw new coding_exception('Cannot manually set metrics.');
    }

    /**
     * Always throws an exception because the managed metrics are read-only.
     *
     * @param mixed $offset Ignored
     * @throws coding_exception
     */
    #[\Override]
    public function offsetUnset(mixed $offset): void {
        throw new coding_exception('Cannot manually unset metrics.');
    }

    /**
     * Required for the {@see cache_data_source_interface `core_cache\data_source_interface`}.
     *
     * @param cache_definition $definition Cache definition object.
     * @return self Instance of the class.
     */
    #[\Override]
    public static function get_instance_for_cache(cache_definition $definition): self {
        if (!isset(self::$cachedatasource)) {
            self::$cachedatasource = new self();
            self::$cachedatasource->dispatch_hook();
        }
        return self::$cachedatasource;
    }

    /**
     * Fetches a {@see registered_metric} instance with the given qualified name from the DB.
     *
     * **NOTE**: This method facilitates explicit `null`-caching for names that are not in the DB. This means if a name was not
     * found in the DB, that fact will be cached. Subsequent attempts to get a metric with that name from the cache will no longer
     * check the DB but return `null` immediately until the cache is cleared.
     *
     * @param string $key Qualified name of the metric to fetch.
     * @return registered_metric|null Metric instance or `null` if no matching metric was not found in the DB.
     * @throws dml_exception
     */
    #[\Override]
    public function load_for_cache($key): registered_metric|null {
        $metrics = $this->load_many_for_cache([$key]);
        if (!array_key_exists($key, $metrics)) {
            return null;
        }
        return $metrics[$key] ?: null;
    }

    /**
     * Fetches {@see registered_metric} instances with the given qualified from the DB.
     *
     * @param string[] $keys Qualified names of the metrics to fetch.
     * @return array<string, registered_metric|false> Associative array indexed with `$keys` mapped to {@see registered_metric}
     *                                                instances or `false` if no matching metric was not found in the DB.
     * @throws dml_exception
     */
    #[\Override]
    public function load_many_for_cache(array $keys): array {
        $output = array_fill_keys($keys, false);
        $metrics = [];
        foreach ($this->collection as $metric) {
            $qname = registered_metric::get_qualified_name($metric::get_component(), $metric::get_name());
            if (array_key_exists($qname, $output)) {
                $metrics[$qname] = $metric;
            }
        }
        $registeredmetrics = array_filter(
            array:    registered_metric::get_for_metrics(...$metrics),
            callback: fn (registered_metric $metric): bool => !is_null($metric->id),
        );
        return array_merge($output, $registeredmetrics);
    }
}

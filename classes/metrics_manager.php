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
use core_cache\data_source_interface as cache_data_source_interface;
use core_cache\definition as cache_definition;
use dml_exception;
use Exception;
use JsonException;
use tool_monitoring\exceptions\metric_not_found;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\metrics_cache;

/**
 * Linchpin of the monitoring API and container for all registered metrics.
 *
 * Registers new {@see metric}s picked up by the {@see metric_collection} hook and provides access to registered ones.
 *
 * Provides array-like subscript access to {@see registered_metric} instances by their qualified name.
 * (See the {@see self::offsetExists `offsetExists`} and {@see self::offsetGet `offsetGet`} methods.)
 * **NOTE**: Access is read-only. Metrics cannot be added to or removed from the manager directly.
 *
 * ```
 * $manager = di::get(metrics_manager::class);
 * if (isset($manager['my_metric']) { // This works.
 *     $metric = $manager['my_metric']; // This also works.
 *     unset($manager['my_metric']); // Error!
 * }
 * $manager['my_metric'] = $something; // Error!
 * ```
 *
 * Collected and registered metrics can be retrieved via the {@see self::filter `filter`} method.
 * Omitting any arguments will return all of them.
 *
 * ```
 * $manager = di::get(metrics_manager::class);
 * // Only get enabled metrics that carry the 'foo' tag:
 * foreach ($manager->filter(enabled: true, tagnames: ['foo']) as $qname => $metric) {
 *     // Now `$qname` is a string and `$metric` is a `registered_metric` object.
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
final readonly class metrics_manager implements ArrayAccess, cache_data_source_interface {
    /**
     * Constructor without additional logic.
     *
     * In production code, the constructor should likely never be called directly. Instead, use {@see di::get} to retrieve an
     * instance from Moodle's dependency injection container like so:
     *
     * ```
     * $manager = di::get(metrics_manager::class);
     * ```
     *
     * This way, the manager will always have an already dispatched {@see metric_collection}.
     *
     * @param metric_collection $collection Metric collection to manage.
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    public function __construct(
        /** @var metric_collection Metric collection being managed. */
        public metric_collection $collection
    ) {}

    /**
     * Produces **registered** metrics for the managed metrics collection.
     *
     * If filters were set via the {@see self::filter `filter`} method, yields only those metrics that match the filter criteria.
     *
     * Will _not_ produce metrics that are not (yet) registered in the database, even if they were picked up by the
     * {@see metric_collection} hook. To ensure all collected metrics are registered, call {@see self::sync `sync`} first.
     *
     * Implementation detail: Tries to load {@see registered_metric} instances for all metrics in the collection from the cache
     * first. Since the {@see metrics_manager} is defined as the cache data source, cache misses will trigger the
     * {@see self::load_many_for_cache `load_many_for_cache`} method, which will query the database for the missing metrics and also
     * automatically update the cache afterwards.
     * Explicit `null`-caching is done, when a metric is not found in the DB. The {@see self::sync `sync`} method must be called
     * to register a newly collected metric and that method also re-builds the cache.
     *
     * @param bool|null $enabled If `true`, the only enabled metrics will be returned; if `false`, only disabled ones;
     *                           passing `null` (default) disables this filter.
     * @param string[] $tagnames Names of tags to filter by. Only metrics that carry all the specified tags will be returned.
     *                           Names will be normalized before looking up the tags. An empty array (default) disables this filter.
     * @return array<string, registered_metric> Registered metrics indexed by their qualified name.
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found At least one of the provided `$tagnames` does not match any existing metric tag.
     */
    public function filter(bool|null $enabled = null, array $tagnames = []): array {
        $tags = metric_tag::get_all_with_names(...$tagnames);
        $qnames = [];
        foreach ($this->collection as $metric) {
            $qname = registered_metric::get_qualified_name($metric::get_component(), $metric::get_name());
            $qnames[$qname] = true;
        }
        return array_filter(
            metrics_cache::get_many(...array_keys($qnames)),
            fn (registered_metric|null $metric): bool
            => !is_null($metric) && (is_null($enabled) || $metric->enabled === $enabled) && !array_diff_key($tags, $metric->tags),
        );
    }

    /**
     * Efficiently synchronizes the managed metric collection with the database.
     *
     * Ensures that a corresponding entry in the database exists for every unique metric in the collection (per qualified name).
     * Optionally deletes every database entry that does not correspond to any metric in the collection.
     *
     * This function issues exactly three `SELECT`, one `DELETE` (optional), and one `INSERT` queries.
     *
     * @param bool $delete If `true`, deletes every database entry that does not correspond to any metric in the collection, and
     *                     triggers individual deletion events for all deleted database records.
     * @return $this Same instance.
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException A metric is configurable but it's default config could not be serialized.
     */
    public function sync(bool $delete = false): self {
        global $DB, $USER;
        try {
            $transaction = $DB->start_delegated_transaction();
            // Get a `registered_metric` instance for every metric we collected.
            // Some may have no DB record and thus a `null` ID; those will need to be inserted.
            $metrics = registered_metric::get_for_metrics(...iterator_to_array($this->collection));
            // Prepare records for insertion and remember the existing IDs of collected-and-registered metrics.
            $existingids = [];
            $toinsert = [];
            $currenttime = time();
            foreach ($metrics as $qname => $metric) {
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
                $metrics[$qname]->id = $otherids[$qname];
                unset($otherids[$qname]);
            }
            // At this point `$otherids` should only contain orphan IDs.
            if ($delete) {
                foreach ($otherids as $id) {
                    metric_tag::remove_all_for_metric($id);
                }
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
        metrics_cache::purge();
        metrics_cache::set(...$metrics);
        return $this;
    }

    /**
     * Checks whether a metric with the given qualified name is registered.
     *
     * Will return `false` if no metric with the given qualified name is (yet) registered in the database, even one was picked up by
     * the {@see metric_collection} hook. To ensure all collected metrics are registered, call {@see self::sync `sync`} first.
     *
     * @param string $offset Qualified name of the metric to check.
     * @return bool `true` if the metric is registered, `false` otherwise.
     * @throws coding_exception
     */
    #[\Override]
    public function offsetExists(mixed $offset): bool {
        try {
            $this->offsetGet($offset);
            return true;
        } catch (metric_not_found) {
            return false;
        }
    }

    /**
     * Returns the registered metric with the given qualified name.
     *
     * Will _not_ return a metric not (yet) registered in the database, even if it was picked up by the {@see metric_collection}
     * hook. To ensure all collected metrics are registered, call {@see self::sync `sync`} first.
     *
     * Implementation detail: Tries to load the requested {@see registered_metric} instance from the cache first.
     * Since the {@see metrics_manager} is defined as the cache data source, a cache miss will trigger the
     * {@see self::load_for_cache `load_for_cache`} method, which will query the database for the missing metric and also
     * automatically update the cache afterwards.
     *
     * @param string $offset Qualified name of the metric to return.
     * @return registered_metric Metric with the given qualified name.
     * @throws coding_exception
     * @throws metric_not_found No metric with the given qualified name is registered.
     */
    #[\Override]
    public function offsetGet(mixed $offset): registered_metric {
        if (is_null($metric = metrics_cache::get($offset))) {
            throw new metric_not_found($offset);
        }
        return $metric;
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
        return di::get(self::class);
    }

    /**
     * Fetches a {@see registered_metric} instance with the given qualified name from the DB.
     *
     * Implementation detail: This method facilitates null-caching if the key either does not match any collected metric or refers
     * to a metric that has not (yet) been registered in the database.
     *
     * @param string $key Qualified name of the metric to fetch.
     * @return registered_metric|null Metric instance or `null` if no matching metric was not found in the DB.
     * @throws dml_exception
     * @throws JsonException A metric is configurable but it's default config could not be serialized.
     */
    #[\Override]
    public function load_for_cache($key): registered_metric|null {
        $metrics = $this->load_many_for_cache([$key]);
        return $metrics[$key];
    }

    /**
     * Fetches {@see registered_metric} instances with the given qualified names from the DB.
     *
     * Implementation detail: This method facilitates null-caching for keys that either do not match any collected metric and those
     * that refer to metrics that have not (yet) been registered in the database.
     *
     * @param string[] $keys Qualified names of the metrics to fetch.
     * @return array<string, registered_metric|null> Associative array indexed with `$keys` mapped to {@see registered_metric}
     *                                               instances or `null` if no matching metric was not found in the DB.
     * @throws dml_exception
     * @throws JsonException A metric is configurable but it's default config could not be serialized.
     */
    #[\Override]
    public function load_many_for_cache(array $keys): array {
        $output = array_fill_keys($keys, null);
        $metrics = [];
        foreach ($this->collection as $metric) {
            $qname = registered_metric::get_qualified_name($metric::get_component(), $metric::get_name());
            if (array_key_exists($qname, $output)) {
                $metrics[$qname] = $metric;
            }
        }
        $registeredmetrics = array_filter(
            array:    registered_metric::get_for_metrics(...$metrics), // The function discards variadic argument names.
            callback: fn (registered_metric $metric): bool => !is_null($metric->id),
        );
        return array_merge($output, $registeredmetrics);
    }
}

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
use tool_monitoring\exceptions\metric_name_invalid;
use tool_monitoring\exceptions\metric_not_found;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\exceptions\tags_disabled;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\managed_metric;
use tool_monitoring\local\metric_record;
use tool_monitoring\local\metrics_cache;

/**
 * Linchpin of the monitoring API and container for all registered metrics.
 *
 * Registers new {@see metric}s picked up by the {@see metric_collection} hook and provides access to registered ones.
 *
 * Provides array-like subscript access to {@see managed_metric} instances by their qualified name.
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
     * Implementation detail: Tries to load {@see managed_metric} instances for all metrics in the collection from the cache
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
     * @return array<string, managed_metric> Registered metrics indexed by their qualified name.
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found At least one of the provided `$tagnames` does not match any existing metric tag.
     * @throws tags_disabled
     */
    public function filter(bool|null $enabled = null, array $tagnames = []): array {
        if ($tagnames && !metric_tag::is_enabled()) {
            throw new tags_disabled(metric_tag::ITEM_TYPE);
        }
        $tags = metric_tag::get_all_with_names(...$tagnames);
        $qnames = [];
        foreach ($this->collection as $metric) {
            $qnames[] = metric_record::get_qualified_name($metric->get_component(), $metric->get_name());
        }
        return array_filter(
            metrics_cache::get_many(...$qnames),
            fn (managed_metric|null $cachedmetric): bool
            => !is_null($cachedmetric)
               && (is_null($enabled) || $cachedmetric->enabled === $enabled)
               && !array_diff_key($tags, $cachedmetric->tags),
        );
    }

    /**
     * Efficiently synchronizes the managed metric collection with the database.
     *
     * Ensures that a corresponding entry in the database exists for every unique metric in the collection (per qualified name).
     * Optionally deletes every database entry that does not correspond to any metric in the collection.
     *
     * @param bool $delete If `true`, deletes every database entry that does not correspond to any metric in the collection, and
     *                     triggers individual deletion events for all deleted database records.
     * @return $this Same instance.
     * @throws coding_exception
     * @throws dml_exception
     * @throws metric_name_invalid
     */
    public function sync(bool $delete = false): self {
        global $DB;
        $collection = $this->validate_collection();
        try {
            $transaction = $DB->start_delegated_transaction();
            $metrics = managed_metric::get_or_register(...$collection);
            if ($delete) {
                [$orphansql, $orphanparams] = $DB->get_in_or_equal(
                    items: array_column($metrics, 'id'),
                    equal: false,
                    onemptyitems: null,
                );
                $orphanids = $DB->get_fieldset_select(metric_record::TABLE, 'id', "id $orphansql", $orphanparams);
                foreach ($orphanids as $id) {
                    metric_tag::remove_all_for_metric($id);
                }
                $DB->delete_records_select(metric_record::TABLE, "id $orphansql", $orphanparams);
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
     * Ensures that the managed metric collection is valid.
     *
     * @return metric[] Validated {@see metric} instances from the collection.
     * @throws metric_name_invalid
     */
    private function validate_collection(): array {
        $collected = [];
        foreach ($this->collection as $collectedmetric) {
            $name = $collectedmetric->get_name();
            if (!preg_match('/^[a-z_][a-z0-9_]{0,99}$/', $name)) {
                throw new metric_name_invalid($collectedmetric->get_component(), $name);
            }
            $collected[] = $collectedmetric;
        }
        return $collected;
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
     * Implementation detail: Tries to load the requested {@see managed_metric} instance from the cache first.
     * Since the {@see metrics_manager} is defined as the cache data source, a cache miss will trigger the
     * {@see self::load_for_cache `load_for_cache`} method, which will query the database for the missing metric and also
     * automatically update the cache afterwards.
     *
     * @param string $offset Qualified name of the metric to return.
     * @return managed_metric Metric with the given qualified name.
     * @throws coding_exception
     * @throws metric_not_found No metric with the given qualified name is registered.
     */
    #[\Override]
    public function offsetGet(mixed $offset): managed_metric {
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
     * Fetches a {@see managed_metric} instance with the given qualified name from the DB.
     *
     * Implementation detail: This method facilitates null-caching if the key either does not match any collected metric or refers
     * to a metric that has not (yet) been registered in the database.
     *
     * @param string $key Qualified name of the metric to fetch.
     * @return managed_metric|null Metric instance or `null` if no matching metric was not found in the DB.
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     */
    #[\Override]
    public function load_for_cache($key): managed_metric|null {
        $metrics = $this->load_many_for_cache([$key]);
        return $metrics[$key];
    }

    /**
     * Fetches {@see managed_metric} instances with the given qualified names from the DB.
     *
     * Implementation detail: This method facilitates null-caching for keys that either do not match any collected metric and those
     * that refer to metrics that have not (yet) been registered in the database.
     *
     * @param string[] $keys Qualified names of the metrics to fetch.
     * @return array<string, managed_metric|null> Associative array indexed with `$keys` mapped to {@see managed_metric}
     *                                               instances or `null` if no matching metric was not found in the DB.
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     */
    #[\Override]
    public function load_many_for_cache(array $keys): array {
        $output = array_fill_keys($keys, null);
        $metrics = [];
        foreach ($this->collection as $metric) {
            $qname = metric_record::get_qualified_name($metric->get_component(), $metric->get_name());
            if (array_key_exists($qname, $output)) {
                $metrics[$qname] = $metric;
            }
        }
        $registeredmetrics = array_filter(
            array:    managed_metric::get_for_metrics(...$metrics), // The function discards variadic argument names.
            callback: fn (managed_metric $registeredmetric): bool => !is_null($registeredmetric->id),
        );
        return array_merge($output, $registeredmetrics);
    }
}

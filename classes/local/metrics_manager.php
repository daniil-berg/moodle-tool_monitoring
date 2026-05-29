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

namespace tool_monitoring\local;

use core\di;
use core\exception\coding_exception;
use core\hook\di_configuration;
use core_cache\data_source_interface as cache_data_source_interface;
use core_cache\definition as cache_definition;
use dml_exception;
use Exception;
use tool_monitoring\exceptions\metric_name_invalid;
use tool_monitoring\exceptions\metric_not_found;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\exceptions\tags_disabled;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\metric;
use tool_monitoring\metric_tag;
use tool_monitoring\registered_metrics;

/**
 * Linchpin of the internal monitoring toolchain and container for all managed metrics.
 *
 * Implements the {@see registered_metrics} consumer interface and narrows the type of the handled objects to {@see managed_metric}.
 *
 * Registers new {@see metric}s picked up by the {@see metric_collection} hook via the {@see self::sync `sync`} method.
 *
 * Caches the managed metrics via the {@see metrics_cache} to speed up read access for consumers.
 * Implements the {@see cache_data_source_interface `data_source_interface`} to conveniently populate the metrics cache on misses.
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
final readonly class metrics_manager implements cache_data_source_interface, registered_metrics {
    /**
     * Constructor without additional logic.
     *
     * In production code, the constructor should likely never be called directly. Instead, use {@see di::get} to retrieve an
     * instance from Moodle's dependency injection container like so:
     *
     * ```
     * use tool_monitoring\local\metrics_manager;
     *
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
     * {@inheritDoc}
     * To ensure all collected metrics are registered, call {@see self::sync `sync`} first.
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
     * @return array<string, managed_metric> Managed metrics indexed by their qualified name.
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found At least one of the provided `$tagnames` does not match any existing metric tag.
     * @throws tags_disabled
     */
    #[\Override]
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
     * {@inheritDoc}
     * To ensure all collected metrics are registered, call {@see self::sync `sync`} first.
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
     * {@inheritDoc}
     * To ensure all collected metrics are registered, call {@see self::sync `sync`} first.
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

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void {
        throw new coding_exception('Cannot manually set metrics.');
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void {
        throw new coding_exception('Cannot manually unset metrics.');
    }

    /**
     * Efficiently synchronizes the managed metric collection with the database.
     *
     * Ensures that a corresponding entry in the database exists for every unique metric in the collection (per qualified name).
     * Optionally deletes every database entry that does not correspond to any metric in the collection.
     * Validates the names of all newly collected metrics.
     * Purges and re-builds a clean metrics cache.
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
        try {
            $transaction = $DB->start_delegated_transaction();
            $metrics = $this->sync_inner($delete);
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
     * Helper for the {@see self::sync `sync`} method to be executed in a transaction.
     *
     * Goes through the following steps.
     *
     * 1. Fetch collected and registered i.e. fully managed metrics.
     * 2. Figure out which collected metrics are new and thus _not yet registered_.
     * 3. If unregistered metrics were collected, insert them and get managed metrics for those.
     * 4. If deletion is requested and orphans (i.e. registered metrics that were _not collected_) exist, delete those.
     *
     * @param bool $delete If `true`, deletes every database entry that does not correspond to any metric in the collection, and
     *                     triggers individual deletion events for all deleted database records.
     * @return array<string, managed_metric> All collected and registered metrics indexed by qualified name.
     * @throws coding_exception
     * @throws dml_exception
     * @throws metric_name_invalid
     */
    private function sync_inner(bool $delete): array {
        global $DB;
        $tablename = metric_record::TABLE;
        $metrics = $this->fetch_existing();
        $managedids = [];
        $unregistered = [];
        foreach ($this->collection as $collectedmetric) {
            [$component, $name] = [$collectedmetric->get_component(), $collectedmetric->get_name()];
            $qname = metric_record::get_qualified_name($component, $name);
            if (is_null($metrics[$qname])) {
                // A new metric was collected that has no corresponding DB record; validate its name first.
                if (!preg_match('/^[a-z_][a-z0-9_]{0,99}$/', $name)) {
                    throw new metric_name_invalid($component, $name);
                }
                $unregistered[$qname] = $collectedmetric;
            } else {
                $managedids[] = $metrics[$qname]->id;
            }
        }
        if (empty($unregistered) && !$delete) {
            return $metrics;
        }
        // This is no-op if `$unregistered` is empty.
        metric_record::insert_many(...array_map(metric_record::from_metric(...), $unregistered));
        // Fetch all records that we did not get before.
        // These should only be newly inserted ones (if any) and orphans (without a collected metric).
        $sqlqname = metric_record::get_qualified_name_sql($DB);
        [$notmanagedsql, $notmanagedparams] = $DB->get_in_or_equal($managedids, equal: false, onemptyitems: null);
        $sql = "SELECT $sqlqname, m.*
                  FROM {{$tablename}} AS m
                 WHERE id $notmanagedsql";
        $others = $DB->get_records_sql($sql, $notmanagedparams);
        // Now we can construct a new instance for every collected metric that was not registered before.
        foreach ($unregistered as $qname => $collectedmetric) {
            $record = metric_record::from_data($others[$qname]);
            $metrics[$qname] = new managed_metric($collectedmetric, $record);
            unset($others[$qname]);
        }
        // At this point `$others` should only contain orphans.
        if ($delete && $others) {
            $orphanids = array_column($others, 'id');
            [$orphansql, $orphanparams] = $DB->get_in_or_equal($orphanids);
            foreach ($orphanids as $id) {
                metric_tag::remove_all_for_metric($id);
            }
            $DB->delete_records_select($tablename, "id $orphansql", $orphanparams);
            // TODO: Trigger individual deletion events here.
        }
        return $metrics;
    }

    /**
     * Fetches existing managed metrics from the DB.
     *
     * Only metrics in the current {@see self::$collection} are considered.
     * Does not return orphan metrics from the DB.
     * Does **not** validate the names of the collected metrics.
     *
     * The returned array is indexed by the qualified names of the registered metrics.
     *
     * @param string ...$qnames Qualified names of the metrics to fetch. If empty, all metrics in the collection are fetched.
     * @return array<string, managed_metric|null> Associative array of metrics, indexed by their qualified names; `null` if no such
     *                                            metric is registered in the DB (yet).
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     */
    private function fetch_existing(string ...$qnames): array {
        global $DB;
        $collected = [];
        // Construct the `IN` expression and parameters from all component-name-combinations.
        $inplaceholders = [];
        $params = [];
        foreach ($this->collection as $i => $collectedmetric) {
            [$component, $name] = [$collectedmetric->get_component(), $collectedmetric->get_name()];
            $qname = metric_record::get_qualified_name($component, $name);
            if (!empty($qnames) && !in_array($qname, $qnames)) {
                continue;
            }
            $collected[$qname] = $collectedmetric;
            $inplaceholders[] = "(:component$i, :name$i)";
            $params["component$i"] = $component;
            $params["name$i"] = $name;
        }
        if (empty($collected)) {
            return [];
        }
        $inlist = implode(', ', $inplaceholders);
        $sqlqname = metric_record::get_qualified_name_sql($DB);
        $tablename = metric_record::TABLE;
        $sql = "SELECT $sqlqname, m.*
                  FROM {{$tablename}} AS m
                 WHERE (m.component, m.name) IN ($inlist)";
        $records = $DB->get_records_sql($sql, $params);
        $tags = metric_tag::get_for_metric_ids(...array_column($records, 'id'));
        $metrics = [];
        foreach ($collected as $qname => $collectedmetric) {
            if (array_key_exists($qname, $records)) {
                $record = metric_record::from_data($records[$qname]);
                $metrics[$qname] = new managed_metric($collectedmetric, $record, $tags[$record->id] ?? []);
            } else {
                $metrics[$qname] = null;
            }
        }
        return $metrics;
    }

    /**
     * Supplies a definition for the {@see registered_metrics} interface to Moodle's dependency injection container.
     *
     * The DI container is already able to return an instance of the {@see metrics_manager} directly.
     * With this configuration it returns that same instance when consumers request a {@see registered_metrics} object.
     *
     * @link https://moodledev.io/docs/apis/core/di#configuring-dependencies Documentation: Dependency injection
     */
    public static function configure_dependency_injection(di_configuration $hook): void {
        // CAUTION: Due to fascinating interplay between how PHP-DI compiles the container and poor error handling in Moodle,
        // the closure **must** use the actual class name, both in the return type annotation and during construction!
        // Otherwise, Behat tests will fail without any visible traceback.
        $hook->add_definition(
            id: registered_metrics::class,
            // phpcs:ignore Squiz.Classes.SelfMemberReference.NotUsed
            definition: fn(): metrics_manager => di::get(metrics_manager::class),
        );
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
     * @return array<string, managed_metric|null> Associative array indexed with `$keys` mapped to {@see managed_metric} instances
     *                                            or `null` if no matching metric was not found in the DB.
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     */
    #[\Override]
    public function load_many_for_cache(array $keys): array {
        return array_merge(array_fill_keys($keys, null), $this->fetch_existing(...$keys));
    }
}

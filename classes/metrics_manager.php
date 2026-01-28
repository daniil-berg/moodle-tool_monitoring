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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring;

use core\di;
use core\exception\coding_exception;
use core\hook\manager as hook_manager;
use core_tag_tag;
use dml_exception;
use Exception;
use tool_monitoring\hook\metric_collection;

/**
 * Linchpin of the monitoring API.
 *
 * Registers new {@see metric}s picked up by the {@see metric_collection} hook and provides access to already registered ones.
 *
 * @property-read array<string, registered_metric> $metrics Registered metrics indexed by their qualified name; must be populated
 *                                                          by calling the {@see dispatch_hook} method.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class metrics_manager {
    /** @var array<string, registered_metric> Collected and registered metrics indexed by their qualified name. */
    private array $metrics = [];

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
     * Special-case getter for the full array of registered metrics.
     *
     * TODO Replace this method with a nice property `get`-hook, once PHP 8.4+ becomes the minimum requirement.
     *
     * @param string $name Name of the property to return.
     * @return mixed Property value.
     */
    public function __get(string $name): mixed {
        if ($name === 'metrics') {
            return $this->metrics;
        }
        return $this->$name; // @codeCoverageIgnore
    }

    /**
     * Dispatches the managed {@see metric_collection} hook allowing callbacks to add metrics.
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
     * Fetches registered metrics from the database for the managed collection.
     *
     * Ignores database entries for previously registered metrics that are _not_ present in the currently managed collection.
     * Issues a single `SELECT` query; does not perform any `INSERT`/`UPDATE`/`DELETE` queries.
     *
     * @param bool $collect If `true` (default), calls the {@see dispatch_hook} method first.
     * @param bool|null $enabled If `true` (default), only enabled metrics are loaded; if `false`, only disabled ones are loaded;
     *                           passing `null` (default) disables this filter.
     * @param string[] $tags Only metrics that carry all the provided tags will be returned.
     * @return $this Same instance.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function fetch(bool $collect = true, bool|null $enabled = true, array $tags = []): self {
        global $DB;
        if ($collect) {
            $this->dispatch_hook();
        }
        // Store metrics indexed by qualified name for later.
        $metrics = [];
        // Construct the `IN` expression and parameters from the component-name-combinations present in the collection.
        $inplaceholders = [];
        $params = [];
        foreach ($this->collection as $i => $metric) {
            $component = $metric::get_component();
            $name = $metric::get_name();
            $qname = registered_metric::get_qualified_name($component, $name);
            if (array_key_exists($qname, $metrics)) {
                trigger_error("Collected more than one metric with the qualified name '$qname'", E_USER_WARNING);
                continue;
            }
            $metrics[$qname] = $metric;
            $inplaceholders[] = "(:component$i,:name$i)";
            $params["component$i"] = $component;
            $params["name$i"] = $name;
        }
        $where = '(m.component,m.name) IN (' . implode(',', $inplaceholders) . ')';
        // Filter by tags.
        $tags = core_tag_tag::normalize($tags);
        $tags = array_values(array_unique($tags));
        $tagcount = count($tags);
        $tablename = registered_metric::TABLE;
        $sqlqname = $DB->sql_concat_join(separator: "'_'", elements: ["m.component", "m.name"]);
        $tagsenabled = core_tag_tag::is_enabled('tool_monitoring', 'metrics');
        if ($tagcount > 0 && $tagsenabled) {
            list($insql, $inparams) = $DB->get_in_or_equal($tags, SQL_PARAMS_NAMED, 'tag');
            $tagcollid = $DB->get_field('tag_coll', 'id', ['name' => 'monitoring', 'component' => 'tool_monitoring']);
            $sql = "SELECT {$sqlqname} AS qname, m.*
                      FROM {{$tablename}} m
                      JOIN (       SELECT ti.itemid
                                     FROM {tag_instance} ti
                                     JOIN {tag} t ON t.id = ti.tagid
                                    WHERE ti.component = :tagcomponent
                                      AND ti.itemtype  = :tagitemtype
                                      AND t.tagcollid  = :tagcollid
                                      AND t.name $insql
                                 GROUP BY ti.itemid
                             HAVING COUNT (DISTINCT t.id) = :tagcount
                           ) x ON x.itemid = m.id
                     WHERE $where";
                $params += [
                        'tagcomponent' => 'tool_monitoring',
                        'tagitemtype' => 'metrics',
                        'tagcollid' => $tagcollid,
                        'tagcount' => $tagcount,
                    ];
                $params += $inparams;
        } else {
            $sql = "SELECT {$sqlqname} AS qname, *
                      FROM {{$tablename}} m
                     WHERE $where";
        }
        // Filter by enabled.
        if (!is_null($enabled)) {
            $sql .= ' AND enabled = :enabled';
            $params['enabled'] = $enabled;
        }
        // Issue a single `SELECT` query and construct the instances from the returned records.
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $qname => $record) {
            $this->metrics[$qname] = registered_metric::from_metric($metrics[$qname], ...(array) $record);
        }
        return $this;
    }

    /**
     * Efficiently synchronizes the managed metric collection with the database.
     *
     * Ensures that a corresponding entry in the database exists for every unique metric in the collection (per qualified name).
     * Optionally deletes every database entry that does not correspond to any metric in the collection.
     *
     * This function issues no more than two `SELECT`, exactly one `DELETE` (optional), and no more than two `INSERT` queries.
     *
     * @param bool $collect If `true` (default), calls the {@see dispatch_hook} method first. **WARNING**: Failing to collect
     *                      all relevant metrics first will cause data loss, if `$delete` is set to `true`.
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
        // Grab all existing records indexed by qualified name.
        $sqlqname = self::get_qualified_name_sql();
        try {
            $transaction = $DB->start_delegated_transaction();
            $existingrecords = $DB->get_records_sql("SELECT $sqlqname AS qname, m.* FROM {" . registered_metric::TABLE . "} AS m");
            // For us to later know which records were inserted, we remember the existing IDs.
            [$notexistingsql, $notexistingparams] = $DB->get_in_or_equal(
                items:        array_column($existingrecords, 'id'),
                equal:        false,
                onemptyitems: null,
            );
            // Iterate over the collection. Construct a new instance for every metric in the collection that has a DB record
            // and add that instance to the `metrics`, making sure to remove the corresponding item from `$existingrecords`.
            // Track all metrics _without_ a matching DB record in the `$unregistered` array.
            $this->metrics = [];
            $unregistered = [];
            foreach ($this->collection as $metric) {
                $qname = registered_metric::get_qualified_name($metric::get_component(), $metric::get_name());
                if (array_key_exists($qname, $this->metrics)) {
                    trigger_error("Collected more than one metric with the qualified name '$qname'", E_USER_WARNING);
                    continue;
                }
                if (array_key_exists($qname, $existingrecords)) {
                    $this->metrics[$qname] = registered_metric::from_metric($metric, ...(array) $existingrecords[$qname]);
                    unset($existingrecords[$qname]);
                } else {
                    $unregistered[$qname] = $metric;
                    // This is just a placeholder for the duplicate check above to work.
                    $this->metrics[$qname] = registered_metric::from_metric($metric);
                }
            }
            // At this point `$existingrecords` should only contain "orphans", i.e. entries for metrics not found in the collection,
            // and `$unregistered` should contain those metrics from the collection that do not have a corresponding DB entry.
            // Optionally, delete the former and insert the latter.
            if ($delete) {
                [$oprphansql, $orphanparams] = $DB->get_in_or_equal(array_column($existingrecords, 'id'), onemptyitems: null);
                $DB->delete_records_select(registered_metric::TABLE, "id $oprphansql", $orphanparams);
                // TODO: Trigger individual deletion events here.
            }
            $toinsert = [];
            $currenttime = time();
            foreach ($unregistered as $qname => $metric) {
                // Prepare the new instances here, then assign their new IDs after insertion.
                $instance = registered_metric::from_metric(
                    metric:       $metric,
                    timecreated:  $currenttime,
                    timemodified: $currenttime,
                    usermodified: $USER->id,
                );
                $toinsert[$qname] = (array) $instance;
                $this->metrics[$qname] = $instance;
            }
            if (count($unregistered) > 2) {
                // Insert in bulk, then grab the new IDs.
                $DB->insert_records(registered_metric::TABLE, $toinsert);
                $newids = $DB->get_records_select_menu(
                    table:  registered_metric::TABLE,
                    select: "id $notexistingsql",
                    params: $notexistingparams,
                    fields: "$sqlqname AS qname, id",
                );
                foreach ($newids as $qname => $id) {
                    $this->metrics[$qname]->id = $id;
                }
            } else {
                // Insert individually.
                foreach ($toinsert as $qname => $data) {
                    $this->metrics[$qname]->id = $DB->insert_record(registered_metric::TABLE, $data);
                }
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
        return $this;
    }

    /**
     * Returns the proper SQL snippet to construct the qualified name.
     *
     * @return string Qualified name SQL.
     */
    private static function get_qualified_name_sql(): string {
        global $DB;
        return $DB->sql_concat_join(separator: "'_'", elements: ['component', 'name']);
    }
}

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
 * Definition of the {@see registered_metric} class.
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

use core\exception\coding_exception;
use core\lang_string;
use dml_exception;
use Exception;
use IteratorAggregate;
use JsonException;
use MoodleQuickForm;
use stdClass;
use tool_monitoring\hook\metric_collection;
use Traversable;

/**
 * Encapsulates all DB manipulations related to {@see metric} instances.
 *
 * Metric values can be retrieved by iterating over an instance of this class.
 *
 * For metrics with custom configurations, the class is generic in terms of the {@see config} type.
 *
 * @property-read string $qualifiedname Qualified name of the metric.
 * @property-read lang_string $description Localized description of the metric.
 * @property-read metric_type $type Type of the metric.
 * @template ConfT of object = stdClass
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
final class registered_metric implements IteratorAggregate {

    /** @var string Name of the mapped DB table. */
    public const string TABLE = 'tool_monitoring_metrics';

    /** @var string[] Names of all fields in the DB table, i.e. all constructor parameters. */
    private const array FIELDS = [
        'component',
        'name',
        'enabled',
        'config',
        'timecreated',
        'timemodified',
        'usermodified',
        'id',
    ];

    /** @var metric<ConfT> */
    private metric $metric;

    /**
     * Constructor without additional logic.
     *
     * @param string $component Component defining the metric.
     * @param string $name Name of the metric.
     * @param bool $enabled If `false` the metric is currently not supposed to be calculated/exported.
     * @param ConfT $config Metric-specific config data; empty object if no specific config is defined for the metric.
     * @param int|null $timecreated Timestamp when the DB table entry for the metric was inserted; `null` if none exists (yet).
     * @param int|null $timemodified Timestamp when the DB table entry was last modified; `null` if not (yet) saved.
     * @param int|null $usermodified ID of the user that last modified the DB table entry; `null` if not (yet) saved.
     * @param int|null $id Primary key of the corresponding DB table row; `null` if not (yet) saved.
     */
    private function __construct(
        public string   $component,
        public string   $name,
        public bool     $enabled      = false,
        public object   $config       = new stdClass(),
        public int|null $timecreated  = null,
        public int|null $timemodified = null,
        public int|null $usermodified = null,
        public int|null $id           = null,
    ) {}

    /**
     * Constructs a new instance from the specified metric.
     *
     * @param metric $metric Metric to wrap in the new instance; unless passed via `...$properties`, the `component`, `name`,
     *                       and `config` properties are derived from the {@see metric::get_component}, {@see metric::get_name},
     *                       and {@see metric::get_default_config_data} methods respectively.
     * @param mixed ...$properties Properties to set/overwrite on the new instance; non-property names are ignored.
     * @return self New instance from the provided metric and optional properties.
     * @throws coding_exception A provided `config` string did not represent a valid JSON object.
     */
    public static function from_metric(metric $metric, mixed ...$properties): self {
        $arguments = [
            'component' => $metric::get_component(),
            'name'      => $metric::get_name(),
            'config'    => $metric::get_default_config_data(),
        ];
        foreach (self::FIELDS as $name) {
            if (!array_key_exists($name, $properties)) {
                continue;
            }
            $value = $properties[$name];
            if ($name == 'config' && is_string($value)) {
                $value = json_decode($value);
                if (!($value instanceof stdClass)) {
                    // TODO: Use custom exception class.
                    throw new coding_exception('The provided `config` is not a valid JSON object.');
                }
            }
            $arguments[$name] = $value;
        }
        $instance = new self(...$arguments);
        $instance->metric = $metric;
        return $instance;
    }

    /**
     * Transforms an instance of the mapped class into an associative array of data that can be used in DB queries.
     *
     * The data can then be passed as an argument to functions such as e.g. {@see \moodle_database::update_record}.
     *
     * In the output array the {@see config} value is serialized with {@see json_encode}.
     *
     * @param string[]|null $fields The output array will only have entries that are properties of the object **and** that are
     *                              specified in this argument. An exception is the {@see id} property; if its value is not `null`
     *                              on the instance, it will always be included in the output. If this argument is `null`, all
     *                              properties will be included in the output array.
     * @return array<string, mixed> DB-friendly data taken from the instance.
     * @throws JsonException The {@see config} object could not be serialized.
     */
    private function to_db(array|null $fields = null): array {
        $data = [];
        if (!is_null($this->id)) {
            $data['id'] = $this->id;
        }
        $returnfields = self::FIELDS;
        if (!is_null($fields)) {
            $returnfields = array_intersect($returnfields, $fields);
        }
        foreach ($returnfields as $field) {
            $data[$field] = $this->$field;
            if ($field == 'config') {
                // TODO: Catch and wrap `JsonException` in custom exception.
                $data[$field] = json_encode($this->$field, JSON_THROW_ON_ERROR);
            }
        }
        return $data;
    }

    /**
     * Inserts a corresponding row into the database table with data from the object.
     *
     * The {@see timecreated} and {@see timemodified} are set to the current time and the {@see usermodified} to the current user
     * before the database entry is created.
     *
     * @return $this Same instance with its {@see id}, {@see timecreated}, {@see timemodified}, and {@see usermodified} updated.
     * @throws dml_exception
     * @throws JsonException The {@see config} object could not be serialized.
     */
    private function create(): self {
        global $DB, $USER;
        $currenttime = time();
        $this->timecreated = $currenttime;
        $this->timemodified = $currenttime;
        $this->usermodified = $USER->id;
        $this->id = $DB->insert_record(self::TABLE, $this->to_db());
        return $this;
    }

    /**
     * Updates the corresponding row in the database table with data from the object.
     *
     * The {@see timemodified} and {@see usermodified} are set to the current time and user respectively before the update.
     *
     * @param string[]|null $fields If specified, only these fields will be updated.
     * @throws dml_exception
     * @throws JsonException The {@see config} object could not be serialized.
     */
    private function update(array|null $fields = null): void {
        global $DB, $USER;
        $this->timemodified = time();
        $this->usermodified = $USER->id;
        $DB->update_record(self::TABLE, $this->to_db($fields));
    }

    /**
     * Derives a qualified name from the provided component and name.
     *
     * @param string $component Moodle component.
     * @param string $name Entity name.
     * @return string Qualified name.
     */
    public static function get_qualified_name(string $component, string $name): string {
        return "{$component}_$name";
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

    /**
     * Special-case getter for the qualified name, description, and type of the metric.
     *
     * TODO Replace this method with nice property `get`-hooks, once PHP 8.4+ becomes the minimum requirement.
     *
     * @param string $name Name of the property to return.
     * @return mixed Property value.
     */
    public function __get(string $name): mixed {
        return match ($name) {
            'qualifiedname' => self::get_qualified_name($this->component, $this->name),
            'description'   => $this->metric::get_description(),
            'type'          => $this->metric::get_type(),
            default         => $this->$name,
        };
    }

    /**
     * Disables the metric making it unavailable for calculation and export.
     *
     * No-op if the metric is already disabled.
     *
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     * @throws JsonException Should never happen.
     */
    public function disable(): void {
        global $DB;
        if (!$this->enabled) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $this->enabled = false;
        $this->update(['enabled', 'timemodified', 'usermodified']);
        event\metric_disabled::for_metric($this)->trigger();
        $transaction->allow_commit();
    }

    /**
     * Enables the metric making it available for calculation and export.
     *
     * No-op if the metric is already enabled.
     *
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     * @throws JsonException Should never happen.
     */
    public function enable(): void {
        global $DB;
        if ($this->enabled) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $this->enabled = true;
        $this->update(['enabled', 'timemodified', 'usermodified']);
        event\metric_enabled::for_metric($this)->trigger();
        $transaction->allow_commit();
    }

    /**
     * Saves the metric's current {@see config} to the database.
     *
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     * @throws JsonException The {@see config} object could not be serialized.
     */
    public function save_config(): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $this->update(['config', 'timemodified', 'usermodified']);
        event\metric_config_updated::for_metric($this)->trigger();
        $transaction->allow_commit();
    }

    /**
     * Calls the {@see metric::add_config_form_elements} on the provided form object.
     *
     * @param MoodleQuickForm $mform Config form to extend.
     */
    public function extend_config_form(MoodleQuickForm $mform): void {
        $this->metric::add_config_form_elements($mform);
    }

    /**
     * Produces the current {@see metric_value}s.
     *
     * This allows the instance to be iterated over in a `foreach` loop.
     *
     * @return Traversable<metric_value> Values of the metric.
     */
    public function getIterator(): Traversable {
        $values = $this->metric->calculate($this->config);
        if ($values instanceof metric_value) {
            $values = [$values];
        }
        foreach ($values as $metricvalue) {
            yield $this->metric::validate_value($metricvalue);
        }
    }

    /**
     * Efficiently synchronizes the database table with the provided metric collection and returns all registered metrics.
     *
     * Ensures that a corresponding entry in the database exists for every unique metric in the collection (per qualified name),
     * **and** that no entries exist that do not correspond to a metric in the collection.
     *
     * **WARNING**: Calling this function with an incomplete metrics collection will cause data loss! Therefore, calling it directly
     * outside of testing is highly discouraged. The {@see metrics_manager::sync_registered_metrics} method should be used instead.
     *
     * Triggers individual deletion events for all deleted database records.
     *
     * This function issues no more than two `SELECT`, exactly one (possibly no-op) `DELETE`, and no more than two `INSERT` queries.
     *
     * @param metric_collection $collection Metrics to synchronize the database table with; a warning will be issued for every
     *                                      duplicate metric (determined via the qualified name) found in the collection, and that
     *                                      metric will be ignored.
     * @return array<string, self> All currently registered metrics that appear in the collection, indexed by their qualified name.
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException Failed to serialize a {@see config} value.
     */
    public static function sync_with_collection(metric_collection $collection): array {
        global $DB, $USER;
        // Grab all existing records indexed by qualified name.
        $sqlqname = self::get_qualified_name_sql();
        try {
            $transaction = $DB->start_delegated_transaction();
            $existingrecords = $DB->get_records(self::TABLE, fields: "$sqlqname AS qname, *");
            // For us to later know which records were inserted, we remember the existing IDs.
            [$notexistingsql, $notexistingparams] = $DB->get_in_or_equal(
                items:        array_column($existingrecords, 'id'),
                equal:        false,
                onemptyitems: null,
            );
            // Iterate over the collection. Construct a new instance for every metric in the collection that has a DB record
            // and add that instance to the `$output`, making sure to remove the corresponding item from `$existingrecords`.
            // Track all metrics _without_ a matching DB record in the `$unregistered` array.
            $output = [];
            $unregistered = [];
            foreach ($collection as $metric) {
                $qname = self::get_qualified_name($metric::get_component(), $metric::get_name());
                if (array_key_exists($qname, $output)) {
                    trigger_error("Collected more than one metric with the qualified name '$qname'", E_USER_WARNING);
                    continue;
                }
                if (array_key_exists($qname, $existingrecords)) {
                    $output[$qname] = self::from_metric($metric, ...(array) $existingrecords[$qname]);
                    unset($existingrecords[$qname]);
                } else {
                    $unregistered[$qname] = $metric;
                    // This is just a placeholder for the duplicate check above to work.
                    $output[$qname] = self::from_metric($metric);
                }
            }
            // At this point `$existingrecords` should only contain "orphans", i.e. entries for metrics not found in the collection,
            // and `$unregistered` should contain those metrics from the collection that do not have a corresponding DB entry.
            // Delete the former and insert the latter.
            [$oprphansql, $orphanparams] = $DB->get_in_or_equal(array_column($existingrecords, 'id'), onemptyitems: null);
            $DB->delete_records_select(self::TABLE, "id $oprphansql", $orphanparams);
            // TODO: Trigger individual deletion events here.
            if (count($unregistered) > 2) {
                // Insert in bulk.
                $toinsert = [];
                $currenttime = time();
                foreach ($unregistered as $qname => $metric) {
                    $instance = self::from_metric(
                        metric:       $metric,
                        timecreated:  $currenttime,
                        timemodified: $currenttime,
                        usermodified: $USER->id,
                    );
                    $toinsert[] = $instance->to_db();
                    $output[$qname] = $instance;
                }
                $DB->insert_records(self::TABLE, $toinsert);
                // Now we just need to get the IDs of the newly inserted records and assign them to the corresponding new instances.
                $newids = $DB->get_records_select_menu(
                    table:  self::TABLE,
                    select: "id $notexistingsql",
                    params: $notexistingparams,
                    fields: "$sqlqname AS qname, id",
                );
                foreach ($newids as $qname => $id) {
                    $output[$qname]->id = $id;
                }
            } else {
                // Insert individually.
                foreach ($unregistered as $qname => $metric) {
                    $output[$qname] = self::from_metric($metric)->create();
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
        return $output;
    }

    /**
     * Returns the registered metrics from the provided collection.
     *
     * Does not construct registered metrics that are _not_ present in the collection, even if they have entries in the database.
     * Issues a single `SELECT` query; does not perform any `INSERT`/`UPDATE`/`DELETE` queries.
     *
     * @param metric_collection $collection Metrics to construct the new instances from; a warning will be issued for every
     *                                      duplicate metric (determined via the qualified name) found in the collection, and that
     *                                      metric will be ignored.
     * @param bool|null $enabled If `true`, only enabled metrics are returned; if `false`, only disabled ones are returned;
     *                           passing `null` (default) disables this filter.
     * @param string[] $tags Only metrics that carry all the provided tags will be returned.
     * @return array<string, self> Metrics indexed by their qualified name.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_from_collection(metric_collection $collection, bool|null $enabled = null, array $tags = []): array {
        global $DB;
        // Store metrics indexed by qualified name for later.
        $metrics = [];
        // Construct the `IN` expression and parameters from the component-name-combinations present in the collection.
        $inplaceholders = [];
        $params = [];
        foreach ($collection as $metric) {
            $component = $metric::get_component();
            $name = $metric::get_name();
            $qname = self::get_qualified_name($component, $name);
            if (array_key_exists($qname, $metrics)) {
                trigger_error("Collected more than one metric with the qualified name '$qname'", E_USER_WARNING);
                continue;
            }
            $metrics[$qname] = $metric;
            $inplaceholders[] = '(?,?)';
            $params[] = $component;
            $params[] = $name;
        }
        // TODO: Filter by tags.
        $where = '(component, name) IN (' . implode(',', $inplaceholders) . ')';
        if (!is_null($enabled)) {
            $where .= ' AND enabled = ?';
            $params[] = $enabled;
        }
        // Issue a single `SELECT` query and construct the instances from the returned records.
        $records = $DB->get_records_select(self::TABLE, $where, $params);
        $output = [];
        foreach ($records as $record) {
            $qname = self::get_qualified_name($record->component, $record->name);
            $output[$qname] = self::from_metric($metrics[$qname], ...(array) $record);
        }
        return $output;
    }
}

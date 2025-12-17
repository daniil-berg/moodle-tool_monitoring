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
use dml_missing_record_exception;
use Exception;
use IteratorAggregate;
use JsonException;
use MoodleQuickForm;
use stdClass;
use Traversable;

/**
 * Encapsulates all DB manipulations related to {@see metric} instances.
 *
 * Metric values can be retrieved by iterating over an instance of this class.
 *
 * For metrics with custom configurations, the trait is generic in terms of the {@see config} type.
 *
 * @property-read string $qualifiedname
 * @property-read lang_string $description
 * @property-read metric_type $type
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

    /** @var array<string, bool> Names of all fields in the DB table mapped to whether or not they are required for construction. */
    private const array FIELDS_REQUIRED = [
        'component'    => true,
        'name'         => true,
        'enabled'      => false,
        'config'       => false,
        'timecreated'  => false,
        'timemodified' => false,
        'usermodified' => false,
        'id'           => false,
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
     * Constructs a new instance from an untyped data object/array with the necessary properties/keys.
     *
     * Which values are required is flagged in the {@see self::FIELDS_REQUIRED} constant.
     *
     * If a `config` key is present and a string value, {@see json_decode} will be used to turn it into a {@see stdClass} object.
     *
     * @param array<string, mixed>|stdClass $untyped Data to use for construction; must have the required keys/properties.
     * @return self New instance constructed from the provided `$untyped` data.
     * @throws coding_exception A required field was missing or a provided `config` string did not represent a valid JSON object.
     */
    private static function from_untyped_object(array|stdClass $untyped): self {
        $untyped = (array) $untyped;
        $arguments = [];
        foreach (self::FIELDS_REQUIRED as $name => $required) {
            if (array_key_exists($name, $untyped)) {
                $value = $untyped[$name];
                if ($name == 'config' && is_string($value)) {
                    $value = json_decode($value);
                    if (!($value instanceof stdClass)) {
                        // TODO: Use custom exception class.
                        throw new coding_exception('The provided `config` is not a valid JSON object.');
                    }
                }
                $arguments[$name] = $value;
            } else if ($required) {
                // TODO: Use custom exception class.
                throw new coding_exception("Missing required `$name`");
            }
        }
        return new self(...$arguments);
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
        $returnfields = array_keys(self::FIELDS_REQUIRED);
        if (!is_null($fields)) {
            $returnfields = array_intersect($returnfields, $fields);
        }
        foreach ($returnfields as $field) {
            $data[$field] = $this->$field;
            if ($field == 'config') {
                $data[$field] = json_encode($this->$field, JSON_THROW_ON_ERROR);
            }
        }
        return $data;
    }

    /**
     * Fetches an instance matching the specified conditions from the database.
     *
     * @param array<string, mixed> $conditions Associative array with field names as keys and values to match.
     * @return self Instance matching the specified `$conditions`.
     * @throws coding_exception
     * @throws dml_exception No matching metric or multiple matching metrics found or an unexpected database error occurred.
     */
    private static function get(array $conditions = []): self {
        global $DB;
        return self::from_untyped_object(
            $DB->get_record(self::TABLE, $conditions, strictness: MUST_EXIST)
        );
    }

    /**
     * Inserts a corresponding row into the database table with data from the object.
     *
     * **Note**:
     * The `id` will always be set by the DB during creation. Therefore, calling this method on an instance with an `id` that is
     * not `null` will result in an error.
     *
     * The {@see timecreated} and {@see timemodified} are set to the current time and the {@see usermodified} to the current user
     * before the database entry is created.
     *
     * @return $this Same instance with its {@see id}, {@see timecreated}, {@see timemodified}, and {@see usermodified} updated.
     * @throws coding_exception Instance already had an {@see id} value.
     * @throws dml_exception
     * @throws JsonException The {@see config} object could not be serialized.
     */
    private function create(): self {
        global $DB, $USER;
        if (!is_null($this->id)) {
            // TODO: Use custom exception class.
            throw new coding_exception('Cannot insert instance that already has an `id` property');
        }
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
     * **Note**:
     * The `id` is needed to identify the actual DB entry to update. If it is not set, an error will be thrown.
     *
     * The {@see timemodified} and {@see usermodified} are set to the current time and user respectively before the update.
     *
     * @param string[]|null $fields If specified, only these fields will be updated.
     * @throws coding_exception Instance was missing an {@see id} value.
     * @throws dml_exception
     * @throws JsonException The {@see config} object could not be serialized.
     */
    private function update(array|null $fields = null): void {
        global $DB, $USER;
        if (is_null($this->id)) {
            // TODO: Use custom exception class.
            throw new coding_exception('Cannot update instance without `id` property');
        }
        $this->timemodified = time();
        $this->usermodified = $USER->id;
        $DB->update_record(self::TABLE, $this->to_db($fields));
    }

    /**
     * Constructs a new instance from the specified metric.
     *
     * If no entry in the database table exists (yet) for the metric, it is created first.
     *
     * @param metric $metric Metric to wrap in the new instance.
     * @return self New instance from the provided metric.
     * @throws coding_exception Should not happen.
     * @throws dml_exception
     * @throws JsonException Failed to (de-)serialize the {@see config} value.
     */
    private static function db_get_or_create_from_metric(metric $metric): self {
        global $DB;
        // Either fetch the existing DB entry or create a new one for the metric with this component & name.
        $conditions = ['component' => $metric::get_component(), 'name' => $metric::get_name()];
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                // Assume we already have a DB entry and construct the new instance from it.
                $instance = self::get($conditions);
            } catch (dml_missing_record_exception) {
                // There is no entry yet; construct the instance first and then creat the DB entry for it.
                // Set the default config as defined in the metric class.
                $conditions['config'] = $metric::get_default_config_data();
                $instance = self::from_untyped_object($conditions)->create();
            }
            $transaction->allow_commit();
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            if (!empty($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($e);
            }
            throw $e;
            // @codeCoverageIgnoreEnd
        }
        return $instance;
    }

    /**
     * Constructs a new instance from the specified metric.
     *
     * Synchronizes the registered metric with the database, unless otherwise specified.
     * If no entry in the database table exists (yet) for the metric, it is created first.
     *
     * @param metric $metric Metric to wrap in the new instance.
     * @param bool $syncdb If `false`, the database is not touched; then the returned instance will have all default properties, and
     *                     only have its {@see self::component} and {@see self::name} derived from the provided `$metric`.
     * @return self New instance from the provided metric.
     * @throws coding_exception Should not happen.
     * @throws dml_exception
     * @throws JsonException Failed to (de-)serialize the {@see config} value.
     */
    public static function from_metric(metric $metric, bool $syncdb = true): self {
        if (!$syncdb) {
            $instance = new self(component: $metric::get_component(), name: $metric::get_name());
        } else {
            $instance = self::db_get_or_create_from_metric($metric);
        }
        $instance->metric = $metric;
        return $instance;
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
}

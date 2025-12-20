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
use IteratorAggregate;
use JsonException;
use MoodleQuickForm;
use stdClass;
use Traversable;

/**
 * Represents a {@see metric} that is managed by the plugin and thus has a corresponding entry in the database.
 *
 * An instance of this class maps to a row in the {@see self::TABLE} database table.
 * Metric values can be retrieved by iterating over an instance of this class.
 *
 * For metrics with custom configurations, the class is generic in terms of the {@see config} type.
 *
 * @property-read string $qualifiedname Qualified name of the metric.
 * @property-read lang_string $description Localized description of the metric.
 * @property-read metric_type $type Type of the metric.
 * @template ConfT of object|null = null
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
     * @param ConfT $config Metric-specific config data; `null` if no specific config is defined for the metric.
     * @param int|null $timecreated Timestamp when the DB table entry for the metric was inserted; `null` if none exists (yet).
     * @param int|null $timemodified Timestamp when the DB table entry was last modified; `null` if not (yet) saved.
     * @param int|null $usermodified ID of the user that last modified the DB table entry; `null` if not (yet) saved.
     * @param int|null $id Primary key of the corresponding DB table row; `null` if not (yet) saved.
     */
    private function __construct(
        public string      $component,
        public string      $name,
        public bool        $enabled      = false,
        public object|null $config       = null,
        public int|null    $timecreated  = null,
        public int|null    $timemodified = null,
        public int|null    $usermodified = null,
        public int|null    $id           = null,
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
                    throw new coding_exception('The provided `config` string is not a valid JSON object.');
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
     * In the output array a non-`null` {@see config} value is serialized with {@see json_encode}.
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
            if ($field == 'config' && !is_null($this->config)) {
                // TODO: Catch and wrap `JsonException` in custom exception.
                $data[$field] = json_encode($this->$field, JSON_THROW_ON_ERROR);
            }
        }
        return $data;
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

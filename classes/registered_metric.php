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
use moodleform;
use MoodleQuickForm;
use stdClass;
use tool_monitoring\form\config as config_form;
use Traversable;
use TypeError;

/**
 * Represents a {@see metric} that is managed by the plugin and thus has a corresponding entry in the database.
 *
 * An instance of this class maps to a row in the {@see self::TABLE} database table.
 * Metric values can be retrieved by iterating over an instance of this class.
 *
 * @property-read string $qualifiedname Qualified name of the metric.
 * @property-read lang_string $description Localized description of the metric.
 * @property-read metric_type $type Type of the metric.
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

    private metric $metric;

    /**
     * @var class-string<metric_config>|null
     */
    private string|null $configclass = null;

    /**
     * Constructor without additional logic.
     *
     * @param string $component Component defining the metric.
     * @param string $name Name of the metric.
     * @param bool $enabled If `false` the metric is currently not supposed to be calculated/exported.
     * @param string|null $config Metric-specific config as a JSON object; `null` if no specific config is defined for the metric.
     * @param int|null $timecreated Timestamp when the DB table entry for the metric was inserted; `null` if none exists (yet).
     * @param int|null $timemodified Timestamp when the DB table entry was last modified; `null` if not (yet) saved.
     * @param int|null $usermodified ID of the user that last modified the DB table entry; `null` if not (yet) saved.
     * @param int|null $id Primary key of the corresponding DB table row; `null` if not (yet) saved.
     */
    private function __construct(
        public string      $component,
        public string      $name,
        public bool        $enabled      = false,
        public string|null $config       = null,
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
     *                       and {@see metric::get_default_config} methods respectively.
     * @param mixed ...$properties Properties to set/overwrite on the new instance; non-property names are ignored.
     * @return self New instance from the provided metric and optional properties.
     */
    public static function from_metric(metric $metric, mixed ...$properties): self {
        // Figure out, if the metric class uses the `with_config` trait.
        try {
            $defaultconfig = call_user_func([$metric, 'get_default_config']);
        } catch (TypeError) {
            // Method does not exist; `with_config` trait is not used.
            $defaultconfig = null;
        }
        $arguments = [
            'component' => $metric::get_component(),
            'name'      => $metric::get_name(),
        ];
        foreach (self::FIELDS as $name) {
            if (!array_key_exists($name, $properties)) {
                continue;
            }
            $arguments[$name] = $properties[$name];
        }
        $instance = new self(...$arguments);
        if ($defaultconfig instanceof metric_config && property_exists($metric, 'configjson')) {
            // Assume the metric uses the `with_config` trait.
            $instance->configclass = $defaultconfig::class;
            if (!array_key_exists('config', $arguments)) {
                // No config was passed to the constructor; fall back to the default.
                $instance->config = json_encode($defaultconfig);
            }
            $metric->configjson = $instance->config;
        }
        $instance->metric = $metric;
        return $instance;
    }

    /**
     * Transforms an instance of the mapped class into an associative array of data that can be used in DB queries.
     *
     * The data can then be passed as an argument to functions such as e.g. {@see \moodle_database::update_record}.
     *
     * @param string[]|null $fields The output array will only have entries that are properties of the object **and** that are
     *                              specified in this argument. An exception is the {@see id} property; if its value is not `null`
     *                              on the instance, it will always be included in the output. If this argument is `null`, all
     *                              properties will be included in the output array.
     * @return array<string, mixed> DB-friendly data taken from the instance.
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
     * Returns config form data from the instance to set via {@see config_form::set_data}.
     *
     * @return array<string, mixed> Associative array of form data.
     */
    public function to_form_data(): array {
        if (!is_null($this->configclass) && !is_null($this->config)) {
            $formdata = $this->configclass::from_json($this->config)->to_form_data();
        } else {
            $formdata = [];
        }
        $formdata['enabled'] = $this->enabled;
        return $formdata;
    }

    /**
     * Updates the instance with the (non-empty) output of {@see moodleform::get_data} and saves it to the database.
     *
     * Only performs an actual update, if {@see enabled} or {@see config} is different from the provided form data; no-op otherwise.
     * Individual events are triggered, depending on what is updated.
     *
     * @param stdClass $formdata Config form data to use for updating.
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     * @throws JsonException The {@see config} object could not be serialized.
     */
    public function update_with_form_data(stdClass $formdata): void {
        global $DB;
        $events = [];
        if (isset($formdata->enabled)) {
            if ($formdata->enabled && !$this->enabled) {
                $this->enabled = true;
                $events[] = event\metric_enabled::for_metric($this);
            } else if (!$formdata->enabled && $this->enabled) {
                $this->enabled = false;
                $events[] = event\metric_disabled::for_metric($this);
            }
        }
        if (!is_null($this->configclass)) {
            $config = json_encode($this->configclass::with_form_data($formdata), JSON_THROW_ON_ERROR);
            if ($config !== $this->config) {
                $this->config = $config;
                $events[] = event\metric_config_updated::for_metric($this);
            }
        }
        if (empty($events)) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $this->update(['enabled', 'config', 'timemodified', 'usermodified']);
        foreach ($events as $event) {
            $event->trigger();
        }
        $transaction->allow_commit();
    }

    /**
     * Calls the {@see config::extend_config_form} on the provided form object.
     *
     * @param MoodleQuickForm $mform Config form to extend.
     */
    public function extend_config_form(MoodleQuickForm $mform): void {
        if (is_null($this->configclass)) {
            return;
        }
        $this->configclass::extend_config_form($mform);
    }

    /**
     * Produces the current {@see metric_value}s.
     *
     * This allows the instance to be iterated over in a `foreach` loop.
     *
     * @return Traversable<metric_value> Values of the metric.
     */
    public function getIterator(): Traversable {
        $values = $this->metric->calculate();
        if ($values instanceof metric_value) {
            $values = [$values];
        }
        foreach ($values as $metricvalue) {
            yield $this->metric::validate_value($metricvalue);
        }
    }
}

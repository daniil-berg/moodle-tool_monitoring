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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring;

use core\exception\coding_exception;
use core\lang_string;
use core_cache\cacheable_object_interface;
use dml_exception;
use IteratorAggregate;
use JsonException;
use moodleform;
use stdClass;
use tool_monitoring\form\config as config_form;
use tool_monitoring\local\metrics_cache;
use Traversable;

/**
 * Represents a {@see metric} that is managed by the plugin and thus has a corresponding entry in the database.
 *
 * An instance of this class maps to a row in the {@see self::TABLE `TABLE`} database table.
 * Metric values can be retrieved by iterating over an instance of this class.
 *
 * @property-read string $qualifiedname Qualified name of the metric.
 * @property-read lang_string $description Localized description of the metric.
 * @property-read metric_type $type Type of the metric.
 * @property-read class-string<metric_config>|null $configclass Name of the associated metric config class, if any.
 * @property-read array<string, metric_tag> $tags Tags on the metric, indexed by their normalized name.
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
final class registered_metric implements cacheable_object_interface, IteratorAggregate {
    /** @var string Name of the mapped DB table. */
    public const TABLE = 'tool_monitoring_metrics';

    /** @var string[] Names of all fields in the DB table; matches all constructor parameters. */
    private const FIELDS = [
        'component',
        'name',
        'enabled',
        'config',
        'timecreated',
        'timemodified',
        'usermodified',
        'id',
    ];

    /** @var array<string, string> Properties of interest that are cached; for convenience, keys and values are the same. */
    private const CACHE_FIELDS = [
        'id' => 'id',
        'component' => 'component',
        'name' => 'name',
        'enabled' => 'enabled',
        'config' => 'config',
        'timecreated' => 'timecreated',
        'timemodified' => 'timemodified',
        'usermodified' => 'usermodified',
        'metric' => 'metric',
        'configclass' => 'configclass',
        'tags' => 'tags',
    ];

    /** @var metric Underlying metric that the instance wraps. */
    private metric $metric;

    /** @var class-string<metric_config>|null Name of the associated metric config class; `null` if not configurable. */
    private string|null $configclass = null;

    /** @var array<string, metric_tag> Tags on the metric, indexed by their normalized name. */
    private array $tags = [];

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
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    private function __construct(
        /** @var string Component defining the metric. */
        public string $component,
        /** @var string Name of the metric. */
        public string $name,
        /** @var bool If `false` the metric is currently not supposed to be calculated/exported. */
        public bool $enabled = false,
        /** @var string|null Metric-specific config as a JSON object; `null` if no specific config is defined for the metric. */
        public string|null $config = null,
        /** @var int|null Timestamp when the DB table entry for the metric was inserted; `null` if none exists (yet). */
        public int|null $timecreated = null,
        /** @var int|null Timestamp when the DB table entry was last modified; `null` if not (yet) saved. */
        public int|null $timemodified = null,
        /** @var int|null ID of the user that last modified the DB table entry; `null` if not (yet) saved. */
        public int|null $usermodified = null,
        /** @var int|null Primary key of the corresponding DB table row; `null` if not (yet) saved. */
        public int|null $id = null,
    ) {}

    /**
     * Constructs a new instance from the specified metric.
     *
     * @param metric $metric Metric to wrap in the new instance; the {@see self::$component `component`}, {@see self::$name `name`},
     *                       {@see self::$configclass `configclass`}, and {@see self::$config `config`} properties are derived from
     *                       {@see metric::get_component}, {@see metric::get_name}, and {@see metric::get_default_config}.
     * @return self New instance from the provided metric.
     * @throws JsonException The metric is configurable but it's default config could not be serialized.
     */
    public static function from_metric(metric $metric): self {
        $instance = new self(component: $metric::get_component(), name: $metric::get_name());
        $instance->set_metric($metric);
        return $instance;
    }

    /**
     * Constructs new instances from the provided metrics, querying the DB for corresponding records.
     *
     * The returned array is indexed by the qualified names of the provided metrics. If a metric is not found in the database,
     * the corresponding instance will only have its {@see self::$component `component`} and {@see self::$name `name`} set, as well
     * as its {@see self::$config `config`} and {@see self::$configclass `configclass`}, if the metric is configurable.
     *
     * @param metric ...$metrics Metrics to construct new instances from.
     *                           Their qualified names **should** be unique, otherwise a warning will be emitted.
     * @return array<string, self> Associative array of instances, indexed by the qualified names of the provided metrics.
     * @throws dml_exception
     * @throws JsonException A metric is configurable but it's default config could not be serialized.
     */
    public static function get_for_metrics(metric ...$metrics): array {
        global $DB;
        if (empty($metrics)) {
            return [];
        }
        $results = [];
        // Construct the `IN` expression and parameters from all unique component-name-combinations.
        $inplaceholders = [];
        $params = [];
        foreach (array_values($metrics) as $i => $metric) {
            // Create the instance now and store it in the `$results` array.
            // If we find a matching record later, we will update that instance.
            $instance = self::from_metric($metric);
            $qname = $instance->qualifiedname;
            if (array_key_exists($qname, $results)) {
                trigger_error("More than one metric with the qualified name '$qname'", E_USER_WARNING);
                continue;
            }
            $results[$qname] = $instance;
            $inplaceholders[] = "(:component$i, :name$i)";
            $params["component$i"] = $instance->component;
            $params["name$i"] = $instance->name;
        }
        $inlist = implode(', ', $inplaceholders);
        $sqlqname = $DB->sql_concat_join(separator: "'_'", elements: ['component', 'name']);
        $tablename = self::TABLE;
        $sql = "SELECT $sqlqname, m.*
                  FROM {{$tablename}} AS m
                 WHERE (m.component, m.name) IN ($inlist)";
        $records = $DB->get_records_sql($sql, $params);
        $tags = metric_tag::get_for_metric_ids(...array_column($records, 'id'));
        foreach ($records as $qname => $record) {
            $instance = $results[$qname];
            foreach (self::FIELDS as $name) {
                $instance->$name = $record->$name;
            }
            $instance->tags = $tags[$record->id] ?? []; // Tags may be disabled.
        }
        return $results;
    }

    /**
     * Assigns the provided metric to the instance.
     *
     * If the metric is configurable, sets the instance's {@see self::$configclass `configclass`} and {@see self::config `config`}
     * properties consistently and updates the metric's {@see metric_with_config::$configjson `configjson`} property.
     *
     * @param metric $metric Metric to assign to the instance.
     * @throws JsonException No {@see self::config `config`} was set and the default config could not be serialized.
     */
    private function set_metric(metric $metric): void {
        if ($metric instanceof metric_with_config) {
            $defaultconfig = $metric::get_default_config();
            $this->configclass = $defaultconfig::class;
            if (is_null($this->config)) {
                // No config set yet; fall back to the default.
                $this->config = json_encode($defaultconfig, JSON_THROW_ON_ERROR);
            }
            $metric->configjson = $this->config;
        }
        $this->metric = $metric;
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
    public function to_db(array|null $fields = null): array {
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
     * @throws coding_exception
     * @throws dml_exception
     */
    private function update(array|null $fields = null): void {
        global $DB, $USER;
        $this->timemodified = time();
        $this->usermodified = $USER->id;
        $DB->update_record(self::TABLE, $this->to_db($fields));
        metrics_cache::set($this);
    }

    /**
     * Enables or disables the metric and persists the change.
     *
     * Does nothing if the metric already has the desired state.
     *
     * @param bool $enabled Desired enabled state.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function persist_enabled_state(bool $enabled): void {
        global $DB;
        if ($this->enabled === $enabled) {
            return;
        }
        $this->enabled = $enabled;
        $event = $enabled ? event\metric_enabled::for_metric($this) : event\metric_disabled::for_metric($this);
        $transaction = $DB->start_delegated_transaction();
        $this->update(['enabled', 'timemodified', 'usermodified']);
        $event->trigger();
        $transaction->allow_commit();
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
     * Special-case getter for some public-read-only properties of the metric.
     *
     * TODO Remove this method in favor of nice property `get`-hooks, once PHP 8.4+ becomes the minimum requirement.
     *
     * @param string $name Name of the property to return.
     * @return mixed Property value.
     * @throws coding_exception Invalid property name passed.
     */
    public function __get(string $name): mixed {
        return match ($name) {
            'qualifiedname' => self::get_qualified_name($this->component, $this->name),
            'description'   => $this->metric::get_description(),
            'type'          => $this->metric::get_type(),
            'configclass'   => $this->configclass,
            'tags'          => $this->tags,
            default         => throw new coding_exception('Undefined property: ' . self::class . '::$' . $name),
        };
    }

    /**
     * Special-case {@see isset} check for some public-read-only properties of the metric.
     *
     * TODO Remove this method in favor of nice property `get`-hooks, once PHP 8.4+ becomes the minimum requirement.
     *
     * @param string $name Name of the property to check.
     * @return bool `true` if the property is set, `false` otherwise.
     */
    public function __isset(string $name): bool {
        return match ($name) {
            'configclass', 'description', 'qualifiedname', 'type' => isset($this->metric),
            'tags' => isset($this->tags),
            default => false,
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
        $tags = [];
        foreach ($this->tags as $tag) {
            $tags[$tag->id] = $tag->get_display_name();
        }
        $formdata['tags'] = $tags;
        return $formdata;
    }

    /**
     * Updates the instance with the (non-empty) output of {@see moodleform::get_data} and saves it to the database.
     *
     * Only performs an actual update, if {@see self::enabled `enabled`} or {@see self::config `config`} is different from the
     * provided form data; no-op otherwise. Individual events are triggered, depending on what is updated.
     *
     * @param stdClass $formdata Config form data to use for updating.
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     * @throws JsonException The {@see self::config `config`} object could not be serialized.
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
        metric_tag::set_for_metric($this, ...$formdata->tags);
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
     * Produces the current {@see metric_value}s.
     *
     * This allows the instance to be iterated over in a `foreach` loop.
     *
     * @return Traversable<metric_value> Values of the metric.
     */
    #[\Override]
    public function getIterator(): Traversable {
        $values = $this->metric->calculate();
        if ($values instanceof metric_value) {
            yield $values;
        } else {
            yield from $values;
        }
    }

    #[\Override]
    public function prepare_to_cache(): array {
        $data = [];
        foreach (self::CACHE_FIELDS as $field) {
            $data[$field] = match ($field) {
                'tags' => array_map(fn (metric_tag $tag): array => $tag->prepare_to_cache(), $this->tags),
                'metric' => get_class($this->metric), // Store just the class name.
                default => $this->$field,
            };
        }
        return $data;
    }

    /**
     * Constructs a new instance from data stored in the cache.
     *
     * @param array<string, mixed>|stdClass $data Data to use for construction.
     * @return self New instance.
     * @throws coding_exception Data has an unexpected type or is missing required fields.
     * @throws JsonException Should never happen.
     */
    #[\Override]
    public static function wake_from_cache(mixed $data): self {
        if ($data instanceof stdClass) {
            $data = (array) $data;
        } else if (!is_array($data) || array_is_list($data)) {
            throw new coding_exception('Received unexpected data type for registered_metric from cache: ' . gettype($data));
        }
        $missing = array_diff_key(self::CACHE_FIELDS, $data);
        if (!empty($missing)) {
            throw new coding_exception("Missing cache fields for registered_metric {$data['id']}: " . implode(', ', $missing));
        }
        $extra = array_diff_key($data, self::CACHE_FIELDS);
        if (!empty($extra)) {
            debugging("Unexpected cache fields for registered_metric {$data['id']}:" . implode(', ', $extra), DEBUG_DEVELOPER);
        }
        $instance = new self(...array_intersect_key($data, array_flip(self::FIELDS)));
        $metric = new $data['metric'](); // Construct from the class name.
        $instance->set_metric($metric);
        $instance->tags = array_map(fn (array $tag): metric_tag => metric_tag::wake_from_cache($tag), $data['tags']);
        return $instance;
    }
}

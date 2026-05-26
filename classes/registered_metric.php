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

use core\di;
use core\exception\coding_exception;
use core\lang_string;
use core_cache\cacheable_object_interface;
use dml_exception;
use IteratorAggregate;
use JsonException;
use moodle_database;
use moodleform;
use stdClass;
use tool_monitoring\exceptions\metric_config_invalid;
use tool_monitoring\form\config as config_form;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\metric_record;
use tool_monitoring\local\metrics_cache;
use Traversable;

/**
 * Represents a {@see metric} that is managed by the plugin and thus has a corresponding entry in the database.
 *
 * Metric values can be retrieved by iterating over an instance of this class.
 *
 * @property-read string $component Component defining the metric.
 * @property-read string $name Name of the metric.
 * @property-read bool $enabled If `false` the metric is currently not supposed to be calculated/exported.
 * @property-read string|null $config Metric-specific config JSON; `null` if default or not configurable.
 * @property-read int|null $timecreated Timestamp when the DB table entry for the metric was inserted; `null` if none exists (yet).
 * @property-read int|null $timemodified Timestamp when the DB table entry was last modified; `null` if not (yet) saved.
 * @property-read int|null $usermodified ID of the user that last modified the DB table entry; `null` if not (yet) saved.
 * @property-read int|null $id Primary key of the corresponding DB table row; `null` if not (yet) saved.
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
    /** @var metric_config|null Default metric config; `null` if not configurable. */
    private metric_config|null $defaultconfig;

    /** @var metric_config|null Metric config cache; `null` if not yet cached or not configurable. */
    private metric_config|null $configcache = null;

    /**
     * Constructs a new instance ensuring consistency between metric and record.
     *
     * @param metric $metric Metric to wrap.
     * @param metric_record $record DB record to manage.
     * @param array<string, metric_tag> $tags Tags to associate with the metric, indexed by their normalized name.
     * @throws coding_exception Metric record has different component or name than the provided metric.
     */
    public function __construct(
        /** @var metric Underlying metric that the instance wraps. */
        private readonly metric $metric,
        /** @var metric_record Managed DB record. */
        private readonly metric_record $record,
        /** @var array<string, metric_tag> Tags on the metric, indexed by their normalized name. */
        private array $tags = [],
    ) {
        if ($record->component !== $metric->get_component() || $record->name !== $metric->get_name()) {
            throw new coding_exception('Metric record does not match the provided metric.');
        }
        $this->defaultconfig = $metric instanceof metric_config_provider ? $metric->get_default_config() : null;
        $this->set_config($this->record->config);
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
            'configclass'   => $this->defaultconfig ? $this->defaultconfig::class : null,
            'description'   => $this->metric->get_description(),
            'qualifiedname' => metric_record::get_qualified_name($this->record->component, $this->record->name),
            'tags'          => $this->tags,
            'type'          => $this->metric->get_type(),
            default         => property_exists($this->record, $name)
                               ? $this->record->$name
                               : throw new coding_exception('Undefined property: ' . self::class . '::$' . $name),
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
            'configclass', 'description', 'qualifiedname', 'tags', 'type' => true,
            default => property_exists($this->record, $name),
        };
    }

    /**
     * Convenience constructor for a new instance from the specified metric.
     *
     * Calls {@see metric_record::from_metric} to create corresponding record instance.
     *
     * @param metric $metric Metric to wrap in the new instance.
     * @param array<string, metric_tag> $tags Tags to associate with the metric, indexed by their normalized name.
     * @return self New instance from the provided metric.
     * @throws coding_exception Should never happen.
     */
    public static function from_metric(metric $metric, array $tags = []): self {
        return new self($metric, metric_record::from_metric($metric), $tags);
    }

    /**
     * Constructs new instances from the provided metrics, querying the DB for corresponding records.
     *
     * The returned array is indexed by the qualified names of the provided metrics. If a metric is not found in the database,
     * the corresponding instance will wrap a fresh {@see metric_record} with default values.
     *
     * @param metric ...$metrics Metrics to construct new instances from.
     * @return array<string, self> Associative array of instances, indexed by the qualified names of the provided metrics.
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     */
    public static function get_for_metrics(metric ...$metrics): array {
        global $DB;
        if (empty($metrics)) {
            return [];
        }
        $uniquemetrics = [];
        // Construct the `IN` expression and parameters from all unique component-name-combinations.
        $inplaceholders = [];
        $params = [];
        foreach (array_values($metrics) as $i => $metric) {
            [$component, $name] = [$metric->get_component(), $metric->get_name()];
            $qname = metric_record::get_qualified_name($component, $name);
            $uniquemetrics[$qname] = $metric;
            $inplaceholders[] = "(:component$i, :name$i)";
            $params["component$i"] = $component;
            $params["name$i"] = $name;
        }
        $inlist = implode(', ', $inplaceholders);
        $sqlqname = metric_record::get_qualified_name_sql($DB);
        $tablename = metric_record::TABLE;
        $sql = "SELECT $sqlqname, m.*
                  FROM {{$tablename}} AS m
                 WHERE (m.component, m.name) IN ($inlist)";
        $records = $DB->get_records_sql($sql, $params);
        $tags = metric_tag::get_for_metric_ids(...array_column($records, 'id'));
        $instances = [];
        foreach ($uniquemetrics as $qname => $metric) {
            if (array_key_exists($qname, $records)) {
                $record = metric_record::from_data($records[$qname]);
                $instances[$qname] = new self($metric, $record, $tags[$record->id] ?? []);
            } else {
                $instances[$qname] = self::from_metric($metric);
            }
        }
        return $instances;
    }

    /**
     * Constructs new instances from the provided metrics and registers those not yet in the DB.
     *
     * For the already registered metrics, the existing instances are fetched.
     * For every metric that is not yet registered, a new record is inserted before a fresh ID is assigned to the new instance.
     *
     * @param metric ...$metrics Metrics to register.
     * @return array<string, self> Registered instances for all provided `$metrics` indexed by qualified name.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_or_register(metric ...$metrics): array {
        global $DB;
        $instances = self::get_for_metrics(...$metrics);
        // Prepare records for insertion and remember the existing IDs of collected-and-registered metrics.
        $existingids = [];
        $toinsert = [];
        foreach ($instances as $qname => $metric) {
            if (is_null($metric->id)) {
                $toinsert[$qname] = $metric;
            } else {
                $existingids[] = $metric->id;
            }
        }
        metric_record::insert_many(...array_column($toinsert, 'record'));
        // Fetch all records that we did not get before.
        // These should only be newly inserted ones (if any) and orphans (without a collected metric).
        [$notexistingsql, $notexistingparams] = $DB->get_in_or_equal($existingids, equal: false, onemptyitems: null);
        $sqlqname = metric_record::get_qualified_name_sql($DB);
        $otherids = $DB->get_records_select_menu(
            table:  metric_record::TABLE,
            select: "id $notexistingsql",
            params: $notexistingparams,
            fields: "$sqlqname AS qname, id",
        );
        // Assign the newly inserted IDs and remove them from the array.
        foreach (array_keys($toinsert) as $qname) {
            $instances[$qname]->record->id = $otherids[$qname];
        }
        return $instances;
    }

    /**
     * Assigns the config JSON to the instance.
     *
     * Maintains the invariant: {@see self::config `config`} is not `null` => metric is configurable.
     * (Equivalently, metric is _not_ configurable => {@see self::config `config`} is `null`.)
     *
     * The reverse is strictly _not_ true. A configurable metric with a {@see self::config `config`} of `null` just resolves the
     * default config, when {@see self::get_config `get_config`} is called.
     *
     * This means passing `null` here for a configurable metric is equivalent to (re-)setting its config to the current default.
     * Passing a string here for a non-configurable metric discards that argument, triggers a {@see debugging `debugging`} call,
     * and assigns `null`.
     *
     * @param string|null $config JSON encoded string or `null` to assign to {@see self::config `config`}.
     */
    private function set_config(string|null $config): void {
        if ($this->is_configurable()) {
            $this->record->config = $config;
        } else {
            if (!is_null($config)) {
                debugging("Cannot set config on non-configurable metric: $this->qualifiedname", DEBUG_DEVELOPER);
            }
            $this->record->config = null;
        }
        $this->configcache = null;
    }

    /**
     * Returns the deserialized config object.
     *
     * @return metric_config|null Metric config object or `null` if the metric is not configurable.
     * @throws metric_config_invalid Failed to deserialize the config of a configurable metric from JSON.
     */
    private function get_config(): metric_config|null {
        if (!$this->is_configurable()) {
            return null;
        }
        if (is_null($this->record->config)) {
            return $this->configcache ??= clone $this->defaultconfig;
        }
        return $this->configcache ??= $this->configclass::from_json($this->record->config);
    }

    /**
     * Returns whether the instance represents a configurable metric.
     *
     * @return bool `true` if the metric is configurable, `false` otherwise.
     */
    private function is_configurable(): bool {
        return !is_null($this->defaultconfig);
    }

    /**
     * Enables or disables the metric and persists the change.
     *
     * Does nothing if the metric already has the desired state.
     *
     * TODO Rename/split into `enable()`/`disable()`
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
        $this->record->enabled = $enabled;
        $event = $enabled ? event\metric_enabled::for_metric($this) : event\metric_disabled::for_metric($this);
        $transaction = $DB->start_delegated_transaction();
        $this->update_record(['enabled']);
        $event->trigger();
        $transaction->allow_commit();
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
            if ($formdata->enabled && !$this->record->enabled) {
                $this->record->enabled = true;
                $events[] = event\metric_enabled::for_metric($this);
            } else if (!$formdata->enabled && $this->record->enabled) {
                $this->record->enabled = false;
                $events[] = event\metric_disabled::for_metric($this);
            }
        }
        if (is_a($this->configclass, metric_config_form_aware::class, allow_string: true)) {
            $config = json_encode($this->configclass::with_form_data($formdata), JSON_THROW_ON_ERROR);
            if ($config !== $this->record->config) {
                $this->set_config($config);
                $events[] = event\metric_config_updated::for_metric($this);
            }
        }
        metric_tag::set_for_metric($this, ...$formdata->tags);
        if (metric_tag::normalize($formdata->tags) !== array_keys($this->tags)) {
            $this->tags = metric_tag::get_for_metric_ids($this->record->id)[$this->record->id];
        }
        if (empty($events)) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $this->update_record(['enabled', 'config']);
        foreach ($events as $event) {
            $event->trigger();
        }
        $transaction->allow_commit();
    }

    /**
     * Wrapper around {@see metric_record::update} that also evicts the metric from the cache.
     *
     * @param string[] $fields Passed through.
     * @throws coding_exception
     * @throws dml_exception
     */
    private function update_record(array $fields = metric_record::FIELDS): void {
        $this->record->update($fields);
        metrics_cache::delete($this->qualifiedname);
    }

    /**
     * Returns config form data from the instance to set via {@see config_form::set_data}.
     *
     * @return array<string, mixed> Associative array of form data.
     * @throws metric_config_invalid Failed to deserialize the config of a configurable metric from JSON.
     */
    public function to_form_data(): array {
        if (is_a($this->configclass, metric_config_form_aware::class, allow_string: true)) {
            $formdata = $this->get_config()->to_form_data();
        } else {
            $formdata = [];
        }
        $formdata['enabled'] = $this->record->enabled;
        $tags = [];
        foreach ($this->tags as $tag) {
            $tags[$tag->id] = $tag->get_display_name();
        }
        $formdata['tags'] = $tags;
        return $formdata;
    }

    /**
     * Produces the current {@see metric_value}s.
     *
     * This allows the instance to be iterated over in a `foreach` loop.
     *
     * @return Traversable<metric_value> Values of the metric.
     * @throws metric_config_invalid Failed to deserialize the config of a configurable metric from JSON.
     */
    #[\Override]
    public function getIterator(): Traversable {
        $values = $this->metric->calculate($this->get_config());
        if ($values instanceof metric_value) {
            yield $values;
        } else {
            yield from $values;
        }
    }

    #[\Override]
    public function prepare_to_cache(): array {
        $data = $this->record->to_array();
        $data['tags'] = array_map(fn (metric_tag $tag): array => $tag->prepare_to_cache(), $this->tags);
        return $data;
    }

    /**
     * Constructs a new instance from data stored in the cache.
     *
     * @param array<string, mixed>|stdClass $data Data to use for construction.
     * @return self New instance.
     * @throws coding_exception Data has an unexpected type or is missing required fields.
     */
    #[\Override]
    public static function wake_from_cache(mixed $data): self {
        if ($data instanceof stdClass) {
            $data = (array) $data;
        } else if (!is_array($data) || array_is_list($data)) {
            throw new coding_exception('Received unexpected data type for registered_metric from cache: ' . gettype($data));
        }
        $fields = array_flip(array_merge(metric_record::FIELDS, ['tags']));
        $missing = array_diff_key($fields, $data);
        if (!empty($missing)) {
            throw new coding_exception('Missing cache fields for registered_metric: ' . implode(', ', $missing));
        }
        $extra = array_diff_key($data, $fields);
        if (!empty($extra)) {
            debugging(
                "Unexpected cache fields for registered_metric {$data['id']}: " . implode(', ', array_keys($extra)),
                DEBUG_DEVELOPER,
            );
        }
        $record = metric_record::from_data($data);
        // Find the matching metric from the collection.
        $collection = di::get(metric_collection::class);
        if (is_null($metric = $collection->get($record->component, $record->name))) {
            throw new coding_exception("No metric collected for component '$record->component' and name '$record->name'");
        }
        // Wake the associated tag instances.
        $tags = array_map(fn (array $tag): metric_tag => metric_tag::wake_from_cache($tag), $data['tags']);
        return new self($metric, $record, $tags);
    }
}

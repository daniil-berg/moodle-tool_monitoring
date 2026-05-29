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
 * Definition of the {@see metric_record} class.
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

use core\exception\coding_exception;
use dml_exception;
use moodle_database;
use stdClass;
use tool_monitoring\metric;
use tool_monitoring\registered_metric;

/**
 * Represents entries in the {@see self::TABLE `TABLE`} database table.
 *
 * Provides DB insert/update operations that supply defaults for {@see self::$timecreated `timecreated`},
 * {@see self::$timemodified `timemodified`} and {@see self::$usermodified `usermodified`} if not explicitly set.
 *
 * **This class is not part of the public API.**
 * Use the {@see registered_metric} interface instead.
 *
 * @property-read string $qualifiedname Qualified name of the metric as per {@see self::get_qualified_name}.
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
final class metric_record {
    /** @var string Name of the mapped DB table. */
    public const TABLE = 'tool_monitoring_metrics';

    /** @var string[] Names of all fields in the DB table; matches all constructor parameters. */
    public const FIELDS = [
        'component',
        'name',
        'enabled',
        'config',
        'timecreated',
        'timemodified',
        'usermodified',
        'id',
    ];

    /**
     * Constructor without additional logic.
     *
     * @param string $component Component defining the metric.
     * @param string $name Name of the metric.
     * @param bool $enabled If `false` the metric is currently not supposed to be calculated/exported.
     * @param string|null $config Metric-specific config JSON; `null` if default or not configurable.
     * @param int|null $timecreated Timestamp when the DB table entry for the metric was inserted; `null` if none exists (yet).
     * @param int|null $timemodified Timestamp when the DB table entry was last modified; `null` if not (yet) saved.
     * @param int|null $usermodified ID of the user that last modified the DB table entry; `null` if not (yet) saved.
     * @param int|null $id Primary key of the corresponding DB table row; `null` if not (yet) saved.
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    public function __construct(
        /** @var string Component defining the metric. */
        public string $component,
        /** @var string Name of the metric. */
        public string $name,
        /** @var bool If `false` the metric is currently not supposed to be calculated/exported. */
        public bool $enabled = false,
        /** @var string|null Metric-specific config JSON; `null` if default or not configurable. */
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
     * Special-case getter for public-read-only properties.
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
            default         => throw new coding_exception('Undefined property: ' . self::class . '::$' . $name),
        };
    }

    /**
     * Special-case {@see isset} check for public-read-only properties.
     *
     * TODO Remove this method in favor of nice property `get`-hooks, once PHP 8.4+ becomes the minimum requirement.
     *
     * @param string $name Name of the property to check.
     * @return bool `true` if the property is set, `false` otherwise.
     */
    public function __isset(string $name): bool {
        return match ($name) {
            'qualifiedname' => true,
            default         => false,
        };
    }

    /**
     * Constructs a new instance from a data object (presumably returned by a DB query).
     *
     * @param array|stdClass $data Data to use for instantiation; must at least have `component` and `name`.
     * @return static New instance matching the provided `$data`.
     */
    public static function from_data(array|stdClass $data): static {
        $data = (array) $data;
        return new static(...array_intersect_key($data, array_flip(self::FIELDS)));
    }

    /**
     * Constructs a new instance from a metric.
     *
     * Derives the {@see self::$component `component`} and {@see self::$name `name`} from the
     * {@see metric::get_component} and {@see metric::get_name} methods of the provided metric.
     * All other properties are set to their default values.
     *
     * @param metric $metric Metric to derive the data from.
     * @return static New instance.
     */
    public static function from_metric(metric $metric): static {
        return new static(component: $metric->get_component(), name: $metric->get_name());
    }

    /**
     * Transforms an instance of the mapped class into an associative array of data that can be used in DB queries.
     *
     * The data can then be passed as an argument to functions such as e.g. {@see \moodle_database::update_record}.
     *
     * @param string[] $fields The output array will only have entries that are properties of the object **and** that are specified
     *                         in this argument.
     * @return array<string, mixed> DB-friendly data taken from the instance.
     */
    public function to_array(array $fields = self::FIELDS): array {
        $data = array_flip(array_intersect(self::FIELDS, $fields));
        array_walk($data, fn (mixed &$value, string $field) => $value = $this->$field);
        return $data;
    }

    /**
     * Inserts records in the DB table for the provided instances.
     *
     * Unless explicitly set to any value other than `null`, assigns the current time to {@see self::$timecreated `timecreated`}
     * and {@see self::$timemodified `timemodified`} and the current user to {@see self::$usermodified `usermodified`} on each
     * instance before inserting.
     *
     * @param self ...$instances Instances to turn into database records.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function insert_many(self ...$instances): void {
        global $DB, $USER;
        if (empty($instances)) {
            return;
        }
        $now = time();
        $toinsert = [];
        foreach ($instances as $instance) {
            $instance->timecreated ??= $now;
            $instance->timemodified ??= $now;
            $instance->usermodified ??= $USER->id;
            $toinsert[] = $instance->to_array();
        }
        $DB->insert_records(self::TABLE, $toinsert);
    }

    /**
     * Updates the corresponding row in the database table with data from the object.
     *
     * The {@see self::$timemodified `timemodified`} is set to the current time before updating.
     *
     * @param string[] $fields If specified, only these fields will be updated.
     * @param int|null $usermodified Assigned to {@see self::$usermodified `usermodified`} before updating; if `null` (default),
     *                               the current user is used.
     * @throws dml_exception
     */
    public function update(array $fields = self::FIELDS, int|null $usermodified = null): void {
        global $DB, $USER;
        $this->timemodified = time();
        $this->usermodified = $usermodified ?? $USER->id;
        $data = ['id' => $this->id] + $this->to_array([...$fields, 'timemodified', 'usermodified']);
        $DB->update_record(self::TABLE, $data);
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
    public static function get_qualified_name_sql(moodle_database $db): string {
        return $db->sql_concat_join(separator: "'_'", elements: ['component', 'name']);
    }
}

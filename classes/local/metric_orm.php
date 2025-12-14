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
 * Definition of the {@see metric_orm} trait.
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

namespace tool_monitoring\local;

use core\exception\coding_exception;
use dml_exception;
use JsonException;
use stdClass;

/**
 * Encapsulates all DB manipulations related to {@see metric} instances.
 *
 * **NOTE**: This trait exists purely to separate DB logic from metrics/monitoring logic _visually_.
 *           It is just an implementation detail and should not be used by classes other than {@see metric}.
 *
 * For metrics with custom configurations, the trait is generic in terms of the {@see config} type.
 *
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
trait metric_orm {

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

    /**
     * Constructor without additional logic.
     *
     * @param string $component Component defining the metric.
     * @param string $name Name of the metric.
     * @param bool $enabled If `false` the metric will never be calculated or exported.
     * @param ConfT $config Metric-specific config data; empty object if no specific config is defined for the metric.
     * @param int|null $timecreated Timestamp when the DB table entry for the metric was inserted; `null` if none exists (yet).
     * @param int|null $timemodified Timestamp when the DB table entry was last modified; `null` if not (yet) saved.
     * @param int|null $usermodified ID of the user that last modified the DB table entry; `null` if not (yet) saved.
     * @param int|null $id Primary key of the corresponding DB table row; `null` if not (yet) saved.
     */
    final private function __construct(
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
     * @return static New instance constructed from the provided `$untyped` data.
     * @throws coding_exception A required field was missing or a provided `config` string did not represent a valid JSON object.
     */
    private static function from_untyped_object(array|stdClass $untyped): static {
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
                throw new coding_exception("Cannot instantiate metric without `$name`");
            }
        }
        return new static(...$arguments);
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
     * @return static Instance matching the specified `$conditions`.
     * @throws coding_exception
     * @throws dml_exception No matching metric or multiple matching metrics found or an unexpected database error occurred.
     */
    private static function get(array $conditions = []): static {
        global $DB;
        return static::from_untyped_object(
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
    private function create(): static {
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
     * @return $this Same instance with updated {@see timemodified} and {@see usermodified} fields.
     * @throws coding_exception Instance was missing an {@see id} value.
     * @throws dml_exception
     * @throws JsonException The {@see config} object could not be serialized.
     */
    private function update(array|null $fields = null): static {
        global $DB, $USER;
        if (is_null($this->id)) {
            // TODO: Use custom exception class.
            throw new coding_exception('Cannot update instance without `id` property');
        }
        $this->timemodified = time();
        $this->usermodified = $USER->id;
        $DB->update_record(self::TABLE, $this->to_db($fields));
        return $this;
    }
}

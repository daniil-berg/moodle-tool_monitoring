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
 * Definition of the {@see metric_config} class.
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
use dml_exception;
use dml_missing_record_exception;
use Exception;
use JsonException;
use stdClass;

/**
 * Maps instances to records in the `tool_monitoring_config` table, encapsulating the configuration of a {@see metric}.
 *
 * If there is custom config {@see data} associated with the metric, it will be saved in the database as a JSON object.
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
final class metric_config {

    /** @var string Name of the mapped DB table. */
    const string TABLE = 'tool_monitoring_config';

    /** @var array<string,bool> Names of all fields in the DB table mapped to whether or not they are required for construction. */
    const array FIELDS_REQUIRED = [
        'component'    => true,
        'name'         => true,
        'enabled'      => false,
        'data'         => false,
        'timecreated'  => false,
        'timemodified' => false,
        'usermodified' => false,
        'id'           => false,
    ];

    /**
     * Constructor without additional logic.
     *
     * @param string $component Component defining the metric the config is for ({@see metric::get_component}).
     * @param string $name Name of the metric the config is for ({@see metric::get_name}).
     * @param bool $enabled If `false` (default) the metric will never be calculated or exported.
     * @param stdClass $data The actual metric-specific configuration data deserialized from a JSON object; empty object if no such
     *                       metric-specific config data exists for the metric.
     * @param int|null $timecreated Timestamp when the database row for the metric config was inserted; `null` if none exists (yet).
     * @param int|null $timemodified Timestamp when the metric config was last modified; `null` if not (yet) saved in the database.
     * @param int|null $usermodified ID of the user that last modified the metric config; `null` if not (yet) saved in the database.
     * @param int|null $id Primary key of the corresponding database table row; `null` if not (yet) saved in the database.
     */
    public function __construct(
        public readonly string   $component,
        public readonly string   $name,
        public          bool     $enabled      = false,
        public          stdClass $data         = new stdClass(),
        public readonly int|null $timecreated  = null,
        public          int|null $timemodified = null,
        public          int|null $usermodified = null,
        public readonly int|null $id           = null,
    ) {}

    /**
     * Constructs a new instance from an untyped data object/array with the necessary properties/keys.
     *
     * Which values are required is flagged in the {@see self::FIELDS_REQUIRED} constant.
     *
     * If a `data` key is present and a string value, {@see json_decode} will be used to turn it into a {@see stdClass} object.
     *
     * @param array|stdClass $untyped Data from which to construct the config instance; must have the required keys/properties.
     * @return self New instance constructed from the provided `$untyped` data.
     * @throws coding_exception A required field was missing or a provided `data` string did not represent a valid JSON object.
     */
    private static function from_untyped_object(array|stdClass $untyped): self {
        $untyped = (array) $untyped;
        $arguments = [];
        foreach (self::FIELDS_REQUIRED as $name => $required) {
            if (array_key_exists($name, $untyped)) {
                $value = $untyped[$name];
                if ($name == 'data' && is_string($value)) {
                    $value = json_decode($value);
                    if (!($value instanceof stdClass)) {
                        // TODO: Use custom exception class.
                        throw new coding_exception('The provided `data` is not a valid JSON object.');
                    }
                }
                $arguments[$name] = $value;
            } else if ($required) {
                // TODO: Use custom exception class.
                throw new coding_exception("Missing `$name` for `metric_config`");
            }
        }
        return new self(...$arguments);
    }

    /**
     * Retrieves the config for the specified metric from the database, creating one first, if it does not exist yet.
     *
     * If a new config entry is created, it will use the data returned by the {@see metric::get_default_config_data} method.
     *
     * @param metric $metric Metric for which to get/create the config object.
     * @return self New instance of the config for the provided `$metric`.
     * @throws coding_exception Should not happen.
     * @throws dml_exception
     * @throws JsonException Failed to (de-)serialize the config `data` value.
     */
    public static function for_metric(metric $metric): self {
        global $DB;
        $conditions = ['component' => $metric::get_component(), 'name' => $metric::get_name()];
        $transaction = $DB->start_delegated_transaction();
        try {
            try {
                $config = self::get($conditions);
            } catch (dml_missing_record_exception) {
                $defaultdata = $metric::get_default_config_data();
                $conditions['data'] = $defaultdata ? (object) $defaultdata : new stdClass();
                $config = self::from_untyped_object($conditions)->create();
            }
            $transaction->allow_commit();
            return $config;
        } catch (Exception $e) {
            if (!empty($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($e);
            }
            throw $e;
        }
    }

    /**
     * Fetches a metric config matching the specified conditions from the database.
     *
     * @param array $conditions Associative array with field names as keys and values to match.
     * @return self Config instance matching the specified `$conditions`.
     * @throws coding_exception
     * @throws dml_exception No matching metric or multiple matching metrics found or an unexpected database error occurred.
     */
    public static function get(array $conditions = []): self {
        global $DB;
        return self::from_untyped_object(
            $DB->get_record(self::TABLE, $conditions, strictness: MUST_EXIST)
        );
    }

    /**
     * Updates the corresponding row in the database table with data from the object.
     *
     * **Note**:
     * The `id` is needed to identify the actual DB entry to update. If it is not set, an error will be thrown.
     *
     * @return $this The same instance with updated {@see timemodified} and {@see usermodified} fields.
     * @throws coding_exception Instance was missing an {@see id} value.
     * @throws dml_exception
     * @throws JsonException The {@see data} object could not be serialized.
     */
    public function update(): self {
        global $DB, $USER;
        if (is_null($this->id)) {
            // TODO: Use custom exception class.
            throw new coding_exception('Cannot update metric config without `id` property');
        }
        $this->timemodified = time();
        $this->usermodified = $USER->id;
        $DB->update_record(self::TABLE, $this->to_db());
        return $this;
    }

    /**
     * Inserts a corresponding row into the database table with data from the object.
     *
     * **Note**:
     * The `id` will always be set by the DB during creation. Therefore, calling this method on an instance with an `id` that is
     * not `null` will result in an error.
     *
     * @return self Copy of the instance with its {@see id} and {@see timecreated} fields set and the {@see timemodified}
     *              and {@see usermodified} fields updated.
     * @throws coding_exception Instance already had an {@see id} value.
     * @throws dml_exception
     * @throws JsonException The {@see data} object could not be serialized.
     */
    public function create(): self {
        global $DB, $USER;
        if (!is_null($this->id)) {
            // TODO: Use custom exception class.
            throw new coding_exception('Cannot insert metric config that already has an `id` property');
        }
        $currenttime = time();
        $this->timemodified = $currenttime;
        $this->usermodified = $USER->id;
        $untyped = $this->to_db();
        $untyped['id'] = $DB->insert_record(self::TABLE, $untyped);
        $untyped['data'] = $this->data;
        $untyped['timecreated'] = $currenttime;
        return self::from_untyped_object($untyped);
    }

    /**
     * Transforms an instance of the class into an associative array of data that can be used in DB queries.
     *
     * The data can then be passed as an argument to functions such as e.g. {@see \moodle_database::update_record}.
     *
     * In the output array the `data` value is serialized with {@see json_encode}.
     *
     * @return array DB-friendly data taken from the instance.
     * @throws JsonException The {@see data} object could not be serialized.
     */
    private function to_db(): array {
        $output = (array) $this;
        $output['data'] = json_encode($this->data, JSON_THROW_ON_ERROR);
        return $output;
    }
}

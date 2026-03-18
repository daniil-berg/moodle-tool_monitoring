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
 * Definition of the {@see metric_config} interface.
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

use JsonSerializable;
use moodleform;
use MoodleQuickForm;
use stdClass;
use tool_monitoring\form\config as config_form;

/**
 * Defines the optional configuration interface used by a {@see metric_with_config}.
 *
 * If a {@see metric_with_config} has specific associated configuration options, those are represented as a JSON object.
 * Classes must therefore implement the {@see JsonSerializable} interface as well as the {@see self::from_json} method. The former
 * method determines what is saved in the database via {@see json_encode}, while the latter does the inverse.
 *
 * In addition, a metric config can be used together {@see moodleform}s. This is facilitated by the {@see self::with_form_data},
 * {@see self::to_form_data}, {@see self::extend_form_definition}, and {@see self::extend_form_validation} methods.
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
interface metric_config extends JsonSerializable {
    /**
     * Constructs a new instance from a JSON config object.
     *
     * Should be the inverse of the {@see JsonSerializable::jsonSerialize} method.
     *
     * @param string $json String of a valid JSON object (not an array or any other type).
     * @return self New instance of the config class.
     */
    public static function from_json(string $json): self;

    /**
     * Constructs a new instance from the (non-empty) output of {@see moodleform::get_data}.
     *
     * @param stdClass $formdata Form data to use for construction.
     * @return self New instance of the config class.
     */
    public static function with_form_data(stdClass $formdata): self;

    /**
     * Transforms an instance into an associative array of data that can be passed to {@see moodleform::set_data}.
     *
     * @return array<string, mixed> Data to set on the config form.
     */
    public function to_form_data(): array;

    /**
     * Extends the definition of the configuration form.
     *
     * Called at the end of the {@see config_form::definition} method.
     *
     * Implementations _should_ ensure that any added form fields are compatible with {@see with_form_data} and {@see to_form_data}.
     *
     * @param config_form $configform Metric configuration form being defined.
     * @param MoodleQuickForm $mform Underlying/wrapped Moodle form instance.
     *
     * @link https://docs.moodle.org/dev/lib/formslib.php_Form_Definition Moodle docs on form definition
     */
    public static function extend_form_definition(config_form $configform, MoodleQuickForm $mform): void;

    /**
     * Extends the validation of the configuration form and returns an array of error messages.
     *
     * Called at the end of the {@see config_form::validation} method.
     *
     * Implementations _should_ only return error messages for fields defined by their own {@see extend_form_definition} method.
     *
     * @param array<string, mixed> $data Form data to validate, indexed by field name.
     * @param config_form $configform Metric configuration form being validated.
     * @param MoodleQuickForm $mform Underlying/wrapped Moodle form instance.
     * @return array<string, string> If something is not valid, an array of error messages, indexed by field name; empty otherwise.
     *
     * @link https://docs.moodle.org/dev/lib/formslib.php_Validation Moodle docs on form validation
     */
    public static function extend_form_validation(array $data, config_form $configform, MoodleQuickForm $mform): array;
}

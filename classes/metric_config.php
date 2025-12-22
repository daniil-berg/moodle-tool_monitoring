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

/**
 * Defines the optional configuration interface of a {@see metric} using the {@see with_config} trait.
 *
 * If a {@see metric} has specific associated configuration options, those are represented as a JSON object. Any implementing class
 * must therefore implement the {@see JsonSerializable} interface as well as the {@see self::from_json} method. The former
 * method determines what is saved in the database via {@see json_encode}, while the latter does the inverse.
 *
 * In addition, a metric config can be used together {@see moodleform}s. This is facilitated by the {@see self::with_form_data},
 * {@see self::to_form_data}, and {@see self::extend_config_form} methods.
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
     * @return static New instance of the config class.
     */
    public static function from_json(string $json): static;

    /**
     * Constructs a new instance from the (non-empty) output of {@see moodleform::get_data}.
     *
     * @param stdClass $formdata Form data to use for construction.
     * @return static New instance of the config class.
     */
    public static function with_form_data(stdClass $formdata): static;

    /**
     * Transforms an instance into an associative array of data that can be passed to {@see moodleform::set_data}.
     *
     * @return array<string, mixed> Data to set on the config form.
     */
    public function to_form_data(): array;

    /**
     * Extends/modifies a {@see MoodleQuickForm} for the config.
     *
     * Implementations _should_ ensure that any added form fields are compatible with {@see with_form_data} and {@see to_form_data}.
     *
     * @param MoodleQuickForm $mform Configuration form.
     */
    public static function extend_config_form(MoodleQuickForm $mform): void;
}

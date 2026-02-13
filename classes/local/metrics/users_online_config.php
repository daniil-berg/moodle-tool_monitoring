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
 * Definition of the {@see users_online_config} class.
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

namespace tool_monitoring\local\metrics;

use core\exception\coding_exception;
use core\lang_string;
use MoodleQuickForm;
use stdClass;
use tool_monitoring\metric_config;

/**
 * Defines the config for the {@see users_online} metric.
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
final readonly class users_online_config implements metric_config {
    /**
     * @var array<float|int> Maximum number of seconds since the last user access for it to be counted.
     *                       A separate (labeled) metric value shall be produced for each value in this array.
     */
    public array $timewindows;

    /**
     * Takes the `timewindows` values, sorts them numerically, and removes duplicates.
     *
     * @param float|int ...$timewindows Maximum number of seconds since the last user access for it to be counted.
     *                                  Passing multiple values here means multiple (labeled) metric values shall be produced.
     * @throws coding_exception
     */
    public function __construct(float|int ...$timewindows) {
        if (empty($timewindows)) {
            throw new coding_exception("At least one 'timewindows' argument must be provided");
        }
        sort($timewindows, SORT_NUMERIC);
        $this->timewindows = array_values(array_unique($timewindows));
    }

    /**
     * Returns the instance as is, in effect turning every public property into a key-value-pair in the resulting JSON object.
     *
     * @return $this Same instance.
     */
    public function jsonSerialize(): self {
        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $json String of a valid JSON object (not an array or any other type).
     * @return self New instance of the config class.
     * @throws coding_exception JSON is not valid or not an object or missing config parameters.
     */
    public static function from_json(string $json): self {
        $data = json_decode($json, associative: true);
        if (empty($data) || !is_array($data) || array_is_list($data)) {
            throw new coding_exception('Invalid JSON');
        }
        $timewindows = $data['timewindows'] ?? null;
        if (is_null($timewindows)) {
            throw new coding_exception("Missing 'timewindows' in JSON");
        }
        if (!is_array($timewindows) || !array_is_list($timewindows)) {
            throw new coding_exception("JSON value 'timewindows' is not an array");
        }
        return new self(...$timewindows);
    }

    /**
     * {@inheritDoc}
     *
     * @param stdClass $formdata Form data to use for construction.
     * @return self New instance of the config class.
     * @throws coding_exception
     */
    public static function with_form_data(stdClass $formdata): self {
        $timewindowsstring = $formdata->timewindows ?? null;
        if (is_null($timewindowsstring)) {
            throw new coding_exception("Missing 'timewindows' in form data");
        }
        if (!is_string($timewindowsstring)) {
            throw new coding_exception("Form data 'timewindows' is not a string");
        }
        $timewindows = explode(',', $timewindowsstring);
        foreach ($timewindows as $value) {
            if (!is_numeric($value)) {
                throw new coding_exception("Form data 'timewindows' contains non-numeric value: $value");
            }
        }
        return new self(...$timewindows);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed> Data to set on the config form.
     */
    public function to_form_data(): array {
        return ['timewindows' => implode(', ', $this->timewindows)];
    }

    /**
     * {@inheritDoc}
     */
    public static function extend_config_form(MoodleQuickForm $mform): void {
        $mform->addElement('text', 'timewindows', new lang_string('users_online_time_windows', 'tool_monitoring'));
        $mform->setType('timewindows', PARAM_TEXT);
    }
}

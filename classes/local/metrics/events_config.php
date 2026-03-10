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
 * Definition of the {@see events_config} class.
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

use core\component;
use core\event\base as event_base;
use core\exception\coding_exception;
use core\lang_string;
use MoodleQuickForm;
use ReflectionClass;
use stdClass;
use tool_monitoring\form\config as config_form;
use tool_monitoring\metric_config;

/**
 * Defines the config for the {@see events} metric.
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
final readonly class events_config implements metric_config {
    /**
     * @var string[] Fully qualified event class names with leading backslash.
     */
    public array $eventnames;

    /**
     * @var array<float|int> Maximum age of log entries in seconds. A separate labeled metric value is produced per value.
     */
    public array $timewindows;

    /**
     * Takes the event names and time windows, normalizes them and validates values.
     *
     * @param string[] $eventnames Fully qualified event class names with leading backslash.
     * @param int[] $timewindows Maximum age of log entries in seconds.
     */
    public function __construct(array $eventnames = [], array $timewindows = []) {
        sort($eventnames);
        $this->eventnames = array_values(array_unique($eventnames));
        sort($timewindows, SORT_NUMERIC);
        $this->timewindows = array_values(array_unique($timewindows));
        foreach ($this->timewindows as $timewindow) {
            if ($timewindow <= 0) {
                throw new coding_exception("Invalid time window value: $timewindow");
            }
        }
    }

    /**
     * Returns the instance as is, in effect turning every public property into a key-value-pair in the resulting JSON object.
     *
     * @return $this Same instance.
     */
    #[\Override]
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
    #[\Override]
    public static function from_json(string $json): self {
        $data = json_decode($json, associative: true);
        if (empty($data) || !is_array($data) || array_is_list($data)) {
            throw new coding_exception('Invalid JSON');
        }
        $eventnames = $data['eventnames'] ?? null;
        if (is_null($eventnames)) {
            $eventnames = [];
        }
        if (!is_array($eventnames) || !array_is_list($eventnames)) {
            throw new coding_exception("JSON value 'eventnames' is not an array");
        }
        $timewindows = $data['timewindows'] ?? null;
        if (is_null($timewindows)) {
            throw new coding_exception("Missing 'timewindows' in JSON");
        }
        if (!is_array($timewindows) || !array_is_list($timewindows)) {
            throw new coding_exception("JSON value 'timewindows' is not an array");
        }
        return new self($eventnames, $timewindows);
    }

    /**
     * {@inheritDoc}
     *
     * @param stdClass $formdata Form data to use for construction.
     * @return self New instance of the config class.
     * @throws coding_exception
     */
    #[\Override]
    public static function with_form_data(stdClass $formdata): self {
        $eventnames = $formdata->eventnames ?? [];
        if (is_null($eventnames)) {
            $eventnames = [];
        } else if (!is_array($eventnames) || !array_is_list($eventnames)) {
            throw new coding_exception("No 'eventnames' array in form data");
        }
        $timewindows = $formdata->timewindows ?? [];
        if (is_null($timewindows)) {
            $timewindows = [];
        } else if (!is_array($timewindows) || !array_is_list($timewindows)) {
            throw new coding_exception("No 'timewindows' array in form data");
        }
        $timewindows = array_values(array_map(fn (mixed $value): string => trim((string) $value), $timewindows));
        if (empty($timewindows)) {
            throw new coding_exception("No time window values in form data");
        }
        foreach ($timewindows as $value) {
            if (!is_numeric($value)) {
                throw new coding_exception("Form data 'timewindows' contains non-numeric value: $value");
            }
        }
        return new self($eventnames, $timewindows);
    }

    #[\Override]
    public function to_form_data(): array {
        return [
            'eventnames' => $this->eventnames,
            'timewindows' => $this->timewindows,
        ];
    }

    #[\Override]
    public static function extend_form_definition(config_form $configform, MoodleQuickForm $mform): void {
        $eventnames = self::get_registered_event_options();
        $mform->addElement(
            'autocomplete',
            'eventnames',
            new lang_string('events_event_names', 'tool_monitoring'),
            array_combine($eventnames, $eventnames),
            ['multiple' => true],
        );
        $mform->setType('eventnames', PARAM_TEXT);
        $mform->addHelpButton('eventnames', 'events_event_names', 'tool_monitoring');

        $mform->addElement(
            'autocomplete',
            'timewindows',
            new lang_string('events_time_windows', 'tool_monitoring'),
            [],
            ['multiple' => true, 'tags' => true, 'showsuggestions' => false],
        );
        $mform->setType('timewindows', PARAM_TAGLIST);
        $mform->addHelpButton('timewindows', 'events_time_windows', 'tool_monitoring');
        $mform->addRule('timewindows', null, 'required', null, 'client');
    }

    #[\Override]
    public static function extend_form_validation(array $data, config_form $configform, MoodleQuickForm $mform): array {
        $errors = [];
        $eventnames = $data['eventnames'] ?? [];
        if (!is_array($eventnames) || !array_is_list($eventnames)) {
            throw new coding_exception("No 'eventnames' array in form data");
        }
        $availableeventnames = self::get_registered_event_options();
        $invalideventnames = array_values(array_diff($eventnames, $availableeventnames));
        if (!empty($invalideventnames)) {
            $errors['eventnames'] = new lang_string(
                identifier: 'error:events_config:eventnames_invalid',
                component: 'tool_monitoring',
                a: implode(', ', $invalideventnames),
            );
        }

        $timewindows = $data['timewindows'] ?? [];
        if (!is_array($timewindows) || !array_is_list($timewindows)) {
            throw new coding_exception("No 'timewindows' array in form data");
        }
        $invalidtimewindows = array_filter($timewindows, fn (string $v) => !is_numeric($v) || $v <= 0);
        if (!empty($invalidtimewindows)) {
            $errors['timewindows'] = new lang_string(
                identifier: 'error:events_config:timewindows_invalid',
                component: 'tool_monitoring',
                a: implode(', ', $invalidtimewindows),
            );
        }
        return $errors;
    }

    /**
     * Returns all selectable event names as values.
     *
     * @return string[]
     */
    private static function get_registered_event_options(): array {
        global $CFG;

        // Disable developer debugging as deprecated events will fire warnings.
        // Setup backup variables to restore the following settings back to what they were when we are finished.
        // See report_eventlist_list_generator::get_all_events_list.
        $debuglevel = $CFG->debug;
        $debugdisplay = $CFG->debugdisplay;
        $debugdeveloper = $CFG->debugdeveloper;
        $CFG->debug = 0;
        $CFG->debugdisplay = false;
        $CFG->debugdeveloper = false;

        $eventsignore = [
            \core\event\unknown_logged::class,
            \logstore_legacy\event\legacy_logged::class,
        ];
        $result = [];
        try {
            $events = component::get_component_classes_in_namespace(null, 'event');
            foreach (array_keys($events) as $event) {
                if (!is_a($event, event_base::class, true) || in_array($event, $eventsignore, true)) {
                    continue;
                }
                $reflectionclass = new ReflectionClass($event);
                if ($reflectionclass->isAbstract()) {
                    continue;
                }
                $result[] = $event;
            }
        } finally {
            $CFG->debug = $debuglevel;
            $CFG->debugdisplay = $debugdisplay;
            $CFG->debugdeveloper = $debugdeveloper;
        }
        sort($result);
        return $result;
    }
}

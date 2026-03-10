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
 * Definition of the {@see events} metric class.
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
use dml_exception;
use tool_monitoring\metric;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;
use tool_monitoring\strict_label_names;
use tool_monitoring\with_config;

/**
 * Gauges the number of selected event classes in the standard log store table for configured time windows.
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
class events extends metric {
    use strict_label_names;
    use with_config;

    /**
     * {@inheritDoc}
     */
    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    /**
     * {@inheritDoc}
     */
    public static function get_description(): lang_string {
        return new lang_string('events_description', 'tool_monitoring');
    }

    /**
     * {@inheritDoc}
     *
     * @return metric_value[]
     * @throws coding_exception
     * @throws dml_exception
     */
    public function calculate(): array {
        global $DB;
        $config = $this->parse_config(events_config::class);
        if (empty($config->eventnames)) {
            return [];
        }
        $values = [];
        $now = time();
        foreach ($config->timewindows as $timewindow) {
            $names = array_map(fn (string $s) => "\\$s", $config->eventnames);
            [$insql, $params] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED, 'event');
            $params['mintimecreated'] = $now - $timewindow;
            $sql = "SELECT eventname,
                           COUNT(*) AS numevents
                      FROM {logstore_standard_log}
                     WHERE eventname $insql
                       AND timecreated >= :mintimecreated
                  GROUP BY eventname";
            $records = $DB->get_records_sql($sql, $params);
            foreach ($config->eventnames as $event) {
                $values[] = new metric_value(
                    value: $records["\\$event"]?->numevents ?? 0,
                    label: [
                        'event' => $event,
                        'time_window' => "{$timewindow}s",
                    ],
                );
            }
        }
        return $values;
    }

    /**
     * {@inheritDoc}
     */
    public static function get_default_config(): events_config {
        return new events_config([], [3600]);
    }

    /**
     * {@inheritDoc}
     */
    protected static function get_label_names(): array {
        return ['event', 'time_window'];
    }
}

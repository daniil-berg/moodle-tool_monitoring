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
 * Definition of the {@see users_online} metric class.
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
use tool_monitoring\with_config;

/**
 * Gauges the number of users online within certain time windows.
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
class users_online extends metric {
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
        return new lang_string('users_online_description', 'tool_monitoring');
    }

    /**
     * {@inheritDoc}
     *
     * @return metric_value[] One metric value per configured time window, labeled with that same time window, in ascending order.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function calculate(): array {
        global $DB;
        $config = $this->parse_config(users_online_config::class);
        $fieldssqlfragments = [];
        $params = [];
        $now = time();
        foreach ($config->timewindows as $i => $timewindow) {
            $fieldssqlfragments[] = "SUM(CASE WHEN lastaccess >= :timestamp$i THEN 1 ELSE 0 END) AS window$timewindow";
            $params["timestamp$i"] = $now - $timewindow;
        }
        $fieldssql = implode(",\n", $fieldssqlfragments);
        $sql = "SELECT $fieldssql
                  FROM {user}
                 WHERE username <> 'guest'";
        $record = $DB->get_record_sql(sql: $sql, params: $params, strictness: MUST_EXIST);
        return array_map(
            fn (float|int $timewindow): metric_value => new metric_value(
                value: $record->{"window$timewindow"},
                label: ['time_window' => "{$timewindow}s"],
            ),
            $config->timewindows,
        );
    }

    /**
     * {@inheritDoc}
     * {@noinspection PhpUnhandledExceptionInspection}
     */
    public static function get_default_config(): users_online_config {
        return new users_online_config(60, 300, 900, 3600);
    }
}

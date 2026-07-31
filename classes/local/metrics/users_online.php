<?php
// This file is part of the tool_monitoring plugin for Moodle - https://moodle.org/
//
// tool_monitoring is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// tool_monitoring is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with tool_monitoring.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_monitoring\local\metrics;

use core\exception\coding_exception;
use dml_exception;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;
use tool_monitoring\metric_with_config;

/**
 * Gauges the number of users online within certain time windows.
 *
 * @phpcs:disable moodle.Commenting.ValidTags.Invalid
 * @extends metric_with_config<users_online_config>
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
class users_online extends metric_with_config {
    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    /**
     * Produces the current metric values.
     *
     * @param users_online_config|null $config Metric configuration.
     * @return metric_value[] One metric value per configured time window, labeled with that same time window, in ascending order.
     * @throws dml_exception
     */
    #[\Override]
    public function calculate(metric_config|null $config = null): array {
        global $DB;
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
     * Returns the default config for the metric.
     *
     * @return users_online_config Config object.
     * @throws coding_exception
     */
    #[\Override]
    public function get_default_config(): users_online_config {
        return new users_online_config(60, 300, 900, 3600);
    }
}

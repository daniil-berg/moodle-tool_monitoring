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
 * Implements the num_users_accessed metric.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauck <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring\local\metrics;

use core\lang_string;
use tool_monitoring\local\metrics\metric_value;

/**
 * Implements the num_users_accessed metric.
 */
class num_users_accessed extends metric_base {

    /**
     * {@inheritDoc}
     *
     * @return metric_type
     */
    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    /**
     * {@inheritDoc}
     * @return \core\lang_string
     */
    public static function get_description(): lang_string {
        return new lang_string('num_users_accessed_description', 'tool_monitoring');
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function calculate(int $max_seconds_ago = 30, int $min_seconds_ago = 0): array {
        global $DB;
        $now = time();
        $metrics = [];
        $where = 'username <> :excl_user AND lastaccess BETWEEN :earliest AND :latest';
        $params = [
            'excl_user' => 'guest',
            'earliest'  => $now - $max_seconds_ago,
            'latest'    => $now - $min_seconds_ago,
        ];
        $value = $DB->count_records_select('user', $where, $params);
        $metric_value = new metric_value($value, ['time' => 30, 'test' => 2]);
        array_push($metrics, $metric_value);

        $metric_value2 = new metric_value(2, ['time' => 15, 'test' => 2]);
        array_push($metrics, $metric_value2);

        return $metrics;
    }
}
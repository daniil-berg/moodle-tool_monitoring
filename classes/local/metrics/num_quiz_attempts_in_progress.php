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
 * Implements the num_quiz_attempts_in_progress metric.
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

use core\lang_string;
use tool_monitoring\metric_type;
use tool_monitoring\metric;
use tool_monitoring\metric_value;

/**
 * Implements the num_quiz_attempts_in_progress metric.
 */
class num_quiz_attempts_in_progress extends metric {

    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    public static function get_description(): lang_string {
        return new lang_string('num_quiz_attempts_in_progress_description', 'tool_monitoring');
    }

    public function calculate(object|null $config): metric_value {
        global $DB;
        $now = time();
        $where = 'state = :state';
        $params = ['state' => 'inprogress'];
        // TODO: Make these configurable.
        $max_seconds_ago = 3600;
        $max_seconds_to_end = 4 * 3600;
        $where .= ' AND timemodified >= :time_modified';
        $where .=' " AND timecheckstate <= :time_check_state"';
        $params['time_modified'] = $now - $max_seconds_ago;
        $params['time_check_state'] = $now + $max_seconds_to_end;
        return new metric_value($DB->count_records_select('quiz_attempts', $where, $params));
    }
}
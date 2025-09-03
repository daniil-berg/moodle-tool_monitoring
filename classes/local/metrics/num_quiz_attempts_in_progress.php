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
 * Definition of the {@see num_quiz_attempts_in_progress}.
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

class num_quiz_attempts_in_progress implements metric_interface {

    public static function get_name(): string {
        return 'num_quiz_attempts_in_progress';
    }

    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    public static function get_description(): lang_string {
        return new lang_string('num_quiz_attempts_in_progress', 'tool_monitoring');
    }

    public static function calculate(int|null $max_seconds_ago = null, int|null $max_seconds_to_end = null): int {
        global $DB;
        $now = time();
        $where = 'state = :state';
        $params = ['state' => 'inprogress'];
        if (!is_null($max_seconds_ago)) {
            $where .= ' AND timemodified >= :time_modified';
            $params['time_modified'] = $now - $max_seconds_ago;
        }
        if (!is_null($max_seconds_to_end)) {
            $where .=' " AND timecheckstate <= :time_check_state"';
            $params['time_check_state'] = $now + $max_seconds_to_end;
        }
        return $DB->count_records_select('quiz_attempts', $where, $params);
    }
}
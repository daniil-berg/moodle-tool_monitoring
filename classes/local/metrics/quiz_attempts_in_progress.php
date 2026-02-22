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
 * Definition of the {@see quiz_attempts_in_progress} metric class.
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
use tool_monitoring\metric_type;
use tool_monitoring\metric;
use tool_monitoring\metric_value;
use tool_monitoring\with_config;

/**
 * Shows the number of ongoing quiz attempts.
 *
 * Attempts in quizzes that have no deadline approaching are excluded. As are attempts that have been idle for too long.
 * Both time windows are configurable.
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
class quiz_attempts_in_progress extends metric {
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
        return new lang_string('quiz_attempts_in_progress_description', 'tool_monitoring');
    }

    /**
     * {@inheritDoc}
     *
     * @return metric_value
     * @throws coding_exception
     * @throws dml_exception
     * /
     */
    public function calculate(): metric_value {
        global $DB;
        $config = $this->parse_config(quiz_attempts_in_progress_config::class);
        $now = time();
        $where = 'state = :state AND timemodified >= :min_time_modified AND timecheckstate <= :max_time_check_state';
        $params = [
            'state'                => 'inprogress',
            'min_time_modified'    => $now - $config->maxidleseconds,
            'max_time_check_state' => $now + $config->maxdeadlineseconds,
        ];
        return new metric_value(
            value: $DB->count_records_select('quiz_attempts', $where, $params),
            label: [
                'deadline_within' => "{$config->maxdeadlineseconds}s",
                'idle_within'     => "{$config->maxidleseconds}s",
            ],
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function get_default_config(): quiz_attempts_in_progress_config {
        return new quiz_attempts_in_progress_config();
    }
}

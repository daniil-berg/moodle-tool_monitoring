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
 * Definition of the {@see num_overdue_tasks} class.
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
use dml_exception;
use Generator;
use tool_monitoring\metric;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;
use tool_monitoring\strict_labels;

/**
 * Calculates the number of tasks that should have already executed but did not.
 *
 * The `task_type` label is used to distinguish between the number of overdue _adhoc_ tasks and overdue _scheduled_ tasks.
 * In the latter case disabled tasks are not counted.
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
class num_overdue_tasks extends metric {
    use strict_labels;

    public static function get_description(): lang_string {
        return new lang_string('metrics:num_overdue_tasks_description', 'tool_monitoring');
    }

    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    public static function get_labels(): array {
        return [['task_type' => 'adhoc'], ['task_type' => 'scheduled']];
    }

    /**
     * @return Generator<metric_value>
     * @throws dml_exception Database query failed.
     */
    public function calculate(): Generator {
        global $DB;
        $where = 'nextruntime <= :next_runtime';
        $params = ['next_runtime' => time()];
        yield new metric_value(
            value: $DB->count_records_select('task_adhoc', $where, $params),
            label: ['task_type' => 'adhoc'],
        );
        $where .= ' AND disabled = :disabled';
        $params['disabled'] = 0;
        yield new metric_value(
            value: $DB->count_records_select('task_scheduled', $where, $params),
            label: ['task_type' => 'scheduled'],
        );
    }
}
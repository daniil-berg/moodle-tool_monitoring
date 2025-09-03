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
 * Implements the num_overdue_tasks_adhoc metric.
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

/**
 * Implements the num_overdue_tasks_adhoc metric.
 */
class num_overdue_tasks_adhoc implements metric_interface {

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public static function get_name(): string {
        return 'num_overdue_tasks_adhoc';
    }

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
        return new lang_string('num_overdue_tasks_adhoc', 'tool_monitoring');
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function calculate(): int {
        global $DB;
        $where = 'nextruntime <= :next_runtime';
        $params = ['next_runtime' => time()];
        return $DB->count_records_select('task_adhoc', $where, $params);
    }
}
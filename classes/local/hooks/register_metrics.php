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

namespace tool_monitoring\local\hooks;

/**
 * Implementing callbacks for the gather_metrics hook.
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

/**
 * Implementing callbacks for the gather_metrics hook.
 */
class register_metrics {

    /**
     * Register our metrics.
     *
     * @param \tool_monitoring\hook\gather_metrics $hook
     * @return void
     */
    public static function callback(\tool_monitoring\hook\gather_metrics $hook): void {
        $hook->add_metric(new \tool_monitoring\local\metrics\num_user_count());
        $hook->add_metric(new \tool_monitoring\local\metrics\num_overdue_tasks());
        $hook->add_metric(new \tool_monitoring\local\metrics\num_quiz_attempts_in_progress());
        $hook->add_metric(new \tool_monitoring\local\metrics\num_tasks_spawned_adhoc());
        $hook->add_metric(new \tool_monitoring\local\metrics\num_tasks_spawned_scheduled());
        $hook->add_metric(new \tool_monitoring\local\metrics\num_users_accessed());
        $hook->add_metric(new \tool_monitoring\local\metrics\num_course_count());
    }
}

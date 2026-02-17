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
 * Definition of the {@see overdue_tasks_test} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection, PhpUnhandledExceptionInspection}
 */

namespace tool_monitoring\local\metrics;

use advanced_testcase;
use core\task\adhoc_test_task;
use core\task\manager as task_manager;
use core\task\scheduled_test2_task;
use core\task\scheduled_test3_task;
use core\task\scheduled_test_task;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Unit tests for the {@see overdue_tasks} class.
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
#[CoversClass(overdue_tasks::class)]
final class overdue_tasks_test extends advanced_testcase {
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once("$CFG->libdir/tests/fixtures/task_fixtures.php");
    }

    public function test_get_type(): void {
        $metric = new overdue_tasks();
        self::assertSame(metric_type::GAUGE, $metric->get_type());
    }

    public function test_get_description(): void {
        $metric = new overdue_tasks();
        $description = $metric->get_description();
        self::assertSame('overdue_tasks_description', $description->get_identifier());
        self::assertSame('tool_monitoring', $description->get_component());
    }

    public function test_get_labels(): void {
        $fixedlabels = overdue_tasks::get_labels();
        self::assertSame([['type' => 'adhoc'], ['type' => 'scheduled']], $fixedlabels);
    }

    public function test_calculate(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        // Create and queue a few adhoc tasks.
        foreach ([-100, -10, -1, 100, 200, 300] as $nextruntimeoffset) {
            task_manager::queue_adhoc_task(new adhoc_test_task(nextruntime: $now + $nextruntimeoffset));
        }
        // Since three of them have their next runtime in the past, they should be counted as overdue.
        $excpectednumadhoc = 3;
        // In addition to the pre-defined scheduled tasks that have never run yet, create a few more.
        $scheduledtask1 = new scheduled_test_task();
        $scheduledtask1->set_minute('*');
        $scheduledtask1->set_next_run_time($now - 1000);
        $scheduledtask2 = new scheduled_test2_task();
        $scheduledtask2->set_minute('*');
        $scheduledtask2->set_next_run_time($now + 1000);
        $scheduledtask3 = new scheduled_test3_task();
        $scheduledtask3->set_minute('*');
        $scheduledtask3->set_next_run_time($now + 2000);
        $DB->insert_records('task_scheduled', [
            task_manager::record_from_scheduled_task($scheduledtask1),
            task_manager::record_from_scheduled_task($scheduledtask2),
            task_manager::record_from_scheduled_task($scheduledtask3),
        ]);
        // The number of overdue scheduled tasks can vary depending on what pre-defined scheduled tasks exist in Moodle.
        $expectednumscheduled = $DB->count_records_select(
            table: 'task_scheduled',
            select: 'nextruntime <= :next_runtime AND disabled = :disabled',
            params: ['next_runtime' => $now, 'disabled' => false],
        );
        $metric = new overdue_tasks();
        $result = iterator_to_array($metric->calculate());
        self::assertCount(2, $result);
        [$numadhoc, $numscheduled] = $result;
        self::assertEquals(
            new metric_value($excpectednumadhoc, ['type' => 'adhoc']),
            $numadhoc,
        );
        self::assertEquals(
            new metric_value($expectednumscheduled, ['type' => 'scheduled']),
            $numscheduled,
        );
    }
}

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

/**
 * Definition of the {@see metric_collection_test} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace tool_monitoring\hook;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_monitoring\local\metrics\courses;
use tool_monitoring\local\metrics\quiz_attempts_in_progress;
use tool_monitoring\local\metrics\user_accounts;

/**
 * Unit tests for the {@see metric_collection} hook.
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
#[CoversClass(metric_collection::class)]
final class metric_collection_test extends advanced_testcase {
    public function test_get_hook_description(): void {
        $hook = new metric_collection();
        $description = $hook->get_hook_description();
        self::assertSame('Provides the ability to register custom metrics.', $description);
    }

    public function test_get_hook_tags(): void {
        $hook = new metric_collection();
        $tags = $hook->get_hook_tags();
        self::assertSame(['metric', 'monitoring', 'tool_monitoring'], $tags);
    }

    /**
     * Tests adding and retrieving metrics.
     *
     * - The {@see metric_collection::add `add`} method.
     * - The {@see metric_collection::get `get`} method.
     * - The {@see IteratorAggregate} implementation.
     */
    public function test_add_get_and_iterator(): void {
        $metric0 = new courses();
        $metric1 = new courses();
        $metric2 = new quiz_attempts_in_progress();
        $metric3 = new user_accounts();
        $collection = new metric_collection();
        $collection->add($metric0);
        $collection->add($metric1); // Should replace `$metric0`.
        $collection->add($metric2);
        $collection->add($metric3);
        self::assertSame($metric1, $collection->get('tool_monitoring', 'courses'));
        self::assertSame($metric2, $collection->get('tool_monitoring', 'quiz_attempts_in_progress'));
        self::assertSame($metric3, $collection->get('tool_monitoring', 'user_accounts'));
        $metrics = iterator_to_array($collection);
        self::assertSame([$metric1, $metric2, $metric3], $metrics);
    }
}

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
 * Definition of the {@see num_courses_test} class.
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
use PHPUnit\Framework\Attributes\CoversClass;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Unit tests for the {@see num_courses} class.
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
#[CoversClass(num_courses::class)]
final class num_courses_test extends advanced_testcase {
    public function test_get_type(): void {
        $metric = new num_courses();
        self::assertSame(metric_type::GAUGE, $metric->get_type());
    }

    public function test_get_description(): void {
        $metric = new num_courses();
        $description = $metric->get_description();
        self::assertSame('num_courses_description', $description->get_identifier());
        self::assertSame('tool_monitoring', $description->get_component());
    }

    public function test_calculate(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        // Course with ID 1 always exists and is visible.
        // Create two more visible and two more hidden courses.
        $generator->create_course(['visible' => true]);
        $generator->create_course(['visible' => true]);
        $generator->create_course(['visible' => false]);
        $generator->create_course(['visible' => false]);
        $metric = new num_courses();
        $values = $metric->calculate();
        self::assertCount(2, $values);
        [$numvisible, $numhidden] = $values;
        self::assertEquals(
            new metric_value(3, ['visible' => 'true']),
            $numvisible,
        );
        self::assertEquals(
            new metric_value(2, ['visible' => 'false']),
            $numhidden,
        );
    }
}

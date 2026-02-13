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
 * Definition of the {@see users_online_test} class.
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
 * Unit tests for the {@see users_online} class.
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
#[CoversClass(users_online::class)]
final class users_online_test extends advanced_testcase {
    public function test_get_type(): void {
        $metric = new users_online();
        self::assertSame(metric_type::GAUGE, $metric->get_type());
    }

    public function test_get_description(): void {
        $metric = new users_online();
        $description = $metric->get_description();
        self::assertSame('users_online_description', $description->get_identifier());
        self::assertSame('tool_monitoring', $description->get_component());
    }

    public function test_calculate(): void {
        $this->resetAfterTest();
        $metric = new users_online();
        // Simulate the default config being applied here.
        $metric->configjson = '{"timewindows": [60, 300, 900, 3600]}';
        // Generate some users with different last access times.
        $now = time();
        $generator = $this->getDataGenerator();
        $generator->create_user(['lastaccess' => $now - 100000]);
        $generator->create_user(['lastaccess' => $now - 3000]);
        $generator->create_user(['lastaccess' => $now - 2000]);
        $generator->create_user(['lastaccess' => $now - 200]);
        $generator->create_user(['lastaccess' => $now - 100]);
        $generator->create_user(['lastaccess' => $now - 20]);
        $generator->create_user(['lastaccess' => $now - 10]);
        $generator->create_user(['lastaccess' => $now]);
        $values = $metric->calculate();
        self::assertCount(4, $values);
        self::assertEquals(
            [
                new metric_value(3, ['time_window' => '60s']),
                new metric_value(5, ['time_window' => '300s']),
                new metric_value(5, ['time_window' => '900s']),
                new metric_value(7, ['time_window' => '3600s']),
            ],
            $values,
        );
    }

    public function test_get_default_config(): void {
        $defaultconfig = users_online::get_default_config();
        self::assertSame([60, 300, 900, 3600], $defaultconfig->timewindows);
    }
}

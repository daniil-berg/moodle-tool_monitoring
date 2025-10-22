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
 * Definition of the {@see metric_value_test} class.
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
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace tool_monitoring;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(metric_value::class)]
class metric_value_test extends advanced_testcase {
    public function test___construct(): void {
        $instance = new metric_value(3.14);
        self::assertSame(3.14, $instance->value);
        self::assertSame([], $instance->label);
        $instance = new metric_value(0, ['a' => 'b']);
        self::assertSame(0, $instance->value);
        self::assertSame(['a' => 'b'], $instance->label);
        $instance = new metric_value(
            value: 420,
            label: ['foo' => 'bar', 'spam' => 'eggs'],
        );
        self::assertSame(420, $instance->value);
        self::assertSame(['foo' => 'bar', 'spam' => 'eggs'], $instance->label);
    }
}

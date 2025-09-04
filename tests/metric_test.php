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
 * Definition of the {@see metric_test} class.
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
use core\exception\coding_exception;
use core\lang_string;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(metric::class)]
class metric_test extends advanced_testcase {

    /**
     * Returns an instance of a class that extends {@see metric} for testing purposes.
     *
     * @return metric Anonymous class instance.
     */
    private static function get_test_metric(): metric {
        return new class extends metric {
            public function calculate(): void {
                $this[['label_a' => 'foo', 'label_b' => 'spam']] = 1;
                $this[['label_a' => 'foo', 'label_b' => 'eggs']] = 42;
                $this[['label_a' => 'bar', 'label_b' => 'spam']] = 2;
                $this[['label_a' => 'bar', 'label_b' => 'eggs']] = 999;
            }

            public static function get_description(): lang_string {
                // Just an arbitrary existing language string.
                return new lang_string('tested');
            }

            public static function get_name(): string {
                return 'test_metric';
            }

            public static function get_type(): metric_type {
                return metric_type::COUNTER;
            }
        };
    }

    public function test_array_like_interface(): void {
        $metric = self::get_test_metric();
        self::assertCount(0, $metric);
        self::assertFalse(isset($metric[['a' => 'b']]));

        $metric[['a' => 'b']] = 0;
        self::assertTrue(isset($metric[['a' => 'b']]));
        self::assertFalse(isset($metric[['a' => 'c']]));
        self::assertCount(1, $metric);

        $metric[['a' => 'c']] = 1;
        $metric[['x' => 'y']] = 2;
        self::assertSame(0, $metric[['a' => 'b']]);
        self::assertSame(1, $metric[['a' => 'c']]);
        self::assertSame(2, $metric[['x' => 'y']]);
        self::assertCount(3, $metric);

        // Test iterator interface.
        [$labelsset, $valuesset] = [[], []];
        foreach ($metric as $labels => $value) {
            $labelsset[] = $labels;
            $valuesset[] = $value;
        }
        self::assertSame([['a' => 'b'], ['a' => 'c'], ['x' => 'y']], $labelsset);
        self::assertSame([0, 1, 2], $valuesset);

        unset($metric[['a' => 'c']]);
        self::assertFalse(isset($metric[['a' => 'c']]));
        self::assertCount(2, $metric);

        // Iterator pointer should still be past the last element.
        self::assertSame(false, $metric->current());
        self::assertNull($metric->key());
        $metric->rewind();
        self::assertSame(0, $metric->current());
        self::assertSame(['a' => 'b'], $metric->key());
        $metric->next();
        self::assertSame(2, $metric->current());
        self::assertSame(['x' => 'y'], $metric->key());
        $metric->next();
        self::assertSame(false, $metric->current());
        self::assertNull($metric->key());
    }

    public function test_array_errors(): void {
        $metric = self::get_test_metric();
        $this->expectException(coding_exception::class);
        $metric['not_an_array'] = 1;
    }
}

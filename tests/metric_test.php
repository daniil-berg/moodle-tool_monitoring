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
use ArrayIterator;
use core\lang_string;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(metric::class)]
class metric_test extends advanced_testcase {

    /**
     * Returns an instance of a class that extends {@see metric} for testing purposes.
     *
     * @param iterable<metric_value>|metric_value $testvalues Metric values to be produced by the test metric.
     * @return metric Anonymous class instance.
     */
    private static function get_test_metric(iterable|metric_value $testvalues): metric {
        // TODO: Fix construction.
        return new class($testvalues) extends metric {

            /**
             * Sets up the test metric instance.
             *
             * @param iterable<metric_value>|metric_value $values Metric values to be produced by the metric.
             */
            public function __construct(
                private readonly iterable|metric_value $values,
            ) {}

            protected function calculate(): iterable|metric_value {
                return $this->values;
            }

            public static function get_description(): lang_string {
                // Just an arbitrary existing language string.
                return new lang_string('tested');
            }

            public static function get_type(): metric_type {
                return metric_type::COUNTER;
            }
        };
    }

    /**
     * @param iterable<metric_value>|metric_value $testvalues Metric values to be produced by the test metric.
     */
    #[DataProvider('test_iterator_provider')]
    public function test_iterator(iterable|metric_value $testvalues): void {
        $metric = self::get_test_metric($testvalues);
        // Consume the metric iterator.
        $metricvalues = iterator_to_array($metric);
        if ($testvalues instanceof metric_value) {
            self::assertEquals([$testvalues], $metricvalues);
        } elseif (is_array($testvalues)) {
            self::assertEquals($testvalues, $metricvalues);
        } else {
            self::assertEquals(iterator_to_array($testvalues), $metricvalues);
        }
    }

    /**
     * Provides test data for the {@see test_iterator} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function test_iterator_provider(): array {
        return [
            'Single metric value returned by the `calculate` method' => [
                'testvalues' => new metric_value(0),
            ],
            'Multiple metric values returned by the `calculate` method in an array' => [
                'testvalues' => [new metric_value(42), new metric_value(3.14)],
            ],
            'Multiple metric values produced by an iterator returned by the `calculate` method' => [
                'testvalues' => new ArrayIterator([new metric_value(-1), new metric_value(-2), new metric_value(-3)]),
            ],
        ];
    }

    public function test_get_name(): void {
        $metric = self::get_test_metric([]);
        $expected = preg_replace('/^tool_monitoring\\\metric@/', 'metric@', $metric::class);
        self::assertSame($expected, $metric::get_name());
    }
}

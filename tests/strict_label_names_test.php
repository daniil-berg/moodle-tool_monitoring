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
 * Definition of the {@see strict_label_names_test} class.
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
use coding_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\testing\metric_strict_label_names;

#[CoversClass(strict_label_names::class)]
class strict_label_names_test extends advanced_testcase {

    /**
     * @param string[] $labelnames Set of expected label names for the test metric.
     * @param metric_value[] $values Metric values to be produced by the test metric.
     * @param string|null $exception Name of exception class to expect; `null` (default) means no exception is expected.
     * @throws coding_exception
     */
    #[DataProvider('test_validate_value_provider')]
    public function test_validate_value(array $labelnames, array $values, string|null $exception = null): void {
        $testmetric = new metric_strict_label_names();
        $testmetric::$labelnames = $labelnames;
        $testmetric->values = $values;
        if (!is_null($exception)) {
            $this->expectException($exception);
        }
        $metric = registered_metric::from_metric($testmetric, syncdb: false);
        self::assertSame($values, iterator_to_array($metric));
    }

    /**
     * Provides test data for the {@see test_validate_value} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function test_validate_value_provider(): array {
        return [
            'Valid label names in different order' => [
                'labelnames' => ['a', 'b', 'c'],
                'values' => [
                    new metric_value(0, ['a' => 'x', 'b' => 'y', 'c' => 'z']),
                    new metric_value(1, ['c' => 'x', 'b' => 'y', 'a' => 'z']),
                ],
            ],
            'One invalid label name on the last value' => [
                'labelnames' => ['a', 'b'],
                'values' => [
                    new metric_value(0, ['a' => 'x', 'b' => 'y']),
                    new metric_value(1, ['b' => 'y', 'a' => 'z']),
                    new metric_value(2, ['c' => 'x', 'b' => 'y']),
                ],
                'exception' => coding_exception::class,
            ],
            'One missing label' => [
                'labelnames' => ['a', 'b'],
                'values' => [new metric_value(0, ['a' => 'x'])],
                'exception' => coding_exception::class,
            ],
            'No labels at all' => [
                'labelnames' => ['a', 'b'],
                'values' => [new metric_value(0)],
                'exception' => coding_exception::class,
            ],
        ];
    }
}

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
 * Definition of the {@see exporter_test} class.
 *
 * @package    monitoringexporter_prometheus
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

namespace monitoringexporter_prometheus;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\testing\test_lang_string;
use tool_monitoring\local\testing\test_metric;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;
use tool_monitoring\registered_metric;

/**
 * Unit tests for the {@see exporter} class.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(exporter::class)]
final class exporter_test extends advanced_testcase {
    #[DataProvider('provider_test_export')]
    public function test_export(array $metrics, string $expected): void {
        $arguments = array_map([registered_metric::class, 'from_metric'], $metrics);
        $output = exporter::export(...$arguments);
        self::assertSame($expected, $output);
    }

    /**
     * Provides test data for the {@see test_export} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_export(): array {
        return [
            'No metrics' => [
                'metrics' => [],
                'expected' => '',
            ],
            'Single counter metric, one value' => [
                'metrics' => [
                    test_metric::create('foo', values: [new metric_value(42)]),
                ],
                'expected' => <<<'TEXT'
                    # TYPE tool_monitoring_foo counter
                    tool_monitoring_foo 42
                    
                    TEXT,
            ],
            'Metric without values' => [
                'metrics' => [
                    test_metric::create('foo'),
                ],
                'expected' => <<<'TEXT'
                    # TYPE tool_monitoring_foo counter
                    
                    TEXT,
            ],
            'Single gauge metric, custom description, three labeled values' => [
                'metrics' => [
                    test_metric::create(
                        name: 'foo',
                        description: new test_lang_string('Lorem ipsum dolor sit amet...'),
                        type: metric_type::GAUGE,
                        values: [
                            new metric_value(1, ['spam' => 'eggs']),
                            new metric_value(2, ['spam' => 'beans']),
                            new metric_value(3, ['spam' => 'toast']),
                        ],
                    ),
                ],
                'expected' => <<<'TEXT'
                    # HELP tool_monitoring_foo Lorem ipsum dolor sit amet...
                    # TYPE tool_monitoring_foo gauge
                    tool_monitoring_foo{spam="eggs"} 1
                    tool_monitoring_foo{spam="beans"} 2
                    tool_monitoring_foo{spam="toast"} 3
                    
                    TEXT,
            ],
            'Metric with labeled value that needs to be escaped' => [
                'metrics' => [
                    test_metric::create(
                        name: 'foo',
                        values: [new metric_value(1, ['spam' => "doublequote\"backslash\\newline\n"])],
                    ),
                ],
                'expected' => <<<'TEXT'
                    # TYPE tool_monitoring_foo counter
                    tool_monitoring_foo{spam="doublequote\"backslash\\newline\n"} 1
                    
                    TEXT,
            ],
            'Metric with description that needs to be escaped' => [
                'metrics' => [
                    test_metric::create(
                        name: 'foo',
                        description: new test_lang_string("doublequote\"backslash\\newline\n"),
                    ),
                ],
                'expected' => <<<'TEXT'
                    # HELP tool_monitoring_foo doublequote"backslash\\newline\n
                    # TYPE tool_monitoring_foo counter
                    
                    TEXT,
            ],
            'Multiple metrics, different types, floats and integers, labeled and unlabeled' => [
                'metrics' => [
                    test_metric::create(
                        name: 'foo',
                        description: new test_lang_string('Lorem ipsum dolor sit amet...'),
                        values: [
                            new metric_value(1, ['spam' => 'eggs']),
                            new metric_value(2, ['spam' => 'beans']),
                            new metric_value(3, ['spam' => 'toast']),
                        ],
                    ),
                    test_metric::create(
                        name: 'bar',
                        type: metric_type::GAUGE,
                        values: [
                            new metric_value(3.14, ['spam' => 'eggs']),
                            new metric_value(420.69, ['spam' => 'beans']),
                        ],
                    ),
                    test_metric::create(
                        name: 'baz',
                        type: metric_type::GAUGE,
                        values: [new metric_value(1e-10)],
                    ),
                ],
                'expected' => <<<'TEXT'
                    # HELP tool_monitoring_foo Lorem ipsum dolor sit amet...
                    # TYPE tool_monitoring_foo counter
                    tool_monitoring_foo{spam="eggs"} 1
                    tool_monitoring_foo{spam="beans"} 2
                    tool_monitoring_foo{spam="toast"} 3
                    # TYPE tool_monitoring_bar gauge
                    tool_monitoring_bar{spam="eggs"} 3.14
                    tool_monitoring_bar{spam="beans"} 420.69
                    # TYPE tool_monitoring_baz gauge
                    tool_monitoring_baz 1.0E-10
                    
                    TEXT,
            ],
            'Metrics with infinite, negative infinite and NAN values' => [
                'metrics' => [
                    test_metric::create(
                        name: 'foo',
                        type: metric_type::GAUGE,
                        values: [new metric_value(INF)],
                    ),
                    test_metric::create(
                        name: 'bar',
                        type: metric_type::GAUGE,
                        values: [new metric_value(-INF)],
                    ),
                    test_metric::create(
                        name: 'baz',
                        type: metric_type::GAUGE,
                        values: [new metric_value(NAN)],
                    ),
                ],
                'expected' => <<<'TEXT'
                    # TYPE tool_monitoring_foo gauge
                    tool_monitoring_foo +Inf
                    # TYPE tool_monitoring_bar gauge
                    tool_monitoring_bar -Inf
                    # TYPE tool_monitoring_baz gauge
                    tool_monitoring_baz NaN
                    
                    TEXT,
            ],
        ];
    }
}

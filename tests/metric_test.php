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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace tool_monitoring;

use advanced_testcase;
use core\exception\coding_exception;
use core\lang_string;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\metrics\overdue_tasks;
use tool_monitoring\local\testing\test_metric;
use tool_monitoring\local\metrics\users_online;

/**
 * Unit tests for the {@see metric} class.
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
#[CoversClass(metric::class)]
final class metric_test extends advanced_testcase {
    public function test___construct(): void {
        $cls = new ReflectionClass(metric::class);
        $constructor = $cls->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        self::assertTrue($constructor->isFinal());
        self::assertSame([], $constructor->getParameters());
    }

    public function test_collect(): void {
        $collection = new metric_collection();
        // The collection should not yet have the test metric.
        self::assertSame([], iterator_to_array($collection));
        $metric = test_metric::collect($collection);
        // Now the collection should have the test metric.
        self::assertSame([$metric], iterator_to_array($collection));
        // Doing the same thing again should create a new instance and replace the previous one in the collection.
        $metric2 = test_metric::collect($collection);
        self::assertSame([$metric2], iterator_to_array($collection));
    }

    /**
     * Tests the {@see metric::get_description} method.
     *
     * @param metric $metric Metric to test with.
     * @param lang_string $expected Expected return value.
     * @throws coding_exception
     */
    #[DataProvider('provider_test_get_description')]
    public function test_get_description(metric $metric, lang_string $expected): void {
        $description = $metric->get_description();
        self::assertEquals($expected, $description);
        self::assertSame($expected->get_identifier(), $description->get_identifier());
        self::assertSame($expected->get_component(), $description->get_component());
    }

    /**
     * Provides test data for the {@see test_get_description} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_description(): array {
        return [
            [
                'metric'   => new overdue_tasks(),
                'expected' => new lang_string('metric:overdue_tasks_desc', 'tool_monitoring'),
            ],
            [
                'metric'   => new users_online(),
                'expected' => new lang_string('metric:users_online_desc', 'tool_monitoring'),
            ],
        ];
    }

    /**
     * Tests the {@see metric::get_name} method.
     *
     * @param metric $metric Metric to test with.
     * @param string $expected Expected return value.
     */
    #[DataProvider('provider_test_get_name')]
    public function test_get_name(metric $metric, string $expected): void {
        self::assertSame($expected, $metric->get_name());
    }

    /**
     * Provides test data for the {@see test_get_name} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_name(): array {
        return [
            [
                'metric'   => new test_metric(),
                'expected' => 'test_metric',
            ],
            [
                'metric'   => new overdue_tasks(),
                'expected' => 'overdue_tasks',
            ],
            [
                'metric'   => new users_online(),
                'expected' => 'users_online',
            ],
        ];
    }

    /**
     * Tests the {@see metric::get_component} method.
     *
     * @param metric $metric Metric to test with.
     * @param string $expected Expected return value.
     */
    #[DataProvider('provider_test_get_component')]
    public function test_get_component(metric $metric, string $expected): void {
        self::assertSame($expected, $metric->get_component());
    }

    /**
     * Provides test data for the {@see test_get_component} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_component(): array {
        return [
            [
                'metric'   => new test_metric(),
                'expected' => 'tool_monitoring',
            ],
            [
                'metric'   => new overdue_tasks(),
                'expected' => 'tool_monitoring',
            ],
            [
                'metric'   => new users_online(),
                'expected' => 'tool_monitoring',
            ],
        ];
    }
}

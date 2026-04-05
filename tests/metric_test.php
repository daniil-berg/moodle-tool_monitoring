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
use tool_monitoring\local\metrics\users_online;
use tool_monitoring\local\testing\metric_settable_values;

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
        $metric = metric_settable_values::collect($collection);
        // Now the collection should have the test metric.
        self::assertSame([$metric], iterator_to_array($collection));
        // Doing the same thing again should create a new instance and extend the collection.
        $metric2 = metric_settable_values::collect($collection);
        self::assertSame([$metric, $metric2], iterator_to_array($collection));
    }

    /**
     * Tests the {@see metric::get_description} method.
     *
     * @param class-string<metric> $class Metric class name.
     * @param lang_string $expected Expected return value.
     * @throws coding_exception
     */
    #[DataProvider('provider_test_get_description')]
    public function test_get_description(string $class, lang_string $expected): void {
        $description = $class::get_description();
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
                'class'    => overdue_tasks::class,
                'expected' => new lang_string('metric:overdue_tasks_desc', 'tool_monitoring'),
            ],
            [
                'class'    => users_online::class,
                'expected' => new lang_string('metric:users_online_desc', 'tool_monitoring'),
            ],
        ];
    }

    /**
     * Tests the {@see metric::get_name} method.
     *
     * @param class-string<metric> $class Metric class name.
     * @param string $expected Expected return value.
     */
    #[DataProvider('provider_test_get_name')]
    public function test_get_name(string $class, string $expected): void {
        self::assertSame($expected, $class::get_name());
    }

    /**
     * Provides test data for the {@see test_get_name} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_name(): array {
        return [
            [
                'class'    => metric_settable_values::class,
                'expected' => 'metric_settable_values',
            ],
            [
                'class'    => overdue_tasks::class,
                'expected' => 'overdue_tasks',
            ],
            [
                'class'    => users_online::class,
                'expected' => 'users_online',
            ],
        ];
    }

    /**
     * Tests the {@see metric::get_component} method.
     *
     * @param class-string<metric> $class Metric class name.
     * @param string $expected Expected return value.
     */
    #[DataProvider('provider_test_get_component')]
    public function test_get_component(string $class, string $expected): void {
        self::assertSame($expected, $class::get_component());
    }

    /**
     * Provides test data for the {@see test_get_component} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_component(): array {
        return [
            [
                'class'    => metric_settable_values::class,
                'expected' => 'tool_monitoring',
            ],
            [
                'class'    => overdue_tasks::class,
                'expected' => 'tool_monitoring',
            ],
            [
                'class'    => users_online::class,
                'expected' => 'tool_monitoring',
            ],
        ];
    }
}

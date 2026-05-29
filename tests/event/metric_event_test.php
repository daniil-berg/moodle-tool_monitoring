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
 * Definition of the {@see metric_event_test} class.
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

namespace tool_monitoring\event;

use advanced_testcase;
use core\event\base as base_event;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\lang_string;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\metric_record;
use tool_monitoring\local\testing\test_metric;

/**
 * Unit tests for the {@see metric_event} class and its subclasses.
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
#[CoversClass(metric_config_updated::class)]
#[CoversClass(metric_deleted::class)]
#[CoversClass(metric_disabled::class)]
#[CoversClass(metric_enabled::class)]
#[CoversClass(metric_event::class)]
final class metric_event_test extends advanced_testcase {
    /**
     * Tests all methods of the {@see metric_event} class and its subclasses.
     *
     * @param class-string<metric_event> $eventclass Name of the event class to test.
     * @param metric_record $record Metric record to pass to the {@see metric_event::for_record `for_record`} constructor.
     * @param string $crud Expected value of the {@see metric_event::$crud `crud`} property.
     * @param string $nameid String ID of the expected language string from the {@see metric_event::get_name `get_name`} method.
     * @param string $description Expected output of the {@see metric_event::get_description `get_description`} method.
     * @throws moodle_exception
     */
    #[DataProvider('provider_test_all_methods')]
    public function test_all_methods(
        string $eventclass,
        metric_record $record,
        string $crud,
        string $nameid,
        string $description,
    ): void {
        $event = $eventclass::for_record($record);
        // Custom magic getter should return the metric's qualified name.
        self::assertSame($record->qualifiedname, $event->metric);
        // Check that our `init` method has been called and the magic getter delegates to the parent implementation.
        self::assertSame(metric_record::TABLE, $event->objecttable);
        self::assertSame(base_event::LEVEL_OTHER, $event->edulevel);
        self::assertSame($crud, $event->crud);
        // Verify URL is as expected.
        $expectedurl = new moodle_url('/admin/tool/monitoring/configure.php', ['metric' => $record->qualifiedname]);
        self::assertEquals($expectedurl, $event->get_url());
        // Check the name language string.
        $name = $event->get_name();
        self::assertInstanceOf(lang_string::class, $name);
        self::assertSame($nameid, $name->get_identifier());
        self::assertTrue(get_string_manager()->string_exists($nameid, 'tool_monitoring'));
        // Verify the description.
        self::assertSame($description, $event->get_description());
    }

    /**
     * Provides test data for the {@see test_all_methods} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_all_methods(): array {
        global $USER;
        $record = metric_record::from_metric(new test_metric());
        return [
            [
                'eventclass'  => metric_config_updated::class,
                'record'      => $record,
                'crud'        => 'u',
                'nameid'      => 'event:metric_config_updated',
                'description' => "User with ID '$USER->id' updated the metric config for '$record->qualifiedname'.",
            ],
            [
                'eventclass'  => metric_deleted::class,
                'record'      => $record,
                'crud'        => 'd',
                'nameid'      => 'event:metric_deleted',
                'description' => "Deleted the DB record for the metric '$record->qualifiedname'.",
            ],
            [
                'eventclass'  => metric_disabled::class,
                'record'      => $record,
                'crud'        => 'u',
                'nameid'      => 'event:metric_disabled',
                'description' => "User with ID '$USER->id' disabled the metric '$record->qualifiedname'.",
            ],
            [
                'eventclass'  => metric_enabled::class,
                'record'      => $record,
                'crud'        => 'u',
                'nameid'      => 'event:metric_enabled',
                'description' => "User with ID '$USER->id' enabled the metric '$record->qualifiedname'.",
            ],
        ];
    }

    /**
     * Checks that our {@see metric_event::validate_data} method throws an exception if no metric is provided during event creation.
     *
     * @throws coding_exception
     */
    public function test_create_without_metric(): void {
        $this->expectException(coding_exception::class);
        metric_enabled::create(['other' => ['no_metric' => 'foo']]);
    }
}

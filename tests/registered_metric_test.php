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
 * Definition of the {@see registered_metric_test} class.
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
use core\event\base as base_event;
use core\exception\coding_exception;
use dml_exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\testing\metric_settable_values;
use tool_monitoring\local\testing\metric_with_custom_config;

#[CoversClass(registered_metric::class)]
class registered_metric_test extends advanced_testcase {

    #[DataProvider('test_from_metric_provider')]
    public function test_from_metric(metric $metric, array $arguments, array|string $expected): void {
        if (is_string($expected)) {
            $this->expectException($expected);
            registered_metric::from_metric($metric, ...$arguments);
            return;
        }
        $instance = registered_metric::from_metric($metric, ...$arguments);
        foreach ($expected as $name => $value) {
            self::assertEquals($value, $instance->$name);
        }
    }

    /**
     * Provides test data for the {@see test_from_metric} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function test_from_metric_provider(): array {
        $metric = new metric_settable_values();
        return [
            'No additional arguments' => [
                'metric'    => $metric,
                'arguments' => [],
                'expected'  => [
                    'id'           => null,
                    'component'    => 'tool_monitoring',
                    'name'         => 'metric_settable_values',
                    'enabled'      => false,
                    'config'       => null,
                    'timecreated'  => null,
                    'timemodified' => null,
                    'usermodified' => null,
                ],
            ],
            'All arguments override values from metric' => [
                'metric'    => $metric,
                'arguments' => [
                    'id'           => 9000,
                    'component'    => 'foo',
                    'name'         => 'bar',
                    'enabled'      => true,
                    'config'       => '{"a":"b"}',
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 789,
                ],
                'expected'  => [
                    'id'           => 9000,
                    'component'    => 'foo',
                    'name'         => 'bar',
                    'enabled'      => true,
                    'config'       => '{"a":"b"}',
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 789,
                ],
            ],
        ];
    }

    /**
     * @param iterable<metric_value>|metric_value $testvalues Metric values to be produced by the test metric.
     */
    #[DataProvider('test_iterator_provider')]
    public function test_iterator(iterable|metric_value $testvalues): void {
        $this->resetAfterTest();
        $metric = new metric_settable_values();
        $metric->values = $testvalues;
        $instance = registered_metric::from_metric($metric);
        // Consume the metric iterator.
        $metricvalues = iterator_to_array($instance);
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

    /**
     * @param string $component Component input.
     * @param string $name Name input.
     * @param string $expected Expected return value name.
     */
    #[DataProvider('test_get_qualified_name_provider')]
    public function test_get_qualified_name(string $component, string $name, string $expected): void {
        self::assertSame($expected, registered_metric::get_qualified_name($component, $name));
    }

    /**
     * Provides test data for the {@see test_get_qualified_name} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function test_get_qualified_name_provider(): array {
        return [
            [
                'component' => 'tool_monitoring',
                'name'      => 'some_metric',
                'expected'  => 'tool_monitoring_some_metric',
            ],
            [
                'component' => 'tool_monitoring',
                'name'      => 'num_overdue_tasks',
                'expected'  => 'tool_monitoring_num_overdue_tasks',
            ],
            [
                'component' => 'foo+-*/bar',
                'name'      => ' this is fine ',
                'expected'  => 'foo+-*/bar_ this is fine ',
            ],
        ];
    }

    public function test___get(): void {
        $instance = registered_metric::from_metric(new metric_settable_values());
        self::assertSame(registered_metric::get_qualified_name($instance->component, $instance->name), $instance->qualifiedname);
        self::assertEquals(metric_settable_values::get_description(), $instance->description);
        self::assertSame(metric_settable_values::get_type(), $instance->type);
    }

    /**
     * @param registered_metric $metric Instance on which to call the method.
     * @param array<string, mixed> $formdata Passed as argument to the method.
     * @param array<string, mixed> $expected Properties expected to be set after the call on both the instance and the DB record.
     * @param class-string<base_event>[] $events Names of event classes expected to be triggered in the given order.
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    #[DataProvider('test_update_with_form_data_provider')]
    public function test_update_with_form_data(
        registered_metric $metric,
        array $formdata,
        array $expected,
        array $events = [],
    ): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        // Set modification time in the past and arbitrary user.
        $creationtime = time() - 1000;
        $newuserid = $generator->create_user()->id;
        $metric->timecreated = $creationtime;
        $metric->timemodified = $creationtime;
        $metric->usermodified = $newuserid;
        // Insert record manually.
        $data = (array) $metric;
        $metric->id = $DB->insert_record(registered_metric::TABLE, $data);
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        // Do some sanity checks.
        $expectedbefore = [
            'id'           => $metric->id,
            'component'    => $metric->component,
            'name'         => $metric->name,
            'enabled'      => $metric->enabled,
            'config'       => $metric->config,
            'timecreated'  => $creationtime,
            'timemodified' => $creationtime,
            'usermodified' => $newuserid,
        ];
        foreach ($expectedbefore as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // Unless otherwise specified, we expect the same properties.
        $expected += $expectedbefore;
        // But if anything is expected to be updated, the modification time and the user should be different.
        if (!empty($events)) {
            unset($expected['timemodified']);
            $expected['usermodified'] = $USER->id;
        }
        // Intercept the event here.
        $eventsink = $this->redirectEvents();
        $metric->update_with_form_data((object) $formdata);
        $eventsink->close();
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        // Check the expected values.
        foreach ($expected as $name => $value) {
            self::assertEquals($value, $record->$name);
            self::assertEquals($value, $metric->$name);
        }
        if (!empty($events)) {
            // Time modified should have been updated as well.
            self::assertGreaterThan($creationtime, $record->timemodified);
        }
        // Check that the events were triggered as expected.
        $actualevents = array_map(fn (base_event $event): string => $event::class, $eventsink->get_events());
        self::assertSame($events, $actualevents);
    }

    /**
     * Provides test data for the {@see test_update_with_form_data} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function test_update_with_form_data_provider(): array {
        $metric = new metric_settable_values();
        $metricwithconfig = new metric_with_custom_config();
        return [
            'Enabled basic metric, nothing changed' => [
                'metric'   => registered_metric::from_metric($metric, enabled: true),
                'formdata' => [
                    'enabled' => true,
                ],
                'expected' => [
                    'config'  => null,
                    'enabled' => true,
                ],
                'events'   => [],
            ],
            'Enabled basic metric, being disabled, arbitrary form data present' => [
                'metric'   => registered_metric::from_metric($metric, enabled: true),
                'formdata' => [
                    'enabled' => false,
                    'some'    => 'data',
                    'what'    => 'ever',
                ],
                'expected' => [
                    'config'  => null,
                    'enabled' => false,
                ],
                'events'   => [
                    event\metric_disabled::class,
                ],
            ],
            'Enabled configurable metric, nothing changed' => [
                'metric'   => registered_metric::from_metric($metricwithconfig, enabled: true, config: '{"foo":"baz","spam":0}'),
                'formdata' => [
                    'enabled' => true,
                    'foo'     => 'baz',
                    'spam'    => 0,
                ],
                'expected' => [
                    'config'  => '{"foo":"baz","spam":0}',
                    'enabled' => true,
                ],
                'events'   => [],
            ],
            'Enabled configurable metric, having config updated' => [
                'metric'   => registered_metric::from_metric($metricwithconfig, enabled: true, config: '{}'),
                'formdata' => [
                    'foo'  => 'baz',
                    'spam' => 0,
                ],
                'expected' => [
                    'config' => '{"foo":"baz","spam":0}',
                ],
                'events'   => [
                    event\metric_config_updated::class,
                ],
            ],
            'Enabled configurable metric, being disabled' => [
                'metric'   => registered_metric::from_metric($metricwithconfig, enabled: true, config: '{"foo":"baz","spam":0}'),
                'formdata' => [
                    'enabled' => false,
                    'foo'     => 'baz',
                    'spam'    => 0,
                ],
                'expected' => [
                    'config'  => '{"foo":"baz","spam":0}',
                    'enabled' => false,
                ],
                'events'   => [
                    event\metric_disabled::class,
                ],
            ],
            'Disabled configurable metric, being enabled' => [
                'metric'   => registered_metric::from_metric($metricwithconfig, enabled: false, config: '{"foo":"baz","spam":0}'),
                'formdata' => [
                    'enabled' => true,
                    'foo'     => 'baz',
                    'spam'    => 0,
                ],
                'expected' => [
                    'config'  => '{"foo":"baz","spam":0}',
                    'enabled' => true,
                ],
                'events'   => [
                    event\metric_enabled::class,
                ],
            ],
            'Disabled configurable metric, being enabled and having config updated' => [
                'metric'   => registered_metric::from_metric($metricwithconfig, enabled: false, config: '{"foo":"baz","spam":0}'),
                'formdata' => [
                    'enabled' => true,
                    'foo'     => 'bar',
                    'spam'    => 1,
                ],
                'expected' => [
                    'config'  => '{"foo":"bar","spam":1}',
                    'enabled' => true,
                ],
                'events'   => [
                    event\metric_enabled::class,
                    event\metric_config_updated::class,
                ],
            ],
        ];
    }
}

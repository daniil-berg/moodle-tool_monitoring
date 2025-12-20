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
use core\exception\coding_exception;
use dml_exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\testing\metric_settable_values;

#[CoversClass(registered_metric::class)]
class registered_metric_test extends advanced_testcase {

    /**
     * @throws coding_exception
     */
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
                    'config'       => (object) ['a' => 'b'],
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 789,
                ],
                'expected'  => [
                    'id'           => 9000,
                    'component'    => 'foo',
                    'name'         => 'bar',
                    'enabled'      => true,
                    'config'       => (object) ['a' => 'b'],
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 789,
                ],
            ],
            'Config as a valid string of a JSON object' => [
                'metric'    => $metric,
                'arguments' => [
                    'config' => '{"spam":1}',
                ],
                'expected'  => [
                    'id'           => null,
                    'component'    => 'tool_monitoring',
                    'name'         => 'metric_settable_values',
                    'enabled'      => false,
                    'config'       => (object) ['spam' => 1],
                    'timecreated'  => null,
                    'timemodified' => null,
                    'usermodified' => null,
                ],
            ],
            'Config as a JSON string, but not of an object' => [
                'metric'    => $metric,
                'arguments' => [
                    'config' => '["a", "b"]',
                ],
                'expected'  => coding_exception::class,
            ],
            'Config as invalid JSON' => [
                'metric'    => $metric,
                'arguments' => [
                    'config' => '/oops',
                ],
                'expected'  => coding_exception::class,
            ],
        ];
    }

    /**
     * @param iterable<metric_value>|metric_value $testvalues Metric values to be produced by the test metric.
     * @throws coding_exception
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

    /**
     * @throws coding_exception
     */
    public function test___get(): void {
        $instance = registered_metric::from_metric(new metric_settable_values());
        self::assertSame(registered_metric::get_qualified_name($instance->component, $instance->name), $instance->qualifiedname);
        self::assertEquals(metric_settable_values::get_description(), $instance->description);
        self::assertSame(metric_settable_values::get_type(), $instance->type);
    }

    /**
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    public function test_enable_disable(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $metric = registered_metric::from_metric(new metric_settable_values());
        // Set modification time in the past.
        $creationtime = time() - 1000;
        $metric->timecreated = $creationtime;
        $metric->timemodified = $creationtime;
        $metric->usermodified = 1;
        // Insert record manually.
        $data = (array) $metric;
        $data['config'] = '{}';
        $metric->id = $DB->insert_record(registered_metric::TABLE, $data);
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        // Do some sanity checks.
        $expectedproperties = [
            'id'           => $metric->id,
            'component'    => $metric->component,
            'name'         => $metric->name,
            'enabled'      => $metric->enabled,
            'config'       => '{}',
            'timecreated'  => $creationtime,
            'timemodified' => $creationtime,
            'usermodified' => 1,
        ];
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        $eventsink = $this->redirectEvents();

        // This should do nothing.
        $metric->disable();
        // Check that nothing changed.
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // Check that no event was triggered.
        self::assertSame([], $eventsink->get_events());

        // This should perform an update.
        $metric->enable();
        // User should now be the current one.
        $expectedproperties['usermodified'] = $USER->id;
        $expectedproperties['enabled'] = true;
        unset($expectedproperties['timemodified']);
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        // Check the expected values.
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // Time modified should have been updated as well.
        self::assertGreaterThan($creationtime, $record->timemodified);
        // The proper event should have been triggered.
        $events = $eventsink->get_events();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(event\metric_enabled::class, $event);
        self::assertArrayHasKey('metric', $event->other);
        self::assertSame($metric, $event->other['metric']);
        $eventsink->clear();

        // This should do nothing.
        $metric->enable();
        // Check that nothing changed.
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        self::assertSame([], $eventsink->get_events());

        // This should perform an update.
        $metric->disable();
        $expectedproperties['enabled'] = false;
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        // Check the expected values.
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // The proper event should have been triggered.
        $events = $eventsink->get_events();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(event\metric_disabled::class, $event);
        self::assertArrayHasKey('metric', $event->other);
        self::assertSame($metric, $event->other['metric']);
        $eventsink->clear();
        $eventsink->close();
    }

    /**
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    public function test_save_config(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $metric = registered_metric::from_metric(new metric_settable_values());
        // Set modification time in the past.
        $creationtime = time() - 1000;
        $metric->timecreated = $creationtime;
        $metric->timemodified = $creationtime;
        $metric->usermodified = 1;
        // Insert record manually.
        $data = (array) $metric;
        $data['config'] = '{}';
        $metric->id = $DB->insert_record(registered_metric::TABLE, $data);
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        // Do some sanity checks.
        $expectedproperties = [
            'id'           => $metric->id,
            'component'    => $metric->component,
            'name'         => $metric->name,
            'enabled'      => $metric->enabled,
            'config'       => '{}',
            'timecreated'  => $creationtime,
            'timemodified' => $creationtime,
            'usermodified' => 1,
        ];
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // We expect only this to be updated.
        $metric->config = (object) ['foo' => 'bar', 'spam' => 'eggs'];
        // We expect these to be ignored in the update.
        $metric->component = 'spam';
        $metric->name = 'eggs';
        $metric->timecreated = 123;
        $metric->timemodified = 0;
        $metric->usermodified = 123456789;
        unset($expectedproperties['timemodified']);
        // Expect only `config` to match what we set above.
        $expectedproperties['config'] = '{"foo":"bar","spam":"eggs"}';
        // User should have been set to the current one.
        $expectedproperties['usermodified'] = $USER->id;
        // Intercept the event here.
        $eventsink = $this->redirectEvents();
        $metric->save_config();
        $eventsink->close();
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        // Check the expected values.
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // Time modified should have been updated as well.
        self::assertGreaterThan($creationtime, $record->timemodified);
        // Check that the event was triggered as expected.
        $events = $eventsink->get_events();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(event\metric_config_updated::class, $event);
        self::assertArrayHasKey('metric', $event->other);
        self::assertSame($metric, $event->other['metric']);
    }
}

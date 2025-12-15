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
use core\exception\coding_exception;
use dml_exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\event\metric_config_updated;
use tool_monitoring\event\metric_disabled;
use tool_monitoring\event\metric_enabled;
use tool_monitoring\hook\metrics_manager;
use tool_monitoring\local\metric_orm;
use tool_monitoring\local\metrics\num_overdue_tasks;
use tool_monitoring\local\metrics\num_users_accessed;
use tool_monitoring\local\testing\metric_strict_label_names;
use tool_monitoring\local\testing\simple_metric;

#[CoversClass(metric::class)]
#[CoversClass(metric_orm::class)]
class metric_test extends advanced_testcase {

    /**
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    public function test_register(): void {
        global $DB;
        $this->resetAfterTest();
        self::assertSame(0, $DB->count_records(metric::TABLE));
        $manager = metrics_manager::instance();
        // The manager should not yet have the test metric.
        $qname = 'tool_monitoring_simple_metric';
        self::assertArrayNotHasKey($qname, $manager->get_metrics());
        // This should insert a DB record for the test metric.
        $metric = simple_metric::register($manager);
        self::assertNotNull($metric->id);
        $record = $DB->get_record(metric::TABLE, ['id' => $metric->id], strictness: MUST_EXIST);
        $expectedproperties = [
            'id'           => $metric->id,
            'component'    => 'tool_monitoring',
            'name'         => 'simple_metric',
            'enabled'      => false,
            'config'       => '{}',
            'timecreated'  => $metric->timecreated,
            'timemodified' => $metric->timemodified,
            'usermodified' => 0,
        ];
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // Now the manager should have the test metric.
        self::assertArrayHasKey($qname, $manager->get_metrics());
        self::assertSame($metric, $manager->get_metrics()[$qname]);
        // Another `register` call should not insert anything new into the DB table.
        $metricscount = $DB->count_records(metric::TABLE);
        // Intercept the manager's warning.
        $warning = null;
        set_error_handler(
            static function (int $errno, string $errstr) use (&$warning): void {
                restore_error_handler();
                $warning = $errstr;
            },
        );
        $metric2 = simple_metric::register($manager);
        self::assertNotNull($warning);
        // Check that the number of DB records is still the same.
        self::assertSame($metricscount, $DB->count_records(metric::TABLE));
        // The ID should also be the same.
        self::assertSame($metric->id, $metric2->id);
        // The object should be a new one though.
        self::assertNotSame($metric, $metric2);
        self::assertSame($metric, $manager->get_metrics()[$qname]);
    }

    /**
     * @param iterable<metric_value>|metric_value $testvalues Metric values to be produced by the test metric.
     */
    #[DataProvider('test_iterator_provider')]
    public function test_iterator(iterable|metric_value $testvalues): void {
        $metric = simple_metric::with_values($testvalues);
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

    /**
     * @param class-string<metric> $class Metric class name.
     * @param string $expected Expected return value name.
     */
    #[DataProvider('test_get_name_provider')]
    public function test_get_name(string $class, string $expected): void {
        self::assertSame($expected, $class::get_name());
    }

    /**
     * Provides test data for the {@see test_get_name} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function test_get_name_provider(): array {
        return [
            [
                'class'    => simple_metric::class,
                'expected' => 'simple_metric',
            ],
            [
                'class'    => metric_strict_label_names::class,
                'expected' => 'metric_strict_label_names',
            ],
            [
                'class'    => num_overdue_tasks::class,
                'expected' => 'num_overdue_tasks',
            ],
            [
                'class'    => num_users_accessed::class,
                'expected' => 'num_users_accessed',
            ],
        ];
    }

    /**
     * @param class-string<metric> $class Metric class name.
     * @param string $expected Expected return value name.
     */
    #[DataProvider('test_get_component_provider')]
    public function test_get_component(string $class, string $expected): void {
        self::assertSame($expected, $class::get_component());
    }

    /**
     * Provides test data for the {@see test_get_component} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function test_get_component_provider(): array {
        return [
            [
                'class'    => simple_metric::class,
                'expected' => 'tool_monitoring',
            ],
            [
                'class'    => metric_strict_label_names::class,
                'expected' => 'tool_monitoring',
            ],
            [
                'class'    => num_overdue_tasks::class,
                'expected' => 'tool_monitoring',
            ],
            [
                'class'    => num_users_accessed::class,
                'expected' => 'tool_monitoring',
            ],
        ];
    }

    /**
     * @param class-string<metric> $class Metric class name.
     * @param string $expected Expected return value name.
     */
    #[DataProvider('test_get_qualified_name_provider')]
    public function test_get_qualified_name(string $class, string $expected): void {
        self::assertSame($expected, $class::get_qualified_name());
    }

    /**
     * Provides test data for the {@see test_get_qualified_name} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function test_get_qualified_name_provider(): array {
        return [
            [
                'class'    => simple_metric::class,
                'expected' => 'tool_monitoring_simple_metric',
            ],
            [
                'class'    => metric_strict_label_names::class,
                'expected' => 'tool_monitoring_metric_strict_label_names',
            ],
            [
                'class'    => num_overdue_tasks::class,
                'expected' => 'tool_monitoring_num_overdue_tasks',
            ],
            [
                'class'    => num_users_accessed::class,
                'expected' => 'tool_monitoring_num_users_accessed',
            ],
        ];
    }

    public function test_get_config_form(): void {
        $metric = simple_metric::with_values([]);
        $form = simple_metric::get_config_form(customdata: ['metric' => $metric]);
        self::assertSame(form\config::class, get_class($form));
    }

    public function test_get_default_config_data(): void {
        self::assertSame([], simple_metric::get_default_config_data());
    }

    /**
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    public function test_save_config(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $metric = simple_metric::with_values([]);
        // Set modification time in the past.
        $creationtime = time() - 1000;
        $metric->timecreated = $creationtime;
        $metric->timemodified = $creationtime;
        $metric->usermodified = 1;
        $data = (array) $metric;
        $data['config'] = '{}';
        // Insert record manually.
        $metric->id = $DB->insert_record(metric::TABLE, $data);
        $record = $DB->get_record(metric::TABLE, ['id' => $metric->id]);
        // Some sanity checks.
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
        // User should be the current one.
        $expectedproperties['usermodified'] = $USER->id;
        // Intercept the event here.
        $eventsink = $this->redirectEvents();
        $metric->save_config();
        $eventsink->close();
        $record = $DB->get_record(metric::TABLE, ['id' => $metric->id]);
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
        self::assertInstanceOf(metric_config_updated::class, $event);
        self::assertArrayHasKey('metric', $event->other);
        self::assertSame($metric, $event->other['metric']);
    }

    /**
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    public function test_enable_disable(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $metric = simple_metric::with_values([]);
        // Set modification time in the past.
        $creationtime = time() - 1000;
        $metric->timecreated = $creationtime;
        $metric->timemodified = $creationtime;
        $metric->usermodified = 1;
        $data = (array) $metric;
        $data['config'] = '{}';
        // Insert record manually.
        $metric->id = $DB->insert_record(metric::TABLE, $data);
        $record = $DB->get_record(metric::TABLE, ['id' => $metric->id]);
        // Some sanity checks.
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
        $record = $DB->get_record(metric::TABLE, ['id' => $metric->id]);
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // Check that the event was triggered as expected.
        self::assertSame([], $eventsink->get_events());

        $metric->enable();
        // User should now be the current one.
        $expectedproperties['usermodified'] = $USER->id;
        $expectedproperties['enabled'] = true;
        unset($expectedproperties['timemodified']);
        $record = $DB->get_record(metric::TABLE, ['id' => $metric->id]);
        // Check the expected values.
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        // Time modified should have been updated as well.
        self::assertGreaterThan($creationtime, $record->timemodified);
        $events = $eventsink->get_events();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(metric_enabled::class, $event);
        self::assertArrayHasKey('metric', $event->other);
        self::assertSame($metric, $event->other['metric']);
        $eventsink->clear();

        // This should do nothing.
        $metric->enable();
        // Check that nothing changed.
        $record = $DB->get_record(metric::TABLE, ['id' => $metric->id]);
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        self::assertSame([], $eventsink->get_events());

        $metric->disable();
        $expectedproperties['enabled'] = false;
        $record = $DB->get_record(metric::TABLE, ['id' => $metric->id]);
        // Check the expected values.
        foreach ($expectedproperties as $name => $value) {
            self::assertEquals($value, $record->$name);
        }
        $events = $eventsink->get_events();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(metric_disabled::class, $event);
        self::assertArrayHasKey('metric', $event->other);
        self::assertSame($metric, $event->other['metric']);
        $eventsink->clear();
        $eventsink->close();
    }
}

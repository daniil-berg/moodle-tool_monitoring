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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace tool_monitoring;

use advanced_testcase;
use ArrayIterator;
use core\di;
use core\event\base as base_event;
use core\event\tag_added;
use core\event\tag_created;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use JsonException;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use tool_monitoring\exceptions\metric_config_invalid;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\metric_record;
use tool_monitoring\local\metrics;
use tool_monitoring\local\testing\test_simple_metric_config_minimal;
use tool_monitoring\local\testing\test_metric;
use tool_monitoring\local\testing\test_metric_with_config;

/**
 * Unit tests for the {@see registered_metric} class.
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
#[CoversClass(registered_metric::class)]
final class registered_metric_test extends advanced_testcase {
    /**
     * Tests the {@see registered_metric::__construct} method.
     *
     * @param metric $metric Passed to the constructor.
     * @param metric_record $record Passed to the constructor.
     * @param moodle_exception|null $exception Expected exception to be thrown.
     * @param string|null $debugging Expected debugging message to be issued.
     * @throws coding_exception
     */
    #[DataProvider('provider_test___construct')]
    public function test___construct(
        metric $metric,
        metric_record $record,
        moodle_exception|null $exception = null,
        string|null $debugging = null,
    ): void {
        if (!is_null($exception)) {
            $this->expectExceptionObject($exception);
            new registered_metric($metric, $record);
            return;
        }
        $instance = new registered_metric($metric, $record);
        $defaultconfigprop = new ReflectionProperty(registered_metric::class, 'defaultconfig');
        $defaultconfig = $defaultconfigprop->getValue($instance);
        if ($metric instanceof metric_config_provider) {
            self::assertEquals($metric->get_default_config(), $defaultconfig);
            self::assertSame($instance->config, $record->config);
        } else {
            self::assertNull($defaultconfig);
            self::assertNull($instance->config);
        }
        if (!is_null($debugging)) {
            self::assertDebuggingCalled($debugging);
        }
    }

    /**
     * Provides test data for the {@see test___construct} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test___construct(): array {
        return [
            'Metric and record inconsistent component' => [
                'metric' => new test_metric(),
                'record' => new metric_record(component: 'bad', name: 'test_metric'),
                'exception' => new coding_exception('Metric record does not match the provided metric.'),
            ],
            'Metric and record inconsistent name' => [
                'metric' => new test_metric(),
                'record' => new metric_record(component: 'tool_monitoring', name: 'bad'),
                'exception' => new coding_exception('Metric record does not match the provided metric.'),
            ],
            'Metric and record consistent' => [
                'metric' => new test_metric(),
                'record' => metric_record::from_metric(new test_metric()),
            ],
            'Configurable metric and record consistent' => [
                'metric' => new test_metric_with_config(),
                'record' => metric_record::from_metric(new test_metric_with_config()),
            ],
            'Non-configurable metric and non-null record config' => [
                'metric' => new test_metric(),
                'record' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric',
                    config: '{"foo":"bar"}',
                ),
                'exception' => null,
                'debugging' => 'Cannot set config on non-configurable metric: tool_monitoring_test_metric',
            ],
        ];
    }

    /**
     * Tests the {@see registered_metric::__get} and {@see registered_metric::__isset} method.
     *
     * @throws coding_exception
     */
    public function test___get___isset(): void {
        $metric = new test_metric();
        $mocktags = [
            'foo' => $this->createStub(metric_tag::class),
            'bar' => $this->createStub(metric_tag::class),
        ];
        $instance = registered_metric::from_metric($metric, $mocktags);
        self::assertTrue(isset($instance->description));
        self::assertEquals($metric->get_description(), $instance->description);
        self::assertTrue(isset($instance->qualifiedname));
        self::assertSame(metric_record::get_qualified_name($instance->component, $instance->name), $instance->qualifiedname);
        self::assertTrue(isset($instance->tags));
        self::assertSame($mocktags, $instance->tags);
        self::assertTrue(isset($instance->type));
        self::assertSame($metric->get_type(), $instance->type);
        self::assertTrue(isset($instance->name));
        self::assertSame($metric->get_name(), $instance->name);

        $instance = registered_metric::from_metric(new test_metric_with_config());
        self::assertTrue(isset($instance->configclass));
        self::assertSame(test_simple_metric_config_minimal::class, $instance->configclass);
    }

    /**
     * Tests the {@see registered_metric::get_for_metrics} method.
     *
     * @param array<array<string, string>> $indb DB records to insert before calling the method.
     * @param array $metrics Metric instances to pass to the method.
     * @param array<string, array<string, string>> $expected Arrays of expected instance properties of the returned objects indexed
     *                                                       by qualified name.
     * @throws coding_exception
     * @throws dml_exception
     */
    #[DataProvider('provider_test_get_for_metrics')]
    public function test_get_for_metrics(array $indb, array $metrics, array $expected): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_records(metric_record::TABLE, $indb);
        $instances = registered_metric::get_for_metrics(...$metrics);
        self::assertCount(count($expected), $instances);
        foreach ($expected as $qname => $properties) {
            $instance = $instances[$qname] ?? null;
            self::assertInstanceOf(registered_metric::class, $instance);
            foreach ($properties as $name => $value) {
                self::assertEquals($value, $instance->$name, "Unexpected $name on $qname instance");
            }
        }
    }

    /**
     * Provides test data for the {@see test_get_for_metrics} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_for_metrics(): array {
        $defaults = [
            'component'    => 'tool_monitoring',
            'enabled'      => false,
            'config'       => null,
            'timecreated'  => null,
            'timemodified' => null,
            'usermodified' => null,
        ];
        $metricmissing = new test_metric();
        $metricquizattempts = new metrics\quiz_attempts_in_progress();
        $metricuseraccounts = new metrics\user_accounts();
        return [
            'No metrics provided' => [
                'indb' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'timecreated'  => 1,
                        'timemodified' => 1,
                        'usermodified' => 1,
                    ],
                ],
                'metrics' => [],
                'expected' => [],
            ],
            'No records in the DB' => [
                'indb' => [],
                'metrics' => [
                    $metricmissing,
                    $metricquizattempts,
                    $metricuseraccounts,
                ],
                'expected' => [
                    'tool_monitoring_test_metric' => [
                        'name' => 'test_metric',
                    ] + $defaults,
                    'tool_monitoring_quiz_attempts_in_progress' => [
                        'name'   => 'quiz_attempts_in_progress',
                    ] + $defaults,
                    'tool_monitoring_user_accounts' => [
                        'name' => 'user_accounts',
                    ] + $defaults,
                ],
            ],
            'Two records in the DB' => [
                'indb' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'quiz_attempts_in_progress',
                        'enabled'      => true,
                        'config'       => '{"maxidleseconds":1200,"maxdeadlineseconds":10800}',
                        'timecreated'  => 123,
                        'timemodified' => 456,
                        'usermodified' => 1,
                    ],
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'user_accounts',
                        'enabled'      => false,
                        'config'       => null,
                        'timecreated'  => 1,
                        'timemodified' => 1,
                        'usermodified' => 1,
                    ],
                ],
                'metrics' => [
                    $metricmissing,
                    $metricquizattempts,
                    $metricuseraccounts,
                ],
                'expected' => [
                    'tool_monitoring_test_metric' => [
                        'name' => 'test_metric',
                    ] + $defaults,
                    'tool_monitoring_quiz_attempts_in_progress' => [
                        'name'         => 'quiz_attempts_in_progress',
                        'enabled'      => true,
                        'config'       => '{"maxidleseconds":1200,"maxdeadlineseconds":10800}',
                        'timecreated'  => 123,
                        'timemodified' => 456,
                        'usermodified' => 1,
                    ] + $defaults,
                    'tool_monitoring_user_accounts' => [
                        'name'         => 'user_accounts',
                        'timecreated'  => 1,
                        'timemodified' => 1,
                        'usermodified' => 1,
                    ] + $defaults,
                ],
            ],
        ];
    }

    /**
     * Tests the {@see registered_metric::get_or_register} method.
     *
     * @param metric[] $metrics Metric instances to pass to the method.
     * @param array<array<string, string>> $indb DB records to insert before calling the method.
     * @param array<string, array<string, string>> $expected Arrays of expected instance properties of the returned objects indexed
     *                                                       by qualified name.
     * @throws coding_exception
     * @throws dml_exception
     */
    #[DataProvider('provider_test_get_or_register')]
    public function test_get_or_register(array $metrics, array $indb, array $expected): void {
        global $DB, $USER;
        $this->resetAfterTest();
        // Sanity check.
        self::assertSame(0, $DB->count_records(metric_record::TABLE));
        // Add pre-existing records.
        $defaults = [
            'timecreated'  => time() - 2000,
            'timemodified' => time() - 1000,
            'usermodified' => 1,
        ];
        $existing = [];
        foreach ($indb as $toinsert) {
            $existing[] = $DB->insert_record(metric_record::TABLE, $toinsert + $defaults);
        }
        $now = time();
        // Do the thing.
        $instances = registered_metric::get_or_register(...$metrics);
        // The number of instances should be the same as the number of records in the DB table.
        $expectedcount = count($expected);
        $records = $DB->get_records(metric_record::TABLE);
        self::assertCount($expectedcount, $instances);
        self::assertCount($expectedcount, $records);
        // Check that there is an instance for every metric and each of them has an ID.
        foreach ($metrics as $metric) {
            $qname = metric_record::get_qualified_name($metric->get_component(), $metric->get_name());
            self::assertArrayHasKey($qname, $instances);
            self::assertNotNull($instances[$qname]->id);
        }
        if (empty($expected)) {
            return;
        }
        // Check that both the returned instances and the raw DB records are exactly as we expect them.
        // To be extra sure, store already checked metric IDs.
        $checkedids = [];
        foreach ($expected as $qname => $properties) {
            $instance = $instances[$qname] ?? null;
            self::assertInstanceOf(registered_metric::class, $instance);
            self::assertNotNull($instance->id);
            self::assertNotContains($instance->id, $checkedids);
            self::assertArrayHasKey($instance->id, $records);
            $record = $records[$instance->id];
            foreach ($properties as $name => $expectedvalue) {
                self::assertEquals($expectedvalue, $record->$name, "Unexpected $name on $qname record");
                self::assertEquals($expectedvalue, $instance->$name, "Unexpected $name on $qname instance");
            }
            if (!in_array($instance->id, $existing)) {
                self::assertGreaterThanOrEqual($now, $record->timecreated, "Unexpected timecreated on $qname record");
                self::assertGreaterThanOrEqual($now, $record->timemodified, "Unexpected timemodified on $qname record");
                self::assertEquals($USER->id, $record->usermodified, "Unexpected usermodified on $qname record");
            }
            $checkedids[] = $instance->id;
        }
    }

    /**
     * Provides test data for the {@see test_get_or_register} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_or_register(): array {
        return [
            'No arguments' => [
                'metrics' => [],
                'indb' => [],
                'expected' => [],
            ],
            'Empty DB before, one new metric' => [
                'metrics' => [
                    new test_metric(),
                ],
                'indb' => [],
                'expected' => [
                    'tool_monitoring_test_metric' => [
                        'name'         => 'test_metric',
                        'component'    => 'tool_monitoring',
                        'enabled'      => false,
                        'config'       => null,
                    ],
                ],
            ],
            '3 metrics; 1 already registered' => [
                'metrics' => [
                    test_metric::create('foo'),
                    test_metric::create('bar'),
                    test_metric::create('baz'),
                ],
                'indb' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => true,
                        'config'       => null,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => null,
                    ],
                    'tool_monitoring_bar' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => true,
                        'config'       => null,
                    ],
                    'tool_monitoring_baz' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'baz',
                        'enabled'      => false,
                        'config'       => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * Tests the {@see registered_metric::to_form_data} method.
     *
     * @throws coding_exception
     * @throws metric_config_invalid
     */
    public function test_to_form_data(): void {
        // Set up mock tag objects.
        $mocktag1 = $this->createMock(metric_tag::class);
        $mocktag2 = $this->createMock(metric_tag::class);
        $mocktag1->expects(self::exactly(2))->method('get_display_name')->willReturn('foo');
        $mocktag1->expects(self::exactly(2))->method('__get')->willReturnMap([['id', 1]]);
        $mocktag2->expects(self::exactly(2))->method('get_display_name')->willReturn('bar');
        $mocktag2->expects(self::exactly(2))->method('__get')->willReturnMap([['id', 2]]);
        $tagsprop = new ReflectionProperty(registered_metric::class, 'tags');

        // Test with regular metric.
        $record = new metric_record(
            component: 'tool_monitoring',
            name: 'test_metric',
            enabled: true,
        );
        $instance = new registered_metric(new test_metric(), $record);
        $tagsprop->setValue($instance, [$mocktag1, $mocktag2]);
        $formdata = $instance->to_form_data();
        self::assertSame(
            ['enabled' => true, 'tags' => [1 => 'foo', 2 => 'bar']],
            $formdata,
        );

        // Now with a configurable metric.
        $record = new metric_record(
            component: 'tool_monitoring',
            name: 'test_metric_with_config',
        );
        $instance = new registered_metric(new test_metric_with_config(), $record);
        $tagsprop->setValue($instance, [$mocktag1, $mocktag2]);
        $formdata = $instance->to_form_data();
        self::assertSame(
            [
                'foo' => 'bar',
                'spam' => 1234567,
                'enabled' => false,
                'tags' => [1 => 'foo', 2 => 'bar'],
            ],
            $formdata,
        );
    }

    /**
     * Tests the {@see IteratorAggregate} implementation of the {@see registered_metric} class.
     *
     * @param iterable<metric_value>|metric_value $testvalues Metric values to be produced by the test metric.
     * @throws coding_exception
     */
    #[DataProvider('provider_test_iterator')]
    public function test_iterator(iterable|metric_value $testvalues): void {
        $this->resetAfterTest();
        $metric = test_metric::create(values: $testvalues);
        $instance = registered_metric::from_metric($metric);
        // Consume the metric iterator.
        $metricvalues = iterator_to_array($instance);
        if ($testvalues instanceof metric_value) {
            self::assertEquals([$testvalues], $metricvalues);
        } else if (is_array($testvalues)) {
            self::assertEquals($testvalues, $metricvalues);
        } else {
            self::assertEquals(iterator_to_array($testvalues), $metricvalues);
        }
    }

    /**
     * Provides test data for the {@see test_iterator} method.
     *
     * @return array[] Arguments for the test method.
     *
     * @phpcs:disable moodle.Strings.ForbiddenStrings.Found
     */
    public static function provider_test_iterator(): array {
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
     * Tests the config passed by {@see registered_metric::getIterator} into {@see metric::calculate} and that it is cached.
     *
     * @throws coding_exception
     * @throws ReflectionException
     */
    public function test_iterator_config_cache(): void {
        $metric = test_metric_with_config::create(values: [new metric_value(0)]);
        // Sanity check.
        self::assertNull($metric->lastconfig);
        $instance = registered_metric::from_metric($metric);
        // This should pass the default config object into `test_metric_with_config::calculate`.
        iterator_to_array($instance);
        $lastconfig = $metric->lastconfig;
        self::assertInstanceOf(test_simple_metric_config_minimal::class, $lastconfig);
        // Calling that again should use the config cache and pass that same object.
        iterator_to_array($instance);
        self::assertSame($lastconfig, $metric->lastconfig);
        // Set a different config; this should clear the config cache.
        $refmethod = new ReflectionMethod(registered_metric::class, 'set_config');
        $refmethod->invoke($instance, '{"foo":"baz","spam":-1}');
        // This should deserialize the new config and passes that object into `test_metric_with_config::calculate`.
        iterator_to_array($instance);
        $lastconfig2 = $metric->lastconfig;
        self::assertInstanceOf(test_simple_metric_config_minimal::class, $lastconfig2);
        self::assertNotSame($lastconfig, $lastconfig2);
        // Calling that again should use the config cache and pass that same object.
        iterator_to_array($instance);
        self::assertSame($lastconfig2, $metric->lastconfig);
    }

    /**
     * Tests the {@see registered_metric::persist_enabled_state} method.
     *
     * @param bool $from Initial enabled state.
     * @param bool $to State to set via {@see registered_metric::persist_enabled_state}.
     * @param class-string<base_event>[] $events Names of event classes expected to be triggered in the given order.
     * @throws dml_exception
     * @throws coding_exception
     * @throws JsonException
     * @throws ReflectionException
     */
    #[DataProvider('provider_test_persist_enabled_state')]
    public function test_persist_enabled_state(bool $from, bool $to, array $events): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        // Set modification time in the past and arbitrary user.
        $creationtime = time() - 1000;
        $creationuser = (int) $generator->create_user()->id;
        $record = new metric_record(
            component: 'tool_monitoring',
            name: 'test_metric',
            enabled: $from,
            timecreated: $creationtime,
            timemodified: $creationtime,
            usermodified: $creationuser,
        );
        // Insert record manually.
        $record->id = $DB->insert_record(metric_record::TABLE, $record->to_array());
        $instance = new registered_metric(new test_metric(), $record);
        // Intercept the event here.
        $eventsink = $this->redirectEvents();
        $instance->persist_enabled_state($to);
        $eventsink->close();
        // Load updated record manually from the database.
        $updatedrecord = $DB->get_record(metric_record::TABLE, ['id' => $record->id]);
        self::assertSame($to, $instance->enabled);
        self::assertEquals($to, (bool) $updatedrecord->enabled);
        // Check that metadata was updated.
        self::assertEquals($updatedrecord->timemodified, $instance->timemodified);
        self::assertEquals($updatedrecord->usermodified, $instance->usermodified);
        if ($from !== $to) {
            self::assertGreaterThan($creationtime, $instance->timemodified);
            self::assertSame($USER->id, $instance->usermodified);
        } else {
            self::assertSame($creationtime, $instance->timemodified);
            self::assertSame($creationuser, $instance->usermodified);
        }
        // Check that the events were triggered as expected.
        $actualevents = array_map(fn (base_event $event): string => $event::class, $eventsink->get_events());
        self::assertSame($events, $actualevents);
    }

    /**
     * Provides test data for the {@see test_persist_enabled_state} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_persist_enabled_state(): array {
        return [
            'No state change when already enabled' => [
                'from' => true,
                'to' => true,
                'events' => [],
            ],
            'No state change when already disabled' => [
                'from' => false,
                'to' => false,
                'events' => [],
            ],
            'Metric gets enabled' => [
                'from' => false,
                'to' => true,
                'events' => [
                    event\metric_enabled::class,
                ],
            ],
            'Metric gets disabled' => [
                'from' => true,
                'to' => false,
                'events' => [
                    event\metric_disabled::class,
                ],
            ],
        ];
    }

    /**
     * Tests the {@see registered_metric::update_with_form_data} method.
     *
     * @param metric $metric Metric to construct the test instance from.
     * @param metric_record $metricrecord Record to construct the test instance from.
     * @param array<string, mixed> $formdata Passed as the argument to the method.
     * @param array<string, mixed> $expected Properties expected to be set after the call on both the instance and the DB record.
     * @param class-string<base_event>[] $events Names of event classes expected to be triggered in the given order.
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     * @throws ReflectionException
     */
    #[DataProvider('provider_test_update_with_form_data')]
    public function test_update_with_form_data(
        metric $metric,
        metric_record $metricrecord,
        array $formdata,
        array $expected,
        array $events = [],
    ): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        // Set modification time in the past and arbitrary user.
        $creationtime = time() - 1000;
        $newuserid = (int) $generator->create_user()->id;
        $metricrecord->timecreated = $creationtime;
        $metricrecord->timemodified = $creationtime;
        $metricrecord->usermodified = $newuserid;
        // Insert record manually.
        $metricrecord->id = $DB->insert_record(metric_record::TABLE, $metricrecord->to_array());
        $existingrecord = $DB->get_record(metric_record::TABLE, ['id' => $metricrecord->id]);
        // Do some sanity checks.
        $expectedbefore = [
            'id'           => $metricrecord->id,
            'component'    => $metricrecord->component,
            'name'         => $metricrecord->name,
            'enabled'      => $metricrecord->enabled,
            'config'       => $metricrecord->config,
            'timecreated'  => $creationtime,
            'timemodified' => $creationtime,
            'usermodified' => $newuserid,
        ];
        foreach ($expectedbefore as $name => $value) {
            self::assertEquals($value, $existingrecord->$name);
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
        // Create an instance and do the thing.
        $instance = new registered_metric($metric, $metricrecord);
        $instance->update_with_form_data((object) $formdata);
        $eventsink->close();
        $updatedrecord = $DB->get_record(metric_record::TABLE, ['id' => $metricrecord->id]);
        if (empty($events)) {
            self::assertEquals($creationtime, $updatedrecord->timemodified, "DB record timemodified unexpectedly changed");
        } else {
            // Time modified should have been updated.
            self::assertGreaterThan($creationtime, $updatedrecord->timemodified);
        }
        // Check that tags are consistent and as expected.
        if (isset($expected['tags'])) {
            self::assertSame($expected['tags'], array_keys($instance->tags));
            $tags = metric_tag::get_for_metric_ids($metricrecord->id)[$metricrecord->id];
            self::assertSame($expected['tags'], array_keys($tags));
            unset($expected['tags']);
        }
        // Check the expected values.
        foreach ($expected as $name => $value) {
            self::assertEquals($value, $updatedrecord->$name, "Unexpected $name on DB record");
            self::assertEquals($value, $instance->$name, "Unexpected $name on instance");
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
    public static function provider_test_update_with_form_data(): array {
        return [
            'Enabled basic metric, nothing changed' => [
                'metricrecord' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric',
                    enabled: true,
                ),
                'metric' => new test_metric(),
                'formdata' => [
                    'enabled' => true,
                    'tags'    => [],
                ],
                'expected' => [
                    'config'  => null,
                    'enabled' => true,
                ],
                'events' => [],
            ],
            'Enabled basic metric, being disabled, arbitrary form data present' => [
                'metricrecord' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric',
                    enabled: true,
                ),
                'metric' => new test_metric(),
                'formdata' => [
                    'enabled' => false,
                    'tags'    => [],
                    'some'    => 'data',
                    'what'    => 'ever',
                ],
                'expected' => [
                    'config'  => null,
                    'enabled' => false,
                ],
                'events' => [
                    event\metric_disabled::class,
                ],
            ],
            'Enabled configurable metric, nothing changed' => [
                'metricrecord' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric_with_config',
                    enabled: true,
                    config: '{"foo":"baz","spam":0}',
                ),
                'metric' => new test_metric_with_config(),
                'formdata' => [
                    'enabled' => true,
                    'tags'    => [],
                    'foo'     => 'baz',
                    'spam'    => 0,
                ],
                'expected' => [
                    'config'  => '{"foo":"baz","spam":0}',
                    'enabled' => true,
                ],
                'events' => [],
            ],
            'Enabled configurable metric, having config updated' => [
                'metricrecord' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric_with_config',
                    enabled: true,
                    config: '{"foo":"baz","spam":0}',
                ),
                'metric' => new test_metric_with_config(),
                'formdata' => [
                    'enabled' => true,
                    'tags'    => [],
                    'foo'     => 'quux',
                    'spam'    => -1,
                ],
                'expected' => [
                    'config' => '{"foo":"quux","spam":-1}',
                ],
                'events'   => [
                    event\metric_config_updated::class,
                ],
            ],
            'Enabled configurable metric, being disabled' => [
                'metricrecord' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric_with_config',
                    enabled: true,
                    config: '{"foo":"baz","spam":0}',
                ),
                'metric' => new test_metric_with_config(),
                'formdata' => [
                    'enabled' => false,
                    'tags'    => [],
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
                'metricrecord' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric_with_config',
                    config: '{"foo":"baz","spam":0}',
                ),
                'metric' => new test_metric_with_config(),
                'formdata' => [
                    'enabled' => true,
                    'tags'    => [],
                    'foo'     => 'baz',
                    'spam'    => 0,
                ],
                'expected' => [
                    'config'  => '{"foo":"baz","spam":0}',
                    'enabled' => true,
                ],
                'events' => [
                    event\metric_enabled::class,
                ],
            ],
            'Disabled configurable metric, being enabled and having config updated' => [
                'metricrecord' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric_with_config',
                    config: '{"foo":"baz","spam":0}',
                ),
                'metric' => new test_metric_with_config(),
                'formdata' => [
                    'enabled' => true,
                    'tags'    => [],
                    'foo'     => 'bar',
                    'spam'    => 1,
                ],
                'expected' => [
                    'config'  => '{"foo":"bar","spam":1}',
                    'enabled' => true,
                ],
                'events' => [
                    event\metric_enabled::class,
                    event\metric_config_updated::class,
                ],
            ],
            'Changing tags and updating config at the same time' => [
                'metricrecord' => new metric_record(
                    component: 'tool_monitoring',
                    name: 'test_metric_with_config',
                    enabled: true,
                    config: '{"foo":"baz","spam":0}',
                ),
                'metric' => new test_metric_with_config(),
                'formdata' => [
                    'enabled' => true,
                    'tags'    => ['beans'],
                    'foo'     => 'bar',
                    'spam'    => 1,
                ],
                'expected' => [
                    'config'  => '{"foo":"bar","spam":1}',
                    'enabled' => true,
                    'tags'    => ['beans'],
                ],
                'events' => [
                    tag_created::class,
                    tag_added::class,
                    event\metric_config_updated::class,
                ],
            ],
        ];
    }

    /**
     * Tests the {@see registered_metric::prepare_to_cache} method.
     *
     * @throws coding_exception
     */
    public function test_prepare_to_cache(): void {
        $record = new metric_record(
            component: 'tool_monitoring',
            name: 'user_accounts',
            enabled: true,
            timecreated: 123,
            timemodified: 456,
            usermodified: 789,
            id: 42,
        );
        $instance = new registered_metric(new metrics\user_accounts(), $record);
        $output = $instance->prepare_to_cache();
        self::assertSame([
            'component'    => 'tool_monitoring',
            'name'         => 'user_accounts',
            'enabled'      => true,
            'config'       => null,
            'timecreated'  => 123,
            'timemodified' => 456,
            'usermodified' => 789,
            'id'           => 42,
            'tags'         => [],
        ], $output);
    }

    /**
     * Tests the {@see registered_metric::wake_from_cache} method.
     *
     * @param mixed $data Data to pass to the method.
     * @param array<string, mixed>|string $expected Expected properties on the new instance or exception class name.
     * @param string|null $debugging Expected debugging message.
     * @throws coding_exception
     */
    #[DataProvider('provider_test_wake_from_cache')]
    public function test_wake_from_cache(mixed $data, array|string $expected, string|null $debugging = null): void {
        if (is_string($expected)) {
            $this->expectException($expected);
            registered_metric::wake_from_cache($data);
            return;
        }
        $instance = registered_metric::wake_from_cache($data);
        foreach ($expected as $name => $value) {
            if ($name === 'tags') {
                continue;
            }
            self::assertEquals($value, $instance->$name);
        }
        // Check that the metric assigned to the new instance is the same as the one collected by the hook.
        $collection = di::get(metric_collection::class);
        $metric = $collection->get($instance->component, $instance->name);
        $metricprop = new ReflectionProperty(registered_metric::class, 'metric');
        self::assertSame($metric, $metricprop->getValue($instance));
        if (!is_null($debugging)) {
            $this->assertDebuggingCalled($debugging);
        }
    }

    /**
     * Provides test data for the {@see test_wake_from_cache} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_wake_from_cache(): array {
        return [
            'All relevant data present' => [
                'data' => (object) [
                    'id'           => 1,
                    'component'    => 'tool_monitoring',
                    'name'         => 'users_online',
                    'enabled'      => true,
                    'config'       => '{"foo":"baz,"spam":42}',
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 1,
                    'tags'         => [],
                ],
                'expected' => [
                    'id'           => 1,
                    'component'    => 'tool_monitoring',
                    'name'         => 'users_online',
                    'enabled'      => true,
                    'config'       => '{"foo":"baz,"spam":42}',
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 1,
                    'tags'         => [],
                ],
            ],
            'Data is not an array/stdClass' => [
                'data' => 'oops',
                'expected' => coding_exception::class,
            ],
            'Data is a list' => [
                'data' => ['foo', 'bar', 'baz'],
                'expected' => coding_exception::class,
            ],
            'Data is missing a required key (id)' => [
                'data' => [
                    'component'    => 'tool_monitoring',
                    'name'         => 'user_accounts',
                    'enabled'      => true,
                    'config'       => '{"foo":"baz,"spam":42}',
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 1,
                    'tags'         => [],
                ],
                'expected' => coding_exception::class,
            ],
            'No matching metric instance collected' => [
                'data' => [
                    'id'           => 1,
                    'component'    => 'beans',
                    'name'         => 'toast',
                    'enabled'      => true,
                    'config'       => '{"foo":"baz,"spam":42}',
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 1,
                    'tags'         => [],
                ],
                'expected' => coding_exception::class,
            ],
            'Unexpected fields' => [
                'data' => (object) [
                    'unexpected'   => 'stuff',
                    'even_more'    => 'stuff',
                    'id'           => 1,
                    'component'    => 'tool_monitoring',
                    'name'         => 'users_online',
                    'enabled'      => true,
                    'config'       => '{"foo":"baz,"spam":42}',
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 1,
                    'tags'         => [],
                ],
                'expected' => [
                    'id'           => 1,
                    'component'    => 'tool_monitoring',
                    'name'         => 'users_online',
                    'enabled'      => true,
                    'config'       => '{"foo":"baz,"spam":42}',
                    'timecreated'  => 123,
                    'timemodified' => 456,
                    'usermodified' => 1,
                    'tags'         => [],
                ],
                'debugging' => "Unexpected cache fields for registered_metric 1: unexpected, even_more",
            ],
        ];
    }
}

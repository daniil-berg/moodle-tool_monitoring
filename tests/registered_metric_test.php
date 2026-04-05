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
use core\event\base as base_event;
use core\exception\coding_exception;
use dml_exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\metrics;
use tool_monitoring\local\testing\custom_metric_config;
use tool_monitoring\local\testing\metric_settable_values;
use tool_monitoring\local\testing\metric_with_custom_config;

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
     * Tests the {@see registered_metric::get_for_metrics} method.
     *
     * @param array<array<string, string>> $indb DB records to insert before calling the method.
     * @param array $metrics Metric instances to pass to the  method.
     * @param array<string, array<string, string>> $expected Arrays of expected instance properties of the returned objects indexed
     *                                                       by qualified name.
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    #[DataProvider('provider_test_get_for_metrics')]
    public function test_get_for_metrics(array $indb, array $metrics, array $expected): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_records(registered_metric::TABLE, $indb);
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
        $metricmissing = new metric_settable_values();
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
                    'tool_monitoring_metric_settable_values' => [
                        'name' => 'metric_settable_values',
                    ] + $defaults,
                    'tool_monitoring_quiz_attempts_in_progress' => [
                        'name'   => 'quiz_attempts_in_progress',
                        'config' => '{"maxidleseconds":1200,"maxdeadlineseconds":10800}',
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
                    'tool_monitoring_metric_settable_values' => [
                        'name' => 'metric_settable_values',
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
     * Tests the {@see IteratorAggregate} implementation of the {@see registered_metric} class.
     *
     * @param iterable<metric_value>|metric_value $testvalues Metric values to be produced by the test metric.
     * @throws JsonException
     */
    #[DataProvider('provider_test_iterator')]
    public function test_iterator(iterable|metric_value $testvalues): void {
        $this->resetAfterTest();
        $metric = new metric_settable_values();
        $metric->values = $testvalues;
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
     * Tests the {@see registered_metric::get_qualified_name} method.
     *
     * @param string $component Component input.
     * @param string $name Name input.
     * @param string $expected Expected return value name.
     */
    #[DataProvider('provider_test_get_qualified_name')]
    public function test_get_qualified_name(string $component, string $name, string $expected): void {
        self::assertSame($expected, registered_metric::get_qualified_name($component, $name));
    }

    /**
     * Provides test data for the {@see test_get_qualified_name} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_qualified_name(): array {
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
     * Tests the {@see registered_metric::__get} method.
     *
     * @throws coding_exception
     * @throws JsonException
     */
    public function test___get(): void {
        $instance = registered_metric::from_metric(new metric_settable_values());
        self::assertSame(registered_metric::get_qualified_name($instance->component, $instance->name), $instance->qualifiedname);
        self::assertEquals(metric_settable_values::get_description(), $instance->description);
        self::assertSame(metric_settable_values::get_type(), $instance->type);
        self::assertSame([], $instance->tags);
        self::assertTrue(isset($instance->tags));
        $instance = registered_metric::from_metric(new metric_with_custom_config());
        self::assertSame(custom_metric_config::class, $instance->configclass);
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
     */
    #[DataProvider('provider_test_persist_enabled_state')]
    public function test_persist_enabled_state(bool $from, bool $to, array $events): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $metric = registered_metric::from_metric(new metric_settable_values());
        $metric->enabled = $from;
        // Set modification time in the past and arbitrary user.
        $generator = $this->getDataGenerator();
        $creationtime = time() - 1000;
        $creationuser = (int) $generator->create_user()->id;
        $metric->timecreated = $creationtime;
        $metric->timemodified = $creationtime;
        $metric->usermodified = $creationuser;
        // Insert record manually.
        $metric->id = $DB->insert_record(registered_metric::TABLE, $metric->to_db());
        // Intercept the event here.
        $eventsink = $this->redirectEvents();
        $metric->persist_enabled_state($to);
        $eventsink->close();
        // Load updated record manually from the database.
        $record = $DB->get_record(registered_metric::TABLE, ['id' => $metric->id]);
        self::assertSame($to, $metric->enabled);
        self::assertEquals($to, (bool) $record->enabled);
        // Check that metadata was updated.
        self::assertEquals($record->timemodified, $metric->timemodified);
        self::assertEquals($record->usermodified, $metric->usermodified);
        if ($from !== $to) {
            self::assertGreaterThan($creationtime, $metric->timemodified);
            self::assertSame($USER->id, $metric->usermodified);
        } else {
            self::assertSame($creationtime, $metric->timemodified);
            self::assertSame($creationuser, $metric->usermodified);
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
     * @param registered_metric $metric Instance on which to call the method.
     * @param array<string, mixed> $formdata Passed as the argument to the method.
     * @param array<string, mixed> $expected Properties expected to be set after the call on both the instance and the DB record.
     * @param class-string<base_event>[] $events Names of event classes expected to be triggered in the given order.
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    #[DataProvider('provider_test_update_with_form_data')]
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
        if (empty($events)) {
            self::assertEquals($creationtime, $record->timemodified, "DB record timemodified unexpectedly changed");
        } else {
            // Time modified should have been updated.
            self::assertGreaterThan($creationtime, $record->timemodified);
        }
        // Check the expected values.
        foreach ($expected as $name => $value) {
            self::assertEquals($value, $record->$name, "Unexpected $name on DB record");
            self::assertEquals($value, $metric->$name, "Unexpected $name on instance");
        }
        // Check that the events were triggered as expected.
        $actualevents = array_map(fn (base_event $event): string => $event::class, $eventsink->get_events());
        self::assertSame($events, $actualevents);
    }

    /**
     * Provides test data for the {@see test_update_with_form_data} method.
     *
     * @return array[] Arguments for the test method.
     * @throws JsonException
     */
    public static function provider_test_update_with_form_data(): array {
        $metricenabled = registered_metric::from_metric(new metric_settable_values());
        $metricenabled->enabled = true;
        $metricdisabledwithconfig = registered_metric::from_metric(new metric_with_custom_config());
        $metricdisabledwithconfig->config = '{"foo":"baz","spam":0}';
        $metricenabledwithconfig = registered_metric::from_metric(new metric_with_custom_config());
        $metricenabledwithconfig->enabled = true;
        $metricenabledwithconfig->config = '{"foo":"baz","spam":0}';
        $metricenabledwithoutconfig = registered_metric::from_metric(new metric_with_custom_config());
        $metricenabledwithoutconfig->enabled = true;
        $metricenabledwithoutconfig->config = '{}';
        return [
            'Enabled basic metric, nothing changed' => [
                'metric'   => $metricenabled,
                'formdata' => [
                    'enabled' => true,
                    'tags' => [],
                ],
                'expected' => [
                    'config'  => null,
                    'enabled' => true,
                ],
                'events'   => [],
            ],
            'Enabled basic metric, being disabled, arbitrary form data present' => [
                'metric'   => $metricenabled,
                'formdata' => [
                    'enabled' => false,
                    'tags' => [],
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
                'metric'   => $metricenabledwithconfig,
                'formdata' => [
                    'enabled' => true,
                    'tags' => [],
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
                'metric'   => $metricenabledwithoutconfig,
                'formdata' => [
                    'enabled' => true,
                    'tags' => [],
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
                'metric'   => $metricenabledwithconfig,
                'formdata' => [
                    'enabled' => false,
                    'tags' => [],
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
                'metric'   => $metricenabledwithconfig,
                'formdata' => [
                    'enabled' => true,
                    'tags' => [],
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
                'metric'   => $metricdisabledwithconfig,
                'formdata' => [
                    'enabled' => true,
                    'tags' => [],
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

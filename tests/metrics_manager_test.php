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
 * Definition of the {@see metrics_manager_test} class.
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
use core\exception\coding_exception;
use dml_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\metrics;
use tool_monitoring\local\testing\metric_settable_values;

/**
 * Unit tests for the {@see metrics_manager} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(metrics_manager::class)]
final class metrics_manager_test extends advanced_testcase {
    /**
     * Returns an instance of an anonymous subclass of {@see metric_settable_values} with the specified name.
     *
     * @param string $name String to return from the class' {@see metric::get_name} method.
     * @return metric_settable_values New metric instance.
     */
    private static function named_metric_factory(string $name): metric_settable_values {
        // @phpcs:disable moodle.PHP.ForbiddenTokens.Found
        return eval("return new class() extends \\tool_monitoring\\local\\testing\\metric_settable_values {
            public static function get_name(): string {
                return '$name';
            }
        };");
    }

    public function test___construct(): void {
        $manager = new metrics_manager();
        self::assertSame([], iterator_to_array($manager->collection));
        self::assertSame([], $manager->metrics);

        $collection = new metric_collection();
        $manager = new metrics_manager($collection);
        self::assertSame($collection, $manager->collection);
        self::assertSame([], $manager->metrics);
    }

    public function test_dispatch_collection(): void {
        $collection = new metric_collection();
        $manager = new metrics_manager($collection);
        $manager->dispatch_hook();
        $expected = [
            metrics\courses::class,
            metrics\num_overdue_tasks::class,
            metrics\num_quiz_attempts_in_progress::class,
            metrics\num_user_count::class,
            metrics\users_online::class,
        ];
        $metricclasses = [];
        foreach ($manager->collection as $metric) {
            if ($metric::get_component() === 'tool_monitoring') {
                $metricclasses[] = $metric::class;
            }
        }
        self::assertEmpty(array_diff($expected, $metricclasses));
        self::assertEmpty(array_diff($metricclasses, $expected));
    }

    /**
     * Tests the {@see metrics_manager::fetch} method.
     *
     * @param metric[] $collected Metric instances to add to the collection beforehand.
     * @param array<string, mixed>[] $registered Associative arrays of data to insert into the {@see registered_metric::TABLE}
     *                                           before calling the tested function.
     * @param array<string, array<string, mixed>> $expected Associative arrays of property name-value-pairs expected to be present
     *                                                      on the metric instances. Indexed by qualified name.
     * @param bool|null $enabled Passed to the tested function.
     * @param string|null $expectedwarning If passed a string, a warning is expected to be triggered containing that text.
     * @throws coding_exception
     * @throws dml_exception
     */
    #[DataProvider('provider_test_fetch')]
    public function test_fetch(
        array $collected,
        array $registered,
        array $expected,
        bool|null $enabled = null,
        string|null $expectedwarning = null,
    ): void {
        global $DB;
        $this->resetAfterTest();
        // Set up metric collection and manager.
        $collection = new metric_collection();
        foreach ($collected as $metric) {
            $collection->add($metric);
        }
        $manager = new metrics_manager($collection);
        // Sanity check.
        self::assertSame(0, $DB->count_records(registered_metric::TABLE));
        // Add pre-existing metrics records.
        foreach ($registered as $toinsert) {
            $DB->insert_record(registered_metric::TABLE, $toinsert);
        }
        $records = $DB->get_records(registered_metric::TABLE);
        // Get ready to intercept warnings.
        $lastwarning = null;
        set_error_handler(
            static function (int $errno, string $errstr) use (&$lastwarning): void {
                $lastwarning = $errstr;
            },
        );
        // Do the thing.
        $manager->fetch(enabled: $enabled);
        restore_error_handler();
        if (!is_null($expectedwarning)) {
            self::assertNotNull($lastwarning);
            self::assertStringContainsString($expectedwarning, $lastwarning);
        } else {
            self::assertNull($lastwarning);
        }
        // The number of registered metrics should be as expected.
        self::assertCount(count($expected), $manager->metrics);
        // The records in the DB table should be unchanged.
        self::assertEquals($records, $DB->get_records(registered_metric::TABLE));
        // Check that the registered metrics are exactly as we expect them and there is a DB record for each of them.
        // To be extra sure, store already checked metric IDs.
        $checkedids = [];
        foreach ($expected as $qname => $properties) {
            self::assertArrayHasKey($qname, $manager->metrics);
            $metric = $manager->metrics[$qname];
            self::assertNotNull($metric->id);
            self::assertNotContains($metric->id, $checkedids);
            self::assertArrayHasKey($metric->id, $records);
            $record = $records[$metric->id];
            foreach ($properties as $name => $expectedvalue) {
                self::assertEquals($expectedvalue, $record->$name);
                self::assertEquals($expectedvalue, $metric->$name);
            }
            $checkedids[] = $metric->id;
        }
    }

    /**
     * Provides test data for the {@see test_fetch} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_fetch(): array {
        return [
            'Collection of 3 different metrics; no pre-existing DB entries' => [
                'collected' => [
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'bar'),
                    self::named_metric_factory(name: 'baz'),
                ],
                'registered' => [],
                'expected' => [],
            ],
            'Collection of 3 metrics, 1 of those already registered; 1 orphan in DB' => [
                'collected' => [
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'spam'),
                    self::named_metric_factory(name: 'eggs'),
                ],
                'registered' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => '{"a":1}',
                        'timecreated'  => 10,
                        'timemodified' => 20,
                        'usermodified' => 1,
                    ],
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => true,
                        'timecreated'  => 30,
                        'timemodified' => 40,
                        'usermodified' => 0,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => '{"a":1}',
                        'timecreated'  => 10,
                        'timemodified' => 20,
                        'usermodified' => 1,
                    ],
                ],
            ],
            'Collection of 3 metrics, all with the same qualified name; 1 different pre-existing record' => [
                'collected' => [
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'foo'),
                ],
                'registered' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'timecreated'  => 2,
                        'timemodified' => 1,
                        'usermodified' => 0,
                    ],
                ],
                'expected' => [],
                'expectedwarning' => "Collected more than one metric with the qualified name 'tool_monitoring_foo'",
            ],
            'Collection of 2 metrics, both registered, 1 disabled; getting enabled only' => [
                'collected' => [
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'bar'),
                ],
                'registered' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => '{"a":1}',
                        'timecreated'  => 10,
                        'timemodified' => 20,
                        'usermodified' => 1,
                    ],
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => true,
                        'config'       => null,
                        'timecreated'  => 30,
                        'timemodified' => 40,
                        'usermodified' => 0,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_bar' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => true,
                        'config'       => null,
                        'timecreated'  => 30,
                        'timemodified' => 40,
                        'usermodified' => 0,
                    ],
                ],
                'enabled' => true,
            ],
            'Collection of 2 metrics, both registered, 1 disabled; getting disabled only' => [
                'collected' => [
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'bar'),
                ],
                'registered' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => '{"a":1}',
                        'timecreated'  => 10,
                        'timemodified' => 20,
                        'usermodified' => 1,
                    ],
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => true,
                        'config'       => null,
                        'timecreated'  => 30,
                        'timemodified' => 40,
                        'usermodified' => 0,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => '{"a":1}',
                        'timecreated'  => 10,
                        'timemodified' => 20,
                        'usermodified' => 1,
                    ],
                ],
                'enabled' => false,
            ],
        ];
    }

    /**
     * Test the {@see metrics_manager::sync} method with the `collect` parameter set to `false`.
     *
     * @param metric[] $collected Metric instances to add to the collection beforehand.
     * @param array<string, mixed>[] $registered Associative arrays of data to insert into the {@see registered_metric::TABLE}
     *                                           before calling the tested function.
     * @param array<string, array<string, mixed>> $expected Associative arrays of property name-value-pairs expected to be present
     *                                                      on the {@see registered_metric} instances as well as on the
     *                                                      corresponding raw database records. Indexed by qualified name.
     * @param bool $delete Passed to the tested method; `false` by default.
     * @param string|null $expectedwarning If passed a string, a warning is expected to be triggered containing that text.
     * @throws coding_exception
     * @throws dml_exception
     */
    #[DataProvider('provider_test_sync')]
    public function test_sync(
        array $collected,
        array $registered,
        array $expected,
        bool $delete = false,
        string|null $expectedwarning = null,
    ): void {
        global $DB;
        $this->resetAfterTest();
        // Set up metric collection and manager.
        $collection = new metric_collection();
        foreach ($collected as $metric) {
            $collection->add($metric);
        }
        $manager = new metrics_manager($collection);
        // Sanity check.
        self::assertSame(0, $DB->count_records(registered_metric::TABLE));
        // Add pre-existing metrics records.
        foreach ($registered as $toinsert) {
            $DB->insert_record(registered_metric::TABLE, $toinsert);
        }
        // Prepare to intercept warnings.
        $lastwarning = null;
        set_error_handler(
            static function (int $errno, string $errstr) use (&$lastwarning): void {
                $lastwarning = $errstr;
            },
        );
        // Do the thing.
        $manager->sync(collect: false, delete: $delete);
        restore_error_handler();
        if (!is_null($expectedwarning)) {
            self::assertNotNull($lastwarning);
            self::assertStringContainsString($expectedwarning, $lastwarning);
        } else {
            self::assertNull($lastwarning);
        }
        // The number of registered metrics should be the same as the number of records in the DB table.
        $expectedcount = count($expected);
        $records = $DB->get_records(registered_metric::TABLE);
        self::assertCount($expectedcount, $manager->metrics);
        self::assertCount($expectedcount, $records);
        // Check that both the registered metrics and the raw DB records are exactly as we expect them.
        // To be extra sure, store already checked metric IDs.
        $checkedids = [];
        foreach ($expected as $qname => $properties) {
            self::assertArrayHasKey($qname, $manager->metrics);
            $metric = $manager->metrics[$qname];
            self::assertNotNull($metric->id);
            self::assertNotContains($metric->id, $checkedids);
            self::assertArrayHasKey($metric->id, $records);
            $record = $records[$metric->id];
            foreach ($properties as $name => $expectedvalue) {
                self::assertEquals($expectedvalue, $record->$name);
                self::assertEquals($expectedvalue, $metric->$name);
            }
            $checkedids[] = $metric->id;
        }
    }

    /**
     * Provides test data for the {@see test_sync} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_sync(): array {
        global $USER;
        return [
            'Collection of 3 different metrics; no pre-existing DB entries' => [
                'collected' => [
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'bar'),
                    self::named_metric_factory(name: 'baz'),
                ],
                'registered' => [],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => null,
                        'usermodified' => $USER->id,
                    ],
                    'tool_monitoring_bar' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => false,
                        'config'       => null,
                        'usermodified' => $USER->id,
                    ],
                    'tool_monitoring_baz' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'baz',
                        'enabled'      => false,
                        'config'       => null,
                        'usermodified' => $USER->id,
                    ],
                ],
            ],
            'Collection of 3 metrics, 1 of those already registered; 1 orphan in DB; with deletion' => [
                'collected' => [
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'spam'),
                    self::named_metric_factory(name: 'eggs'),
                ],
                'registered' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => '{"a":1}',
                        'timecreated'  => 10,
                        'timemodified' => 20,
                        'usermodified' => 1,
                    ],
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => true,
                        'config'       => null,
                        'timecreated'  => 30,
                        'timemodified' => 40,
                        'usermodified' => 0,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => '{"a":1}',
                        'timecreated'  => 10,
                        'timemodified' => 20,
                        'usermodified' => 1,
                    ],
                    'tool_monitoring_spam' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'spam',
                        'enabled'      => false,
                        'config'       => null,
                        'usermodified' => $USER->id,
                    ],
                    'tool_monitoring_eggs' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'eggs',
                        'enabled'      => false,
                        'config'       => null,
                        'usermodified' => $USER->id,
                    ],
                ],
                'delete' => true,
            ],
            'Collection of 3 metrics with the same qualified name; 1 different pre-existing record; with deletion' => [
                'collected' => [
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'foo'),
                    self::named_metric_factory(name: 'foo'),
                ],
                'registered' => [
                    [
                        'component'    => 'tool_monitoring',
                        'name'         => 'bar',
                        'enabled'      => true,
                        'config'       => null,
                        'timecreated'  => 30,
                        'timemodified' => 40,
                        'usermodified' => 0,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'component'    => 'tool_monitoring',
                        'name'         => 'foo',
                        'enabled'      => false,
                        'config'       => null,
                        'usermodified' => $USER->id,
                    ],
                ],
                'delete' => true,
                'expectedwarning' => "Collected more than one metric with the qualified name 'tool_monitoring_foo'",
            ],
        ];
    }
}

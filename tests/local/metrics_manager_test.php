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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace tool_monitoring\local;

use advanced_testcase;
use core\di;
use core\exception\coding_exception;
use core_tag_area;
use dml_exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\exceptions\metric_name_invalid;
use tool_monitoring\exceptions\metric_not_found;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\exceptions\tags_disabled;
use tool_monitoring\exceptions\tool_monitoring_exception;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\testing\test_metric;
use tool_monitoring\local\testing\test_metric_with_config;
use tool_monitoring\metric;
use tool_monitoring\metric_tag;

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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(metrics_manager::class)]
final class metrics_manager_test extends advanced_testcase {
    public function test___construct(): void {
        // Test manual construction.
        $collection = new metric_collection();
        $manager = new metrics_manager($collection);
        self::assertSame($collection, $manager->collection);
        // Check that DI dispatches the collection hook automatically and it subsequently contains our expected metrics.
        $manager = di::get(metrics_manager::class);
        $expected = [
            metrics\courses::class,
            metrics\overdue_tasks::class,
            metrics\quiz_attempts_in_progress::class,
            metrics\user_accounts::class,
            metrics\users_online::class,
        ];
        $metricclasses = [];
        foreach ($manager->collection as $metric) {
            if ($metric->get_component() === 'tool_monitoring') {
                $metricclasses[] = $metric::class;
            }
        }
        self::assertEmpty(array_diff($expected, $metricclasses));
        self::assertEmpty(array_diff($metricclasses, $expected));
        // Check that the instances are the same when getting them repeatedly via the DI container.
        self::assertSame($manager, di::get(metrics_manager::class));
    }

    /**
     * Tests the {@see metrics_manager::getIterator} and {@see metrics_manager::filter} methods.
     *
     * Indirectly also tests the cache data source mechanism because iteration first attempts to load metrics from the cache.
     *
     * @param metric[] $collected Metric instances to add to the collection beforehand.
     * @param array<string, mixed>[] $registered Associative arrays of data to insert into the {@see metric_record::TABLE}
     *                                           before calling the tested function.
     * @param array<string, array<string, mixed>> $expected Associative arrays of property name-value-pairs expected to be present
     *                                                      on the metric instances. Indexed by qualified name.
     * @param bool|null $enabled Passed to the {@see metrics_manager::filter} function.
     * @param array $tagnames Passed to the {@see metrics_manager::filter} function.
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found
     * @throws tags_disabled
     */
    #[DataProvider('provider_test_filter')]
    public function test_filter(
        array $collected,
        array $registered,
        array $expected,
        bool|null $enabled = null,
        array $tagnames = [],
    ): void {
        global $DB;
        $this->resetAfterTest();
        // Set up metric collection and manager.
        $collection = new metric_collection();
        array_walk($collected, fn (metric $m) => $collection->add($m));
        di::set(metric_collection::class, $collection);
        $manager = di::get(metrics_manager::class);
        // Sanity check.
        self::assertSame(0, $DB->count_records(metric_record::TABLE));
        // Add pre-existing metrics records.
        $defaults = [
            'component'    => 'tool_monitoring',
            'timecreated'  => 123,
            'timemodified' => 456,
            'usermodified' => 1,
        ];
        foreach ($registered as $toinsert) {
            $metricid = $DB->insert_record(metric_record::TABLE, $toinsert + $defaults);
            if (isset($toinsert['tags'])) {
                metric_tag::set_for_metric($metricid, ...$toinsert['tags']);
            }
        }
        $records = $DB->get_records(metric_record::TABLE);
        // Call the tested method.
        $metrics = $manager->filter(enabled: $enabled, tagnames: $tagnames);
        // The records in the DB table should be unchanged.
        self::assertEquals($records, $DB->get_records(metric_record::TABLE));
        // The number of registered metrics should be as expected.
        self::assertCount(count($expected), $metrics);
        // Check that the registered metrics are exactly as we expect them, and there is a DB record for each of them.
        // To be extra sure, store already checked metric IDs.
        $checkedids = [];
        foreach ($expected as $qname => $properties) {
            self::assertArrayHasKey($qname, $metrics);
            $metric = $metrics[$qname];
            self::assertNotNull($metric->id);
            self::assertNotContains($metric->id, $checkedids);
            self::assertArrayHasKey($metric->id, $records);
            $record = $records[$metric->id];
            foreach ($properties + $defaults as $name => $expectedvalue) {
                self::assertEquals($expectedvalue, $record->$name);
                self::assertEquals($expectedvalue, $metric->$name);
            }
            $checkedids[] = $metric->id;
        }
    }

    /**
     * Provides test data for the {@see test_filter} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_filter(): array {
        return [
            'Collection of 3 different metrics; no pre-existing DB entries' => [
                'collected' => [
                    test_metric::create('foo'),
                    test_metric::create('bar'),
                    test_metric::create('baz'),
                ],
                'registered' => [],
                'expected' => [],
            ],
            'Collection of 3 metrics, 1 of those already registered; 1 orphan in DB' => [
                'collected' => [
                    test_metric_with_config::create('foo'),
                    test_metric::create('spam'),
                    test_metric::create('eggs'),
                ],
                'registered' => [
                    [
                        'name'    => 'foo',
                        'enabled' => false,
                        'config'  => '{"a":1}',
                    ],
                    [
                        'name'    => 'bar',
                        'enabled' => true,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'name'    => 'foo',
                        'enabled' => false,
                        'config'  => '{"a":1}',
                    ],
                ],
            ],
            'Collection of 3 metrics, all with the same qualified name; 1 different pre-existing record' => [
                'collected' => [
                    test_metric::create('foo'),
                    test_metric::create('foo'),
                    test_metric::create('foo'),
                ],
                'registered' => [
                    [
                        'name' => 'bar',
                    ],
                ],
                'expected' => [],
            ],
            'Collection of 2 metrics, both registered, 1 disabled; getting enabled only' => [
                'collected' => [
                    test_metric::create('foo'),
                    test_metric::create('bar'),
                ],
                'registered' => [
                    [
                        'name'    => 'foo',
                        'enabled' => false,
                    ],
                    [
                        'name'    => 'bar',
                        'enabled' => true,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_bar' => [
                        'name'    => 'bar',
                        'enabled' => true,
                    ],
                ],
                'enabled' => true,
            ],
            'Collection of 2 metrics, both registered, 1 disabled; getting disabled only' => [
                'collected' => [
                    test_metric_with_config::create('foo'),
                    test_metric::create('bar'),
                ],
                'registered' => [
                    [
                        'name'    => 'foo',
                        'enabled' => false,
                        'config'  => '{"a":1}',
                    ],
                    [
                        'name'    => 'bar',
                        'enabled' => true,
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'name'    => 'foo',
                        'enabled' => false,
                        'config'  => '{"a":1}',
                    ],
                ],
                'enabled' => false,
            ],
            'Collection of 3 metrics, with 2 matching the single specified tag' => [
                'collected' => [
                    test_metric::create('foo'),
                    test_metric::create('bar'),
                    test_metric::create('baz'),
                ],
                'registered' => [
                    [
                        'name' => 'foo',
                        'tags' => ['spam', 'eggs'],
                    ],
                    [
                        'name' => 'bar',
                        'tags' => ['eggs', 'ham'],
                    ],
                    [
                        'name' => 'baz',
                        'tags' => ['spam', 'ham'],
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'name' => 'foo',
                    ],
                    'tool_monitoring_bar' => [
                        'name' => 'bar',
                    ],
                ],
                'enabled' => null,
                'tagnames' => ['eggs'],
            ],
            'Collection of 3 metrics, only 1 matching all specified tags' => [
                'collected' => [
                    test_metric::create('foo'),
                    test_metric::create('bar'),
                    test_metric::create('baz'),
                ],
                'registered' => [
                    [
                        'name' => 'foo',
                        'tags' => ['spam', 'eggs'],
                    ],
                    [
                        'name' => 'bar',
                        'tags' => ['eggs', 'ham'],
                    ],
                    [
                        'name' => 'baz',
                        'tags' => ['spam', 'ham'],
                    ],
                ],
                'expected' => [
                    'tool_monitoring_foo' => [
                        'name' => 'foo',
                    ],
                ],
                'enabled' => null,
                'tagnames' => ['eggs', 'spam'],
            ],
        ];
    }

    /**
     * Tests that the manager continues to produce the expected metrics after changes are made to tag instances.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found
     * @throws tags_disabled
     */
    public function test_tag_instance_changes(): void {
        global $DB;
        $this->resetAfterTest();
        // Add and register two metrics with some tag overlap.
        $collection = new metric_collection();
        $collection->add(test_metric::create('foo'));
        $collection->add(test_metric::create('bar'));
        di::set(metric_collection::class, $collection);
        $manager = di::get(metrics_manager::class);
        $defaults = [
            'component'    => 'tool_monitoring',
            'timecreated'  => 1,
            'timemodified' => 1,
            'usermodified' => 1,
        ];
        $metricidfoo = $DB->insert_record(metric_record::TABLE, ['name' => 'foo', ...$defaults]);
        $metricidbar = $DB->insert_record(metric_record::TABLE, ['name' => 'bar', ...$defaults]);
        metric_tag::set_for_metric($metricidfoo, 'spam', 'eggs');
        metric_tag::set_for_metric($metricidbar, 'spam', 'beans');

        // Sanity checks.
        $metrictagsfoo = array_column($manager['tool_monitoring_foo']->tags, 'name');
        self::assertSame(['spam', 'eggs'], $metrictagsfoo);
        $metrics = $manager->filter(tagnames: ['spam', 'eggs']);
        self::assertCount(1, $metrics);
        self::assertSame($metricidfoo, reset($metrics)->id);

        // Now we remove the tag instance; a new manager should no longer return the metric when filtering.
        metric_tag::remove_item_tag(
            component: 'tool_monitoring',
            itemtype: metric_tag::ITEM_TYPE,
            itemid: $metricidfoo,
            tagname: 'spam',
        );
        di::reset_container();
        di::set(metric_collection::class, $collection);
        $manager = di::get(metrics_manager::class);
        $metrics = $manager->filter(tagnames: ['spam', 'eggs']);
        self::assertEmpty($metrics);
        // The metric should no longer carry the tag when grabbed explicitly.
        $metrictagsfoo = array_column($manager['tool_monitoring_foo']->tags, 'name');
        self::assertSame(['eggs'], $metrictagsfoo);

        // Now link the `eggs` tag to the `bar` metric as well; it should now be returned by a new manager.
        metric_tag::set_for_metric($metricidbar, 'spam', 'eggs', 'beans');
        di::reset_container();
        di::set(metric_collection::class, $collection);
        $manager = di::get(metrics_manager::class);
        $metrics = $manager->filter(tagnames: ['spam', 'eggs']);
        self::assertCount(1, $metrics);
        self::assertSame($metricidbar, reset($metrics)->id);
        // The metric should now carry the tag when grabbed explicitly.
        $metrictagsbar = array_column($manager['tool_monitoring_bar']->tags, 'name');
        self::assertSame(['spam', 'eggs', 'beans'], $metrictagsbar);
    }

    /**
     * Tests that the {@see metrics_manager::filter} method throws when the {@see metric_tag::ITEM_TYPE} tag area is disabled.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found
     * @throws tags_disabled
     */
    public function test_filter_throws_on_disabled_tag_area(): void {
        global $DB;
        $this->resetAfterTest();
        $area = $DB->get_record('tag_area', ['itemtype' => metric_tag::ITEM_TYPE, 'component' => 'tool_monitoring']);
        core_tag_area::update($area, ['enabled' => false]);
        $this->expectExceptionObject(new tags_disabled(metric_tag::ITEM_TYPE));
        di::get(metrics_manager::class)->filter(tagnames: ['foo']);
    }

    /**
     * Tests that the {@see metrics_manager::filter} method throws when the `usetags` setting is off.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found
     * @throws tags_disabled
     */
    public function test_filter_throws_on_disabled_tags(): void {
        $this->resetAfterTest();
        set_config('usetags', false);
        $this->expectExceptionObject(new tags_disabled(metric_tag::ITEM_TYPE));
        di::get(metrics_manager::class)->filter(tagnames: ['foo']);
    }

    /**
     * Test the {@see metrics_manager::sync} method with the `collect` parameter set to `false`.
     *
     * @param metric[] $collected Metric instances to add to the collection beforehand.
     * @param array<string, mixed>[] $registered Associative arrays of data to insert into the {@see metric_record::TABLE}
     *                                           before calling the tested function.
     * @param array<string, array<string, mixed>> $expected Associative arrays of property name-value-pairs expected to be present
     *                                                      on the {@see managed_metric} instances as well as on the
     *                                                      corresponding raw database records. Indexed by qualified name.
     * @param bool $delete Passed to the tested method; `false` by default.
     * @throws coding_exception
     * @throws dml_exception
     * @throws metric_name_invalid
     * @throws tag_not_found
     * @throws tags_disabled
     */
    #[DataProvider('provider_test_sync')]
    public function test_sync(
        array $collected,
        array $registered,
        array $expected,
        bool $delete = false,
    ): void {
        global $DB;
        $this->resetAfterTest();
        // Set up metric collection and manager.
        $collection = new metric_collection();
        foreach ($collected as $metric) {
            $collection->add($metric);
        }
        di::set(metric_collection::class, $collection);
        $manager = di::get(metrics_manager::class);
        // Sanity check.
        self::assertSame(0, $DB->count_records(metric_record::TABLE));
        // Add pre-existing metrics records.
        foreach ($registered as $toinsert) {
            $DB->insert_record(metric_record::TABLE, $toinsert);
        }
        // Do the thing.
        $metrics = $manager->sync(delete: $delete)->filter();
        // The number of registered metrics should be the same as the number of records in the DB table.
        $expectedcount = count($expected);
        $records = $DB->get_records(metric_record::TABLE);
        self::assertCount($expectedcount, $metrics);
        self::assertCount($expectedcount, $records);
        // Check that both the registered metrics and the raw DB records are exactly as we expect them.
        // To be extra sure, store already checked metric IDs.
        $checkedids = [];
        foreach ($expected as $qname => $properties) {
            $metric = $metrics[$qname] ?? null;
            self::assertInstanceOf(managed_metric::class, $metric);
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
                    test_metric::create('foo'),
                    test_metric::create('bar'),
                    test_metric::create('baz'),
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
                    test_metric_with_config::create('foo'),
                    test_metric::create('spam'),
                    test_metric::create('eggs'),
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
        ];
    }

    /**
     * Tests that the {@see metrics_manager::sync} method removes tag associations when deleting a metric.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws metric_name_invalid
     * @throws JsonException
     */
    public function test_sync_deletes_tag_instances_for_deleted_metrics(): void {
        global $DB;
        $this->resetAfterTest();

        // Create new metric foo in the database.
        $data = [
            'component'    => 'tool_monitoring',
            'name'         => 'foo',
            'enabled'      => true,
            'config'       => null,
            'timecreated'  => 30,
            'timemodified' => 40,
            'usermodified' => 0,
        ];
        $metricid = $DB->insert_record(metric_record::TABLE, $data);
        self::assertNotNull($metricid);

        // Add tags alpha and beta to metric foo.
        metric_tag::set_for_metric($metricid, 'alpha', 'beta');
        self::assertSame(
            2,
            $DB->count_records('tag_instance', [
                'component' => 'tool_monitoring',
                'itemtype' => metric_tag::ITEM_TYPE,
                'itemid' => $metricid,
            ]),
        );

        di::set(metric_collection::class, new metric_collection());
        $manager = di::get(metrics_manager::class);
        // This should delete the `foo` metric.
        $manager->sync(delete: true);
        self::assertFalse($DB->record_exists(metric_record::TABLE, ['id' => $metricid]));

        // Ensure that tags are gone, too.
        self::assertSame(
            0,
            $DB->count_records('tag_instance', [
                'component' => 'tool_monitoring',
                'itemtype' => metric_record::TABLE,
                'itemid' => $metricid,
            ]),
        );
        self::assertSame([$metricid => []], metric_tag::get_for_metric_ids($metricid));
    }

    /**
     * Tests that the {@see metrics_manager::sync} method throws an exception when a collected metric does not pass validation.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws metric_name_invalid
     */
    #[DataProvider('provider_test_sync_throws_on_invalid_collection')]
    public function test_sync_throws_on_invalid_collection(string $name, tool_monitoring_exception $exception): void {
        $this->resetAfterTest();
        $collection = new metric_collection();
        $collection->add(test_metric::create('foo'));
        $collection->add(test_metric::create($name));
        $collection->add(test_metric::create('bar'));
        di::set(metric_collection::class, $collection);
        $manager = di::get(metrics_manager::class);
        $this->expectExceptionObject($exception);
        $manager->sync();
    }

    /**
     * Provides test data for the {@see test_sync_throws_on_invalid_collection} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_sync_throws_on_invalid_collection(): array {
        return [
            'Metric name empty' => [
                'name' => '',
                'exception' => new metric_name_invalid('tool_monitoring', ''),
            ],
            'Metric name too long' => [
                'name' => str_repeat('a', 101),
                'exception' => new metric_name_invalid('tool_monitoring', str_repeat('a', 101)),
            ],
            'Metric name contains space' => [
                'name' => 'foo bar',
                'exception' => new metric_name_invalid('tool_monitoring', 'foo bar'),
            ],
            'Metric name contains upper-case letter' => [
                'name' => 'Foo',
                'exception' => new metric_name_invalid('tool_monitoring', 'Foo'),
            ],
            'Metric name starts with digit' => [
                'name' => '1foo',
                'exception' => new metric_name_invalid('tool_monitoring', '1foo'),
            ],
        ];
    }

    /**
     * Tests {@see metrics_manager::offsetExists}, {@see metrics_manager::offsetGet}, and {@see metrics_manager::offsetUnset}.
     *
     * Indirectly also tests the cache data source mechanism because getter first attempts to load a metric from the cache.
     *
     * @throws dml_exception
     */
    public function test_array_access(): void {
        global $DB;
        $this->resetAfterTest();
        $collection = new metric_collection();
        $collection->add(test_metric::create('foo'));
        di::set(metric_collection::class, $collection);
        $manager = di::get(metrics_manager::class);
        $toinsert = [
            'component'    => 'tool_monitoring',
            'name'         => 'foo',
            'enabled'      => false,
            'timecreated'  => 10,
            'timemodified' => 20,
            'usermodified' => 1,
        ];
        $DB->insert_record(metric_record::TABLE, $toinsert);
        $qname = 'tool_monitoring_foo';
        self::assertTrue(isset($manager[$qname]));
        $metric = $manager[$qname];
        self::assertInstanceOf(managed_metric::class, $metric);
        self::assertSame($qname, $metric->qualifiedname);
        // Ensure we cannot unset a metric.
        $this->expectException(coding_exception::class);
        unset($manager[$qname]);
    }

    public function test_array_metric_not_found(): void {
        $manager = di::get(metrics_manager::class);
        self::assertFalse(isset($manager['foo']));
        $this->expectException(metric_not_found::class);
        $manager['foo'];
    }

    public function test_array_set_error(): void {
        $manager = di::get(metrics_manager::class);
        $this->expectException(coding_exception::class);
        $manager['foo'] = test_metric::create('foo');
    }
}

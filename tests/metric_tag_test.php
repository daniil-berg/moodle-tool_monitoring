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
 * Definition of the {@see metric_tag_test} class.
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
use core\exception\moodle_exception;
use core_tag_area;
use dml_exception;
use JsonException;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\local\testing\metric_settable_values;
use tool_monitoring\local\testing\metric_with_custom_config;

/**
 * Unit tests for the {@see metric_tag} class.
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
#[CoversClass(metric_tag::class)]
final class metric_tag_test extends advanced_testcase {
    /**
     * Tests the {@see metric_tag::get_collection_id} method.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_get_collection_id(): void {
        global $DB;
        $expected = (int) $DB->get_field(
            table: 'tag_coll',
            return: 'id',
            conditions: ['name' => metric_tag::COLLECTION_NAME, 'component' => 'tool_monitoring'],
        );
        self::assertSame($expected, metric_tag::get_collection_id());
    }

    /**
     * Tests the {@see metric_tag::get_all_with_names} method.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found
     */
    #[DataProvider('provider_test_get_all_with_names')]
    public function test_get_all_with_names(array $indb, array $names, string|null $exception = null): void {
        $this->resetAfterTest();
        $tagarea = core_tag_area::get_areas()[metric_tag::ITEM_TYPE]['tool_monitoring'];
        $tags = metric_tag::create_if_missing($tagarea->tagcollid, $indb);
        if (!is_null($exception)) {
            $this->expectException($exception);
            metric_tag::get_all_with_names(...$names);
            return;
        }
        $normalizednames = metric_tag::normalize($names);
        $output = metric_tag::get_all_with_names(...$names);
        self::assertCount(count($names), $output);
        foreach ($names as $name) {
            $normalizedname = $normalizednames[$name];
            $expected = (array) $tags[$normalizedname]->to_object();
            self::assertArrayHasKey($normalizedname, $output);
            $tag = $output[$normalizedname];
            self::assertInstanceOf(metric_tag::class, $tag);
            foreach ($expected as $property => $value) {
                self::assertEquals($value, $tag->$property, "Unexpected $property on '$normalizedname' tag");
            }
        }
    }

    /**
     * Provides test data for the {@see test_get_all_with_names} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_all_with_names(): array {
        return [
            'No arguments' => [
                'indb' => [],
                'names' => [],
            ],
            'Get subset of existing tags' => [
                'indb' => ['foo', 'bar', 'baz'],
                'names' => ['foo', 'bar'],
            ],
            'Try to get non-existent tag' => [
                'indb' => ['foo', 'bar', 'baz'],
                'names' => ['foo', 'quux'],
                'exception' => tag_not_found::class,
            ],
        ];
    }

    /**
     * Tests {@see metric_tag::get_for_metric_ids}, {@see metric_tag::set_for_metric}, and {@see metric_tag::remove_all_for_metric}.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    public function test_get_set_remove_tag_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $metricdefaults = [
            'component'    => 'tool_monitoring',
            'enabled'      => false,
            'timecreated'  => 1,
            'timemodified' => 1,
            'usermodified' => 1,
        ];
        $records = [
            ['name' => 'metric_settable_values', ...$metricdefaults],
            ['name' => 'metric_with_custom_config', ...$metricdefaults],
        ];
        $DB->insert_records(registered_metric::TABLE, $records);
        [
            'tool_monitoring_metric_settable_values' => $metric1,
            'tool_monitoring_metric_with_custom_config' => $metric2,
        ] = registered_metric::get_for_metrics(new metric_settable_values(), new metric_with_custom_config());
        // Assign tag instances.
        metric_tag::set_for_metric($metric1->id, 'foo', 'bar');
        metric_tag::set_for_metric($metric2, 'bar', 'baz');
        // Retrieve tags with item IDs set.
        $metricstags = metric_tag::get_for_metric_ids($metric1->id, $metric2->id);
        self::assertCount(2, $metricstags);
        [$metric1->id => $metrictags1, $metric2->id => $metrictags2] = $metricstags;
        self::assertCount(2, $metrictags1);
        foreach (['foo', 'bar'] as $tagname) {
            self::assertArrayHasKey($tagname, $metrictags1);
            $tag = $metrictags1[$tagname];
            self::assertInstanceOf(metric_tag::class, $tag);
            self::assertEquals($metric1->id, $tag->itemid);
        }
        self::assertCount(2, $metrictags2);
        foreach (['bar', 'baz'] as $tagname) {
            self::assertArrayHasKey($tagname, $metrictags2);
            $tag = $metrictags2[$tagname];
            self::assertInstanceOf(metric_tag::class, $tag);
            self::assertEquals($metric2->id, $tag->itemid);
        }
        // Remove all tags for one metric.
        metric_tag::remove_all_for_metric($metric1);
        $metricstags = metric_tag::get_for_metric_ids($metric1->id, $metric2->id);
        self::assertCount(2, $metricstags);
        [$metric1->id => $metrictags1, $metric2->id => $metrictags2check] = $metricstags;
        // The first metric should now have no tags.
        self::assertCount(0, $metrictags1);
        // The second metric should still have the same tags.
        self::assertCount(2, $metrictags2check);
        foreach ($metrictags2check as $tagname => $tag) {
            self::assertEquals($metrictags2[$tagname], $tag);
        }
        // Remove all tags for the other metric.
        metric_tag::remove_all_for_metric($metric2->id);
        // The returned arrays should now all be empty.
        $metricstags = metric_tag::get_for_metric_ids($metric1->id, $metric2->id);
        self::assertSame([$metric1->id => [], $metric2->id => []], $metricstags);
    }

    /**
     * Tests {@see metric_tag::get_edit_url} and {@see metric_tag::get_manage_url}.
     *
     * @throws moodle_exception
     */
    public function test_urls(): void {
        $this->resetAfterTest();
        $tagarea = core_tag_area::get_areas()[metric_tag::ITEM_TYPE]['tool_monitoring'];
        ['foo' => $tag] = metric_tag::create_if_missing($tagarea->tagcollid, ['foo']);
        self::assertInstanceOf(metric_tag::class, $tag);
        self::assertEquals(
            new moodle_url('/tag/edit.php', ['id' => $tag->id]),
            $tag->get_edit_url(),
        );
        self::assertEquals(
            new moodle_url('/tag/manage.php', ['tc' => $tagarea->tagcollid]),
            metric_tag::get_manage_url(),
        );
    }

    /**
     * Tests the {@see metric_tag::wake_from_cache} method.
     *
     * @throws coding_exception
     */
    #[DataProvider('provider_test_wake_from_cache')]
    public function test_wake_from_cache(mixed $data, array|string $expected): void {
        if (is_string($expected)) {
            $this->expectException($expected);
            metric_tag::wake_from_cache($data);
            return;
        }
        $instance = metric_tag::wake_from_cache($data);
        foreach ($expected as $name => $value) {
            self::assertEquals($value, $instance->$name, "Unexpected $name on tag instance");
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
                    'id'                   => 1,
                    'userid'               => 1,
                    'name'                 => 'foo',
                    'rawname'              => 'Foo',
                    'isstandard'           => 0,
                    'description'          => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                    'descriptionformat'    => 0,
                    'flag'                 => 0,
                    'timemodified'         => 1,
                    'taginstanceid'        => 1,
                    'taginstancecontextid' => 1,
                ],
                'expected' => [
                    'id'                   => 1,
                    'userid'               => 1,
                    'name'                 => 'foo',
                    'rawname'              => 'Foo',
                    'isstandard'           => 0,
                    'description'          => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                    'descriptionformat'    => 0,
                    'flag'                 => 0,
                    'timemodified'         => 1,
                    'taginstanceid'        => 1,
                    'taginstancecontextid' => 1,
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
                    'userid'               => 1,
                    'name'                 => 'foo',
                    'rawname'              => 'Foo',
                    'isstandard'           => 0,
                    'description'          => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                    'descriptionformat'    => 0,
                    'flag'                 => 0,
                    'timemodified'         => 1,
                    'taginstanceid'        => 1,
                    'taginstancecontextid' => 1,
                ],
                'expected' => coding_exception::class,
            ],
        ];
    }
}

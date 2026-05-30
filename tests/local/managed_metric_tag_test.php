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
 * Definition of the {@see managed_metric_tag_test} class.
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

namespace local;

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
use tool_monitoring\local\managed_metric;
use tool_monitoring\local\managed_metric_tag;
use tool_monitoring\local\metric_record;
use tool_monitoring\local\testing\test_metric;
use tool_monitoring\local\testing\test_metric_with_config;

/**
 * Unit tests for the {@see managed_metric_tag} class.
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
#[CoversClass(managed_metric_tag::class)]
final class managed_metric_tag_test extends advanced_testcase {
    /**
     * Tests the {@see managed_metric_tag::get_collection_id} method.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_get_collection_id(): void {
        global $DB;
        $expected = (int) $DB->get_field(
            table: 'tag_coll',
            return: 'id',
            conditions: ['name' => managed_metric_tag::COLLECTION_NAME, 'component' => 'tool_monitoring'],
        );
        self::assertSame($expected, managed_metric_tag::get_collection_id());
    }

    /**
     * Tests the {@see managed_metric_tag::get_all_with_names} method.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found
     */
    #[DataProvider('provider_test_get_all_with_names')]
    public function test_get_all_with_names(array $indb, array $names, string|null $exception = null): void {
        $this->resetAfterTest();
        $tagarea = core_tag_area::get_areas()[managed_metric_tag::ITEM_TYPE]['tool_monitoring'];
        $tags = managed_metric_tag::create_if_missing($tagarea->tagcollid, $indb);
        if (!is_null($exception)) {
            $this->expectException($exception);
            managed_metric_tag::get_all_with_names(...$names);
            return;
        }
        $normalizednames = managed_metric_tag::normalize($names);
        $output = managed_metric_tag::get_all_with_names(...$names);
        self::assertCount(count($names), $output);
        foreach ($names as $name) {
            $normalizedname = $normalizednames[$name];
            $expected = (array) $tags[$normalizedname]->to_object();
            self::assertArrayHasKey($normalizedname, $output);
            $tag = $output[$normalizedname];
            self::assertInstanceOf(managed_metric_tag::class, $tag);
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
     * Tests cache use the {@see managed_metric_tag::get_all_with_names} method.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found
     */
    public function test_get_all_with_names_cached(): void {
        $this->resetAfterTest();
        $tagarea = core_tag_area::get_areas()[managed_metric_tag::ITEM_TYPE]['tool_monitoring'];
        managed_metric_tag::create_if_missing($tagarea->tagcollid, ['foo', 'bar', 'baz']);
        $exception = null;
        try {
            // Populate cache including a `null` for a non-existent tag.
            managed_metric_tag::get_all_with_names('quux');
        } catch (tag_not_found $e) {
            $exception = $e;
        }
        self::assertNotNull($exception); // Sanity check.
        $this->resetAfterTest();
        $tags = managed_metric_tag::get_all_with_names('foo', 'bar', 'baz');
        self::assertSame(['foo', 'bar', 'baz'], array_keys($tags));
        $this->expectExceptionObject(new tag_not_found('quux', managed_metric_tag::COLLECTION_NAME));
        managed_metric_tag::get_all_with_names('foo', 'quux');
    }

    /**
     * Tests {@see managed_metric_tag::get_for_metric_ids}, {@see managed_metric_tag::set_for_metric}, and {@see managed_metric_tag::remove_all_for_metric}.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    public function test_get_set_remove_tag_instance(): void {
        global $DB;
        $this->resetAfterTest();
        // Create two metric records.
        $rawmetric1 = new test_metric();
        $rawmetric2 = new test_metric_with_config();
        metric_record::insert_many(...array_map(metric_record::from_metric(...), [$rawmetric1, $rawmetric2]));
        $metricrecord1 = metric_record::from_data($DB->get_record(metric_record::TABLE, ['name' => 'test_metric']));
        $metricrecord2 = metric_record::from_data($DB->get_record(metric_record::TABLE, ['name' => 'test_metric_with_config']));
        $metric1 = new managed_metric($rawmetric1, $metricrecord1);
        $metric2 = new managed_metric($rawmetric2, $metricrecord2);
        // Assign tag instances.
        managed_metric_tag::set_for_metric($metric1->id, 'foo', 'bar');
        managed_metric_tag::set_for_metric($metric2, 'bar', 'baz');
        // Retrieve tags with item IDs set.
        $metricstags = managed_metric_tag::get_for_metric_ids($metric1->id, $metric2->id);
        self::assertCount(2, $metricstags);
        [$metric1->id => $metrictags1, $metric2->id => $metrictags2] = $metricstags;
        self::assertCount(2, $metrictags1);
        foreach (['foo', 'bar'] as $tagname) {
            self::assertArrayHasKey($tagname, $metrictags1);
            $tag = $metrictags1[$tagname];
            self::assertInstanceOf(managed_metric_tag::class, $tag);
            self::assertEquals($metric1->id, $tag->itemid);
        }
        self::assertCount(2, $metrictags2);
        foreach (['bar', 'baz'] as $tagname) {
            self::assertArrayHasKey($tagname, $metrictags2);
            $tag = $metrictags2[$tagname];
            self::assertInstanceOf(managed_metric_tag::class, $tag);
            self::assertEquals($metric2->id, $tag->itemid);
        }
        // Remove all tags for one metric.
        managed_metric_tag::remove_all_for_metric($metric1);
        $metricstags = managed_metric_tag::get_for_metric_ids($metric1->id, $metric2->id);
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
        managed_metric_tag::remove_all_for_metric($metric2->id);
        // The returned arrays should now all be empty.
        $metricstags = managed_metric_tag::get_for_metric_ids($metric1->id, $metric2->id);
        self::assertSame([$metric1->id => [], $metric2->id => []], $metricstags);
    }

    /**
     * Tests {@see managed_metric_tag::get_edit_url} and {@see managed_metric_tag::get_manage_url}.
     *
     * @throws moodle_exception
     */
    public function test_urls(): void {
        $this->resetAfterTest();
        $tagarea = core_tag_area::get_areas()[managed_metric_tag::ITEM_TYPE]['tool_monitoring'];
        ['foo' => $tag] = managed_metric_tag::create_if_missing($tagarea->tagcollid, ['foo']);
        self::assertInstanceOf(managed_metric_tag::class, $tag);
        self::assertEquals(
            new moodle_url('/tag/edit.php', ['id' => $tag->id]),
            $tag->get_edit_url(),
        );
        self::assertEquals(
            new moodle_url('/tag/manage.php', ['tc' => $tagarea->tagcollid]),
            managed_metric_tag::get_manage_url(),
        );
    }

    /**
     * Tests the {@see managed_metric_tag::wake_from_cache} method.
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
            managed_metric_tag::wake_from_cache($data);
            return;
        }
        $instance = managed_metric_tag::wake_from_cache($data);
        foreach ($expected as $name => $value) {
            self::assertEquals($value, $instance->$name, "Unexpected $name on tag instance");
        }
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
            'Unexpected fields' => [
                'data' => (object) [
                    'unexpected'           => 'stuff',
                    'even_more'            => 'stuff',
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
                'debugging' => "Unexpected cache fields for metric tag 1: unexpected, even_more",
            ],
        ];
    }
}

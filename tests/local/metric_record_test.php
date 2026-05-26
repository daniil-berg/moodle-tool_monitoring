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
 * Definition of the {@see metric_record_test} class.
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
use core\exception\coding_exception;
use dml_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use tool_monitoring\local\testing\test_metric;
use tool_monitoring\registered_metric;

/**
 * Unit tests for the {@see metric_record} class.
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
#[CoversClass(metric_record::class)]
final class metric_record_test extends advanced_testcase {
    /**
     * Tests the {@see metric_record::from_data} method.
     *
     * @param array<string, mixed>|stdClass $data Passed to the method.
     * @param array<string, mixed> $expected Expected properties on the returned instance.
     */
    #[DataProvider('provider_test_from_data')]
    public function test_from_data(array|stdClass $data, array $expected): void {
        $record = metric_record::from_data($data);
        foreach ($expected as $field => $value) {
            self::assertSame($value, $record->$field);
        }
    }

    /**
     * Provides test data for the {@see test_from_data} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_from_data(): array {
        $alldata = [
            'id' => 42,
            'component' => 'test_component',
            'name' => 'test_name',
            'enabled' => true,
            'config' => '{"foo": "bar"}',
            'timecreated' => 123,
            'timemodified' => 456,
            'usermodified' => 789,
        ];
        return [
            'Array with all fields plus garbage' => [
                'data' => [
                    ...$alldata,
                    'irrelevant' => 'stuff',
                    'will be' => 'ignored',
                ],
                'expected' => $alldata,
            ],
            'Object with all fields plus garbage' => [
                'data' => (object) [
                    ...$alldata,
                    'irrelevant' => 'stuff',
                    'will be' => 'ignored',
                ],
                'expected' => $alldata,
            ],
            'Array with only required fields' => [
                'data' => [
                    'component' => 'test_component',
                    'name' => 'test_name',
                ],
                'expected' => [
                    'component' => 'test_component',
                    'name' => 'test_name',
                    'enabled' => false,
                    'config' => null,
                    'timecreated' => null,
                    'timemodified' => null,
                    'usermodified' => null,
                    'id' => null,
                ],
            ],
        ];
    }

    public function test_from_metric(): void {
        $metric = new test_metric();
        $record = metric_record::from_metric($metric);
        self::assertSame($metric->get_component(), $record->component);
        self::assertSame($metric->get_name(), $record->name);
        self::assertFalse($record->enabled);
        self::assertNull($record->config);
        self::assertNull($record->timecreated);
        self::assertNull($record->timemodified);
        self::assertNull($record->usermodified);
        self::assertNull($record->id);
    }

    /**
     * Tests the {@see metric_record::to_array} method.
     *
     * @param metric_record $record Test instance.
     * @param string[] $fields Passed to the method.
     * @param array<string, mixed> $expected Expected return value.
     */
    #[DataProvider('provider_test_to_array')]
    public function test_to_array(metric_record $record, array $fields, array $expected): void {
        $output = $record->to_array($fields);
        self::assertSame($expected, $output);
    }

    /**
     * Provides test data for the {@see test_to_array} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_to_array(): array {
        $record = new metric_record(
            component: 'test_component',
            name: 'test_name',
            enabled: true,
            config: '{"foo": "bar"}',
            timecreated: 123,
            timemodified: 456,
            usermodified: 789,
            id: 42,
        );
        $alldata = [
            'component'    => 'test_component',
            'name'         => 'test_name',
            'enabled'      => true,
            'config'       => '{"foo": "bar"}',
            'timecreated'  => 123,
            'timemodified' => 456,
            'usermodified' => 789,
            'id'           => 42,
        ];
        return [
            'Default/all fields' => [
                'record' => $record,
                'fields' => metric_record::FIELDS,
                'expected' => $alldata,
            ],
            'Only some fields' => [
                'record' => $record,
                'fields' => ['component', 'name', 'usermodified'],
                'expected' => [
                    'component'    => 'test_component',
                    'name'         => 'test_name',
                    'usermodified' => 789,
                ],
            ],
            'Some fields, some garbage' => [
                'record' => $record,
                'fields' => ['component', 'name', 'garbage', 'gets', 'ignored'],
                'expected' => [
                    'component' => 'test_component',
                    'name'      => 'test_name',
                ],
            ],
        ];
    }

    /**
     * Tests the {@see metric_record::insert_many} method.
     *
     * @param array<string, metric_record> $instances Instances to insert indexed by qualified name.
     * @throws coding_exception
     * @throws dml_exception
     */
    #[DataProvider('provider_test_insert_many')]
    public function test_insert_many(array $instances): void {
        global $DB;
        $this->resetAfterTest();
        metric_record::insert_many(...$instances);
        $qnamesql = registered_metric::get_qualified_name_sql($DB);
        $fields = implode(',', metric_record::FIELDS);
        $rows = $DB->get_records(metric_record::TABLE, fields: "$qnamesql, $fields");
        self::assertCount(count($instances), $rows);
        if (empty($instances)) {
            return;
        }
        foreach ($rows as $qname => $row) {
            foreach (metric_record::FIELDS as $field) {
                if ($field === 'id') {
                    continue;
                }
                self::assertEquals($instances[$qname]->$field, $row->$field, "Unexpected $field on $qname record");
            }
        }
    }

    /**
     * Provides test data for the {@see test_insert_many} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_insert_many(): array {
        return [
            'No arguments' => [
                'instances' => [],
            ],
            'One instance' => [
                'instances' => [
                    'test_component_foo' => new metric_record(
                        component: 'test_component',
                        name: 'foo',
                        enabled: true,
                        config: '{"foo": "bar"}',
                        timecreated: 123,
                        timemodified: 456,
                        usermodified: 789,
                    ),
                ],
            ],
            'Three instances' => [
                'instances' => [
                    'test_component_foo' => new metric_record(
                        component: 'test_component',
                        name: 'foo',
                        enabled: true,
                        config: '{"foo": "bar"}',
                        timecreated: 123,
                        timemodified: 456,
                        usermodified: 789,
                    ),
                    'test_component_bar' => new metric_record(
                        component: 'test_component',
                        name: 'bar',
                        enabled: false,
                        config: null,
                        timecreated: 123,
                        timemodified: 456,
                        usermodified: 789,
                    ),
                    'test_component_baz' => new metric_record(
                        component: 'test_component',
                        name: 'baz',
                        enabled: true,
                        config: null,
                        timecreated: 123,
                        timemodified: 456,
                        usermodified: 789,
                    ),
                ],
            ],
        ];
    }

    /**
     * Tests the {@see metric_record::update} method.
     *
     * @param metric_record $record Test instance.
     * @param array<string, mixed> $changes Properties to mutate before calling the method.
     * @param string[] $fields First argument to the method.
     * @param int|null $usermodified Second argument to the method.
     * @param array<string, mixed> $expected Expected DB row values (excluding auto-stamped `timemodified`/`usermodified`).
     * @throws dml_exception
     */
    #[DataProvider('provider_test_update')]
    public function test_update(
        metric_record $record,
        array $changes,
        array $fields,
        int|null $usermodified,
        array $expected,
    ): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $record->id = $DB->insert_record(metric_record::TABLE, $record->to_array());
        foreach ($changes as $field => $change) {
            $record->$field = $change;
        }
        $recordbefore = clone $record;
        $before = time();
        $record->update($fields, $usermodified);
        $after = time();
        // Check `timemodified` is set to current time and `usermodified` to the provided user or `$USER->id`.
        self::assertGreaterThanOrEqual($before, $record->timemodified);
        self::assertLessThanOrEqual($after, $record->timemodified);
        self::assertSame($usermodified ?? $USER->id, $record->usermodified);
        // No other properties should have been touched.
        foreach (metric_record::FIELDS as $field) {
            if (in_array($field, ['timemodified', 'usermodified'], strict: true)) {
                continue;
            }
            self::assertSame($recordbefore->$field, $record->$field, "Unexpected change on record field $field");
        }
        // Check DB row is as expected.
        $row = $DB->get_record(metric_record::TABLE, ['id' => $record->id], strictness: MUST_EXIST);
        self::assertEquals($record->timemodified, $row->timemodified);
        self::assertEquals($record->usermodified, $row->usermodified);
        foreach ($expected as $field => $value) {
            self::assertEquals($value, $row->$field, "Unexpected $field value on row");
        }
    }

    /**
     * Provides test data for the {@see test_update} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_update(): array {
        $testdata = [
            'component'    => 'test_component',
            'name'         => 'test_name',
            'enabled'      => true,
            'config'       => '{"foo": "bar"}',
            'timecreated'  => 123,
        ];
        $testrecord = metric_record::from_data($testdata + ['timemodified' => 456, 'usermodified' => 789]);
        return [
            'No changes' => [
                'record' => clone $testrecord,
                'changes' => [],
                'fields' => metric_record::FIELDS,
                'usermodified' => null,
                'expected' => $testdata,
            ],
            'Fields changed, update full record' => [
                'record' => clone $testrecord,
                'changes' => ['enabled' => false, 'config' => '{"foo": "baz"}'],
                'fields' => metric_record::FIELDS,
                'usermodified' => null,
                'expected' => [...$testdata, 'enabled' => false, 'config' => '{"foo": "baz"}'],
            ],
            'Fields changed, update only some of them' => [
                'record' => clone $testrecord,
                'changes' => ['enabled' => false, 'config' => '{"foo": "baz"}'],
                'fields' => ['enabled'],
                'usermodified' => null,
                'expected' => [...$testdata, 'enabled' => false],
            ],
            'Override usermodified' => [
                'record' => clone $testrecord,
                'changes' => [],
                'fields' => metric_record::FIELDS,
                'usermodified' => 42,
                'expected' => $testdata,
            ],
        ];
    }
}

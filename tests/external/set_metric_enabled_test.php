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
 * Definition of the {@see set_metric_enabled_test} class.
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

namespace tool_monitoring\external;

use advanced_testcase;
use context_system;
use core\di;
use core\exception\coding_exception;
use core\exception\invalid_parameter_exception;
use core\exception\required_capability_exception;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\restricted_context_exception;
use dml_exception;
use Exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\testing\metric_settable_values;
use tool_monitoring\metric;
use tool_monitoring\registered_metric;

/**
 * Unit tests for the {@see set_metric_enabled} class.
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
#[CoversClass(set_metric_enabled::class)]
final class set_metric_enabled_test extends advanced_testcase {
    public function test_execute_parameters(): void {
        $expected = new external_function_parameters([
            'metric' => new external_value(PARAM_ALPHAEXT, 'Qualified metric name'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether to enable the metric'),
        ]);
        self::assertEquals($expected, set_metric_enabled::execute_parameters());
    }

    /**
     * Tests the {@see set_metric_enabled::execute} method.
     *
     * @param metric[] $metricscollected Metrics to add to the collection beforehand.
     * @param array $metricsregistered Metrics to insert into the database beforehand.
     * @param string $qualifiedname Qualified name of the metric to enable/disable.
     * @param bool $enabled Whether to enable or disable the metric.
     * @param class-string<Exception>|null $exception Expected exception class or `null` if no exception is expected.
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws JsonException
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    #[DataProvider('provider_test_execute')]
    public function test_execute(
        array $metricscollected,
        array $metricsregistered,
        string $qualifiedname,
        bool $enabled,
        string|null $exception = null,
    ): void {
        global $DB;
        $this->resetAfterTest();
        // Set up metric collection for the test.
        $collection = new metric_collection();
        foreach ($metricscollected as $metric) {
            $collection->add($metric);
        }
        di::set(metric_collection::class, $collection);
        // Sanity check.
        self::assertEquals(0, $DB->count_records(registered_metric::TABLE));
        // Insert registered metrics into DB.
        $DB->insert_records(registered_metric::TABLE, $metricsregistered);
        // Create manager user and set it for the test.
        $user = self::getDataGenerator()->create_user();
        self::getDataGenerator()->role_assign('manager', $user->id, context_system::instance());
        self::setUser($user);
        // Get the record of the metric to try and enable/disable.
        $qnamesql = registered_metric::get_qualified_name_sql($DB);
        $record = $DB->get_record_select(
            table: registered_metric::TABLE,
            select: "$qnamesql = :qname",
            params: ['qname' => $qualifiedname],
        );
        // Do the thing.
        if (is_null($exception)) {
            $output = set_metric_enabled::execute($qualifiedname, $enabled);
            self::assertSame([], $output);
            // Check that the metric state now matches what was requested.
            self::assertEquals(
                $enabled,
                $DB->get_field_select(
                    table: registered_metric::TABLE,
                    return: 'enabled',
                    select: "$qnamesql = :qname",
                    params: ['qname' => $qualifiedname],
                ),
            );
        } else {
            try {
                set_metric_enabled::execute($qualifiedname, $enabled);
                self::fail("Expected exception of class $exception was not thrown");
            } catch (Exception $e) {
                self::assertInstanceOf($exception, $e);
                // Check that the metric state did not change. If there was no record to begin with, this should work too.
                self::assertEquals(
                    $record,
                    $DB->get_record_select(
                        table: registered_metric::TABLE,
                        select: "$qnamesql = :qname",
                        params: ['qname' => $qualifiedname],
                    ),
                );
            }
        }
    }

    /**
     * Provides test data for the {@see test_execute} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_execute(): array {
        $defaults = [
            'component' => 'tool_monitoring',
            'timecreated' => 1,
            'timemodified' => 1,
            'usermodified' => 1,
        ];
        return [
            'Trying to disable a metric that is registered but not collected' => [
                'metricscollected' => [],
                'metricsregistered' => [
                    ['name' => 'foo', 'enabled' => true] + $defaults,
                ],
                'qualifiedname' => 'tool_monitoring_foo',
                'enabled' => false,
                'exception' => invalid_parameter_exception::class,
            ],
            'Enabling a metric that is collected but not (yet) registered' => [
                'metricscollected' => [
                    new metric_settable_values(),
                ],
                'metricsregistered' => [
                    ['name' => 'foo', 'enabled' => true] + $defaults,
                    ['name' => 'bar', 'enabled' => true] + $defaults,
                ],
                'qualifiedname' => 'tool_monitoring_metric_settable_values',
                'enabled' => true,
            ],
            'Disabling a metric that is both collected and registered' => [
                'metricscollected' => [
                    new metric_settable_values(),
                ],
                'metricsregistered' => [
                    ['name' => 'metric_settable_values', 'enabled' => true] + $defaults,
                    ['name' => 'foo', 'enabled' => true] + $defaults,
                    ['name' => 'bar', 'enabled' => true] + $defaults,
                ],
                'qualifiedname' => 'tool_monitoring_metric_settable_values',
                'enabled' => false,
            ],
        ];
    }

    /**
     * Tests the {@see set_metric_enabled::execute} method when called as a guest.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws JsonException
     * @throws restricted_context_exception
     */
    public function test_execute_as_guest(): void {
        $this->resetAfterTest();
        self::setGuestUser();
        $this->expectException(required_capability_exception::class);
        set_metric_enabled::execute('foo', true);
    }

    /**
     * Tests the {@see set_metric_enabled::execute} method when the user does not have the required capability.
     *
     * @throws restricted_context_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws JsonException
     */
    public function test_execute_without_permission(): void {
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->expectException(required_capability_exception::class);
        set_metric_enabled::execute('foo', true);
    }

    public function test_execute_returns(): void {
        $expected = new external_single_structure([]);
        self::assertEquals($expected, set_metric_enabled::execute_returns());
    }
}

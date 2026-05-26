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
 * Definition of the {@see metrics_cache_test} class.
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
use PHPUnit\Framework\Attributes\CoversClass;
use tool_monitoring\local\metrics\quiz_attempts_in_progress;
use tool_monitoring\local\metrics\user_accounts;
use tool_monitoring\registered_metric;

/**
 * Unit tests for the {@see metrics_cache} class.
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
#[CoversClass(metrics_cache::class)]
final class metrics_cache_test extends advanced_testcase {
    /**
     * Tests that {@see metrics_cache::set} with positional args uses the metric's qualified name as the cache key.
     *
     * @throws coding_exception
     */
    public function test_set_uses_qualified_name_for_positional_args(): void {
        $this->resetAfterTest();
        $metric = registered_metric::from_metric(new user_accounts());
        metrics_cache::set($metric);
        $result = metrics_cache::get($metric->qualifiedname);
        self::assertInstanceOf(registered_metric::class, $result);
        self::assertSame($metric->qualifiedname, $result->qualifiedname);
    }

    /**
     * Tests that {@see metrics_cache::set} with named args uses the arg name rather than the metric's qualified name as cache key.
     *
     * @throws coding_exception
     */
    public function test_set_uses_provided_key_for_named_args(): void {
        $this->resetAfterTest();
        $metric = registered_metric::from_metric(new user_accounts());
        metrics_cache::set(custom_cache_key: $metric);
        self::assertInstanceOf(registered_metric::class, metrics_cache::get('custom_cache_key'));
        // No metric cached under the actual qualified name.
        self::assertNull(metrics_cache::get($metric->qualifiedname));
    }

    /**
     * Tests that {@see metrics_cache::get} works and returns `null` on a cache miss.
     *
     * A miss triggers the data source, which returns `null` for any key not matching a collected metric.
     *
     * @throws coding_exception
     */
    public function test_get(): void {
        $this->resetAfterTest();
        $metric = registered_metric::from_metric(new user_accounts());
        metrics_cache::set($metric);
        self::assertEquals($metric, metrics_cache::get($metric->qualifiedname));
        self::assertNull(metrics_cache::get('not_a_collected_metric'));
    }

    /**
     * Tests that {@see metrics_cache::get_many} returns cached instances for hits and `null` for misses.
     *
     * @throws coding_exception
     */
    public function test_get_many(): void {
        $this->resetAfterTest();
        $metric1 = registered_metric::from_metric(new user_accounts());
        $metric2 = registered_metric::from_metric(new quiz_attempts_in_progress());
        metrics_cache::set($metric1, $metric2);
        $results = metrics_cache::get_many(
            $metric1->qualifiedname,
            $metric2->qualifiedname,
            'not_a_collected_metric',
        );
        self::assertCount(3, $results);
        self::assertEquals($metric1, $results[$metric1->qualifiedname]);
        self::assertEquals($metric2, $results[$metric2->qualifiedname]);
        self::assertNull($results['not_a_collected_metric']);
    }

    /**
     * Tests the {@see metrics_cache::delete} method.
     *
     * @throws coding_exception
     */
    public function test_delete(): void {
        $this->resetAfterTest();
        $metric1 = registered_metric::from_metric(new user_accounts());
        $metric2 = registered_metric::from_metric(new quiz_attempts_in_progress());
        metrics_cache::set($metric1, $metric2);
        // Sanity check.
        self::assertEquals($metric1, metrics_cache::get($metric1->qualifiedname));
        self::assertEquals($metric2, metrics_cache::get($metric2->qualifiedname));
        // Delete.
        metrics_cache::delete($metric1->qualifiedname, $metric2->qualifiedname);
        self::assertNull(metrics_cache::get($metric1->qualifiedname));
        self::assertNull(metrics_cache::get($metric2->qualifiedname));
    }

    /**
     * Tests that {@see metrics_cache::purge} clears all entries and returns true.
     *
     * @throws coding_exception
     */
    public function test_purge(): void {
        $this->resetAfterTest();
        $metric1 = registered_metric::from_metric(new user_accounts());
        $metric2 = registered_metric::from_metric(new quiz_attempts_in_progress());
        metrics_cache::set($metric1, $metric2);
        // Sanity check.
        self::assertEquals($metric1, metrics_cache::get($metric1->qualifiedname));
        self::assertEquals($metric2, metrics_cache::get($metric2->qualifiedname));
        // Purge.
        $success = metrics_cache::purge();
        self::assertTrue($success);
        self::assertNull(metrics_cache::get($metric1->qualifiedname));
        self::assertNull(metrics_cache::get($metric2->qualifiedname));
    }
}

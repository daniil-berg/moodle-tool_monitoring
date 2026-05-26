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
 * Definition of the {@see observer_test} class.
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

namespace tool_monitoring\event;

use advanced_testcase;
use context_system;
use core\event\tag_added;
use core\event\tag_created;
use core\event\tag_deleted;
use core\event\tag_removed;
use core\event\tag_updated;
use core\exception\coding_exception;
use core_cache\cache;
use dml_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\metric_record;
use tool_monitoring\metric_tag;

/**
 * Unit tests for the {@see observer} class.
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
#[CoversClass(observer::class)]
final class observer_test extends advanced_testcase {
    /**
     * Tests that {@see observer::tag_instance_added_or_removed} is no-op when the tagged item is not a metric.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_tag_instance_added_or_removed_with_non_metric(): void {
        global $DB;
        $this->resetAfterTest();
        // Insert actual record so the early-return can actually prevent something.
        $metricid = $DB->insert_record(metric_record::TABLE, [
            'component'    => 'tool_monitoring',
            'name'         => 'test_metric',
            'enabled'      => false,
            'timecreated'  => 1,
            'timemodified' => 1,
            'usermodified' => 1,
        ]);
        $qname = metric_record::get_qualified_name('tool_monitoring', 'test_metric');
        // Insert an arbitrary sentinel value into the cache in place of the actual metric for simplicity.
        $cache = cache::make('tool_monitoring', 'metrics');
        $cache->set($qname, 'sentinel');
        // Tag a course, not a metric, but use the same ID.
        $event = tag_added::create([
            'objectid'  => 1,
            'contextid' => context_system::instance()->id,
            'other'     => [
                'tagid'      => 1,
                'tagname'    => 'foo',
                'tagrawname' => 'Foo',
                'itemid'     => $metricid,
                'itemtype'   => 'course',
            ],
        ]);
        // This should return early.
        observer::tag_instance_added_or_removed($event);
        // The cache should still be untouched.
        self::assertSame('sentinel', $cache->get($qname));
    }

    /**
     * Tests that {@see observer::tag_instance_added_or_removed} clears the metric's cache entry.
     *
     * @param class-string<tag_added|tag_removed> $eventclass Event class to test.
     * @throws coding_exception
     * @throws dml_exception
     */
    #[DataProvider('provider_test_tag_instance_added_or_removed')]
    public function test_tag_instance_added_or_removed(string $eventclass): void {
        global $DB;
        $this->resetAfterTest();
        // Insert an actual metric record.
        $metricid = $DB->insert_record(metric_record::TABLE, [
            'component'    => 'tool_monitoring',
            'name'         => 'test_metric',
            'enabled'      => false,
            'timecreated'  => 1,
            'timemodified' => 1,
            'usermodified' => 1,
        ]);
        $qname = metric_record::get_qualified_name('tool_monitoring', 'test_metric');
        // Insert an arbitrary sentinel value into the cache in place of the actual metric for simplicity.
        $cache = cache::make('tool_monitoring', 'metrics');
        $cache->set($qname, 'sentinel');
        self::assertSame('sentinel', $cache->get($qname)); // Sanity check.
        // Tag the metric we just registered.
        $event = $eventclass::create([
            'objectid'  => 1,
            'contextid' => context_system::instance()->id,
            'other'     => [
                'tagid'      => 1,
                'tagname'    => 'foo',
                'tagrawname' => 'Foo',
                'itemid'     => $metricid,
                'itemtype'   => metric_tag::ITEM_TYPE,
            ],
        ]);
        // This should clear the sentinel cache entry.
        observer::tag_instance_added_or_removed($event);
        // Although we implemented a data source for our metrics cache, it relies on the hook actually having collected metrics.
        // It did not collect our `test_metric` here, so the cache will be populated with a `null` entry on miss.
        self::assertNull($cache->get($qname));
    }

    /**
     * Provides test data for the {@see test_tag_instance_added_or_removed} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_tag_instance_added_or_removed(): array {
        return [
            'tag_added'   => [tag_added::class],
            'tag_removed' => [tag_removed::class],
        ];
    }

    /**
     * Tests {@see observer::tag_created_or_deleted_or_updated} clears the tag cache entry, and purges the metrics cache on update.
     *
     * @param class-string<tag_created|tag_deleted|tag_updated> $eventclass Event class to test.
     * @param bool $expectpurge Whether we expect the metrics cache to be purged.
     * @throws coding_exception
     * @throws dml_exception
     */
    #[DataProvider('provider_test_tag_created_or_deleted_or_updated')]
    public function test_tag_created_or_deleted_or_updated(string $eventclass, bool $expectpurge): void {
        $this->resetAfterTest();
        $tagcache = cache::make('tool_monitoring', 'metric_tags');
        $metricscache = cache::make('tool_monitoring', 'metrics');
        // Set sentinel values again for simplicity.
        $tagcache->set('foo', 'sentinel');
        $metricscache->set('bar', 'sentinel');
        // Construct the event.
        $event = $eventclass::create([
            'objectid' => 1,
            'context'  => context_system::instance(),
            'other'    => [
                'name'    => 'foo',
                'rawname' => 'Foo',
            ],
        ]);
        // Trigger the observer callback.
        observer::tag_created_or_deleted_or_updated($event);
        self::assertFalse($tagcache->get('foo'), 'Tag cache entry should be cleared');
        if ($expectpurge) {
            self::assertNull($metricscache->get('bar'), 'Metrics cache should be purged on rename');
        } else {
            self::assertSame('sentinel', $metricscache->get('bar'), 'Metrics cache should be untouched');
        }
    }

    /**
     * Provides test data for the {@see test_tag_created_or_deleted_or_updated} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_tag_created_or_deleted_or_updated(): array {
        return [
            'tag_created does not purge metrics cache' => [tag_created::class, false],
            'tag_deleted does not purge metrics cache' => [tag_deleted::class, false],
            'tag_updated purges metrics cache'   => [tag_updated::class, true],
        ];
    }
}

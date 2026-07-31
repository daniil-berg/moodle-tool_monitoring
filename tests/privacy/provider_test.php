<?php
// This file is part of the tool_monitoring plugin for Moodle - https://moodle.org/
//
// tool_monitoring is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// tool_monitoring is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with tool_monitoring.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Definition of the {@see provider_test} class.
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

namespace tool_monitoring\privacy;

use advanced_testcase;
use core\context\system as context_system;
use core\context\user as context_user;
use core\di;
use core\exception\coding_exception;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\request\content_writer;
use dml_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\metric_record;
use tool_monitoring\local\metrics_cache;
use tool_monitoring\local\testing\test_metric;

/**
 * Unit tests for the {@see provider} class.
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
#[CoversClass(provider::class)]
final class provider_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        writer::reset();
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('tool_monitoring'));

        $items = $collection->get_collection();
        self::assertCount(1, $items);
        $table = reset($items);
        self::assertInstanceOf(database_table::class, $table);
        self::assertSame(metric_record::TABLE, $table->get_name());
        self::assertSame(['usermodified'], array_keys($table->get_privacy_fields()));

        // All referenced string IDs must resolve.
        $stringmanager = get_string_manager();
        self::assertTrue($stringmanager->string_exists($table->get_summary(), 'tool_monitoring'));
        foreach ($table->get_privacy_fields() as $stringid) {
            self::assertTrue($stringmanager->string_exists($stringid, 'tool_monitoring'));
        }
    }

    /**
     * Tests the {@see provider::get_contexts_for_userid} method.
     *
     * @throws dml_exception
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $this->create_metric_record('foo', $user->id);
        $this->create_metric_record('bar', $otheruser->id);
        $contextids = provider::get_contexts_for_userid($user->id)->get_contextids();
        self::assertEquals([context_user::instance($user->id)->id], $contextids);
    }

    public function test_get_contexts_for_userid_without_records(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        self::assertEmpty(provider::get_contexts_for_userid($user->id)->get_contextids());
    }

    /**
     * Tests the {@see provider::get_users_in_context} method.
     *
     * @throws dml_exception
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $this->create_metric_record('foo', $user->id);
        $this->create_metric_record('bar', $otheruser->id);
        $userlist = new userlist(context_user::instance($user->id), 'tool_monitoring');
        provider::get_users_in_context($userlist);
        self::assertEquals([$user->id], $userlist->get_userids());
    }

    /**
     * Tests that the {@see provider::get_users_in_context} method returns empty for non-user contexts.
     *
     * @throws dml_exception
     */
    public function test_get_users_in_context_ignores_non_user_contexts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_metric_record('foo', $user->id);
        $userlist = new userlist(context_system::instance(), 'tool_monitoring');
        provider::get_users_in_context($userlist);
        self::assertEmpty($userlist->get_userids());
    }

    /**
     * Tests the {@see provider::export_user_data} method.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $metricid = $this->create_metric_record('foo', $user->id);
        $this->create_metric_record('bar', $otheruser->id);
        $context = context_user::instance($user->id);
        provider::export_user_data(new approved_contextlist($user, 'tool_monitoring', [$context->id]));
        $writer = writer::with_context($context);
        self::assertInstanceOf(content_writer::class, $writer);
        self::assertTrue($writer->has_any_data());
        $data = (array) $writer->get_data([get_string('pluginname', 'tool_monitoring')]);
        self::assertCount(1, $data);
        $record = reset($data);
        self::assertInstanceOf(stdClass::class, $record);
        self::assertEquals($metricid, $record->id);
        self::assertSame('foo', $record->name);
    }

    /**
     * Tests that the {@see provider::export_user_data} method does nothing on another user's context.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_export_user_data_ignores_foreign_contexts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $this->create_metric_record('foo', $user->id);
        $foreigncontext = context_user::instance($otheruser->id);
        provider::export_user_data(new approved_contextlist($user, 'tool_monitoring', [$foreigncontext->id]));
        $writer = writer::with_context($foreigncontext);
        self::assertInstanceOf(content_writer::class, $writer);
        self::assertFalse($writer->has_any_data());
    }

    /**
     * Tests that the {@see provider::export_user_data} method writes nothing if the user modified no metric.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_export_user_data_without_records(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $this->create_metric_record('bar', $otheruser->id);
        $context = context_user::instance($user->id);
        provider::export_user_data(new approved_contextlist($user, 'tool_monitoring', [$context->id]));
        $writer = writer::with_context($context);
        self::assertInstanceOf(content_writer::class, $writer);
        self::assertFalse($writer->has_any_data());
    }

    /**
     * Tests the {@see provider::delete_data_for_all_users_in_context} method.
     *
     * @throws dml_exception
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $metricid = $this->create_metric_record('foo', $user->id);
        $othermetricid = $this->create_metric_record('bar', $otheruser->id);
        provider::delete_data_for_all_users_in_context(context_user::instance($user->id));
        // Ensure only the audit reference is cleared and the row associated with the user remains otherwise unchanged.
        self::assertSame(2, $DB->count_records(metric_record::TABLE));
        self::assertEquals(0, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $metricid]));
        self::assertEquals($otheruser->id, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $othermetricid]));
    }

    /**
     * Tests that the {@see provider::delete_data_for_all_users_in_context} method does nothing for non-user contexts.
     *
     * @throws dml_exception
     */
    public function test_delete_data_for_all_users_in_context_ignores_non_user_contexts(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $metricid = $this->create_metric_record('foo', $user->id);
        provider::delete_data_for_all_users_in_context(context_system::instance());
        self::assertEquals($user->id, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $metricid]));
    }

    /**
     * Tests the {@see provider::delete_data_for_user} method.
     *
     * @throws dml_exception
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $metricid = $this->create_metric_record('foo', $user->id);
        $othermetricid = $this->create_metric_record('bar', $otheruser->id);
        $context = context_user::instance($user->id);
        provider::delete_data_for_user(new approved_contextlist($user, 'tool_monitoring', [$context->id]));
        self::assertEquals(0, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $metricid]));
        self::assertEquals($otheruser->id, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $othermetricid]));
    }

    /**
     * Tests that the {@see provider::delete_data_for_user} method does nothing on another user's context.
     *
     * @throws dml_exception
     */
    public function test_delete_data_for_user_ignores_foreign_contexts(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $metricid = $this->create_metric_record('foo', $user->id);
        $foreigncontext = context_user::instance($otheruser->id);
        provider::delete_data_for_user(new approved_contextlist($user, 'tool_monitoring', [$foreigncontext->id]));
        self::assertEquals($user->id, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $metricid]));
    }

    /**
     * Tests the {@see provider::delete_data_for_users} method.
     *
     * @throws dml_exception
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $metricid = $this->create_metric_record('foo', $user->id);
        $othermetricid = $this->create_metric_record('bar', $otheruser->id);
        $context = context_user::instance($user->id);
        provider::delete_data_for_users(new approved_userlist($context, 'tool_monitoring', [$user->id]));
        self::assertEquals(0, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $metricid]));
        self::assertEquals($otheruser->id, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $othermetricid]));
    }

    /**
     * Tests that the {@see provider::delete_data_for_users} method does nothing for users outside the context.
     *
     * @throws dml_exception
     */
    public function test_delete_data_for_users_ignores_users_outside_the_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $othermetricid = $this->create_metric_record('bar', $otheruser->id);
        $context = context_user::instance($user->id);
        provider::delete_data_for_users(new approved_userlist($context, 'tool_monitoring', [$otheruser->id]));
        self::assertEquals($otheruser->id, $DB->get_field(metric_record::TABLE, 'usermodified', ['id' => $othermetricid]));
    }

    /**
     * Tests that anonymizing a user purges the metrics cache, regardless of which deletion entry point was used.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_delete_purges_the_metrics_cache(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_metric_record('foo', $user->id);
        // Reading a metric from the cache requires the metric to be in the collection.
        $collection = new metric_collection();
        $collection->add(test_metric::create('foo'));
        di::set(metric_collection::class, $collection);
        $qualifiedname = metric_record::get_qualified_name('tool_monitoring', 'foo');
        // Load the metric into the cache holding the user ID.
        $cached = metrics_cache::get($qualifiedname);
        self::assertNotNull($cached);
        self::assertEquals($user->id, $cached->usermodified);
        provider::delete_data_for_all_users_in_context(context_user::instance($user->id));
        // Cache should have been purged. Another load should grab the anonymized record from the DB and cache it.
        self::assertEquals(0, metrics_cache::get($qualifiedname)->usermodified);
    }

    /**
     * Inserts a metric record last modified by the specified user.
     *
     * @param string $name Name of the metric.
     * @param int $usermodified ID of the user to record as last modifier.
     * @return int ID of the inserted record.
     * @throws dml_exception
     */
    private function create_metric_record(string $name, int $usermodified): int {
        global $DB;
        return $DB->insert_record(metric_record::TABLE, [
            'component'    => 'tool_monitoring',
            'name'         => $name,
            'enabled'      => 1,
            'timecreated'  => 1,
            'timemodified' => 1,
            'usermodified' => $usermodified,
        ]);
    }
}

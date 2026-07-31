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
 * Definition of the {@see provider} class.
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

namespace tool_monitoring\privacy;

use core\context;
use core\context\user as context_user;
use core\exception\coding_exception;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use dml_exception;
use tool_monitoring\local\metric_record;
use tool_monitoring\local\metrics_cache;

/**
 * Privacy subsystem provider.
 *
 * The plugin stores no personal data of its own; the only user reference is the `usermodified` audit column of the
 * {@see metric_record::TABLE} table, recording which administrator last enabled, disabled or configured a metric.
 *
 * Deletion requests **anonymize** rather than remove those rows.
 *
 * @link https://moodledev.io/docs/apis/subsystems/privacy
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
class provider implements core_userlist_provider, metadata_provider, plugin_provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            name: metric_record::TABLE,
            privacyfields: ['usermodified' => 'privacy:metadata:tool_monitoring_metrics:usermodified'],
            summary: 'privacy:metadata:tool_monitoring_metrics',
        );
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $tablename = metric_record::TABLE;
        $sql = "SELECT ctx.id
                  FROM {{$tablename}} AS m
                  JOIN {context} AS ctx ON ctx.instanceid = m.usermodified AND ctx.contextlevel = :contextlevel
                 WHERE m.usermodified = :userid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['userid' => $userid, 'contextlevel' => CONTEXT_USER]);
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        $tablename = metric_record::TABLE;
        $sql = "SELECT m.usermodified
                  FROM {{$tablename}} AS m
                 WHERE m.usermodified = :userid";
        $userlist->add_from_sql(
            fieldname: 'usermodified',
            sql: $sql,
            params: ['userid' => $context->instanceid],
        );
    }

    /**
     * {@inheritDoc}
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = self::find_own_user_context($contextlist, $userid);
        if (is_null($context)) {
            return;
        }
        $records = $DB->get_records(metric_record::TABLE, ['usermodified' => $userid]);
        if (empty($records)) {
            return;
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'tool_monitoring')],
            (object) $records,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     */
    #[\Override]
    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_user) {
            return;
        }
        self::delete_user_data($context->instanceid);
    }

    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     */
    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        if (!is_null(self::find_own_user_context($contextlist, $userid))) {
            self::delete_user_data($userid);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws dml_exception
     */
    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        // Loose `in_array` on purpose: `get_userids` is documented `int[]`, but yields the raw DB strings.
        if (!$context instanceof context_user || !in_array($context->instanceid, $userlist->get_userids())) {
            return;
        }
        self::delete_user_data($context->instanceid);
    }

    /**
     * Returns the user's own user context if the request approved it.
     *
     * At most one context can ever match, because {@see self::get_contexts_for_userid `get_contexts_for_userid`} only ever reports
     * the context of the user the request is for.
     *
     * @param approved_contextlist $contextlist Approved contexts of the request.
     * @param int $userid ID of the user the request is for.
     * @return context_user|null The user's own context, or `null` if the request did not approve it.
     */
    private static function find_own_user_context(approved_contextlist $contextlist, int $userid): context_user|null {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && $context->instanceid == $userid) {
                return $context;
            }
        }
        return null;
    }

    /**
     * Anonymizes the audit trail of the specified user by resetting `usermodified` to `0` on all affected metric records.
     *
     * The metric records themselves are site configuration and therefore must be kept.
     * The whole metrics cache is purged.
     *
     * @param int $userid ID of the user whose audit trail to anonymize.
     * @throws dml_exception
     */
    private static function delete_user_data(int $userid): void {
        global $DB;
        $DB->set_field_select(
            table: metric_record::TABLE,
            newfield: 'usermodified',
            newvalue: 0,
            select: 'usermodified = :userid',
            params: ['userid' => $userid]
        );
        metrics_cache::purge();
    }
}

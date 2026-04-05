<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for tool_monitoring.
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

defined('MOODLE_INTERNAL') || die();

use core\exception\moodle_exception;
use tool_monitoring\metric_tag;

/**
 * Upgrade code for the monitoring tool.
 *
 * @param int $oldversion
 * @return bool
 * @throws moodle_exception
 *
 * {@noinspection PhpUnused}
 */
function xmldb_tool_monitoring_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026041000) {
        $transaction = $DB->start_delegated_transaction();

        // The tag itemtype must match an existing DB table name. Older versions used "metrics",
        // but the actual records live in "tool_monitoring_metrics", so migrate both area and instances.
        $DB->set_field(
            table: 'tag_area',
            newfield: 'itemtype',
            newvalue: metric_tag::ITEM_TYPE,
            conditions: ['component' => 'tool_monitoring', 'itemtype' => 'metrics']
        );
        $DB->set_field(
            table: 'tag_instance',
            newfield: 'itemtype',
            newvalue: metric_tag::ITEM_TYPE,
            conditions: ['component' => 'tool_monitoring', 'itemtype' => 'metrics']
        );

        $transaction->allow_commit();

        upgrade_plugin_savepoint(true, 2026041000, 'tool', 'monitoring');
    }

    return true;
}

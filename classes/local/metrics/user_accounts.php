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
 * Definition of the {@see user_accounts} metric class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring\local\metrics;

use core\lang_string;
use dml_exception;
use Generator;
use tool_monitoring\metric_type;
use tool_monitoring\metric;
use tool_monitoring\metric_value;

/**
 * Gauges the current number of user accounts.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_accounts extends metric {
    /**
     * {@inheritDoc}
     */
    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    /**
     * {@inheritDoc}
     */
    public static function get_description(): lang_string {
        return new lang_string('user_accounts_description', 'tool_monitoring');
    }

    /**
     * {@inheritDoc}
     *
     * @return Generator<metric_value> Yields a {@see metric_value} for each combination of auth type and suspended/deleted state.
     * @throws dml_exception
     */
    public function calculate(): Generator {
        global $DB;
        $authtypes = get_enabled_auth_plugins();
        $fieldssqlfragments = [];
        $params = [];
        foreach ($authtypes as $auth) {
            $fieldssqlfragments = array_merge($fieldssqlfragments, [
                "SUM(CASE WHEN auth = :{$auth}00 AND suspended = 0 AND deleted = 0 THEN 1 ELSE 0 END) AS {$auth}activeexisting",
                "SUM(CASE WHEN auth = :{$auth}10 AND suspended = 1 AND deleted = 0 THEN 1 ELSE 0 END) AS {$auth}suspendedexisting",
                "SUM(CASE WHEN auth = :{$auth}01 AND suspended = 0 AND deleted = 1 THEN 1 ELSE 0 END) AS {$auth}activedeleted",
                "SUM(CASE WHEN auth = :{$auth}11 AND suspended = 1 AND deleted = 1 THEN 1 ELSE 0 END) AS {$auth}suspendeddeleted",
            ]);
            $params += [
                "{$auth}00" => $auth,
                "{$auth}10" => $auth,
                "{$auth}01" => $auth,
                "{$auth}11" => $auth,
            ];
        }
        $fieldssql = implode(",\n", $fieldssqlfragments);
        $sql = "SELECT $fieldssql FROM {user}";
        $record = $DB->get_record_sql(sql: $sql, params: $params, strictness: MUST_EXIST);
        foreach ($authtypes as $auth) {
            yield new metric_value(
                value: $record->{"{$auth}activeexisting"},
                label: ['auth' => $auth, 'suspended' => 'false', 'deleted' => 'false'],
            );
            yield new metric_value(
                value: $record->{"{$auth}suspendedexisting"},
                label: ['auth' => $auth, 'suspended' => 'true', 'deleted' => 'false'],
            );
            yield new metric_value(
                value: $record->{"{$auth}activedeleted"},
                label: ['auth' => $auth, 'suspended' => 'false', 'deleted' => 'true'],
            );
            yield new metric_value(
                value: $record->{"{$auth}suspendeddeleted"},
                label: ['auth' => $auth, 'suspended' => 'true', 'deleted' => 'true'],
            );
        }
    }
}

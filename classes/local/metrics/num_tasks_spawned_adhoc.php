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
 * Implements the num_tasks_spawned_adhoc metric.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauck <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring\local\metrics;

use core\lang_string;
use core\exception\coding_exception;


/**
 * Implements the num_tasks_spawned_adhoc metric.
 */
class num_tasks_spawned_adhoc implements metric_interface {

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public static function get_name(): string {
        return 'num_tasks_spawned_adhoc';
    }

    /**
     * {@inheritDoc}
     *
     * @return metric_type
     */
    public static function get_type(): metric_type {
        return metric_type::COUNTER;
    }

    /**
     * {@inheritDoc}
     * @return \core\lang_string
     */
    public static function get_description(): lang_string {
        return new lang_string('num_tasks_spawned_adhoc', 'tool_monitoring');
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public static function calculate(): int {
        global $CFG;
        return self::sum_last_sequence_value("{$CFG->prefix}task_adhoc_id_seq");
    }

    /**
     * Returns the `last_value` from the specified PostgreSQL sequence.
     *
     * @param string ...$sequences Name of the sequence of interest. If multiple names are passed, the **sum** of their last values
     *                             will be returned.
     * @return int Last sequence value
     * @throws coding_exception DB used is not PostgreSQL.
     * @throws dml_exception
     */
    protected static function sum_last_sequence_value(string ...$sequences): int {
        global $DB;
        if ($DB->get_dbfamily() !== 'postgres') {
            // TODO: Use custom exception class.
            throw new coding_exception('DB family is not supported');
        }
        [$insql, $inparams] = $DB->get_in_or_equal($sequences);
        $sql = "SELECT SUM(last_value)
                  FROM pg_sequences
                 WHERE sequencename $insql";
        return $DB->get_field_sql($sql, $inparams);
    }
}
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
 * Definition of the {@see courses} metric class.
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
use tool_monitoring\metric_type;
use tool_monitoring\metric;
use tool_monitoring\metric_value;

/**
 * Gauges the current number of courses.
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
class courses extends metric {
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
        return new lang_string('courses_description', 'tool_monitoring');
    }

    /**
     * {@inheritDoc}
     *
     * @return metric_value[]
     * @throws dml_exception
     */
    public function calculate(): array {
        global $DB;
        $sql = "SELECT SUM(visible)                                 AS numvisible,
                       SUM(CASE WHEN visible = 0 THEN 1 ELSE 0 END) AS numhidden
                  FROM {course}";
        $record = $DB->get_record_sql($sql, strictness: MUST_EXIST);
        return [
            new metric_value($record->numvisible, ['visible' => 'true']),
            new metric_value($record->numhidden, ['visible' => 'false']),
        ];
    }
}

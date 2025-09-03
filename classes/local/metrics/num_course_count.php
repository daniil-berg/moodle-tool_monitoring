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
 * Implements the num_course_count metric.
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
use tool_monitoring\local\metrics\metric;

/**
 * Implements the num_course_count metric.
 */
class num_course_count extends metric {

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public static function get_name(): string {
        return 'num_course_count';
    }

    /**
     * {@inheritDoc}
     *
     * @return metric_type
     */
    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    /**
     * {@inheritDoc}
     * @return \core\lang_string
     */
    public static function get_description(): lang_string {
        return new lang_string('num_course_count_description', 'tool_monitoring');
    }

    public static function get_labels(): array {
        return ['time', 'category'];
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function calculate(): void {
        global $DB;

        $this->set_value(12, [100, 1]);
        $this->set_value(6, [100, 3]);
        $this->set_value(3, [100, 4]);
        $this->set_value(17, [100, 6]);

        $this->set_value(6, [10, 1]);
        $this->set_value(17, [10, 2]);
    }
}
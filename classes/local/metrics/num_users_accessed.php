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
 * Implements the num_users_accessed metric.
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
use MoodleQuickForm;
use tool_monitoring\metric;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Implements the num_users_accessed metric.
 *
 * @extends metric<object{timewindow: int}>
 */
class num_users_accessed extends metric {

    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    public static function get_description(): lang_string {
        return new lang_string('num_users_accessed_description', 'tool_monitoring');
    }

    protected function calculate(): metric_value {
        global $DB;
        $where = 'username <> :excl_user AND lastaccess >= :earliest';
        $params = [
            'excl_user' => 'guest',
            'earliest'  => time() - $this->config->timewindow,
        ];
        return new metric_value($DB->count_records_select('user', $where, $params));
    }

    public static function add_config_form_elements(MoodleQuickForm $mform) {
        $mform->addElement('text', 'timewindow', 'Users online in the last seconds');
        // TODO: Localize and allow to set multiple values.
        $mform->setType('timewindow', PARAM_INT);
    }

    public static function get_default_config_data(): array {
        return [
            'timewindow' => 300,
        ];
    }
}
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
 * Plugin administration pages are defined here.
 *
 * @package     tool_monitoring
 * @category    admin
 * @copyright   2025 MootDACH DevCamp
 *              Daniel Fainberg <d.fainberg@tu-berlin.de>
 *              Martin Gauck <martin.gauk@tu-berlin.de>
 *              Sebastian Rupp <sr@artcodix.com>
 *              Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *              Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\lang_string;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_monitoring_settings', new lang_string('pluginname', 'monitorinexporter_prometheus'));

    $settings->add(new admin_setting_configpasswordunmask('tool_monitoring/prometheus_token',
            get_string('setting_token', 'monitorinexporter_prometheus'),
            get_string('setting_token_desc', 'monitorinexporter_prometheus'),
            ''));

    $ADMIN->add('tools', $settings);
}

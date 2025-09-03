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
 * Plugin settings.
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

use tool_monitoring\plugininfo\monitoringexporter;

defined('MOODLE_INTERNAL') || die;

global $ADMIN, $CFG;
require_once("$CFG->dirroot/mod/assign/adminlib.php");

$ADMIN->add(
    'tools',
    new admin_category(
        name: 'monitoringcategory',
        visiblename: new lang_string('pluginname', 'tool_monitoring'),
    ),
);

$overviewlink = new admin_externalpage(
    'monitoringmetricsoverviewlink',
    get_string('metricsoverview', 'tool_monitoring'),
    new moodle_url('/admin/tool/monitoring'),
    'tool/monitoring:list_metrics',
);
$ADMIN->add('monitoringcategory', $overviewlink);

foreach (core_plugin_manager::instance()->get_plugins_of_type('monitoringexporter') as $subplugin) {
    /** @var monitoringexporter $subplugin */
    $settings = new admin_settingpage(
        name: $subplugin->type . '_' . $subplugin->name,
        visiblename: $subplugin->displayname,
        req_capability: 'moodle/site:config',
    );
    if ($ADMIN->fulltree) {
        include($subplugin->full_path('settings.php'));
    }
    $ADMIN->add('monitoringcategory', $settings);
}

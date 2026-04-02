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
 * Declarations of admin settings for the plugin.
 *
 * @link https://moodledev.io/docs/apis/subsystems/admin Moodle docs Admin subsystem
 *
 * @package     tool_monitoring
 * @category    admin
 * @copyright   2025 MootDACH DevCamp
 *              Daniel Fainberg <d.fainberg@tu-berlin.de>
 *              Martin Gauk <martin.gauk@tu-berlin.de>
 *              Sebastian Rupp <sr@artcodix.com>
 *              Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *              Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpUndefinedVariableInspection, PhpUnhandledExceptionInspection}
 */

use tool_monitoring\plugininfo\monitoringexporter;

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

// Create a top-level plugin category and add it under the admin tools super-category.
$monitoringcategory = new admin_category(
    name: 'tool_monitoring',
    visiblename: new lang_string('pluginname', 'tool_monitoring'),
);
/** @var admin_root $ADMIN */
$ADMIN->add('tools', $monitoringcategory);

// Create a link to the metrics overview page and add it as the first item in the monitoring category.
$overviewlink = new admin_externalpage(
    name: 'tool_monitoring_overview',
    visiblename: new lang_string('settings:metrics_overview', 'tool_monitoring'),
    url: new moodle_url('/admin/tool/monitoring'),
    req_capability: 'tool/monitoring:manage_metrics',
);
$ADMIN->add('tool_monitoring', $overviewlink);

// As the second item in the monitoring category, group all settings pages for the exporters.
$monitoringexportercategory = new admin_category(
    name: 'tool_monitoring_exporters',
    visiblename: new lang_string('settings:exporters', 'tool_monitoring'),
);
$ADMIN->add('tool_monitoring', $monitoringexportercategory);

// Underneath the exporter category, add all exporter settings pages.
/** @var monitoringexporter $subplugin */
foreach (core_plugin_manager::instance()->get_plugins_of_type('monitoringexporter') as $subplugin) {
    $subplugin->load_settings($ADMIN, 'tool_monitoring_exporters', $hassiteconfig);
}

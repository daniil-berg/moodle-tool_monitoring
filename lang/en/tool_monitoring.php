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
 * Plugin strings are defined here.
 *
 * @package     tool_monitoring
 * @category    string
 * @copyright   2025 MootDACH DevCamp
 *              Daniel Fainberg <d.fainberg@tu-berlin.de>
 *              Martin Gauk <martin.gauk@tu-berlin.de>
 *              Sebastian Rupp <sr@artcodix.com>
 *              Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *              Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Monitoring';
$string['subplugintype_monitoringexporter_plural'] = 'Exporter types';

$string['action:edit'] = 'Edit';

$string['error:unique_metric_name'] = 'Collected more than one metric with the qualified name ${a}';
$string['error:metric_name_required'] = 'Metric name is required';
$string['error:no_constructor'] = 'No constructor defined for {$a}';
$string['error:private_constructor'] = 'Constructor of {$a} is private';
$string['error:json_decode'] = 'Json decode error';
$string['error:missing_value_json'] = 'Missing {$a} in JSON';
$string['error:missing_value_form_data'] = 'Missing {$a} in form data';
$string['error:invalid_label_names'] = 'Invalid label names: {$a}';
$string['error:label_not_allowed'] = 'Label not allowed: {$a}';
$string['error:metric_config'] = 'Metric config JSON is not set';
$string['error:class_not_implemented'] = 'Provided class {$a->class} does not implement {$a->configclass}';

$string['event:metric_config_updated'] = 'Metric configuration updated';
$string['event:metric_config_updated_description'] = 'User with ID {$a->userid} updated the metric config for {$a->metric}.';
$string['event:metric_disabled'] = 'Metric disabled';
$string['event:metric_disabled_description'] = 'User with ID {$a->userid} disabled the metric {$a->metric}.';
$string['event:metric_enabled_description'] = 'User with ID {$a->userid} enabled the metric {$a->metric}.';
$string['event:metric_enabled'] = 'Metric enabled';


$string['metric:num_courses_description'] = 'Current number of courses';
$string['metric:num_overdue_tasks_description'] = 'Number of tasks (excluding disabled ones) for which the next runtime is not in the future';
$string['metric:num_quiz_attempts_in_progress_description'] = 'Number of ongoing quiz attempts';
$string['metric:num_user_count_description'] = 'Number of total registered users';
$string['metric:num_users_accessed_description'] = 'Number of users that have recently accessed the site';

$string['settings:actions'] = 'Actions';
$string['settings:component'] = 'Component';
$string['settings:configure_metric'] = 'Configure Metric';
$string['settings:description'] = 'Description';
$string['settings:manage_metrics'] = 'Manage and configure monitoring metrics';
$string['settings:metric_enabled'] = 'Metric enabled';
$string['settings:metric_overview'] = 'Overview of Available Metrics';
$string['settings:monitoring_metrics'] = 'Monitoring Metrics';
$string['settings:name'] = 'Name';
$string['settings:type'] = 'Type';

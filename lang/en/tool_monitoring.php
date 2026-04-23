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
 * English language strings for the component.
 *
 * @link https://docs.moodle.org/dev/String_API Moodle docs String API
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

$string['error:form_data_value_missing'] = 'Form data is missing a value for the "{$a->fieldname}" field.';
$string['error:json_invalid'] = 'Invalid JSON encountered or the top-level type in that JSON is wrong.';
$string['error:json_key_missing'] = 'JSON object is missing the "{$a->key}" key.';
$string['error:metric_config_not_implemented'] = 'The "{$a->classname}" class does not implement the "metric_config" interface.';
$string['error:metric_not_found'] = 'No metric with the qualified name "{$a->qualifiedname}" is registered.';
$string['error:quiz_attempts_in_progress_config:input_invalid'] = 'Invalid input';
$string['error:simple_metric_config_constructor_missing'] = 'The "{$a->classname}" class does not have a constructor.';
$string['error:tag_not_found'] = 'No tag named "{$a->tagname}" exists in the "{$a->collectionname}" collection.';
$string['error:users_online_config:timewindows_invalid'] = 'Invalid time window(s) provided: {$a}';

$string['event:metric_config_updated'] = 'Metric configuration updated';
$string['event:metric_disabled'] = 'Metric disabled';
$string['event:metric_enabled'] = 'Metric enabled';

$string['metric:courses_desc'] = 'Current number of courses';
$string['metric:overdue_tasks_desc'] = 'Number of tasks (excluding disabled ones) for which the next runtime is not in the future';
$string['metric:quiz_attempts_in_progress_config:maxdeadlineseconds'] = 'Maximum deadline time';
$string['metric:quiz_attempts_in_progress_config:maxdeadlineseconds_help'] = 'Do not count attempts that have a deadline in more than this number of seconds.';
$string['metric:quiz_attempts_in_progress_config:maxidleseconds'] = 'Maximum idle time';
$string['metric:quiz_attempts_in_progress_config:maxidleseconds_help'] = 'Do not count attempts that are idle longer than this number of seconds.';
$string['metric:quiz_attempts_in_progress_desc'] = 'Number of ongoing quiz attempts with an approaching deadline';
$string['metric:user_accounts_desc'] = 'Current number of user accounts';
$string['metric:users_online_config:timewindows'] = 'Time window (seconds)';
$string['metric:users_online_config:timewindows_help'] = 'Number of seconds since the last user access for that user to be counted as online. Multiple values can be specified, separated by commas; the metric will produce a separate metric value for each time window.';
$string['metric:users_online_desc'] = 'Number of users that have recently accessed the site';

$string['monitoring:manage_metrics'] = 'Manage and configure monitoring metrics';

$string['pluginname'] = 'Monitoring';

$string['settings:actions'] = 'Actions';
$string['settings:component'] = 'Component';
$string['settings:configure'] = 'Configure';
$string['settings:configure_metric'] = 'Configure Metric';
$string['settings:description'] = 'Description';
$string['settings:edit_tag'] = 'Edit tag {$a}';
$string['settings:exporters'] = 'Available Exporters';
$string['settings:manage_tags'] = 'Manage tags';
$string['settings:metric_enabled'] = 'Metric enabled';
$string['settings:metrics_overview'] = 'Overview of Available Metrics';
$string['settings:metrics_overview_show_all'] = 'Show all metrics';
$string['settings:name'] = 'Name';
$string['settings:tag_filter'] = 'Filter:';
$string['settings:type'] = 'Type';

$string['subplugintype_monitoringexporter_plural'] = 'Exporter types';

$string['tagarea_tool_monitoring_metrics'] = 'Metrics';
$string['tagcollection_monitoring'] = 'Monitoring';

$string['testing:metric:testing_simple_metric_config:notpromotedstring'] = 'String with a default; not promoted to any property.';
$string['testing:metric:testing_simple_metric_config:privatereadonlyfloat'] = 'Float with a default; promoted to private property.';
$string['testing:metric:testing_simple_metric_config:protectedint'] = 'Integer with a default; promoted to protected property.';
$string['testing:metric:testing_simple_metric_config:publicbool'] = 'Boolean with a default; promoted to public property.';
$string['testing:metric:testing_simple_metric_config:publicobj'] = 'Object with a default; promoted to public property.';
$string['testing:metric:testing_simple_metric_config:publicstringrequired'] = 'Required string; promoted to public property.';
$string['testing:metric:testing_simple_metric_config:publicstringrequired_help'] = 'Help text for the above.';
$string['testing:metric:testing_simple_metric_config:publicunion'] = 'Union of types with a default; promoted to public property.';

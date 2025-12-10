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
 * Displays the configuration form for a single metric.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpUnhandledExceptionInspection}
 */

use tool_monitoring\output\configure;

require_once(__DIR__ . '/../../../config.php');

global $OUTPUT, $PAGE;

require_login();

$context = context_system::instance();
require_capability('tool/monitoring:configure_metrics', $context);

$qualifiedname = required_param('metric', PARAM_ALPHAEXT);

$PAGE->set_url('/admin/tool/monitoring/configure.php', ['metric' => $qualifiedname]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('configure_metric', 'tool_monitoring'));
$PAGE->set_heading(get_string('configure_metric', 'tool_monitoring'));
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();
echo $OUTPUT->render(new configure($qualifiedname));
echo $OUTPUT->footer();

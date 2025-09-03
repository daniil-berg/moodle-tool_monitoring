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
 * List of all metrics.
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

require_once(__DIR__ . '/../../../config.php');
require_login();


$PAGE->set_url('/admin/tool/monitoring/');
$PAGE->set_title('Monitoring Metrics Overview');
$PAGE->set_heading('Monitoring Metrics Overview');
$PAGE->set_context(context_system::instance());

$hook = new \tool_monitoring\hook\gather_metrics();
\core\di::get(\core\hook\manager::class)->dispatch($hook);

$metrics = $hook->get_metrics();

echo $OUTPUT->header();

foreach ($metrics as $metric) {
    $name = $metric::get_name();
    $description = $metric::get_description()->out();
    echo "<p>$name: $description</p>";
}

echo $OUTPUT->footer();

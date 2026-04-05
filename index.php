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
 * Displays a table of all available/registered metrics.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpUnhandledExceptionInspection}
 */

use core\di;
use tool_monitoring\metric_tag;
use tool_monitoring\metrics_manager;
use tool_monitoring\output\overview;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

global $PAGE, $OUTPUT;

require_login();

require_capability('tool/monitoring:manage_metrics', context_system::instance());

// Handle tags parameter for filtering of multiple tags.
$taglist = optional_param('tag', '', PARAM_TAGLIST);
$params = [];
if ($taglist) {
    $tagnames = explode(',', $taglist);
    $params['tag'] = $taglist;
} else {
    $tagnames = [];
}

admin_externalpage_setup('tool_monitoring_overview');
$PAGE->set_secondary_active_tab('modules');
$PAGE->set_url('/admin/tool/monitoring/', $params);

$manager = di::get(metrics_manager::class)->sync(delete: true);
$overview = new overview(
    metrics: $manager->filter(tagnames: $tagnames),
    tags: metric_tag::get_all_with_names(...$tagnames),
);
echo $OUTPUT->header();
echo $OUTPUT->render($overview);
echo $OUTPUT->footer();

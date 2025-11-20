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

namespace tool_monitoring\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core_tag_tag;
use moodle_url;
use tool_monitoring\metrics_manager;

/**
 * Class overview
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
class overview implements renderable, templatable {

    private array $entries = [];

    public function __construct() {
        $this->synchronise_database();
    }

    private function synchronise_database() {
        global $DB, $USER;
        $manager = new metrics_manager();
        $metrics = $manager->get_metrics();
        foreach ($metrics as $metric) {
            $component = $metric::get_component();
            $name = $metric::get_name();
            $record = $DB->get_record('tool_monitoring_settings', ['component' => $component, 'name' => $name]);
            if (!$record) {
                $record = (object) [
                    'component' => $component,
                    'name' => $name,
                    'enabled' => 0,
                    'tags' => '',
                    'settings' => '{}',
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $USER->id,
                ];
                $record->id = $DB->insert_record('tool_monitoring_settings', $record);
            }
            $this->entries[] = [
                'record' => $record,
                'metric' => $metric,
            ];
        }
        // TODO Add records left over in database but missing in $metrics.
    }

    /**
     * {@inheritDoc}
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT;
        $lines = [];
        foreach ($this->entries as $entry) {
            ['record' => $record, 'metric' => $metric] = $entry;
            $tagshtml = $OUTPUT->tag_list(
                core_tag_tag::get_item_tags(
                    'tool_monitoring',
                    'metrics',
                    $record->id));
            $edit = new moodle_url('/admin/tool/monitoring/configure.php', ['id' => $record->id]);
            $lines[] = [
                'component' => $metric::get_component(),
                'name' => $metric::get_name(),
                'type' => $metric::get_type()->value,
                'description' => $metric::get_description()->out(),
                'edit' => $edit->out(false),
                'tagshtml' => $tagshtml,
            ];
        }
        return [
            'metrics' => $lines,
        ];
    }
}

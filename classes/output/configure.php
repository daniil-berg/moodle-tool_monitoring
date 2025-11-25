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

use context_system;
use core\exception\coding_exception;
use core\output\renderable;
use core\output\templatable;
use core_tag_tag;
use moodle_url;
use stdClass;
use tool_monitoring\form\config;
use tool_monitoring\metric;
use tool_monitoring\metrics_manager;

/**
 * Render configuration form for a metric
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
class configure implements renderable, templatable {

    /**
     * @var stdClass
     */
    private stdClass $record;
    private metric $metric;
    private config $form;

    public function __construct(int $id){
        global $DB;
        $this->record = $DB->get_record('tool_monitoring_config', ['id' => $id], '*', MUST_EXIST);
        $this->metric = $this->get_metric();
        $customdata = [
            'metric' => $this->metric,
        ];
        $this->form = new config(null, $customdata);
        if ($this->form->is_cancelled()) {
            redirect(new moodle_url('/admin/tool/monitoring/'));
        } else if ($data = $this->form->get_data()) {
            $this->process_data($data);
            redirect(new moodle_url('/admin/tool/monitoring/'));
        } else {
            $data = [
                'id' => $this->record->id,
                'tags' => core_tag_tag::get_item_tags_array('tool_monitoring', 'metrics', $this->record->id),
            ];
            $this->form->set_data($data);
        }
    }

    private function get_metric() {
        $manager = new metrics_manager();
        $metrics = $manager->get_metrics();
        $component = $this->record->component;
        $name = $this->record->name;
        foreach ($metrics as $metric) {
            if ($metric::get_component() === $component && $metric::get_name() === $name) {
                return $metric;
            }
        }
        throw new coding_exception("metric {$component}/{$name} not found");
    }

    /**
     * {@inheritDoc}
     */
    public function export_for_template(\core\output\renderer_base $output) {
        ob_start();
        $this->form->display();
        $html = ob_get_clean();
        return [
            'form' => $html,
        ];
    }



    private function process_data(stdClass $data) {
        // TODO save data
        core_tag_tag::set_item_tags(
            'tool_monitoring',
            'metrics',
            $this->record->id,
            context_system::instance(),
            $data->tags
        );
    }
}

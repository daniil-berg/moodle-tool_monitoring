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
use core\output\templatable;
use moodle_url;
use tool_monitoring\form\config;
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
    /** @var metrics_manager  */
    private metrics_manager $manager;

    private config $form;

    public function __construct(int $id) {
        $this->manager = metrics_manager::load_metric($id);
        $this->form = $this->manager->get_metric_config_form($id);
        if ($this->form->is_cancelled()) {
            redirect(new moodle_url('/admin/tool/monitoring/'));
        } else if ($data = $this->form->get_data()) {
            $this->manager->save_metric_config($id, $data);
            redirect(new moodle_url('/admin/tool/monitoring/'));
        }
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
}

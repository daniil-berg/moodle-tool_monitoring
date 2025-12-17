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
 * Definition of the renderable {@see configure} class.
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

namespace tool_monitoring\output;

use core\exception\moodle_exception;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use JsonException;
use moodle_url;
use tool_monitoring\form\config;
use tool_monitoring\metrics_manager;

/**
 * Provides a configuration form for a specified metric.
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

    /** @var config Metric config form to be rendered. */
    private config $form;

    /**
     * Instantiates the underlying {@see config} form for the specified metric.
     *
     * @param string $qualifiedname Qualified name of the metric for which to render the config form.
     * @throws moodle_exception
     */
    public function __construct(string $qualifiedname) {
        $metric = metrics_manager::instance()->metrics[$qualifiedname] ?? null;
        if (is_null($metric)) {
            throw new moodle_exception('invalidrecord', 'error', '', 'tool_monitoring_metrics');
        }
        $this->form = config::for_metric($metric);
    }

    /**
     * Processes the form data if the form is submitted or canceled and issues HTTP redirects.
     *
     * This method must be called before any output is sent to the browser.
     *
     * @throws moodle_exception
     * @throws JsonException Metric-specific config data could not be (de-)serialized.
     */
    public function process_form(): void {
        if ($this->form->is_cancelled()) {
            redirect(new moodle_url('/admin/tool/monitoring/'));
        } else if ($this->form->is_submitted() && $this->form->is_validated()) {
            $this->form->save();
            redirect(new moodle_url('/admin/tool/monitoring/'));
        }
    }

    public function export_for_template(renderer_base $output): array {
        ob_start();
        $this->form->display();
        $html = ob_get_clean();
        return ['form' => $html];
    }
}

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

namespace tool_monitoring\form;

global $CFG;

use core\exception\coding_exception;
use dml_exception;
use JsonException;
use moodleform;
use tool_monitoring\event\metric_config_updated;
use tool_monitoring\metric;
use tool_monitoring\metric_config;

require_once("$CFG->libdir/formslib.php");

/**
 * Configuration form for a metric
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
class config extends moodleform {

    /**
     * @throws coding_exception
     */
    protected function definition(): void {
        $mform = $this->_form;
        $metric = $this->_customdata['metric'];
        $mform->addElement('static', 'component', get_string('component', 'tool_monitoring'), $metric::get_component());
        $mform->addElement('static', 'name', get_string('name', 'tool_monitoring'), $metric::get_name());
        $mform->addElement('static', 'type', get_string('type', 'tool_monitoring'), $metric::get_type()->value);
        $mform->addElement('static', 'description', get_string('description', 'tool_monitoring'), $metric::get_description());
        $mform->addElement('advcheckbox', 'enabled', get_string('metricenabled', 'tool_monitoring'));
        $this->add_action_buttons();
    }

    /**
     * Returns a new instance for configuring the specified metric.
     *
     * @param metric $metric Metric for which to return the form.
     * @return self New config form instance.
     */
    public static function for_metric(metric $metric): self {
        $customdata = ['metric' => $metric];
        $form = $metric::get_config_form(customdata: $customdata);
        $formdata = (array) $metric->config->data;
        $formdata['enabled'] = $metric->config->enabled;
        $form->set_data($formdata);
        return $form;
    }

    /**
     * Updates the {@see metric_config} of the specified metric with the submitted form data.
     *
     * If no form data is present, or it did not pass validation, this method does nothing.
     *
     * @param metric $metric Metric for which to update the config with the form data.
     * @throws coding_exception Should not happen.
     * @throws dml_exception
     * @throws JsonException The metric-specific config data could not be serialized.
     */
    public function save(metric $metric): void {
        global $DB;
        if (is_null($formdata = $this->get_data())) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $metric->config->enabled = $formdata->enabled ?? false;
        // Only store metric-specific config in the `data` field.
        $data = [];
        foreach (array_keys($metric::get_default_config_data()) as $field) {
            if (property_exists($formdata, $field)) {
                $data[$field] = $formdata->$field;
            }
        }
        $metric->config->data = (object) $data;
        $metric->config->update();
        metric_config_updated::for_metric($metric)->trigger();
        $transaction->allow_commit();
    }
}

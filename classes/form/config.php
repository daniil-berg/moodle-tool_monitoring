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

use core\exception\coding_exception;
use dml_exception;
use JsonException;
use moodleform;
use tool_monitoring\registered_metric;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

/**
 * Configuration form for a metric.
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
final class config extends moodleform {
    /** @var registered_metric Metric for which this form is defined; set in the {@see definition} method. */
    private registered_metric $metric;

    #[\Override]
    protected function definition(): void {
        $this->metric = $this->_customdata['metric'];
        $this->_form->addElement('hidden', 'metric', $this->metric->qualifiedname);
        $this->_form->setType('metric', PARAM_ALPHAEXT);
        $this->_form->addElement('static', 'component', get_string('component', 'tool_monitoring'), $this->metric->component);
        $this->_form->addElement('static', 'name', get_string('name', 'tool_monitoring'), $this->metric->name);
        $this->_form->addElement('static', 'type', get_string('type', 'tool_monitoring'), $this->metric->type->value);
        $this->_form->addElement('static', 'description', get_string('description', 'tool_monitoring'), $this->metric->description);
        $this->_form->addElement('advcheckbox', 'enabled', get_string('metricenabled', 'tool_monitoring'));
        $this->_form->addElement(
            'tags',
            'tags',
            get_string('tags'),
            [
                'itemtype' => 'metrics',
                'component' => 'tool_monitoring',
            ]
        );
        $this->metric->extend_config_form($this->_form);
    }

    #[\Override]
    protected function after_definition(): void {
        $this->add_action_buttons();
        parent::after_definition();
    }

    /**
     * Returns a new instance for configuring the specified metric.
     *
     * @param registered_metric $metric Metric for which to return the form.
     * @return self New config form instance.
     */
    public static function for_metric(registered_metric $metric): self {
        $form = new self(customdata: ['metric' => $metric]);
        $form->set_data($metric->to_form_data());
        return $form;
    }

    /**
     * Updates the registered metric in the database with the submitted form data.
     *
     * If no form data is present, or it did not pass validation, this method does nothing.
     *
     * @throws coding_exception Should not happen.
     * @throws dml_exception
     * @throws JsonException The metric-specific config data could not be serialized.
     */
    public function save(): void {
        if (is_null($formdata = $this->get_data())) {
            return;
        }
        $this->metric->update_with_form_data($formdata);
    }
}

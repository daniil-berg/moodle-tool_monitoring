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
 * Definition of the {@see config} form class.
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

namespace tool_monitoring\form;

use core\exception\coding_exception;
use core\lang_string;
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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config extends moodleform {
    /** @var registered_metric Metric for which this form is defined; set in the {@see definition} method. */
    private registered_metric $metric;

    #[\Override]
    protected function definition(): void {
        $this->metric = $this->_customdata['metric'];
        $this->_form->setType('metric', PARAM_ALPHAEXT);
        $this->add_static_field(
            name: 'component',
            label: new lang_string('settings:component', 'tool_monitoring'),
            value: $this->metric->component,
        );
        $this->add_static_field(
            name: 'name',
            label: new lang_string('settings:name', 'tool_monitoring'),
            value: $this->metric->name,
        );
        $this->add_static_field(
            name: 'type',
            label: new lang_string('settings:type', 'tool_monitoring'),
            value: $this->metric->type->value,
        );
        $this->add_static_field(
            name: 'description',
            label: new lang_string('settings:description', 'tool_monitoring'),
            value: $this->metric->description,
        );
        $this->add_advanced_checkbox_field(
            name: 'enabled',
            label: new lang_string('settings:metric_enabled', 'tool_monitoring'),
        );
        $this->add_tags_field(
            itemtype: registered_metric::TABLE,
            component: 'tool_monitoring',
        );
        if (!is_null($this->metric->configclass)) {
            $this->metric->configclass::extend_form_definition($this, $this->_form);
        }
    }

    #[\Override]
    protected function after_definition(): void {
        $this->add_action_buttons();
        parent::after_definition();
    }

    /**
     * Adds a static form field.
     *
     * @param string $name Name for the field.
     * @param lang_string $label Label for the field.
     * @param string $value Static value for the field.
     */
    private function add_static_field(string $name, lang_string $label, string $value): void {
        $this->_form->addElement('static', $name, $label, $value);
    }

    /**
     * Adds an advanced checkbox form field.
     *
     * @param string $name Name for the field.
     * @param lang_string $label Label for the field.
     */
    private function add_advanced_checkbox_field(string $name, lang_string $label): void {
        $this->_form->addElement('advcheckbox', $name, $label);
    }

    /**
     * Adds a tags selector form field.
     *
     * @param string $itemtype Tag item type.
     * @param string $component Tag component name.
     */
    private function add_tags_field(string $itemtype, string $component): void {
        $this->_form->addElement('tags', 'tags', new lang_string('tags'), ['itemtype' => $itemtype, 'component' => $component]);
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!is_null($this->metric->configclass)) {
            $errors = array_merge($errors, $this->metric->configclass::extend_form_validation($data, $this, $this->_form));
        }
        return $errors;
    }

    /**
     * Returns a new instance for configuring the specified metric.
     *
     * @param registered_metric $metric Metric for which to return the form.
     * @return self New config form instance.
     */
    public static function for_metric(registered_metric $metric): self {
        global $PAGE;
        $form = new self(action: $PAGE->url, customdata: ['metric' => $metric]);
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

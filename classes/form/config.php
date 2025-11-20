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

use moodleform;

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

    protected function definition() {
        $mform = $this->_form;

        $metric = $this->_customdata['metric'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('static', 'component', get_string('component', 'tool_monitoring'), $metric::get_component());
        $mform->addElement('static', 'name', get_string('name', 'tool_monitoring'), $metric::get_name());
        $mform->addElement('static', 'type', get_string('type', 'tool_monitoring'), $metric::get_type()->value);
        $mform->addElement('static', 'description', get_string('description', 'tool_monitoring'), $metric::get_description());

        $this->add_action_buttons();
    }
}

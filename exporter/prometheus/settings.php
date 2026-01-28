<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     monitoringexporter_prometheus
 * @copyright   2025 MootDACH DevCamp
 *              Daniel Fainberg <d.fainberg@tu-berlin.de>
 *              Martin Gauk <martin.gauk@tu-berlin.de>
 *              Sebastian Rupp <sr@artcodix.com>
 *              Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *              Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(
        new admin_setting_configpasswordunmask(
            name: 'monitoringexporter_prometheus/prometheus_token',
            visiblename: get_string('setting_token', 'monitoringexporter_prometheus'),
            description: get_string('setting_token_desc', 'monitoringexporter_prometheus'),
            defaultsetting: '',
        )
    );
}

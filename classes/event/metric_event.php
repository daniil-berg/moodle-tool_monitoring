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
 * Definition of the {@see metric_event} abstract event class.
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

namespace tool_monitoring\event;

use coding_exception;
use core\context\system;
use core\event\base;
use core\exception\moodle_exception;
use dml_exception;
use moodle_url;
use tool_monitoring\registered_metric;

/**
 * Metric-related event base class for convenience.
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
abstract class metric_event extends base {

    /** @var registered_metric Metric that the event relates to. */
    protected registered_metric $metric;

    /**
     * Initialises event properties.
     *
     * @throws dml_exception
     */
    protected function init(): void {
        $this->context = system::instance();
        $this->data['objecttable'] = registered_metric::TABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns URL to the config page for the metric.
     *
     * @return moodle_url URL to the config page.
     * @throws moodle_exception
     */
    public function get_url(): moodle_url {
        return new moodle_url('/admin/tool/monitoring/configure.php', ['metric' => $this->metric->qualifiedname]);
    }

    /**
     * Validates that a {@see registered_metric} instance was passed via the `other` data during construction.
     *
     * @throws coding_exception No `metric` key in the `other` array or value not a {@see registered_metric} object.
     */
    public function validate_data(): void {
        $metric = $this->data['other']['metric'] ?? null;
        if (!($metric instanceof registered_metric)) {
            throw new coding_exception('No metric passed to metric event.');
        }
        $this->metric = $metric;
    }

    /**
     * Constructs a new instance of the event for the given metric.
     *
     * @param registered_metric $metric Metric that the event relates to.
     * @return static New event object.
     * @throws coding_exception
     */
    public static function for_metric(registered_metric $metric): static {
        return static::create([
            'objectid' => $metric->id,
            'other'    => ['metric' => $metric],
        ]);
    }
}

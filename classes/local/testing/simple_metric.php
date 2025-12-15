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
 * Definition of the {@see simple_metric} class.
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

namespace tool_monitoring\local\testing;

use core\lang_string;
use tool_monitoring\metric;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Metric for testing purposes.
 *
 * Simply produces the values specified in its public constructor.
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
class simple_metric extends metric {
    /** @var iterable<metric_value>|metric_value Metric values to be produced by the metric. */
    private readonly iterable|metric_value $values;

    /**
     * Sets up the metric instance.
     *
     * @param iterable<metric_value>|metric_value $values Metric value(s) to be produced by the metric.
     */
    public static function with_values(iterable|metric_value $values): static {
        $metric = new static(component: 'foo', name: 'bar');
        $metric->values = $values;
        return $metric;
    }

    protected function calculate(): iterable|metric_value {
        return $this->values;
    }

    public static function get_description(): lang_string {
        // Just an arbitrary existing language string.
        return new lang_string('pluginname', 'tool_monitoring');
    }

    public static function get_type(): metric_type {
        return metric_type::COUNTER;
    }
}
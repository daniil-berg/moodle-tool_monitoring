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
 * Definition of the {@see strict_label_names} trait.
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

namespace tool_monitoring;

use core\exception\coding_exception;

/**
 * Enforces a specific set of label names for the {@see metric} exhibiting this trait.
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
trait strict_label_names
{
    /**
     * Returns the exact set of label names expected for the metric exhibiting this trait.
     *
     * @return string[] Array of label names; order is not relevant.
     */
    abstract protected static function get_label_names(): array;

    /**
     * Ensures the metric value has the exact label names defined by {@see get_label_names}.
     *
     * @param metric_value $metricvalue Metric value instance to be validated.
     * @return metric_value Valid metric value.
     * @throws coding_exception Label names do not match.
     */
    public static function validate_value(metric_value $metricvalue): metric_value {
        $allowed = array_flip(static::get_label_names());
        if (!empty(array_diff_key($allowed, $metricvalue->label) + array_diff_key($metricvalue->label, $allowed))) {
            // TODO: Use custom exception class.
            throw new coding_exception(
                get_string('error:invalid_label_names', 'tool_monitoring', json_encode($metricvalue->label)));
        }
        return $metricvalue;
    }
}

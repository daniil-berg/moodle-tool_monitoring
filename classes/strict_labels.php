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
 * Definition of the {@see strict_labels} trait.
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
 * Enforces an allowed set of labels for the {@see metric} exhibiting this trait.
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
trait strict_labels {
    /**
     * Returns the set of allowed labels expected for the metric exhibiting this trait.
     *
     * @return array<array<string,string>> Array of labels (mapping label names to label values); order is not relevant.
     */
    abstract static function get_labels(): array;

    /**
     * Ensures the metric value has one of the allowed labels defined by {@see get_labels}.
     *
     * @param metric_value $metricvalue Metric value instance to be validated.
     * @return metric_value Valid metric value.
     * @throws coding_exception Label not allowed.
     */
    protected static function validate_value(metric_value $metricvalue): metric_value {
        if (!in_array($metricvalue->label, static::get_labels())) {
            // TODO: Use custom exception class.
            throw new coding_exception('Label not allowed: ' . json_encode($metricvalue->label));
        }
        return $metricvalue;
    }
}

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
 * Definition of the {@see metric_strict_label_names} class.
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

use tool_monitoring\metric_value;
use tool_monitoring\strict_label_names;

/**
 * Metric for testing purposes.
 *
 * Produces values specified in the public constructor and validates their label names against those specified in the constructor.
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
class metric_strict_label_names extends simple_metric {
    use strict_label_names;

    /** @var string[] Set of expected label names for the test metric. */
    private static array $labelnames = [];

    /**
     * Sets up the metric instance.
     *
     * @param string[] $labelnames Set of expected label names for the test metric.
     * @param metric_value[] $values Metric values to be produced by the test metric.
     */
    public static function with_label_names_and_values(array $labelnames, array $values): static {
        $metric = static::with_values($values);
        self::$labelnames = $labelnames;
        return $metric;
    }

    protected static function get_label_names(): array {
        return self::$labelnames;
    }
}
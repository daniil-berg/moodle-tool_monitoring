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
 * Definition of the {@see prometheus} exporter class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauck <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace monitoringexporter_prometheus;

use tool_monitoring\local\metrics\metric_interface;

/**
 * Exports metrics in Prometheus format.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauck <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {

    /**
     * Exports the provided metrics in the Prometheus text format.
     *
     * @param class-string<metric_interface>[] $metrics Metrics classes.
     * @return string Prometheus text format.
     */
    public static function export(array $metrics): string {
        return implode("\n", array_map([self::class, "export_metric"], $metrics));
    }

    /**
     * Exports the provided metric in the Prometheus text format including HELP and TYPE comments.
     *
     * @param class-string<metric_interface> $metric Metrics class implementing {@see metric_interface}.
     * @return string Prometheus text format for a single metric.
     */
    private static function export_metric(string $metric): string {
        $value = $metric::calculate();
        $help = $metric::get_description()->out();
        $name = $metric::get_name();
        $type = $metric::get_type();
        $output = "# HELP $name $help\n";
        $output .= "# TYPE $name $type->value\n";
        $output .= "$name $value";
        return $output;
    }
}

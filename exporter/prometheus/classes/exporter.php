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

use tool_monitoring\metric;
use tool_monitoring\simple_metric;

/**
 * Exports metrics in Prometheus format.
 *
 * @see https://prometheus.io/docs/instrumenting/exposition_formats
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
     * @param (metric|simple_metric)[] $metrics
     * @return string Prometheus text format.
     */
    public static function export(array $metrics): string {
        return implode("\n", array_map([self::class, "export_metric"], $metrics));
    }

    /**
     * Exports the provided metric in the Prometheus text format including HELP and TYPE comments.
     *
     * @param metric|simple_metric $metric
     * @return string Prometheus text format for a single metric.
     */
    private static function export_metric(metric|simple_metric $metric): string {
        $metric->calculate();
        $help = $metric::get_description()->out();
        $name = $metric::get_name();
        $type = $metric::get_type();
        $output = "# HELP $name $help\n";
        $output .= "# TYPE $name $type->value\n";
        if ($metric instanceof simple_metric) {
            $output .= "$name {$metric->get_value()}";
        } else {
            $lines = [];
            foreach ($metric as $labels => $value) {
                $labelsstring = self::labels_to_string($labels);
                $lines[] = "$name{{$labelsstring}} $value";
            }
            $output .= implode("\n", $lines);
        }
        return $output;
    }

    /**
     * Generates the Prometheus metric labels string from a labels array.
     *
     * @see https://prometheus.io/docs/instrumenting/exposition_formats/#comments-help-text-and-type-information
     *
     * @param array $labels Associative array of labels mapped to their respective values for a single metric.
     * @return string Comma-separated name-value-pairs in the Prometheus format.
     */
    private static function labels_to_string(array $labels): string {
        $pairs = [];
        foreach ($labels as $labelname => $labelvalue) {
            $pairs[] = "$labelname=\"$labelvalue\"";
        }
        return implode(', ', $pairs);
    }
}

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
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace monitoringexporter_prometheus;

use tool_monitoring\metric;
use tool_monitoring\metric_value;

/**
 * Exports metrics in Prometheus format.
 *
 * @see https://prometheus.io/docs/instrumenting/exposition_formats
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
class exporter {

    /**
     * Exports the provided metrics in the Prometheus text format.
     *
     * @param metric[] $metrics Array of metric instances to export.
     * @return string Prometheus text format.
     */
    public static function export(array $metrics): string {
        return implode("\n", array_map([self::class, 'export_metric'], $metrics));
    }

    /**
     * Exports the provided metric in the Prometheus text format including `HELP` and `TYPE` comments.
     *
     * @see https://prometheus.io/docs/instrumenting/exposition_formats/#comments-help-text-and-type-information
     *
     * @param metric $metric Instance of the metric to export.
     * @return string Prometheus text format for a single metric.
     */
    private static function export_metric(metric $metric): string {
        $name = $metric::get_component() . "." . $metric::get_name();
        $output = "# HELP $name {$metric::get_description()->out()}\n";
        $output .= "# TYPE $name {$metric::get_type()->value}";
        foreach ($metric as $metricvalue) {
            $output .= "\n" . self::get_metric_value_line($metricvalue, $name);
        }
        return $output;
    }

    /**
     * Generates a metric value line in the Prometheus format.
     *
     * @param metric_value $metricvalue Potentially labeled metric value.
     * @param string $metricname Name of the metric.
     * @return string Line for exporting the metric value.
     */
    private static function get_metric_value_line(metric_value $metricvalue, string $metricname): string {
        if (!$metricvalue->label) {
            return "$metricname $metricvalue->value";
        }
        $pairs = [];
        foreach ($metricvalue->label as $labelname => $labelvalue) {
            $pairs[] = "$labelname=\"$labelvalue\"";
        }
        return "$metricname{" . implode(', ', $pairs) . "} $metricvalue->value";
    }
}

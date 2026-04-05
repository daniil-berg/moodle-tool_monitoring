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
 * Definition of the {@see prometheus} class.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace monitoringexporter_prometheus;

use core\exception\coding_exception;
use dml_exception;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\metric_value;
use tool_monitoring\metrics_manager;
use tool_monitoring\registered_metric;

/**
 * Exports metrics in Prometheus format.
 *
 * @link https://prometheus.io/docs/instrumenting/exposition_formats Prometheus format documentation
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /**
     * Exports enabled metrics in the Prometheus text format.
     *
     * @param string ...$tagnames Names of tags to filter the metrics by. Exports only metrics that carry all the specified tags.
     *                            Names will be normalized before looking up the tags. Not passing any disables this filter.
     * @return string Prometheus text format.
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found At least one of the provided `$tagnames` does not match any existing metric tag.
     */
    public static function export(string ...$tagnames): string {
        $manager = new metrics_manager();
        $lines = [];
        foreach ($manager->filter(enabled: true, tagnames: $tagnames) as $metric) {
            $lines[] = self::export_metric($metric);
        }
        return implode("\n", $lines);
    }

    /**
     * Exports the provided metric in the Prometheus text format, including `HELP` and `TYPE` comments.
     *
     * @link https://prometheus.io/docs/instrumenting/exposition_formats/#comments-help-text-and-type-information Documentation
     *
     * @param registered_metric $metric Instance of the metric to export.
     * @return string Prometheus text format for a single metric.
     */
    private static function export_metric(registered_metric $metric): string {
        $name = $metric->qualifiedname;
        $output = "# HELP $name {$metric->description->out()}\n";
        $output .= "# TYPE $name {$metric->type->value}";
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

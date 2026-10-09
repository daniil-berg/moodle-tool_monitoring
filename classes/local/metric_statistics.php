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
 * Definition of the {@see \tool_monitoring\local\metric_statistics} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring\local;

/**
 * Stores some stats for {@see metric}s that will be used for meta metrics.
 *
 * This class is static and the contained values are for the last calculation of the metrics.
 * Metric stat values can be retrieved by iterating over the instance of this class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class metric_statistics {
    /**
     * @var array $stats stores the metric statistics, organized by metric name
     */
    private static array $stats = [];

    /**
     * @var array $metametricqualifiednames stores the names of meta metrics to filter those from statistics
     */
    private static array $metametricqualifiednames = [
        "tool_monitoring_meta_metrics_count",
        "tool_monitoring_meta_metrics_samples",
        "tool_monitoring_meta_metrics_timings",
    ];

    /**
     * Saves statistics for a given metric name.
     *
     * @param string $metricname name of the metric to save the stats for
     * @param string $statisticname the name of the statistic to save
     * @param int $value the value of the statistic to save
     */
    public static function record_statistic(string $metricname, string $statisticname, int $value): void {
        if (!in_array($metricname, self::$metametricqualifiednames)) {
            self::$stats[$metricname][$statisticname] = $value;
        };
    }

    /**
     * Gets the saved metric stats, organized by metric name.
     *
     * @return array of metric stats
     */
    public static function get_all(): array {
        return self::$stats;
    }

    /**
     * Gets the saved metric stats for a specific metric.
     *
     * @return array of metric stats
     */
    public static function get(string $metricname): array {
        return self::$stats[$metricname] ?? [];
    }
}

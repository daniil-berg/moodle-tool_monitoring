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
 * Definition of the {@see metaMetrics_count} metric class.
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

namespace tool_monitoring\local\metrics\meta;

use dml_exception;
use tool_monitoring\hook\after_metric_calculated;
use tool_monitoring\hook\before_metric_calculated;
use tool_monitoring\local\metric_statistics;
use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Gauges the current number of metrics monitored by tool_monitoring.
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
class meta_metrics_timings extends metric {
    #[\Override]
    public function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    /**
     * Produces the current metric values.
     *
     * @return metric_value[] Two metric values, one for visible courses and one for hidden courses.
     * @throws dml_exception
     */
    #[\Override]
    public function calculate(metric_config|null $config = null): iterable|metric_value {
        $stats = metric_statistics::get_all();
        $totaltiming = 0;

        foreach ($stats as $metric => $metricstats) {
            $starttime = $metricstats['start_time'] ?? 0;
            $endtime = $metricstats['end_time'] ?? 0;
            $duration = $metricstats['duration'] ?? 0;
            yield new metric_value(
                $duration,
                [
                    "metric" => $metric,
                    "start_time" => $starttime,
                    "end_time" => $endtime,
                ]
            );
            $totaltiming += $duration;
        }

        yield new metric_value($totaltiming);
    }

    /**
     * pre metric calculation callback; saves the time the metric calculation finishes
     */
    public static function pre_calculate(before_metric_calculated $hook): void {
        $starttime = intval(hrtime(true));
        metric_statistics::record_statistic($hook->qualifiedname, 'start_time', $starttime);
    }

    /**
     * post metric calculation callback; saves the time the metric calculation finishes
     * and calculates the duration it to complete
     */
    public static function post_calculate(after_metric_calculated $hook): void {
        $endtime = intval(hrtime(true));
        $starttime = metric_statistics::get($hook->qualifiedname)['start_time'] ?? 0;
        $duration = intval(($endtime - $starttime) / 1000000);
        metric_statistics::record_statistic($hook->qualifiedname, 'end_time', $endtime);
        metric_statistics::record_statistic($hook->qualifiedname, 'duration', $duration);
    }
}

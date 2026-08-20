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
use tool_monitoring\metric_statistics;
use tool_monitoring\metric_type;
use tool_monitoring\metric;
use tool_monitoring\metric_value;
use tool_monitoring\metrics_manager;

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
class meta_metrics_samples extends metric {
    #[\Override]
    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    /**
     * Produces the current metric values.
     *
     * @return metric_value[] Two metric values, one for visible courses and one for hidden courses.
     * @throws dml_exception
     */
    #[\Override]
    public function calculate(): iterable {
        $stats = metric_statistics::get();

        foreach ($stats as $metricname => $metricstats) {
            yield new metric_value($metricstats['sample_count'], [
                    'metric_name' => $metricname,
            ]);
        }
    }
}

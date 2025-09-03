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
 * Definition of the {@see metrics_manager} class.
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

namespace tool_monitoring;

use tool_monitoring\local\metrics\metric_interface;

/**
 * Metrics manager to gather all available metrics and operations.
 */
class metrics_manager {
    /**
     * All available metrics in the system.
     * @var class-string<metric_interface>[]
     */
    protected $allmetrics = [];

    /**
     * Constructor that gathers all available metrics.
     */
    public function __construct() {
        $hook = new \tool_monitoring\hook\gather_metrics();
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        $this->allmetrics = $hook->get_metrics();
    }

    /**
     * Get all available metrics.
     *
     * @return class-string<metric_interface>[]
     */
    public function get_all_metrics(): array {
        return $this->allmetrics;
    }

    /**
     * Filter the metrics by tag and return the metric names with their values.
     *
     * @param string $tag
     * @return array{string:class-string<metric_interface>}
     */
    public function calculate_needed_metrics(string $tag): array {
        // TODO: filter.
        $metricvalues = [];

        foreach ($this->allmetrics as $metric) {
            $metricvalues[$metric::get_name()] = $metric;
        }

        return $metricvalues;
    }
}

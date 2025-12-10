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
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring;

use core\di;
use core\exception\coding_exception;
use core\hook\manager as hook_manager;
use dml_exception;
use JsonException;
use tool_monitoring\hook\gather_metrics;

/**
 * Manager for accessing all registered metrics.
 *
 * Implemented as a singleton, accessed via the {@see metrics_manager::instance} method.
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
final class metrics_manager {
    /** @var metrics_manager Singleton object */
    private static self $instance;

    /** @var array<string, metric> All registered metrics in the system indexed by their qualified name. */
    private array $metrics;

    /**
     * Returns the metrics manager singleton, constructing one on the first call.
     *
     * When called for the first time, dispatches the {@see gather_metrics} hook and stores all registered metrics.
     *
     * @return self Singleton metrics manager object.
     */
    public static function instance(): self {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Dispatches the {@see gather_metrics} hook and stores all registered metrics.
     */
    private function __construct() {
        $hook = new gather_metrics();
        di::get(hook_manager::class)->dispatch($hook);
        $this->metrics = $hook->get_metrics();
    }

    /**
     * Returns the registered metrics, optionally filtering by tag.
     *
     * @param string|null $tag If provided, only metrics with that tag will be returned.
     * @param bool $refreshconfigs If `true` (default), the {@see metric::load_config} method is called on each instance first.
     * @return metric[] Metrics indexed by their qualified name.
     * @throws coding_exception Should not happen.
     * @throws dml_exception Unexpected error in a database query; only possible if configs are refreshed.
     * @throws JsonException Failed to (de-)serialize a config `data` value; only possible if configs are refreshed.
     */
    public function get_metrics(string|null $tag = null, bool $refreshconfigs = true): array {
        if ($refreshconfigs) {
            foreach ($this->metrics as $metric) {
                $metric->load_config();
            }
        }
        if (is_null($tag)) {
            return $this->metrics;
        }
        // TODO: Implement configurable tags for metrics via settings and filter the metrics accordingly here.
        return array_filter(
            $this->metrics,
            fn (metric $metric): bool => true,
        );
    }

    /**
     * Returns the metric with the specified name or `null` if no such metric is registered.
     *
     * @param string $qualifiedname Qualified name of the desired metric.
     * @param bool $refreshconfig If `true` (default), the metric's {@see metric::load_config} method is called first.
     * @return metric|null The desired metric or `null` if it was not found.
     * @throws coding_exception Should not happen.
     * @throws dml_exception Unexpected error in a database query; only possible if config is refreshed.
     * @throws JsonException Failed to (de-)serialize a config `data` value; only possible if config is refreshed.
     */
    public function get_metric(string $qualifiedname, bool $refreshconfig = true): metric|null {
        $metric = $this->metrics[$qualifiedname] ?? null;
        if (!is_null($metric) && $refreshconfig) {
            $metric->load_config();
        }
        return $metric;
    }
}

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

namespace tool_monitoring\hook;

use core\attribute\label;
use core\attribute\tags;
use core\di;
use core\hook\manager as hook_manager;
use tool_monitoring\metric;

/**
 * Linchpin of the monitoring API.
 *
 * Registered metrics can be retrieved with the {@see get_metrics} and {@see get_metric} methods.
 * This class also represents a hook in the sense of the {@link https://moodledev.io/docs/apis/core/hooks Moodle Hooks API} that
 * allows callbacks to register metrics via the {@see add_metric} method.
 *
 * Implemented as a singleton, accessed via the {@see instance} method.
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
#[label('Provides the ability to register custom metrics.')]
#[tags('metric', 'monitoring', 'tool_monitoring')]
final class metrics_manager {
    /** @var self Singleton object */
    private static self $instance;

    /** @var array<string, metric> All registered metrics indexed by their qualified name. */
    private array $metrics = [];

    /**
     * Returns the singleton object, constructing one on the first call.
     *
     * When called for the first time, dispatches the hook allowing callbacks to register metrics.
     *
     * @return self Singleton object.
     */
    public static function instance(): self {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Dispatches the hook allowing callbacks to register metrics.
     */
    private function __construct() {
        di::get(hook_manager::class)->dispatch($this);
    }

    /**
     * Registers the provided metric.
     *
     * @param metric $metric
     */
    public function add_metric(metric $metric): void {
        $key = $metric::get_qualified_name();
        if (array_key_exists($key, $this->metrics)) {
            trigger_error("Metric named '$key' is already registered", E_USER_WARNING);
            return;
        }
        $this->metrics[$key] = $metric;
    }

    /**
     * Returns the registered metrics, optionally filtering by tag.
     *
     * @param string|null $tag If provided, only metrics with that tag will be returned.
     * @return metric[] Metrics indexed by their qualified name.
     */
    public function get_metrics(string|null $tag = null): array {
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
     * @return metric|null The desired metric or `null` if it was not found.
     */
    public function get_metric(string $qualifiedname): metric|null {
        return $this->metrics[$qualifiedname] ?? null;
    }
}

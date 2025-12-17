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
use tool_monitoring\hook\metric_collection;

/**
 * Linchpin of the monitoring API.
 *
 * Enabled metrics can be retrieved and optionally filtered by tag via the {@see get_enabled_metrics} method.
 *
 * Implemented as a singleton, accessed via the {@see instance} method.
 *
 * @property-read array<string, registered_metric> $metrics All registered metrics indexed by their qualified name.
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
    /** @var self Singleton object. */
    private static self $instance;

    /** @var array<string, registered_metric> All registered metrics indexed by their qualified name. */
    private array $metrics = [];

    /**
     * Returns the singleton object, constructing one on the first call.
     *
     * When called for the first time, dispatches the {@see metric_collection} hook and attempts to register all added metrics.
     * If it encounters a metric with a qualified name that is already registered, that instance is ignored and a warning issued.
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
     * Dispatches the {@see metric_collection} hook allowing callbacks to register metrics.
     *
     * Populates its {@see self::metrics} array with {@see registered_metric} instances derived from the metrics the hook picked up.
     * If it encounters a metric with a qualified name that is already registered, that instance is ignored and a warning issued.
     *
     * @throws coding_exception Should not happen.
     * @throws dml_exception
     * @throws JsonException Failed to (de-)serialize the {@see registered_metric::config} value.
     */
    private function __construct() {
        $collection = new metric_collection();
        di::get(hook_manager::class)->dispatch($collection);
        foreach ($collection as $metric) {
            $qname = registered_metric::get_qualified_name($metric::get_component(), $metric::get_name());
            if (array_key_exists($qname, $this->metrics)) {
                trigger_error("Metric '$qname' is already registered", E_USER_WARNING);
                return;
            }
            $this->metrics[$qname] = registered_metric::from_metric($metric);
        }
    }

    /**
     * Special-case getter for the full array of registered metrics.
     *
     * TODO Replace this method with a nice property `get`-hook, once PHP 8.4+ becomes the minimum requirement.
     *
     * @param string $name Name of the property to return.
     * @return mixed Property value.
     */
    public function __get(string $name): mixed {
        return match ($name) {
            'metrics' => $this->metrics,
            default   => $this->$name,
        };
    }

    /**
     * Returns the enabled registered metrics, optionally filtering by tags.
     *
     * @param string ...$tags Only metrics that carry all the provided tags will be returned.
     * @return array<string, registered_metric> Metrics indexed by their qualified name.
     */
    public function get_enabled_metrics(string ...$tags): array {
        // TODO: Filter by metric tags.
        return array_filter(
            $this->metrics,
            fn (registered_metric $metric): bool => $metric->enabled,
        );
    }
}

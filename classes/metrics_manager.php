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
 * @property-read array<string, registered_metric> $metrics Registered metrics indexed by their qualified name; must be populated
 *                                                          by calling the {@see sync_registered_metrics} method.
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

    /** @var metric_collection Metric collection hook (already dispatched). */
    private metric_collection $collection;

    /** @var array<string, registered_metric> All collected and registered metrics indexed by their qualified name. */
    private array $metrics = [];

    /**
     * Returns the singleton object, constructing one on the first call.
     *
     * When called for the first time, dispatches the {@see metric_collection} hook and stores the collection of metrics.
     *
     * @param bool $syncdb If `true`, calls {@see sync_registered_metrics} method on the instance before returning it.
     * @return self Manager singleton.
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException Failed to serialize a {@see registered_metric::config} value during synchronization.
     */
    public static function instance(bool $syncdb = false): self {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        if ($syncdb) {
            self::$instance->sync_registered_metrics();
        }
        return self::$instance;
    }

    /**
     * Dispatches the {@see metric_collection} hook allowing callbacks to add metrics.
     */
    private function __construct() {
        $this->collection = new metric_collection();
        di::get(hook_manager::class)->dispatch($this->collection);
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
        if ($name === 'metrics') {
            return $this->metrics;
        }
        return $this->$name;
    }

    /**
     * Synchronizes all collected metrics with the database.
     *
     * For details see the {@see registered_metric::sync_with_collection} method.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException Failed to serialize a {@see registered_metric::config} value.
     */
    public function sync_registered_metrics(): void {
        $this->metrics = registered_metric::sync_with_collection($this->collection);
    }

    /**
     * Returns the enabled registered metrics, optionally filtering by tags.
     *
     * For details see the {@see registered_metric::get_from_collection} method.
     *
     * @param string ...$tags Only metrics that carry all the provided tags will be returned.
     * @return array<string, registered_metric> Metrics indexed by their qualified name.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_enabled_metrics(string ...$tags): array {
        return registered_metric::get_from_collection($this->collection, enabled: true, tags: $tags);
    }
}

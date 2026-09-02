<?php
// This file is part of the tool_monitoring plugin for Moodle - https://moodle.org/
//
// tool_monitoring is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// tool_monitoring is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with tool_monitoring.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_monitoring\hook;

use core\di;
use core\hook\described_hook;
use core\hook\di_configuration;
use core\hook\manager as hook_manager;
use IteratorAggregate;
use tool_monitoring\metric;
use Traversable;

/**
 * Hook for collecting {@see metric}s defined in different components throughout the system.
 *
 * A callback can use the {@see self::add} method to add a metric instance to the collection.
 *
 * An instance of this hook is dispatched automatically when injecteregistered_metricsd as a dependency by the DI container.
 *
 * @link https://moodledev.io/docs/apis/core/hooks Documentation: Hooks API
 * @link https://moodledev.io/docs/apis/core/hooks#hook-instance Documentation: Hook instance
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
final class metric_collection implements described_hook, IteratorAggregate {
    /** @var array<string, array<string, metric>> All added metrics indexed first by component, then by name. */
    private array $metrics = [];

    /**
     * Adds the specified metric to the collection.
     *
     * If a metric with the same component and name already exists, it will be silently replaced.
     *
     * @param metric $metric Metric instance to add.
     */
    public function add(metric $metric): void {
        $component = $metric->get_component();
        if (!isset($this->metrics[$component])) {
            $this->metrics[$component] = [];
        }
        $this->metrics[$component][$metric->get_name()] = $metric;
    }

    /**
     * Returns the metric with the given component and name.
     *
     * @param string $component Moodle component name.
     * @param string $name Metric name.
     * @return metric|null Metric with the given component and name, or `null` if no such metric was added to the collection.
     */
    public function get(string $component, string $name): metric|null {
        return $this->metrics[$component][$name] ?? null;
    }

    /**
     * Supplies a definition for the class to Moodle's dependency injection container.
     *
     * This ensures that the hook is always emitted/dispatched by the DI container first before it is injected as a dependency.
     *
     * @link https://moodledev.io/docs/apis/core/hooks#hook-emitter Documentation: Hook emitter
     * @link https://moodledev.io/docs/apis/core/di#configuring-dependencies Documentation: Dependency injection
     */
    public static function configure_dependency_injection(di_configuration $hook): void {
        $hook->add_definition(
            id: self::class,
            // CAUTION: Due to fascinating interplay between how PHP-DI compiles the container and poor error handling in Moodle,
            // the closure **must** use the actual class name, both in the return type annotation and during construction!
            // Otherwise, Behat tests will fail without any visible traceback.
            definition: fn(): metric_collection => di::get(hook_manager::class)->dispatch(new metric_collection()),
        );
    }

    #[\Override]
    public static function get_hook_description(): string {
        return 'Provides the ability to register custom metrics.';
    }

    #[\Override]
    public static function get_hook_tags(): array {
        return ['metric', 'monitoring', 'tool_monitoring'];
    }

    /**
     * Yields the metrics from the collection.
     *
     * @return Traversable<metric> Previously added metrics.
     */
    #[\Override]
    public function getIterator(): Traversable {
        foreach ($this->metrics as $inner) {
            foreach ($inner as $metric) {
                yield $metric;
            }
        }
    }
}

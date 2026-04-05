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
 * Definition of the {@see metric_collection} hook class.
 *
 * @link https://moodledev.io/docs/apis/core/hooks Documentation: Hooks API
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

namespace tool_monitoring\hook;

use core\attribute\label;
use core\attribute\tags;
use core\di;
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
 * An instance of this hook is dispatched automatically when injected as a dependency by the DI container.
 *
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
#[label('Provides the ability to register custom metrics.')]
#[tags('metric', 'monitoring', 'tool_monitoring')]
final class metric_collection implements IteratorAggregate {
    /** @var metric[] All added metrics. */
    private array $metrics = [];

    /**
     * Adds the specified metric to the collection.
     *
     * @param metric $metric Metric instance to add.
     */
    public function add(metric $metric): void {
        $this->metrics[] = $metric;
    }

    /**
     * Yields the metrics from the collection in the order they were added.
     *
     * @return Traversable<metric> Previously added metrics.
     */
    #[\Override]
    public function getIterator(): Traversable {
        foreach ($this->metrics as $metric) {
            yield $metric;
        }
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
}

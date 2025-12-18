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
 * Definition of the {@see metric_collection} class.
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
use IteratorAggregate;
use tool_monitoring\metric;
use Traversable;

/**
 * Hook for collecting {@see metric}s defined in different components throughout the system.
 *
 * A callback can use the {@see self::add} method to add a metric instance to the collection.
 *
 * @link https://moodledev.io/docs/apis/core/hooks Moodle Hooks API
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
    public function getIterator(): Traversable {
        foreach ($this->metrics as $metric) {
            yield $metric;
        }
    }
}

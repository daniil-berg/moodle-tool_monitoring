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
 * Definition of the {@see gather_metrics} hook.
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
use tool_monitoring\metric;

/**
 * Linchpin of the monitoring API.
 *
 * Hook callbacks can register metrics via the {@see add_metric} method.
 * Registered metrics can be retrieved with the {@see get_metrics} method.
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
final class gather_metrics {

    /** @var metric[] All registered metrics indexed by name. */
    private array $metrics = [];

    /**
     * Registers the provided metric.
     *
     * @param metric $metric
     */
    public function add_metric(string $metric): void {
        // TODO: Ensure unique names?
        $this->metrics[$metric::get_name()] = new $metric();
    }

    /**
     * Returns all registered metrics.
     *
     * @return metric[] Metrics indexed by name.
     */
    public function get_metrics(): array {
        return $this->metrics;
    }
}

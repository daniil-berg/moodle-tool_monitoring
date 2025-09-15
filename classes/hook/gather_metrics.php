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

use tool_monitoring\local\metrics\metric_interface;

/**
 * Hook dispatched at the very call on the metrics api.
 */
#[\core\attribute\label('Hook dispatched at the very call on the metrics api.')]
#[\core\attribute\tags('metric')]
final class gather_metrics {

    /**
     * List of registered metrics class names.
     *
     * @var class-string<metric_interface>[]
     */
    private array $metrics = [];

    /**
     * Register a metrics class through its class name.
     *
     * @param class-string<metric_interface> $metric The reference to a class implementing {@see metric_interface}.
     * @return void
     */
    public function add_metric(string $metric) {
        $this->metrics[] = $metric;
    }

    /**
     * Get all registered metrics class names.
     * @return class-string<metric_interface>[]
     */
    public function get_metrics() {
        return $this->metrics;
    }
}

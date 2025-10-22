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

use tool_monitoring\metric;

/**
 * Hook dispatched at the very call on the metrics api.
 */
#[\core\attribute\label('Hook dispatched at the very call on the metrics api.')]
#[\core\attribute\tags('metric')]
final class gather_metrics {

    /**
     * List of registered metrics.
     *
     * @var metric[]
     */
    private array $metrics = [];

    /**
     * Registers a metric.
     *
     * @param metric $metric
     */
    public function add_metric(metric $metric) {
        $this->metrics[] = $metric;
    }

    /**
     * Get all registered metrics class names.
     *
     * @return metric[]
     */
    public function get_metrics(): array {
        return $this->metrics;
    }
}

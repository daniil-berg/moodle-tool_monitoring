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
use core\hook\manager;
use tool_monitoring\hook\gather_metrics;

/**
 * Metrics manager to gather all available metrics and operations.
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

    /** @var metric[] All available metrics in the system indexed by name. */
    protected array $metrics = [];

    /**
     * Dispatches the {@see gather_metrics} hook and then stores all available metrics.
     */
    public function __construct() {
        $hook = new gather_metrics();
        di::get(manager::class)->dispatch($hook);
        $this->metrics = $hook->get_metrics();
    }

    /**
     * Returns the registered metrics.
     *
     * Optionally filters the metrics by tag.
     *
     * @param string|null $tag If provided, only metrics with that tag will be returned.
     * @return metric[] Metrics indexed by their name.
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
}

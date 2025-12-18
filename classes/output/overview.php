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
 * Definition of the renderable {@see overview} class.
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

namespace tool_monitoring\output;

use core\exception\moodle_exception;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use moodle_url;
use tool_monitoring\registered_metric;

/**
 * Provides information about all available metrics and links to their configuration pages.
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
final readonly class overview implements renderable, templatable {

    /**
     * Constructor without additional logic.
     *
     * @param array<string, registered_metric> $metrics Metrics for which to render the overview, indexed by qualified name.
     */
    public function __construct(
        private array $metrics,
    ) {}

    /**
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): array {
        $lines = [];
        foreach ($this->metrics as $qualifiedname => $metric) {
            $configurl = new moodle_url('/admin/tool/monitoring/configure.php', ['metric' => $qualifiedname]);
            $lines[] = [
                'component'   => $metric->component,
                'name'        => $metric->name,
                'type'        => $metric->type->value,
                'description' => $metric->description->out(),
                'configurl'   => $configurl->out(false),
            ];
        }
        return ['metrics' => $lines];
    }
}

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

/**
 * Definition of the {@see metric_calculation_failed} class.
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

namespace tool_monitoring\exceptions;

use Exception;

/**
 * Wrapper for any {@see Exception} that occurred when trying to call {@see metric::calculate}.
 *
 * To view the original exception, use the {@see self::getPrevious `getPrevious`} method.
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
class metric_calculation_failed extends tool_monitoring_exception {
    /**
     * Passes the qualified name through to the parent constructor as the {@see parent::$a `a`} context.
     *
     * Also passes information about the underlying exception as {@see parent::$debuginfo `debuginfo`} as well as the exception
     * itself as the `previous` argument to the parent constructor.
     *
     * @param string $qualifiedname Name of the affected metric.
     * @param Exception $previous Original exception.
     */
    public function __construct(
        /** @var string Name of the affected metric. */
        public readonly string $qualifiedname,
        Exception $previous,
    ) {
        parent::__construct(
            a: ['qualifiedname' => $qualifiedname],
            debuginfo: get_class($previous) . ': ' . $previous->getMessage(),
            previous: $previous,
        );
    }
}

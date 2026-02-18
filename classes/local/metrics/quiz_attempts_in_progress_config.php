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
 * Definition of the {@see quiz_attempts_in_progress_config} class.
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

namespace tool_monitoring\local\metrics;

use core\attribute\label;
use core\exception\coding_exception;
use tool_monitoring\simple_metric_config;

/**
 * Defines the config for the {@see quiz_attempts_in_progress} metric.
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
final class quiz_attempts_in_progress_config extends simple_metric_config {
    /**
     * Constructor without additional logic.
     *
     * @param int $maxidleseconds Do not count attempts that are idle longer than this number of seconds.
     * @param int $maxdeadlineseconds Do not count attempts that have a deadline in more than this number of seconds.
     * @throws coding_exception
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    public function __construct(
        /** @var int Do not count attempts that are idle longer than this number of seconds. */
        #[label('Maximum idle time (seconds)')]
        public readonly int $maxidleseconds = 600,
        /** @var int Do not count attempts that have a deadline in more than this number of seconds. */
        #[label('Maximum deadline time (seconds)')]
        public readonly int $maxdeadlineseconds = 3600,
    ) {
        if ($maxidleseconds <= 0 || $maxdeadlineseconds <= 0) {
            throw new coding_exception('Time values must be positive.');
        }
    }
}

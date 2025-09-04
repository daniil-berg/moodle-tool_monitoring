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
 * Definition of the abstract {@see simple_metric} class.
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

use core\exception\coding_exception;
use tool_monitoring\local\unqualified_class_name;

/**
 * Encapsulates a simple unlabeled metric value.
 *
 * Concrete classes extending the {@see metric} class must implement the {@see calculate} method, in addition to
 * the {@see described_metric::get_description} and {@see described_metric::get_type} methods.
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
abstract class simple_metric implements described_metric {
    use unqualified_class_name;

    /**
     * @var float|int Internal store of the metric values.
     */
    protected float|int $value;

    public function get_value(): float|int {
        if (!isset($this->value)) {
            // TODO: Replace with custom exception type.
            throw new coding_exception('Metric value not set');
        }
        return $this->value;
    }

    /**
     * Sets the current metric value on the instance.
     *
     * This method is what exporters will ultimately call before passing on the metric values to their
     * respective monitoring service.
     *
     * Example implementation:
     * ```
     * class my_metric extends simple_metric {
     *     public function calculate(): void {
     *         global $DB;
     *         // Maybe fetch value from the database.
     *         // Then assign that value to the metric.
     *         $this->value = 42;
     *     }
     *     // Implementations of other methods...
     * }
     * ```
     */
    abstract public function calculate(): void;
}

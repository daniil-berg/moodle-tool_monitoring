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
 * Definition of the abstract {@see configurable_metric} class.
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

/**
 * Base class for all metrics that allow configuration.
 *
 * This allows creating metrics that can be configured in the admin area by defining a form and default values.
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
abstract class configurable_metric extends metric {
    /** @var array The configuration of the metric. */
    protected array $config = [];

    /**
     * Form definition for the metric configuration.
     *
     * Please make sure to call {@see form\config::definition()} in your definition.
     *
     * @param mixed ...$args arguments that have to be passed to the moodleform constructor
     */
    abstract public static function get_config_form(...$args): form\config;

    /**
     * Default values for the metric configuration.
     *
     * @return array
     */
    abstract public static function get_config_default(): array;

    /**
     * Sets the metric configuration.
     *
     * Must be called before {@see calculate()}.
     *
     * @param array $config
     * @return void
     */
    public function set_config(array $config): void {
        $this->config = $config;
    }

    /**
     * Save additional configuration data (optional).
     *
     * This method is called when the metric manager saves the configuration. This gives the metric a chance
     * to save additional data at other places.
     *
     * @param int $metricid
     * @param \stdClass $data as returned by {@see form\config::get_data()}
     * @return void
     */
    public static function save_additional_config(int $metricid, \stdClass $data): void {
        // Does nothing by default.
    }
}

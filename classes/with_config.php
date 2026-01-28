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
 * Definition of the {@see with_config} trait.
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

/**
 * Extends a {@see metric} allowing it to define a custom {@see metric_config} for itself.
 *
 * For convenience and type safety, the {@see get_config} method allows subclasses to parse their configuration,
 * typically from within the {@see metric::calculate} method.
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
trait with_config {
    /** @var string|null Metric-specific config in JSON format. */
    public string|null $configjson = null;

    /**
     * Calls the {@see metric_config::from_json} method of the specified class to parse the config JSON.
     *
     * @phpcs:disable moodle.Commenting.ValidTags.Invalid
     * @template ConfT of metric_config
     * @param string $class Config class implementing {@see metric_config} to construct the object from.
     * TODO: Replace `string` here with `class-string<ConfT>` when `local_moodlecheck` finally goes the way of the dodo.
     * @return ConfT Config object of the provided class.
     * @throws coding_exception The {@see configjson} is not set or `$class` does not implement the {@see metric_config}.
     */
    public function parse_config(string $class): metric_config {
        if (!isset($this->configjson)) {
            throw new coding_exception('Metric config JSON is not set.');
        }
        $configclass = metric_config::class;
        if (!is_subclass_of($class, $configclass)) {
            throw new coding_exception("Provided class '$class' does not implement '$configclass'");
        }
        return $class::from_json($this->configjson);
    }

    /**
     * Returns the default config for the metric.
     *
     * @return metric_config Config object.
     */
    abstract public static function get_default_config(): metric_config;
}

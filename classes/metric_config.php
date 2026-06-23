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
 * Definition of the {@see metric_config} interface.
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

namespace tool_monitoring;

use JsonSerializable;
use tool_monitoring\exceptions\metric_config_invalid;

/**
 * Defines the optional configuration interface used by a {@see metric} that implements {@see metric_config_provider}.
 *
 * If a metric has specific associated configuration options, those are represented as a JSON object.
 * Classes must therefore implement the {@see JsonSerializable} interface as well as the {@see self::from_json `from_json`} method.
 * The former method determines what is saved in the database via {@see json_encode}, while the latter does the inverse.
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
interface metric_config extends JsonSerializable {
    /**
     * Constructs a new instance from a JSON config object.
     *
     * Should be the inverse of the {@see static::jsonSerialize `jsonSerialize`} method.
     *
     * @param string $json String of a valid JSON object (not an array or any other type).
     * @return static New instance of the config class.
     * @throws metric_config_invalid
     */
    public static function from_json(string $json): static;
}

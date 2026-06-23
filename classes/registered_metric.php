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
 * Definition of the {@see registered_metric} interface.
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

use core\lang_string;
use Exception;
use IteratorAggregate;
use tool_monitoring\exceptions\metric_calculation_failed;
use tool_monitoring\exceptions\metric_config_invalid;
use Traversable;

/**
 * Encapsulates the interface that a registered metric exposes to exporters and other consumers.
 *
 * **This interface is a consumer-only contract.**
 * Plugins and sub-plugins should use it for annotation but are discouraged from implementing it.
 * Implementations risk source-breaking changes whenever methods or properties are added to the interface.
 *
 * TODO Readonly-property PHPDoc annotations will be replaced by property `get`-hooks, once PHP 8.4 becomes the minimum requirement.
 *
 * @property-read string $qualifiedname Qualified name of the metric.
 * @property-read string $component Component defining the metric.
 * @property-read string $name Name of the metric.
 * @property-read lang_string $description Localized description of the metric.
 * @property-read metric_type $type Type of the metric.
 * @property-read bool $enabled If `false` the metric is currently not supposed to be calculated/exported.
 * @property-read array<string, metric_tag> $tags Tags on the metric, indexed by their normalized name.
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
interface registered_metric extends IteratorAggregate {
    /**
     * Produces the current {@see metric_value}s.
     *
     * This allows the instance to be iterated over in a `foreach` loop.
     *
     * @return Traversable<metric_value> Values of the metric.
     * @throws metric_calculation_failed An {@see Exception} occurred trying to produce the metric values.
     */
    #[\Override]
    public function getIterator(): Traversable;

    /**
     * Returns the current config.
     *
     * @return metric_config|null Metric config object or `null` if the metric is not configurable.
     * @throws metric_config_invalid Failed to deserialize the config of a configurable metric from JSON.
     */
    public function get_config(): metric_config|null;
}

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
 * Definition of the {@see mock_registered_metric} class.
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

namespace tool_monitoring\local\testing;

use core\lang_string;
use tool_monitoring\metric_tag;
use tool_monitoring\metric_type;

/**
 * Class that emulates part of the {@see registered_metric} interface.
 *
 * **TESTING ONLY: This exists purely to run unit tests.**
 *
 * @codeCoverageIgnore
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
final readonly class mock_registered_metric {
    /**
     * Constructor without additional logic.
     *
     * @param string $name Name of the metric.
     * @param string $component Name of the component that owns the metric.
     * @param metric_type $type Type of the metric.
     * @param lang_string $description Description of the metric.
     * @param bool $enabled Whether the metric is enabled.
     * @param array<string, metric_tag> $tags Tags associated with the metric.
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    public function __construct(
        /** @var string Name of the metric. */
        public string $name,
        /** @var string Name of the component that owns the metric. */
        public string $component = 'tool_monitoring',
        /** @var metric_type Type of the metric. */
        public metric_type $type = metric_type::COUNTER,
        /** @var lang_string Description of the metric. */
        public lang_string $description = new lang_string('pluginname', 'tool_monitoring'),
        /** @var bool Whether the metric is enabled. */
        public bool $enabled = false,
        /** @var array<string, metric_tag> Tags associated with the metric. */
        public array $tags = [],
    ) {}
}

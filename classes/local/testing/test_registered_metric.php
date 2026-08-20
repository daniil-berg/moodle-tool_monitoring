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

namespace tool_monitoring\local\testing;

use core\exception\coding_exception;
use core\lang_string;
use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_config_provider;
use tool_monitoring\metric_tag;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;
use tool_monitoring\registered_metric;
use Traversable;

/**
 * Example implementation of {@see registered_metric}.
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
class test_registered_metric implements registered_metric {
    /** @var string Qualified name of the metric. */
    public string $qualifiedname;

    /**
     * Sets the {@see self::$qualifiedname} property.
     *
     * @param string $name Name of the metric.
     * @param string $component Component defining the metric.
     * @param lang_string $description Localized description of the metric.
     * @param metric_type $type Type of the metric.
     * @param bool $enabled If `false` the metric is currently not supposed to be calculated/exported.
     * @param array<string, metric_tag> $tags Tags on the metric, indexed by their normalized name.
     * @param metric_config|null $config Metric-specific config to return from {@see metric::get_config `get_config`}.
     * @param metric_value[] $values Values to produce during iteration.
     */
    public function __construct(
        /** @var string Name of the metric. */
        public string $name,
        /** @var string Component defining the metric. */
        public string $component = 'tool_monitoring',
        /** @var lang_string Localized description of the metric. */
        public lang_string $description = new test_lang_string(),
        /** @var metric_type Type of the metric. */
        public metric_type $type = metric_type::COUNTER,
        /** @var bool If `false` the metric is currently not supposed to be calculated/exported. */
        public bool $enabled = true,
        /** @var array<string, metric_tag> Tags on the metric, indexed by their normalized name. */
        public array $tags = [],
        /** @var metric_config|null Metric-specific config returned from {@see metric::get_config `get_config`}. */
        private metric_config|null $config = null,
        /** @var metric_value[] Values produced during iteration. */
        private array $values = [],
    ) {
        $this->qualifiedname = $component . '_' . $name;
    }

    /**
     * Creates a new instance from a raw metric.
     *
     * The metric is immediately calculated, and the resulting values are passed to the constructor.
     * If the metric implements {@see metric_config_provider}, the default config is passed to the constructor.
     *
     * @param metric $metric Metric to create an instance from.
     * @param array<string, metric_tag> $tags Tags to associate with the metric, indexed by their normalized name.
     * @param bool $enabled Passed to the constructor.
     * @return static New instance.
     * @throws coding_exception
     */
    public static function from_metric(metric $metric, array $tags = [], bool $enabled = true): static {
        $config = $metric instanceof metric_config_provider ? $metric->get_default_config() : null;
        $values = $metric->calculate($config);
        return new static(
            name: $metric->get_name(),
            component: $metric->get_component(),
            description: $metric->get_description(),
            type: $metric->get_type(),
            enabled: $enabled,
            tags: $tags,
            config: $config,
            values: $values instanceof metric_value ? [$values] : iterator_to_array($values),
        );
    }

    #[\Override]
    public function get_config(): metric_config|null {
        return $this->config;
    }

    #[\Override]
    public function getIterator(): Traversable {
        yield from $this->values;
    }
}

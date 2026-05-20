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
 * Definition of the {@see test_metric_with_config} class.
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
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;
use tool_monitoring\metric_with_config;

/**
 * Example metric with a custom config that allows setting arbitrary values to be returned by its methods.
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
class test_metric_with_config extends metric_with_config {
    /** @var iterable<metric_value>|metric_value Metric values produced by the {@see self::calculate `calculate`} method. */
    private iterable|metric_value $values = [];

    /** @var metric_type Type returned from the {@see self::get_type `get_type`} method. */
    private metric_type $type = metric_type::COUNTER;

    /** @var string String returned from the {@see self::get_name `get_name`} method. */
    private string $name = 'test_metric_with_config';

    /** @var lang_string|null Language string returned from the {@see self::get_description `get_description`} method. */
    private lang_string|null $description = null;

    /**
     * Returns a new instance with the specified attributes.
     *
     * @param string $name String to return from the {@see metric::get_name `get_name`} method.
     * @param lang_string $description Language string to return from the {@see self::get_description `get_description`} method.
     * @param metric_type $type Type to return from the {@see metric::get_type `get_type`} method.
     * @param iterable|metric_value $values Values to produce from the {@see metric::calculate `calculate`} method.
     * @return static New configured instance.
     */
    public static function create(
        string $name = 'test_metric_with_config',
        lang_string $description = new lang_string('pluginname', 'tool_monitoring'), // Just an arbitrary existing language string.
        metric_type $type = metric_type::COUNTER,
        iterable|metric_value $values = [],
    ): static {
        $metric = new static();
        $metric->values = $values;
        $metric->type = $type;
        $metric->description = $description;
        $metric->name = $name;
        return $metric;
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable|metric_value {
        return $this->values;
    }

    #[\Override]
    public function get_type(): metric_type {
        return $this->type;
    }

    #[\Override]
    public function get_description(): lang_string {
        return $this->description ?? new lang_string('pluginname', 'tool_monitoring'); // Just an arbitrary existing language string.
    }

    #[\Override]
    public function get_name(): string {
        return $this->name;
    }

    #[\Override]
    public static function get_default_config(): test_simple_metric_config_minimal {
        return new test_simple_metric_config_minimal();
    }
}

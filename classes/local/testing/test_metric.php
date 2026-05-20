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
 * Definition of the {@see test_metric} class.
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
use tool_monitoring\metric;
use tool_monitoring\metric_config;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Example metric that allows setting arbitrary values to be returned by its methods.
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
class test_metric extends metric {
    /** @var iterable<metric_value>|metric_value Metric values produced by the {@see self::calculate `calculate`} method. */
    private iterable|metric_value $values = [];

    /** @var metric_type Type returned from the {@see self::get_type `get_type`} method. */
    private metric_type $type = metric_type::COUNTER;

    /** @var string String returned from the {@see self::get_name `get_name`} method. */
    private string $name = 'test_metric';

    /**
     * Returns a new instance with the specified attributes.
     *
     * @param string $name String to return from the {@see metric::get_name `get_name`} method.
     * @param metric_type $type Type to return from the {@see metric::get_type `get_type`} method.
     * @param iterable|metric_value $values Values to produce from the {@see metric::calculate `calculate`} method.
     * @return static New configured instance.
     */
    public static function create(
        string $name = 'test_metric',
        metric_type $type = metric_type::COUNTER,
        iterable|metric_value $values = [],
    ): static {
        $metric = new static();
        $metric->values = $values;
        $metric->type = $type;
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
        // Just an arbitrary existing language string.
        return new lang_string('pluginname', 'tool_monitoring');
    }

    #[\Override]
    public function get_name(): string {
        return $this->name;
    }
}

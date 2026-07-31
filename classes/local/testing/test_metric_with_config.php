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
use tool_monitoring\metric_config;
use tool_monitoring\metric_config_provider;
use tool_monitoring\metric_value;

/**
 * Example metric with a custom config that allows setting arbitrary values to be returned by its methods.
 *
 * **TESTING ONLY: This exists purely to run unit tests.**
 *
 * @property-read metric_config|null $lastconfig Last config passed to {@see self::calculate}.
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
class test_metric_with_config extends test_metric implements metric_config_provider {
    /** @var metric_config|null Last config passed to {@see self::calculate}. */
    private metric_config|null $lastconfig = null;

    /**
     * Getter for the last config passed to {@see self::calculate}.
     *
     * TODO Replace with property hook.
     *
     * @param string $name Property name.
     * @return metric_config|null
     * @throws coding_exception
     */
    public function __get(string $name): metric_config|null {
        return match ($name) {
            'lastconfig' => $this->lastconfig,
            default      => throw new coding_exception('Undefined property: ' . self::class . '::$' . $name),
        };
    }

    /**
     * Checks whether a previous call to {@see self::calculate} passed a non-`null` config.
     *
     * @param string $name Property name.
     * @return bool
     */
    public function __isset(string $name): bool {
        return match ($name) {
            'lastconfig' => isset($this->lastconfig),
            default      => false,
        };
    }

    #[\Override]
    public function calculate(metric_config|null $config = null): iterable|metric_value {
        $this->lastconfig = $config;
        return parent::calculate($config);
    }

    #[\Override]
    public function get_default_config(): test_simple_metric_config_minimal {
        return new test_simple_metric_config_minimal();
    }
}

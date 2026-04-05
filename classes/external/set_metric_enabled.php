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
 * Definition of the {@see set_metric_enabled} class.
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

namespace tool_monitoring\external;

use context_system;
use core\exception\coding_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\restricted_context_exception;
use dml_exception;
use invalid_parameter_exception;
use required_capability_exception;
use tool_monitoring\metrics_manager;

/**
 * External service to enable or disable a monitoring metric.
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
final class set_metric_enabled extends external_api {
    /**
     * Describes parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'metric' => new external_value(PARAM_ALPHAEXT, 'Qualified metric name'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether to enable the metric'),
        ]);
    }

    /**
     * Enables or disables a metric.
     *
     * @param string $qualifiedname Qualified metric name.
     * @param bool $enabled Desired enabled state.
     * @return array<string, mixed> Empty result.
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function execute(string $qualifiedname, bool $enabled): array {
        ['metric' => $qualifiedname, 'enabled' => $enabled] = self::validate_parameters(
            self::execute_parameters(),
            [
                'metric' => $qualifiedname,
                'enabled' => $enabled,
            ],
        );
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('tool/monitoring:manage_metrics', $context);
        $manager = new metrics_manager();
        $metric = $manager->sync()[$qualifiedname] ?? null;
        if (is_null($metric)) {
            throw new invalid_parameter_exception("Unknown metric '$qualifiedname'.");
        }
        $metric->persist_enabled_state($enabled);
        return [];
    }

    /**
     * Describes return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([]);
    }
}

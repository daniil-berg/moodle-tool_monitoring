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
 * Definition of the {@see metric_with_config_test} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace tool_monitoring;

use advanced_testcase;
use core\exception\coding_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use tool_monitoring\exceptions\metric_config_not_implemented;
use tool_monitoring\local\testing\custom_metric_config;
use tool_monitoring\local\testing\metric_with_custom_config;

/**
 * Unit tests for the {@see metric_with_config} class.
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
#[CoversClass(metric_with_config::class)]
final class metric_with_config_test extends advanced_testcase {
    /**
     * Tests the {@see metric_with_config::parse_config} method.
     *
     * @throws coding_exception
     * @throws metric_config_not_implemented
     */
    #[DataProvider('provider_test_parse_config')]
    public function test_parse_config(string|null $configjson, string $class, metric_config|string $expected): void {
        $metric = new metric_with_custom_config();
        $metric->configjson = $configjson;
        if (is_string($expected)) {
            $this->expectException($expected);
            $metric->parse_config($class);
        } else {
            $output = $metric->parse_config($class);
            self::assertEquals($expected, $output);
        }
    }

    /**
     * Provides test data for the {@see test_parse_config} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_parse_config(): array {
        return [
            'Config JSON not set' => [
                'configjson' => null,
                'class' => metric_with_custom_config::class,
                'expected' => coding_exception::class,
            ],
            'Class is not a metric config' => [
                'configjson' => '{}',
                'class' => stdClass::class,
                'expected' => metric_config_not_implemented::class,
            ],
            'Valid metric config' => [
                'configjson' => '{"foo":"baz","spam":0}',
                'class' => custom_metric_config::class,
                'expected' => new custom_metric_config('baz', 0),
            ],
        ];
    }
}

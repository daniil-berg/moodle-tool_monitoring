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
 * Definition of the {@see metric_calculation_failed_test} class.
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

namespace tool_monitoring\exceptions;

use advanced_testcase;
use core\exception\coding_exception;
use dml_missing_record_exception;
use Exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the {@see metric_calculation_failed} class.
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
#[CoversClass(metric_calculation_failed::class)]
#[CoversClass(tool_monitoring_exception::class)]
final class metric_calculation_failed_test extends advanced_testcase {
    /**
     * Tests the constructor.
     *
     * @param string $qualifiedname Passed to the constructor.
     * @param Exception $previous Original exception to wrap in an instance of {@see metric_calculation_failed} and expected output
     *                            from the {@see Exception::getPrevious `getPrevious`} method.
     */
    #[DataProvider('provider_test___construct')]
    public function test___construct(string $qualifiedname, Exception $previous): void {
        $exception = new metric_calculation_failed(qualifiedname: $qualifiedname, previous: $previous);
        $expecteddebuginfo = get_class($previous) . ': ' . $previous->getMessage();
        self::assertSame($qualifiedname, $exception->qualifiedname);
        self::assertSame('error:metric_calculation_failed', $exception->errorcode);
        self::assertSame('tool_monitoring', $exception->module);
        self::assertSame(['qualifiedname' => $qualifiedname], $exception->a);
        self::assertSame($expecteddebuginfo, $exception->debuginfo);
        self::assertTrue(get_string_manager()->string_exists('error:metric_calculation_failed', 'tool_monitoring'));
        self::assertSame("Could not calculate metric \"$qualifiedname\". ($expecteddebuginfo)", $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * Provides test data for the {@see test___construct} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test___construct(): array {
        return [
            [
                'qualifiedname' => 'component_foo_metric',
                'previous' => new coding_exception('Oh no!'),
            ],
            [
                'qualifiedname' => 'component_bar_metric',
                'previous' => new json_invalid(),
            ],
            [
                'qualifiedname' => 'component_baz_metric',
                'previous' => new dml_missing_record_exception('baz_table'),
            ],
            [
                'qualifiedname' => 'component_quux_metric',
                'previous' => new JsonException('Not a moodle_exception'),
            ],
        ];
    }
}

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
 * Definition of the {@see tool_monitoring_exception_test} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace tool_monitoring\exceptions;

use advanced_testcase;
use core\exception\moodle_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the {@see tool_monitoring_exception} class and its subclasses.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(form_data_value_missing::class)]
#[CoversClass(json_invalid::class)]
#[CoversClass(json_key_missing::class)]
#[CoversClass(metric_config_not_implemented::class)]
#[CoversClass(simple_metric_config_constructor_missing::class)]
#[CoversClass(tag_not_found::class)]
#[CoversClass(tool_monitoring_exception::class)]
final class tool_monitoring_exception_test extends advanced_testcase {
    /**
     * Tests the constructor of the given class.
     *
     * @param class-string<tool_monitoring_exception> $exceptionclass Name of the exception class to construct.
     * @param array<string, mixed> $properties Arguments to unpack into the constructor. These are also the expected properties of
     *                                         the exception instance and its expected {@see moodle_exception::$a `a`} context.
     * @param string $errorcode Expected {@see moodle_exception::$errorcode `errorcode`} value of the exception instance.
     * @param string $module Expected {@see moodle_exception::$module `module`} value of the exception instance.
     * @param string $message Expected message returned by the exception's {@see Exception::getMessage `getMessage`} method.
     */
    #[DataProvider('provider_test___construct')]
    public function test___construct(
        string $exceptionclass,
        array $properties,
        string $errorcode,
        string $module,
        string $message,
    ): void {
        $exception = new $exceptionclass(...array_values($properties));
        self::assertInstanceOf(moodle_exception::class, $exception); // Sanity check.
        foreach ($properties as $key => $value) {
            self::assertSame($value, $exception->$key);
        }
        self::assertSame($errorcode, $exception->errorcode);
        self::assertSame($module, $exception->module);
        self::assertSame($properties, $exception->a);
        self::assertTrue(get_string_manager()->string_exists($errorcode, $module));
        self::assertSame($message, $exception->getMessage());
    }

    /**
     * Provides test data for the {@see test___construct} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test___construct(): array {
        return [
            [
                'exceptionclass' => form_data_value_missing::class,
                'properties' => ['fieldname' => 'foo'],
                'errorcode' => 'error:form_data_value_missing',
                'module' => 'tool_monitoring',
                'message' => 'Form data is missing a value for the "foo" field.',
            ],
            [
                'exceptionclass' => json_invalid::class,
                'properties' => [],
                'errorcode' => 'error:json_invalid',
                'module' => 'tool_monitoring',
                'message' => 'Invalid JSON encountered or the top-level type in that JSON is wrong.',
            ],
            [
                'exceptionclass' => json_key_missing::class,
                'properties' => ['key' => 'bar'],
                'errorcode' => 'error:json_key_missing',
                'module' => 'tool_monitoring',
                'message' => 'JSON object is missing the "bar" key.',
            ],
            [
                'exceptionclass' => metric_config_not_implemented::class,
                'properties' => ['classname' => 'baz'],
                'errorcode' => 'error:metric_config_not_implemented',
                'module' => 'tool_monitoring',
                'message' => 'The "baz" class does not implement the "metric_config" interface.',
            ],
            [
                'exceptionclass' => simple_metric_config_constructor_missing::class,
                'properties' => ['classname' => 'spam'],
                'errorcode' => 'error:simple_metric_config_constructor_missing',
                'module' => 'tool_monitoring',
                'message' => 'The "spam" class does not have a constructor.',
            ],
            [
                'exceptionclass' => tag_not_found::class,
                'properties' => ['tagname' => 'eggs', 'collectionname' => 'beans'],
                'errorcode' => 'error:tag_not_found',
                'module' => 'tool_monitoring',
                'message' => 'No tag named "eggs" exists in the "beans" collection.',
            ],
        ];
    }
}

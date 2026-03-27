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
 * Definition of the {@see simple_metric_config_test} class.
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

namespace tool_monitoring;

use advanced_testcase;
use core\exception\coding_exception;
use core\lang_string;
use MoodleQuickForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use stdClass;
use tool_monitoring\exceptions\form_data_value_missing;
use tool_monitoring\exceptions\json_invalid;
use tool_monitoring\exceptions\json_key_missing;
use tool_monitoring\exceptions\simple_metric_config_constructor_missing;
use tool_monitoring\form\config as config_form;
use tool_monitoring\local\testing\testing_simple_metric_config;
use tool_monitoring\local\testing\testing_simple_metric_config_cache;
use tool_monitoring\local\testing\testing_simple_metric_config_missing_constructor;

/**
 * Unit tests for the {@see simple_metric_config} class.
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
#[CoversClass(simple_metric_config::class)]
final class simple_metric_config_test extends advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        // We are using the `MoodleQuickForm` class. Hopefully, requiring the `formslib.php` will not be needed soon.
        require_once("$CFG->libdir/formslib.php");
    }

    public function test_serialize(): void {
        $config = new testing_simple_metric_config('spam');
        $output = $config->jsonSerialize();
        self::assertSame($output, $config);
        self::assertSame(
            '{"notpromotedstring":"bar","publicstringrequired":"spam","publicobj":{},"publicbool":true,"publicunion":null}',
            json_encode($config),
        );
    }

    /**
     * Tests the {@see simple_metric_config::from_json `from_json`} method.
     *
     * @param string $json JSON string to parse.
     * @param array<string, mixed>|string $expected Expected properties of the returned object or exception class name.
     * @throws json_invalid
     * @throws json_key_missing
     * @throws simple_metric_config_constructor_missing
     */
    #[DataProvider('provider_test_from_json')]
    public function test_from_json(string $json, array|string $expected): void {
        if (is_string($expected)) {
            $this->expectException($expected);
            testing_simple_metric_config::from_json($json);
            return;
        }
        $config = testing_simple_metric_config::from_json($json);
        foreach ($expected as $name => $value) {
            self::assertEquals($value, $config->$name);
        }
    }

    /**
     * Provides test data for the {@see test_from_json} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_from_json(): array {
        return [
            'Empty JSON' => [
                'json' => '',
                'expected' => json_invalid::class,
            ],
            'Invalid JSON' => [
                'json' => 'invalid json',
                'expected' => json_invalid::class,
            ],
            'Just a string' => [
                'json' => '"spam"',
                'expected' => json_invalid::class,
            ],
            'Top-level array' => [
                'json' => '[1, 2, 3]',
                'expected' => json_invalid::class,
            ],
            'Missing required key' => [
                'json' => '{"publicobj": {}, "protectedint": 0, "privatefloat": 0.0, "publicbool": true}',
                'expected' => json_key_missing::class,
            ],
            'Missing some optional keys' => [
                'json' => '{"publicstringrequired": "spam", "notpromotedstring": "baz"}',
                'expected' => [
                    'publicstringrequired' => 'spam',
                    'publicobj' => new stdClass(),
                    'publicbool' => true,
                    'publicunion' => null,
                    'notpromotedstring' => 'baz',
                ],
            ],
        ];
    }

    /**
     * Tests the {@see simple_metric_config::with_form_data `with_form_data`} method.
     *
     * @param stdClass $formdata Form data to construct the object from.
     * @param array<string, mixed>|string $expected Expected properties of the returned object or exception class name.
     * @throws form_data_value_missing
     * @throws simple_metric_config_constructor_missing
     */
    #[DataProvider('provider_test_with_form_data')]
    public function test_with_form_data(stdClass $formdata, array|string $expected): void {
        if (is_string($expected)) {
            $this->expectException($expected);
            testing_simple_metric_config::with_form_data($formdata);
            return;
        }
        $config = testing_simple_metric_config::with_form_data($formdata);
        foreach ($expected as $name => $value) {
            self::assertEquals($value, $config->$name);
        }
    }

    /**
     * Provides test data for the {@see test_with_form_data} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_with_form_data(): array {
        return [
            'Missing required field value' => [
                'formdata' => (object) ['publicobj' => new stdClass(), 'protectedint' => 0, 'privatefloat' => 0.0],
                'expected' => form_data_value_missing::class,
            ],
            'Missing some optional field values' => [
                'formdata' => (object) ['publicstringrequired' => 'spam', 'notpromotedstring' => 'baz'],
                'expected' => [
                    'publicstringrequired' => 'spam',
                    'publicobj' => new stdClass(),
                    'publicbool' => true,
                    'publicunion' => null,
                    'notpromotedstring' => 'baz',
                ],
            ],
        ];
    }

    public function test_to_form_data(): void {
        $config = new testing_simple_metric_config('spam');
        self::assertEquals(
            [
                'notpromotedstring' => 'bar',
                'publicstringrequired' => 'spam',
                'publicobj' => new stdClass(),
                'publicbool' => true,
                'publicunion' => null,
            ],
            $config->to_form_data(),
        );
    }

    /**
     * Tests the {@see simple_metric_config::get_form_definition `get_form_definition`} method.
     *
     * @throws coding_exception
     * @throws simple_metric_config_constructor_missing
     */
    public function test_extend_form_definition(): void {
        $mockconfigform = $this->createMock(config_form::class);
        $mockmform = $this->createMock(MoodleQuickForm::class);
        $expectedcalls = [
            ['addElement', 'text', 'publicstringrequired', self::get_lang_string('publicstringrequired')],
            ['setType', 'publicstringrequired', PARAM_TEXT],
            ['addHelpButton', 'publicstringrequired', self::get_lang_string_id('publicstringrequired'), 'tool_monitoring'],
            ['addElement', 'text', 'publicobj', self::get_lang_string('publicobj')],
            ['setType', 'publicobj', PARAM_TEXT],
            ['addElement', 'text', 'protectedint', self::get_lang_string('protectedint')],
            ['setType', 'protectedint', PARAM_INT],
            ['addRule', 'protectedint', null, 'numeric', null, 'client'],
            ['addElement', 'text', 'privatereadonlyfloat', self::get_lang_string('privatereadonlyfloat')],
            ['setType', 'privatereadonlyfloat', PARAM_FLOAT],
            ['addRule', 'privatereadonlyfloat', null, 'numeric', null, 'client'],
            ['addElement', 'advcheckbox', 'publicbool', self::get_lang_string('publicbool')],
            ['setType', 'publicbool', PARAM_BOOL],
            ['addElement', 'text', 'publicunion', self::get_lang_string('publicunion')],
            ['setType', 'publicunion', PARAM_TEXT],
            ['addElement', 'text', 'notpromotedstring', self::get_lang_string('notpromotedstring')],
            ['setType', 'notpromotedstring', PARAM_TEXT],
        ];
        $calls = [];
        $mockmform->expects($this->exactly(7))->method('addElement')->willReturnCallback(
            function (string $type, string $name, lang_string $label) use (&$calls): void {
                $calls[] = ['addElement', $type, $name, $label];
            }
        );
        $mockmform->expects($this->exactly(7))->method('setType')->willReturnCallback(
            function (string $name, string $paramtype) use (&$calls): void {
                $calls[] = ['setType', $name, $paramtype];
            }
        );
        $mockmform->expects($this->exactly(2))->method('addRule')->willReturnCallback(
            function (string $name, null $message, string $type, null $format, string $validation) use (&$calls): void {
                $calls[] = ['addRule', $name, $message, $type, $format, $validation];
            }
        );
        $mockmform->expects($this->once())->method('addHelpButton')->willReturnCallback(
            function (string $name, string $identifier, string $component) use (&$calls): void {
                $calls[] = ['addHelpButton', $name, $identifier, $component];
            }
        );
        testing_simple_metric_config::extend_form_definition($mockconfigform, $mockmform);
        self::assertEquals($expectedcalls, $calls);
    }

    /**
     * Returns the correct language string ID for the given field name of {@see testing_simple_metric_config}.
     *
     * @param string $name Name of the field.
     * @return string Valid language string ID.
     */
    private static function get_lang_string_id(string $name): string {
        return "testing:metric:testing_simple_metric_config:$name";
    }

    /**
     * Returns the correct testing language string for the given field name of {@see testing_simple_metric_config}.
     *
     * @param string $name Name of the field.
     * @return lang_string Valid language string.
     * @throws coding_exception
     */
    private static function get_lang_string(string $name): lang_string {
        return new lang_string(self::get_lang_string_id($name), 'tool_monitoring');
    }

    public function test_extend_form_validation(): void {
        $mockdata = [];
        $mockconfigform = $this->createMock(config_form::class);
        $mockmform = $this->createMock(MoodleQuickForm::class);
        $output = testing_simple_metric_config::extend_form_validation($mockdata, $mockconfigform, $mockmform);
        self::assertSame([], $output);
    }

    /**
     * Calls the {@see simple_metric_config::from_json `from_json`} method on a class with no constructor.
     *
     * @throws json_invalid
     * @throws json_key_missing
     * @throws simple_metric_config_constructor_missing
     */
    public function test_missing_constructor(): void {
        $classname = testing_simple_metric_config_missing_constructor::class;
        $this->expectExceptionObject(new simple_metric_config_constructor_missing($classname));
        testing_simple_metric_config_missing_constructor::from_json('{"foo":"bar"}');
    }

    /**
     * Calls the {@see simple_metric_config::from_json `from_json`} method repeatedly to test the reflection cache.
     *
     * @throws json_invalid
     * @throws json_key_missing
     * @throws simple_metric_config_constructor_missing
     */
    public function test_constructorparameters_cache(): void {
        $cache = new ReflectionProperty(simple_metric_config::class, 'constructorparameters');
        // Verify there is no entry for that test class in the cache yet.
        self::assertArrayNotHasKey(testing_simple_metric_config_cache::class, $cache->getValue());
        // Call the method once. This should add an entry to the cache.
        testing_simple_metric_config_cache::from_json('{"foo":"baz","spam":1}');
        self::assertArrayHasKey(testing_simple_metric_config_cache::class, $cache->getValue());
        // Save cache entry for later. Should be an array of parameters.
        $cacheentry1 = $cache->getValue()[testing_simple_metric_config_cache::class];
        self::assertSame(['foo', 'spam'], array_keys($cacheentry1));
        // Call the method again.
        testing_simple_metric_config_cache::from_json('{"foo":"quux","spam":2}');
        // The cache entry should be the exact same array. Its values should be identical, not just equal.
        $cacheentry2 = $cache->getValue()[testing_simple_metric_config_cache::class];
        self::assertSame($cacheentry1, $cacheentry2);
    }
}

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
 * Definition of the {@see users_online_config_test} class.
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
 * {@noinspection PhpIllegalPsrClassPathInspection, PhpUnhandledExceptionInspection}
 */

namespace tool_monitoring\local\metrics;

use advanced_testcase;
use core\exception\coding_exception;
use core\lang_string;
use MoodleQuickForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Unit tests for the {@see users_online_config} class.
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
#[CoversClass(users_online_config::class)]
final class users_online_config_test extends advanced_testcase {
    /**
     * @param array $timewindows Arguments to unpack into the {@see users_online_config} constructor.
     * @param array<float|int>|string $expected Expected value of the {@see users_online_config::timewindows} property;
     *                                          exception class name if an exception is expected.
     * @throws coding_exception
     */
    #[DataProvider('provider_test___construct')]
    public function test___construct(array $timewindows, array|string $expected): void {
        if (is_string($expected)) {
            $this->expectException($expected);
        }
        $config = new users_online_config(...$timewindows);
        self::assertSame($expected, $config->timewindows);
    }

    /**
     * Provides test data for the {@see test___construct} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test___construct(): array {
        return [
            'Three integers already in order' => [
                'timewindows' => [1, 2, 3],
                'expected'    => [1, 2, 3],
            ],
            'Integers and floats not in order' => [
                'timewindows' => [3.14, 100, 1, 5],
                'expected'    => [1, 3.14, 5, 100],
            ],
            'Integers but with duplicates' => [
                'timewindows' => [1, 2, 3, 4, 3, 2, 1, 1],
                'expected'    => [1, 2, 3, 4],
            ],
            [
                'timewindows' => ['foo' => 1, 'bar' => 3, 'baz' => 2],
                'expected'    => [1, 2, 3],
            ],
            [
                'timewindows' => ['1', '3.14', 2, 0.69],
                'expected'    => [0.69, 1, 2, 3.14],
            ],
            [
                'timewindows' => [],
                'expected'    => coding_exception::class,
            ],
        ];
    }

    public function test_serialize(): void {
        $config = new users_online_config(1, 2, 3);
        self::assertSame($config, $config->jsonSerialize());
        self::assertSame('{"timewindows":[1,2,3]}', json_encode($config));
    }

    #[DataProvider('provider_test_from_json')]
    public function test_from_json(string $json, array|string $expected): void {
        if (is_string($expected)) {
            $this->expectException($expected);
        }
        $config = users_online_config::from_json($json);
        self::assertEquals($expected, $config->timewindows);
    }

    /**
     * Provides test data for the {@see test_from_json} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_from_json(): array {
        return [
            'Invalid JSON' => [
                'json' => '-',
                'expected' => coding_exception::class,
            ],
            'Not a JSON object, but a number' => [
                'json' => '1',
                'expected' => coding_exception::class,
            ],
            'Not a JSON object, but an array' => [
                'json' => '[1, 2, 3]',
                'expected' => coding_exception::class,
            ],
            "Missing 'timewindows' key" => [
                'json' => '{"foo":1}',
                'expected' => coding_exception::class,
            ],
            "Non array value of 'timewindows'" => [
                'json' => '{"timewindows":1}',
                'expected' => coding_exception::class,
            ],
            'Valid array of three integers' => [
                'json' => '{"timewindows":[1,2,3]}',
                'expected' => [1, 2, 3],
            ],
        ];
    }

    #[DataProvider('provider_test_with_form_data')]
    public function test_with_form_data(stdClass $data, array|string $expected): void {
        if (is_string($expected)) {
            $this->expectException($expected);
        }
        $config = users_online_config::with_form_data($data);
        self::assertEquals($expected, $config->timewindows);
    }

    /**
     * Provides test data for the {@see test_with_form_data} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_with_form_data(): array {
        return [
            "Missing 'timewindows' form data" => [
                'data' => (object) ['foo' => 'bar'],
                'expected' => coding_exception::class,
            ],
            "Non-string 'timewindows' value" => [
                'data' => (object) ['timewindows' => 1],
                'expected' => coding_exception::class,
            ],
            "Non-numeric value in 'timewindows' list" => [
                'data' => (object) ['timewindows' => '1,2,foo'],
                'expected' => coding_exception::class,
            ],
            'Valid array of integers and floats' => [
                'data' => (object) ['timewindows' => '1,2,3.14,0.01'],
                'expected' => [0.01, 1, 2, 3.14],
            ],
        ];
    }

    public function test_to_form_data(): void {
        $config = new users_online_config(1, 2, 3);
        self::assertSame(['timewindows' => '1, 2, 3'], $config->to_form_data());
    }

    public function test_extend_config_form(): void {
        $mockform = $this->createMock(MoodleQuickForm::class);
        $mockform->expects($this->once())
            ->method('addElement')
            ->with('text', 'timewindows', new lang_string('users_online_time_windows', 'tool_monitoring'));
        $mockform->expects($this->once())
            ->method('setType')
            ->with('timewindows', PARAM_TEXT);
        users_online_config::extend_config_form($mockform);
    }
}

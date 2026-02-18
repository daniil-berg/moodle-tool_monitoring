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
 * Definition of the {@see quiz_attempts_in_progress_config_test} class.
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the {@see quiz_attempts_in_progress_config} class.
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
#[CoversClass(quiz_attempts_in_progress_config::class)]
final class quiz_attempts_in_progress_config_test extends advanced_testcase {
    /**
     * Tests the {@see quiz_attempts_in_progress_config} constructor.
     *
     * @param int $maxidleseconds Passed to the {@see quiz_attempts_in_progress_config} constructor.
     * @param int $maxdeadlineseconds Passed to the {@see quiz_attempts_in_progress_config} constructor.
     * @param array<float|int>|string $expected Expected properties or exception class name if an exception is expected.
     * @throws coding_exception
     */
    #[DataProvider('provider_test___construct')]
    public function test___construct(int $maxidleseconds, int $maxdeadlineseconds, array|string $expected): void {
        if (is_string($expected)) {
            $this->expectException($expected);
        }
        $config = new quiz_attempts_in_progress_config(maxidleseconds: $maxidleseconds, maxdeadlineseconds: $maxdeadlineseconds);
        if (is_array($expected)) {
            foreach ($expected as $name => $value) {
                self::assertEquals($value, $config->$name);
            }
        }
    }

    /**
     * Provides test data for the {@see test___construct} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test___construct(): array {
        return [
            [
                'maxidleseconds'     => 1,
                'maxdeadlineseconds' => 1,
                'expected'           => [
                    'maxidleseconds'     => 1,
                    'maxdeadlineseconds' => 1,
                ],
            ],
            [
                'maxidleseconds'     => 100,
                'maxdeadlineseconds' => 1000,
                'expected'           => [
                    'maxidleseconds'     => 100,
                    'maxdeadlineseconds' => 1000,
                ],
            ],
            [
                'maxidleseconds'     => -1,
                'maxdeadlineseconds' => 1,
                'expected'           => coding_exception::class,
            ],
            [
                'maxidleseconds'     => 0,
                'maxdeadlineseconds' => 1,
                'expected'           => coding_exception::class,
            ],
            [
                'maxidleseconds'     => 1,
                'maxdeadlineseconds' => 0,
                'expected'           => coding_exception::class,
            ],
        ];
    }
}

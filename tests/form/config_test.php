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
 * Definition of the {@see config_test} class.
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

namespace tool_monitoring\form;

use advanced_testcase;
use core\exception\coding_exception;
use core\lang_string;
use dml_exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\metrics\users_online;
use tool_monitoring\local\testing\metric_settable_values;
use tool_monitoring\registered_metric;

/**
 * Unit tests for the {@see config} form class.
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
#[CoversClass(config::class)]
final class config_test extends advanced_testcase {
    /**
     * Tests all methods of the {@see config} form class.
     *
     * - {@see config::for_metric `for_metric`}
     * - {@see config::definition `definition`}
     * - {@see config::after_definition `after_definition`}
     * - {@see config::validation `validation`}
     * - {@see config::save `save`}}
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws JsonException
     */
    #[DataProvider('provider_test_all_methods')]
    public function test_all_methods(
        registered_metric $metric,
        array $validationdata,
        array $validationerrors,
    ): void {
        global $PAGE;
        $PAGE->set_url('/admin/tool/monitoring/configure.php', ['metric' => $metric->qualifiedname]);
        // Construction runs all the definition methods.
        $form = config::for_metric($metric);
        $errors = $form->validation($validationdata, []);
        self::assertCount(count($validationerrors), $errors);
        foreach ($validationerrors as $fieldname => $stringid) {
            self::assertArrayHasKey($fieldname, $errors);
            $error = $errors[$fieldname];
            self::assertInstanceOf(lang_string::class, $error);
            self::assertSame($stringid, $error->get_identifier());
            self::assertTrue(get_string_manager()->string_exists($error->get_identifier(), $error->get_component()));
        }
        $form->save(); // Without valid POST data (session token and all), the `get_data` method returns `null` and this is no-op.
    }

    /**
     * Provides test data for the {@see test_all_methods} method.
     *
     * @return array[] Arguments for the test method.
     * @throws JsonException
     */
    public static function provider_test_all_methods(): array {
        $testmetric = registered_metric::from_metric(new metric_settable_values());
        $usersonline = registered_metric::from_metric(new users_online());
        return [
            'Empty data' =>[
                'metric' => $testmetric,
                'validationdata' => [],
                'validationerrors' => [],
            ],
            'Valid values and unrelated fields' => [
                'metric' => $testmetric,
                'validationdata' => ['enabled' => 1, 'tags' => ['foo', 'bar'], 'some' => 'data'],
                'validationerrors' => [],
            ],
            'Valid config value in form data' => [
                'metric' => $usersonline,
                'validationdata' => ['timewindows' => '1,2,3'],
                'validationerrors' => [],
            ],
            'Invalid config value in form data' => [
                'metric' => $usersonline,
                'validationdata' => ['timewindows' => 'a,b,c'],
                'validationerrors' => [
                    'timewindows' => 'error:users_online_config:timewindows_invalid',
                ],
            ],
        ];
    }
}

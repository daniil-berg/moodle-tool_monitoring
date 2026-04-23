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
 * Definition of the {@see configure_test} class.
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

namespace tool_monitoring\output;

use advanced_testcase;
use core\exception\moodle_exception;
use core\output\renderer_base;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_monitoring\local\testing\metric_settable_values;
use tool_monitoring\registered_metric;

/**
 * Unit tests for the {@see configure} class.
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
#[CoversClass(configure::class)]
final class configure_test extends advanced_testcase {
    /**
     * Tests all {@see configure} methods.
     *
     * - {@see configure::__construct `__construct`}
     * - {@see configure::process_form `process_form`}.
     * - {@see configure::export_for_template `export_for_template`}.
     *
     * @throws JsonException
     * @throws moodle_exception
     */
    public function test_all_methods(): void {
        global $PAGE;
        $metric = registered_metric::from_metric(new metric_settable_values());
        $PAGE->set_url('/admin/tool/monitoring/configure.php', ['metric' => $metric->qualifiedname]);
        $configure = new configure($metric);
        $mockrenderer = $this->createMock(renderer_base::class);
        $templatecontext = $configure->export_for_template($mockrenderer);
        self::assertArrayHasKey('form', $templatecontext);
        self::assertIsString($templatecontext['form']);
        // Without valid POST data (session token and all), this always returns `false`.
        self::assertFalse($configure->process_form());
    }
}

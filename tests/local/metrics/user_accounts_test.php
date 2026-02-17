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
 * Definition of the {@see user_accounts_test} class.
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
use PHPUnit\Framework\Attributes\CoversClass;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Unit tests for the {@see user_accounts} class.
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
#[CoversClass(user_accounts::class)]
final class user_accounts_test extends advanced_testcase {
    public function test_get_type(): void {
        $metric = new user_accounts();
        self::assertSame(metric_type::GAUGE, $metric->get_type());
    }

    public function test_get_description(): void {
        $metric = new user_accounts();
        $description = $metric->get_description();
        self::assertSame('user_accounts_description', $description->get_identifier());
        self::assertSame('tool_monitoring', $description->get_component());
    }

    public function test_calculate(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        // For 'email' auth, create 4 active & non-deleted, 1 active & deleted, and 2 suspended & non-deleted users.
        $generator->create_user(['auth' => 'email', 'suspended' => 0, 'deleted' => 0]);
        $generator->create_user(['auth' => 'email', 'suspended' => 0, 'deleted' => 0]);
        $generator->create_user(['auth' => 'email', 'suspended' => 0, 'deleted' => 0]);
        $generator->create_user(['auth' => 'email', 'suspended' => 0, 'deleted' => 0]);
        $generator->create_user(['auth' => 'email', 'suspended' => 0, 'deleted' => 1]);
        $generator->create_user(['auth' => 'email', 'suspended' => 1, 'deleted' => 0]);
        $generator->create_user(['auth' => 'email', 'suspended' => 1, 'deleted' => 0]);
        // Admin and guest already exist and have 'manual' auth by default.
        // Add 1 more active & non-deleted user for 'manual' auth.
        $generator->create_user(['auth' => 'manual', 'suspended' => 0, 'deleted' => 0]);
        $metric = new user_accounts();
        $result = iterator_to_array($metric->calculate());
        // With 4 values per auth type and the 3 auth types 'email', 'nologin', and 'manual' we should get 12 metric values.
        self::assertCount(12, $result);
        foreach ($result as $value) {
            self::assertInstanceOf(metric_value::class, $value);
            $expectedvalue = match ($value->label) {
                // This count be the admin, the guest, and the user we just created.
                ['auth' => 'manual', 'suspended' => 'false', 'deleted' => 'false'] => 3,
                // These next three are the 'email' users we created above.
                ['auth' => 'email', 'suspended' => 'false', 'deleted' => 'false'] => 4,
                ['auth' => 'email', 'suspended' => 'false', 'deleted' => 'true'] => 1,
                ['auth' => 'email', 'suspended' => 'true', 'deleted' => 'false'] => 2,
                // All these other combinations should be zero.
                ['auth' => 'email', 'suspended' => 'true', 'deleted' => 'true'],
                ['auth' => 'nologin', 'suspended' => 'false', 'deleted' => 'false'],
                ['auth' => 'nologin', 'suspended' => 'false', 'deleted' => 'true'],
                ['auth' => 'nologin', 'suspended' => 'true', 'deleted' => 'false'],
                ['auth' => 'nologin', 'suspended' => 'true', 'deleted' => 'true'],
                ['auth' => 'manual', 'suspended' => 'false', 'deleted' => 'true'],
                ['auth' => 'manual', 'suspended' => 'true', 'deleted' => 'false'],
                ['auth' => 'manual', 'suspended' => 'true', 'deleted' => 'true'] => 0,
                // Fail gracefully if we have not exhaustively covered all possible combinations.
                default => $this->fail('Unexpected label: ' . json_encode($value->label)),
            };
            self::assertSame($expectedvalue, $value->value);
        }
    }
}

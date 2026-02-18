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
 * Definition of the {@see quiz_attempts_in_progress_test} class.
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
use mod_quiz_generator;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Unit tests for the {@see quiz_attempts_in_progress} class.
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
#[CoversClass(quiz_attempts_in_progress::class)]
final class quiz_attempts_in_progress_test extends advanced_testcase {
    public function test_get_type(): void {
        $metric = new quiz_attempts_in_progress();
        self::assertSame(metric_type::GAUGE, $metric->get_type());
    }

    public function test_get_description(): void {
        $metric = new quiz_attempts_in_progress();
        $description = $metric->get_description();
        self::assertSame('quiz_attempts_in_progress_description', $description->get_identifier());
        self::assertSame('tool_monitoring', $description->get_component());
    }

    public function test_calculate(): void {
        global $DB;
        $this->resetAfterTest();
        $metric = new quiz_attempts_in_progress();
        // Simulate the default config being applied here.
        $metric->configjson = '{"maxdeadlineseconds": 3600, "maxidleseconds": 600}';
        // Generate some quiz attempts.
        $now = time();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $user3 = $generator->create_user();
        /** @var mod_quiz_generator $quizgenerator */
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $quizsettings1 = $quizgenerator->create_test_quiz(
            layout: ['Heading', ['TF1', 1, 'truefalse']],
            settings: ['course' => $course->id],
        );
        $quizsettings2 = $quizgenerator->create_test_quiz(
            layout: ['Heading', ['TF1', 1, 'truefalse']],
            settings: ['course' => $course->id],
        );
        // Unfortunately, the `create_attempt` method forgets to pass the user ID along to `quiz_prepare_and_start_new_attempt`,
        // so we have to switch users manually here.
        $this->setUser($user1);
        $attempt11 = $quizgenerator->create_attempt($quizsettings1->get_quizid(), $user1->id);
        $attempt21 = $quizgenerator->create_attempt($quizsettings2->get_quizid(), $user1->id);
        $this->setUser($user2);
        $attempt12 = $quizgenerator->create_attempt($quizsettings1->get_quizid(), $user2->id);
        $attempt22 = $quizgenerator->create_attempt($quizsettings2->get_quizid(), $user2->id);
        $this->setUser($user3);
        $attempt23 = $quizgenerator->create_attempt($quizsettings2->get_quizid(), $user3->id);
        $this->setUser();
        $DB->update_record(
            'quiz_attempts',
            ['id' => $attempt11->id, 'timecheckstate' => $now + 1000],
        );
        $DB->update_record(
            'quiz_attempts',
            ['id' => $attempt12->id, 'timecheckstate' => $now + 1000, 'timemodified' => $now - 1000], // Idle for too long.
        );
        $DB->update_record(
            'quiz_attempts',
            ['id' => $attempt21->id, 'timecheckstate' => $now + 7200], // Deadline too far in the future.
        );
        $DB->update_record(
            'quiz_attempts',
            ['id' => $attempt22->id, 'timecheckstate' => $now],
        );
        $DB->update_record(
            'quiz_attempts',
            ['id' => $attempt23->id, 'timecheckstate' => $now, 'state' => 'finished'], // Finished.
        );
        $output = $metric->calculate();
        self::assertEquals(2, $output->value);
        self::assertSame(['deadline_within' => '3600s', 'idle_within' => '600s'], $output->label);
    }

    public function test_get_default_config(): void {
        $defaultconfig = quiz_attempts_in_progress::get_default_config();
        self::assertSame(3600, $defaultconfig->maxdeadlineseconds);
        self::assertSame(600, $defaultconfig->maxidleseconds);
    }
}

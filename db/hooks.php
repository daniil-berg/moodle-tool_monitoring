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
 * Registration of metrics via the Hooks API.
 *
 * @link https://moodledev.io/docs/apis/core/hooks#registering-of-hook-callbacks API Documentation
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

use core\hook\di_configuration;
use tool_monitoring\hook\metric_collection;
use tool_monitoring\local\metrics;
use tool_monitoring\local\metrics_manager;

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    ['hook' => di_configuration::class, 'callback' => [metric_collection::class, 'configure_dependency_injection']],
    ['hook' => di_configuration::class, 'callback' => [metrics_manager::class, 'configure_dependency_injection']],
    ['hook' => metric_collection::class, 'callback' => [metrics\courses::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\overdue_tasks::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\quiz_attempts_in_progress::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\user_accounts::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\users_online::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\meta\meta_metrics_count::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\meta\meta_metrics_samples::class, 'collect']],
    ['hook' => metric_collection::class, 'callback' => [metrics\meta\meta_metrics_timings::class, 'collect']],
];

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
 * Description of event observers.
 *
 * @link https://docs.moodle.org/dev/Events_API#Event_observers Events API documentation
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

use core\event\tag_added;
use core\event\tag_created;
use core\event\tag_deleted;
use core\event\tag_removed;
use core\event\tag_updated;
use tool_monitoring\event\observer;

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => tag_added::class,
        'callback' => [observer::class, 'tag_instance_added_or_removed'],
    ],
    [
        'eventname' => tag_created::class,
        'callback' => [observer::class, 'tag_created_or_deleted_or_updated'],
    ],
    [
        'eventname' => tag_deleted::class,
        'callback' => [observer::class, 'tag_created_or_deleted_or_updated'],
    ],
    [
        'eventname' => tag_removed::class,
        'callback' => [observer::class, 'tag_instance_added_or_removed'],
    ],
    [
        'eventname' => tag_updated::class,
        'callback' => [observer::class, 'tag_created_or_deleted_or_updated'],
    ],
];

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
 * Definition of the {@see observer} class.
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

namespace tool_monitoring\event;

use core\event\tag_added;
use core\event\tag_created;
use core\event\tag_deleted;
use core\event\tag_removed;
use core\event\tag_updated;
use core\exception\coding_exception;
use core_cache\cache;
use dml_exception;
use tool_monitoring\local\metrics_cache;
use tool_monitoring\metric_tag;
use tool_monitoring\registered_metric;

/**
 * Provides callbacks for events observed by the plugin.
 *
 * **This class is not part of the public API.**
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
final class observer {
    /**
     * Ensures the metric associated with the added/removed tag instance is removed from the cache.
     *
     * @param tag_added|tag_removed $event Event object.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function tag_instance_added_or_removed(tag_added|tag_removed $event): void {
        global $DB;
        if ($event->other['itemtype'] !== metric_tag::ITEM_TYPE) {
            return;
        }
        $sqlqname = registered_metric::get_qualified_name_sql($DB);
        $qname = $DB->get_field(registered_metric::TABLE, $sqlqname, ['id' => $event->other['itemid']]);
        metrics_cache::delete($qname);
    }

    /**
     * Ensures the referenced tag is removed from the cache.
     *
     * This invalidates a cache entry with the same name when a tag is deleted or updated, but also when a new tag is created
     * because we are doing null-caching in {@see metric_tag::get_all_with_names}.
     *
     * @param tag_created|tag_deleted|tag_updated $event Event object.
     * @throws coding_exception
     */
    public static function tag_created_or_deleted_or_updated(tag_created|tag_deleted|tag_updated $event): void {
        cache::make('tool_monitoring', 'metric_tags')->delete($event->other['name']);
    }
}

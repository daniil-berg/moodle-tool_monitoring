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
 * Local callback functions.
 *
 * @package   tool_monitoring
 * @copyright 2025 Malte Schmitz <mal.schmitz@uni-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns metrics tagged with a specified tag.
 *
 * This is a callback used by the tag area mod_book/book_chapters to search for book chapters
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function tool_monitoring_get_tagged_metrics($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = true, $page = 0) {
    global $OUTPUT;
    $perpage = $exclusivemode ? 20 : 5;

    // Build the SQL query.
    $query = "SELECT m.id, m.name, m.component
                FROM {tool_monitoring_config} m
                JOIN {tag_instance} tt ON m.id = tt.itemid
               WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component
                 AND m.id %ITEMFILTER%
            ORDER BY m.component, m.name";

    $params = ['itemtype' => 'metrics', 'tagid' => $tag->id, 'component' => 'tool_monitoring'];

    $totalpages = $page + 1;

    // Use core_tag_index_builder to build the list of items.
    $builder = new core_tag_index_builder('tool_monitoring', 'metrics', $query, $params, $page * $perpage, $perpage + 1);

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
        array_pop($items);
    }

    // Build the display contents.
    if ($items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            $url = new moodle_url('/admin/tool/monitoring/configure.php', ['id' => $item->id]);
            $name = $item->component . '/' . $item->name;
            $html = html_writer::link($url, $name);
            $tagfeed->add('', $html, '');
        }

        $content = $OUTPUT->render_from_template('core_tag/tagfeed',
            $tagfeed->export_for_template($OUTPUT));

        return new core_tag\output\tagindex($tag, 'tool_monitoring', 'metrics', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    }
}
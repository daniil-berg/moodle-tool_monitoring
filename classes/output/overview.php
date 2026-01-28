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
 * Definition of the renderable {@see overview} class.
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

namespace tool_monitoring\output;

use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use moodle_url;
use tool_monitoring\metrics_manager;
use tool_monitoring\registered_metric;
use core_tag_tag;

/**
 * Provides information about all available metrics and links to their configuration pages.
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
final readonly class overview implements renderable, templatable {
    private metrics_manager $manager;

    /**
     * Constructor without additional logic.
     *
     * @param string @tagname TODO
     */
    public function __construct(
        private string $tagname,
    ) {
        $this->manager = new metrics_manager();
        if ($this->tagname) {
            $this->manager->fetch(enabled: null, tags: [$this->tagname]);
        } else {
            $this->manager->sync(delete: true);
        }
    }

    /**
     * Throws an exception if the provided tag collection ID does not match the tag collection configured in the
     * db/tag.php of tool_monitoring.
     *
     * @param int $tagcollid
     * @return void
     */
    public static function assert_tag_collection_id(int $tagcollid) {
        global $DB;
        $correctid = $DB->get_field('tag_coll', 'id', ['name' => 'monitoring', 'component' => 'tool_monitoring']);
        if ($tagcollid != $correctid) {
            throw new coding_exception('Wrong tag collection ID');
        }
    }

    /**
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $tagsenabled = core_tag_tag::is_enabled('tool_monitoring', 'metrics');
        $tagcollid = $DB->get_field('tag_coll', 'id', ['name' => 'monitoring', 'component' => 'tool_monitoring']);
        $managetagsurl = new moodle_url('/tag/manage.php', ['tc' => $tagcollid]);
        $lines = [];
        foreach ($this->manager->metrics as $qualifiedname => $metric) {
            $configurl = new moodle_url('/admin/tool/monitoring/configure.php', ['metric' => $qualifiedname]);
            $line = [
                'component'   => $metric->component,
                'name'        => $metric->name,
                'type'        => $metric->type->value,
                'description' => $metric->description->out(),
                'configurl'   => $configurl->out(false),
            ];
            if ($tagsenabled) {
                $tags = core_tag_tag::get_item_tags(
                    'tool_monitoring',
                    'metrics',
                    $metric->id);
                $line['tags'] = array_map(function (core_tag_tag $tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->rawname,
                        'viewurl' => core_tag_tag::make_url($tag->tagcollid, $tag->rawname)->out(false),
                    ];
                }, array_values($tags));
            }
            $lines[] = $line;
        }
        // TODO show link to /tag/edit.php with params id (of the tag) and returnurl to index page
        // TODO ignore filtering if tag area is disabled
        // TODO better rendering of h2 header in case of active tag filtering
        // TODO add back link to all metrics in case of active tag filtering
        // TODO support filtering for multiple tags: clicking on a different tag adds that to the active filter
        return [
            'metrics' => $lines,
            'tagsenabled' => $tagsenabled,
            'managetagsurl' => $managetagsurl,
            'filtered' => !empty($this->tagname),
            'tagname' => $this->tagname,
        ];
    }
}

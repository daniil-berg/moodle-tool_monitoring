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
use core_h5p\core;
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
    /**
     * Constructor without additional logic.
     * @var metrics_manager The metrics manager used to load and sync the metrics.
     */
    private metrics_manager $manager;

    /**
     * @var bool Is our tag area enabled?
     */
    private bool $tagsenabled;

    /**
     * @var array<core_tag_tag> We only show metrics with at least these tags.
     */
    private array $tags;

    /**
     * @var int The tag collection ID for our tag collection.
     */
    private int $tagcollid;

    /**
     * The constructor fetches the metrics matching the given tags. An empty array loads all available metrics and
     *  starts a synchronization.
     *
     * @param string[] $tagnames tag names
     */
    public function __construct(array $tagnames) {
        global $DB;
        $this->manager = new metrics_manager();
        $this->tagsenabled = core_tag_tag::is_enabled('tool_monitoring', 'metrics');
        $tags = [];
        if ($this->tagsenabled) {
            $this->tagcollid = $DB->get_field('tag_coll', 'id', ['name' => 'monitoring', 'component' => 'tool_monitoring']);
            foreach ($tagnames as $tagname) {
                $tag = core_tag_tag::get_by_name($this->tagcollid, $tagname);
                if ($tag) {
                    $tags[] = $tag;
                }
            }
        }
        $this->tags = $tags;
        if (!empty($this->tags) && $this->tagsenabled) {
            $tagnames = array_map(fn (core_tag_tag $t) => $t->rawname, $this->tags);
            $this->manager->fetch(enabled: null, tags: $tagnames);
        } else {
            $this->manager->sync(delete: true);
        }
    }

    /**
     * Issue an HTTP redirect to either the Moodle tag manager or (if the tag collection ID matches) to the overview
     * filtered by the given tag name.
     *
     * @param mixed $tagcollid
     * @param mixed $tagname
     * @return void
     */
    public static function redirect_tagname(mixed $tagcollid, mixed $tagname) {
        global $DB;
        $correctid = $DB->get_field('tag_coll', 'id', ['name' => 'monitoring', 'component' => 'tool_monitoring']);
        if ($tagcollid != $correctid) {
            redirect(new moodle_url('/tag/index.php', ['tc' => $tagcollid, 'tag' => $tagname]));
        } else {
            redirect(new moodle_url('/admin/tool/monitoring/', ['tags' => $tagname]));
        }
    }

    /**
     * Generate a URL to the current overview with an additional tag in the filter.
     *
     * @param core_tag_tag $tag The new tag.
     * @return moodle_url
     */
    private function add_tag_url(core_tag_tag $tag) {
        $tags = $this->tags;
        if (!array_filter($this->tags, fn (core_tag_tag $t) => $t->id == $tag->id)) {
            $tags[] = $tag;
        }
        $tagnames = array_map(fn (core_tag_tag $t) => $t->rawname, $tags);
        return new moodle_url('/admin/tool/monitoring/', ['tags' => implode(',', $tagnames)]);
    }

    /**
     * Generate a URL to the current overview with one tag removed from the filter.
     *
     * @param core_tag_tag $tag The tag to remove.
     * @return moodle_url
     */
    private function remove_tag_url(core_tag_tag $tag) {
        $tags = array_filter($this->tags, fn (core_tag_tag $t) => $t->id != $tag->id);
        $params = [];
        if (!empty($tags)) {
            $tagnames = array_map(fn (core_tag_tag $t) => $t->rawname, $tags);
            $params['tags'] = implode(',', $tagnames);
        }
        return new moodle_url('/admin/tool/monitoring/', $params);
    }

    /**
     * {@inheritDoc}
     *
     * @param renderer_base $output
     * @return array
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $returnurl = $output->get_page()->url->out_as_local_url(false);
        $managetagsurl = new moodle_url('/tag/manage.php', ['tc' => $this->tagcollid]);
        $lines = [];
        foreach ($this->manager->metrics as $qualifiedname => $metric) {
            $configurl = new moodle_url('/admin/tool/monitoring/configure.php', ['metric' => $qualifiedname, 'returnurl' => $returnurl]);
            $line = [
                'component'   => $metric->component,
                'name'        => $metric->name,
                'type'        => $metric->type->value,
                'description' => $metric->description->out(),
                'configurl'   => $configurl->out(false),
            ];
            if ($this->tagsenabled) {
                $tags = core_tag_tag::get_item_tags(
                    'tool_monitoring',
                    'metrics',
                    $metric->id);
                $line['tags'] = array_map(function (core_tag_tag $tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->rawname,
                        'viewurl' => $this->add_tag_url($tag)->out(false),
                    ];
                }, array_values($tags));
            }
            $lines[] = $line;
        }
        $filtered = !empty($this->tags) && $this->tagsenabled;
        $data = [
            'metrics' => $lines,
            'tagsenabled' => $this->tagsenabled,
            'managetagsurl' => $managetagsurl,
            'filtered' => $filtered,
        ];
        if ($filtered) {
            $allmetricsurl = new moodle_url('/admin/tool/monitoring/');
            $data['allmetricsurl'] = $allmetricsurl->out(false);
            $data['tags'] = [];
            foreach ($this->tags as $tag) {
                $editurl = new moodle_url('/tag/edit.php', ['id' => $tag->id, 'returnurl' => $returnurl]);
                $data['tags'][] = [
                    'name' => $tag->rawname,
                    'removeurl' => $this->remove_tag_url($tag)->out(false),
                    'editurl' => $editurl->out(false),
                ];
            }
        }
        return $data;
    }
}

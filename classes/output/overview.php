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
     * The constructor fetches the metrics matching the given tags. An empty array loads all available metrics and
     *  starts a synchronization.
     *
     * @param array<string> $tags tag names
     */
    public function __construct(
        private array $tags,
    ) {
        $this->manager = new metrics_manager();
        $this->tagsenabled = core_tag_tag::is_enabled('tool_monitoring', 'metrics');
        if (!empty($this->tags) && $this->tagsenabled) {
            $this->manager->fetch(enabled: null, tags: $this->tags);
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
     * Add the given tag name to the list of tags. Returns a new array without modifying this object.
     *
     * @param string $tag The new tag name.
     * @return string[] The resulting tag names.
     */
    private function add_tag(string $tag) {
        $normalized = core_tag_tag::normalize($this->tags);
        $normtag = array_values(core_tag_tag::normalize([$tag]))[0];
        if (!in_array($normtag, $normalized)) {
            return [...$this->tags, $tag];
        }
        return $this->tags;
    }

    /**
     * Remove the given tag name from the list of tags. Returns a new array without modifying this object.
     *
     * @param string $tag The tag name to remove.
     * @return string[] The resulting tag names.
     */
    private function remove_tag(string $tag) {
        $normalized = array_values(core_tag_tag::normalize($this->tags));
        $normtag = array_values(core_tag_tag::normalize([$tag]))[0];
        $key = array_search($normtag, $normalized);
        if ($key !== false) {
            return array_filter($this->tags, fn (string $k) => $k != $key, ARRAY_FILTER_USE_KEY);
        }
        return $this->tags;
    }

    /**
     * {@inheritDoc}
     *
     * @param renderer_base $output
     * @return array
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $DB;
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
            if ($this->tagsenabled) {
                $tags = core_tag_tag::get_item_tags(
                    'tool_monitoring',
                    'metrics',
                    $metric->id);
                $line['tags'] = array_map(function (core_tag_tag $tag) {
                    $url = new moodle_url('/admin/tool/monitoring/', ['tags' => implode(',', $this->add_tag($tag->rawname))]);
                    return [
                        'id' => $tag->id,
                        'name' => $tag->rawname,
                        'viewurl' => $url->out(false),
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
            $tagcollid = $DB->get_field('tag_coll', 'id', ['name' => 'monitoring', 'component' => 'tool_monitoring']);
            $allmetricsurl = new moodle_url('/admin/tool/monitoring/');
            $data['allmetricsurl'] = $allmetricsurl->out(false);
            $data['tags'] = [];
            foreach ($this->tags as $tagname) {
                $othertags = $this->remove_tag($tagname);
                $params = [];
                if (!empty($othertags)) {
                    $params['tags'] = $othertags;
                }
                $removeurl = new moodle_url('/admin/tool/monitoring/', ['tags' => implode(',', $othertags)]);
                $tag = core_tag_tag::get_by_name($tagcollid, $tagname);
                $editurl = new moodle_url('/tag/edit.php', ['id' => $tag->id]);
                $data['tags'][] = [
                    'name' => $tagname,
                    'removeurl' => $removeurl->out(false),
                    'editurl' => $editurl->out(false),
                ];
            }
        }
        return $data;
    }
}

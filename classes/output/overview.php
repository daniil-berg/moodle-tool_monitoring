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

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use moodle_url;
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
     *
     * @param array<string, registered_metric> $metrics Metrics for which to render the overview, indexed by qualified name.
     * @param array<core_tag_tag> $tags Metrics were filtered with these tags.
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    public function __construct(
        /** @var array<string, registered_metric> Metrics for which to render the overview, indexed by qualified name. */
        private array $metrics,
        /** @var array<core_tag_tag> Metrics were filtered with these tags. */
        private array $tags
    ) {}

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
        return new moodle_url('/admin/tool/monitoring/', ['tag' => implode(',', $tagnames)]);
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
            $params['tag'] = implode(',', $tagnames);
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
        global $DB;
        $tagcollid = $DB->get_field('tag_coll', 'id', ['name' => 'monitoring', 'component' => 'tool_monitoring']);
        $tagsenabled = core_tag_tag::is_enabled('tool_monitoring', 'metrics');
        $returnurl = $output->get_page()->url->out_as_local_url(false);
        $managetagsurl = new moodle_url('/tag/manage.php', ['tc' => $tagcollid]);
        $lines = [];
        foreach ($this->metrics as $qualifiedname => $metric) {
            $configurl = new moodle_url('/admin/tool/monitoring/configure.php', ['metric' => $qualifiedname, 'returnurl' => $returnurl]);
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
                        'viewurl' => $this->add_tag_url($tag)->out(false),
                    ];
                }, array_values($tags));
            }
            $lines[] = $line;
        }
        $filtered = !empty($this->tags) && $tagsenabled;
        $data = [
            'metrics' => $lines,
            'tagsenabled' => $tagsenabled,
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

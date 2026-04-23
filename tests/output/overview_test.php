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
 * Definition of the {@see overview_test} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace tool_monitoring\output;

use advanced_testcase;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\output\renderer_base;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\local\testing\mock_metric_tag;
use tool_monitoring\local\testing\mock_registered_metric;
use tool_monitoring\metric_tag;

/**
 * Unit tests for the {@see overview} class.
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
#[CoversClass(overview::class)]
final class overview_test extends advanced_testcase {
    /**
     * Tests the {@see overview::export_for_template} method.
     *
     * Also implicitly tests the {@see overview::__construct} method.
     *
     * @param array<string, mock_registered_metric> $metrics Mock-metrics to pass to the constructor, indexed by qualified name.
     * @param array<string, mock_metric_tag> $tags Mock-tags to pass to the constructor, indexed by normalized tag name.
     * @throws moodle_exception
     */
    #[DataProvider('provider_test_export_for_template')]
    public function test_export_for_template(array $metrics, array $tags, bool $tagsenabled): void {
        $this->resetAfterTest();
        set_config('usetags', $tagsenabled);
        $mockrenderer = $this->createMock(renderer_base::class);
        $overview = new overview($metrics, $tags);
        $output = $overview->export_for_template($mockrenderer);
        // There must always be a `metrics` list.
        self::assertArrayHasKey('metrics', $output);
        $metricrows = $output['metrics'];
        // The number of elements in the `metrics` list should be equal to the number of metrics passed to the constructor.
        self::assertCount(count($metrics), $metricrows);
        // These keys must be present for every entry in the `metrics` list.
        $expectedmetricskeys = array_flip([
            'component',
            'name',
            'qualified_name',
            'type',
            'description',
            'enabled',
            'config_url',
        ]);
        foreach ($metricrows as $metricrow) {
            // We only care about the row having at least these keys.
            self::assertEmpty(array_diff_key($expectedmetricskeys, $metricrow));
            $qname = $metricrow['qualified_name'];
            // There should be a matching metric that was passed to the constructor.
            self::assertArrayHasKey($qname, $metrics);
            $metric = $metrics[$qname];
            // Check the row data against the matching metric.
            self::assertSame($metric->component, $metricrow['component']);
            self::assertSame($metric->name, $metricrow['name']);
            self::assertSame($metric->type->value, $metricrow['type']);
            self::assertSame($metric->description->out(), $metricrow['description']);
            self::assertSame($metric->enabled, $metricrow['enabled']);
            $configurl = new moodle_url('/admin/tool/monitoring/configure.php', ['metric' => $qname]);
            self::assertSame($configurl->out(escaped: false), $metricrow['config_url']);
            if (!$tagsenabled) {
                self::assertArrayNotHasKey('tags', $metricrow);
                continue;
            }
            // Check each tag entry.
            self::assertArrayHasKey('tags', $metricrow);
            self::assertCount(count($metric->tags), $metricrow['tags']);
            foreach ($metricrow['tags'] as $tagentry) {
                ['id' => $tagid, 'name' => $tagname, 'view_url' => $tagviewurl] = $tagentry;
                // There should be a matching tag on the metric.
                // In our mock-tags the raw name is equal to the normalized name.
                self::assertArrayHasKey($tagname, $metric->tags);
                $tag = $metric->tags[$tagname];
                // Check the tag data against the matching tag.
                self::assertSame($tag->id, $tagid);
                self::assertSame($tag->rawname, $tagname);
                $parsedviewurl = new moodle_url($tagviewurl);
                // Extract the set of tags from the query parameter.
                // That set must be exactly the union of the filter-tags and the matching tag.
                $tagsinurl = array_flip(explode(',', $parsedviewurl->get_param('tag')));
                $expectedtags = $tags + [$tag->name => $tag];
                self::assertEmpty(array_diff_key($tagsinurl, $expectedtags));
                self::assertEmpty(array_diff_key($expectedtags, $tagsinurl));
            }
        }
        self::assertEquals($tagsenabled, $output['is_tagging_enabled']);
        self::assertEquals(metric_tag::get_manage_url(), $output['manage_tags_url']);
        $hastags = !empty($tags) && $tagsenabled;
        self::assertEquals($hastags, $output['has_tags']);
        if ($hastags) {
            $allmetricsurl = new moodle_url('/admin/tool/monitoring/');
            self::assertEquals($allmetricsurl->out(escaped: false), $output['all_metrics_url']);
            self::assertCount(count($tags), $output['tags']);
            foreach ($output['tags'] as $tagentry) {
                ['name' => $tagname, 'remove_url' => $tagremoveurl, 'edit_url' => $tagediturl] = $tagentry;
                // There should be a matching tag that was passed to the constructor.
                // In our mock-tags the raw name is equal to the normalized name.
                self::assertArrayHasKey($tagname, $tags);
                $tag = $tags[$tagname];
                self::assertSame($tag->rawname, $tagname);
                self::assertSame($tag->get_edit_url()->out(escaped: false), $tagediturl);
                $parsedremoveurl = new moodle_url($tagremoveurl);
                // Extract the set of tags from the query parameter.
                // That set must be exactly the filter-tags minus the matching tag.
                $tagsinurl = array_flip(explode(',', $parsedremoveurl->get_param('tag')));
                $expectedtags = $tags;
                unset($expectedtags[$tag->name]);
                self::assertEmpty(array_diff_key($tagsinurl, $expectedtags));
                self::assertEmpty(array_diff_key($expectedtags, $tagsinurl));
            }
        } else {
            self::assertArrayNotHasKey('all_metrics_url', $output);
            self::assertArrayNotHasKey('tags', $output);
        }
    }

    /**
     * Provides test data for the {@see test_export_for_template} method.
     *
     * @return array[] Arguments for the test method.
     * @throws coding_exception
     */
    public static function provider_test_export_for_template(): array {
        $tagspam = new mock_metric_tag(1, 'spam');
        $tageggs = new mock_metric_tag(2, 'eggs');
        $tagbeans = new mock_metric_tag(3, 'beans');
        $metricfoo = new mock_registered_metric('foo');
        $metricbar = new mock_registered_metric(
            name: 'bar',
            tags: ['spam' => $tagspam, 'eggs' => $tageggs],
        );
        $metricbaz = new mock_registered_metric(
            name: 'baz',
            tags: ['beans' => $tagbeans],
        );
        return [
            'Tagging disabled, metrics with tags and tag filter set' => [
                'metrics' => [
                    'tool_monitoring_foo' => $metricfoo,
                    'tool_monitoring_bar' => $metricbar,
                    'tool_monitoring_baz' => $metricbaz,
                ],
                'tags' => [
                    'spam' => $tagspam,
                    'beans' => $tagbeans,
                ],
                'tagsenabled' => false,
            ],
            'Tagging enabled, metrics with tags and tag filter set' => [
                'metrics' => [
                    'tool_monitoring_foo' => $metricfoo,
                    'tool_monitoring_bar' => $metricbar,
                    'tool_monitoring_baz' => $metricbaz,
                ],
                'tags' => [
                    'spam' => $tagspam,
                    'beans' => $tagbeans,
                ],
                'tagsenabled' => true,
            ],
            'Tagging enabled, metrics with tags, but no tag filter set' => [
                'metrics' => [
                    'tool_monitoring_foo' => $metricfoo,
                    'tool_monitoring_bar' => $metricbar,
                    'tool_monitoring_baz' => $metricbaz,
                ],
                'tags' => [],
                'tagsenabled' => true,
            ],
        ];
    }
}

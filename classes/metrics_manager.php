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
 * Definition of the {@see metrics_manager} class.
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

namespace tool_monitoring;

use core\context\system;
use core\di;
use core\exception\coding_exception;
use core\hook\manager;
use core_tag_tag;
use stdClass;
use tool_monitoring\hook\gather_metrics;

/**
 * Metrics manager to gather all available metrics and operations.
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
final class metrics_manager {
    /** @var array<string, metric> All loaded metrics. The key is the name given by {@see metric::get_unique_name()}. */
    protected array $metrics;

    /** @var array<string, stdClass> Map of metric names to configuration. */
    protected array $configmap;

    /** @var array<int, string> Map of metric ids to metric name. */
    protected array $namebyid;

    /**
     * Dispatches the {@see gather_metrics} hook and stores all loaded metrics.
     *
     * @param array<stdClass> $configs list of loaded metric configurations from tool_monitoring_config table
     */
    private function __construct(array $configs) {
        $allmetrics = self::gather_all_metrics();

        // Store the metrics and their configuration.
        $this->metrics = [];
        $this->configmap = [];
        $this->namebyid = [];

        foreach ($configs as $config) {
            $key = $config->component . '_' . $config->name;
            if (isset($allmetrics[$key])) {
                $metric = $allmetrics[$key];
                $this->metrics[$key] = $metric;
                $this->configmap[$key] = $config;
                $this->namebyid[$config->id] = $key;
            }
        }
    }

    /**
     * Loads all available metrics and their configuration from the database.
     *
     * @return self
     * @throws \dml_exception
     */
    public static function load_all_metrics(): self {
        global $DB;

        $configs = $DB->get_records('tool_monitoring_config');

        return new self($configs);
    }

    public static function load_metrics_by_tag(string $tag, bool $onlyenabled): self {
        // TODO
    }

    /**
     * Load a single metric.
     *
     * @param int $metricid
     * @return self
     */
    public static function load_metric(int $metricid): self {
        global $DB;

        $config = $DB->get_record('tool_monitoring_config', ['id' => $metricid], strictness: MUST_EXIST);

        return new self([$config]);
    }

    /**
     * Get all hooks defined in the system.
     *
     * @return array<string, metric> all metrics indexed by their unique name
     */
    public static function gather_all_metrics(): array {
        $hook = new gather_metrics();
        di::get(manager::class)->dispatch($hook);
        return $hook->get_metrics();
    }

    /**
     * Get the moodle form for the metric configuration.
     *
     * @param int $metricid
     * @param mixed ...$args Additional arguments to forward to the form constructor
     * @return form\config
     */
    public function get_metric_config_form(int $metricid, mixed ...$args): form\config {
        if (!isset($this->namebyid[$metricid])) {
            throw new coding_exception("Requested metric id $metricid was not loaded.");
        }

        $name = $this->namebyid[$metricid];
        $metric = $this->metrics[$name];
        $customdata = [
            'metric' => $metric,
        ];

        if ($metric instanceof configurable_metric) {
            $form = $metric::get_config_form(...$args, customdata: $customdata);
            $formdata = self::get_metric_config($metric, $name);
        } else {
            $form = new form\config(...$args, customdata: $customdata);
            $formdata = [];
        }
        $formdata['id'] = $metricid;
        $formdata['enabled'] = $this->configmap[$name]->enabled;
        $formdata['tags'] = core_tag_tag::get_item_tags_array('tool_monitoring', 'metrics', $metricid);
        $form->set_data($formdata);

        return $form;
    }

    /**
     * Save a metric configuration.
     *
     * @param int $metricid
     * @param stdClass $data as returned by {@see form\config::get_data()} from the metric configuration form
     * @return void
     */
    public function save_metric_config(int $metricid, stdClass $data): void {
        global $DB;

        if (!isset($this->namebyid[$metricid])) {
            throw new coding_exception("Requested metric id $metricid was not loaded.");
        }

        $metric = $this->metrics[$this->namebyid[$metricid]];
        $transaction = $DB->start_delegated_transaction();

        $enabled = $data->enabled ?? false;
        $tags = $data->tags ?? [];

        if ($metric instanceof configurable_metric) {
            // Give metric the chance to store config values at other places.
            $metric->save_additional_config($metricid, $data);

            // Manager only stores config values that are actually defined in the metric.
            $data = array_intersect_key((array)$data, $metric::get_config_default());
        } else {
            $data = [];
        }

        $DB->update_record('tool_monitoring_config', [
            'id' => $metricid,
            'enabled' => $enabled,
            'data' => json_encode($data),
        ]);

        core_tag_tag::set_item_tags(
            'tool_monitoring',
            'metrics',
            $metricid,
            system::instance(),
            $tags
        );

        $transaction->allow_commit();
    }

    /**
     * Get the metric configuration.
     *
     * The default configuration is merged with the stored configuration. This way, the metric can assume that
     * all config keys are included, regardless of what is stored in the database.
     *
     * @param configurable_metric $metric
     * @param $metricname
     * @return array
     */
    private function get_metric_config(configurable_metric $metric, $metricname): array {
        $data = $this->configmap[$metricname]->data ?: '{}';
        $configdata = json_decode($data, true);
        return array_merge($metric::get_config_default(), $configdata);
    }

    /**
     * Returns the registered metrics.
     *
     * Optionally filters the metrics by tag.
     *
     * @param string|null $tag If provided, only metrics with that tag will be returned.
     * @return metric[] Metrics indexed by their name.
     */
    public function get_metrics(string|null $tag = null): array {
        if (is_null($tag)) {
            return $this->metrics;
        }
        // TODO: Implement configurable tags for metrics via settings and filter the metrics accordingly here.
        return array_filter(
            $this->metrics,
            fn (metric $metric): bool => true,
        );
    }

    /**
     * Returns the metrics and prepares the objects for export.
     *
     * @return metric[] Metrics indexed by their unique name.
     */
    public function get_metrics_for_export(): array {
        foreach ($this->metrics as $name => $metric) {
            if ($metric instanceof configurable_metric) {
                $metric->set_config($this->get_metric_config($metric, $name));
            }
        }

        return $this->metrics;
    }
}

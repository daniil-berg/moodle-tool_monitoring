# Monitoring Moodle - `tool_monitoring`

The **`tool_monitoring`** Plugin makes it easy to integrate standard monitoring setups (such as [Prometheus][prometheus home] & [Grafana][grafana oss home]) with any running [Moodle][moodle home] instance.

Development started at the 2025 [Moodle Moot DACH][moodlemootdach home] DevCamp, where the project [won 1st prize][moodlemootdach 2025 votes], showing strong community demand for a standardized monitoring solution for Moodle.

📈 ==**TODO: Screenshot Grafana Dashboard**==

---

## Contents

- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
  - [Admin Settings](#admin-settings)
  - [Prometheus configuration](#prometheus-configuration)
  - [Installing a custom metric](#installing-a-custom-metric)
  - [Making a metric configurable (advanced)](#making-a-metric-configurable-advanced)
  - [Grouping metrics with tags (optional)](#grouping-metrics-with-tags-optional)
- [Terminology](#terminology)
  - [Metric](#metric)
  - [Metric value](#metric-value)
  - [Metric type](#metric-type)
  - [Label](#label)
  - [Exporter](#exporter)
- [Architecture](#architecture)
  - [Base `metric` class](#base-metric-class)
  - [Hook `metric_collection`](#hook-metric_collection)
  - [DB table and `registered_metric` wrapper](#db-table-and-registered_metric-wrapper)
  - [Central `metrics_manager`](#central-metrics_manager)
  - [Configurable metrics (advanced)](#configurable-metrics-advanced)
  - [Exporter sub-plugins](#exporter-sub-plugins)
- [Copyright](#copyright)

---

## Features

- 🍱 **Just works**: Ready-made with [sensible Moodle metrics](#pre-installed-metrics) and a [Prometheus endpoint][prometheus docs instances] out of the box.
- 🏗️ **Extensible**: To define your own metric, simply extend the [`metric`][. metric] class and [register it for collection](#registering-the-metric).
- 🗃️ **Generic**: Agnostic towards the monitoring software used; supports custom [exporters](#exporter-sub-plugins).
- 🧑‍💻 **Admin-friendly**: Convenient [admin dashboard](#admin-settings) to view and configure individual metrics and exporters.
- 🔧 **Customizable**: For advanced use, metrics can be [configured](#configurable-metrics-advanced) and even tagged via the [Moodle Tag API][moodle docs tag api].

## Installation

Just like any other Moodle plugin. It belongs in the `public/admin/tool/monitoring` directory.
For example, using `git`:

```shell
$ git clone https://github.com/daniil-berg/moodle-tool_monitoring.git public/admin/tool/monitoring
```

For other options and general plugin installation instructions, see the [official Moodle documentation][moodle docs plugin install].

## Usage

### Admin Settings

#### Dashboard

The admin dashboard can be found at `/admin/tool/monitoring` or by navigating to _Site administration_ > _Plugins_ > _Admin tools_ > _Monitoring_ > _Overview_.

📄 ==**TODO: Screenshot Admin Overview**==

There you can view all registered metrics, enable/disable them, add/remove metric tags, and configure some metrics individually.

#### Pre-installed metrics

Out of the box, `tool_monitoring` comes with the following metrics:

|            Name             | Description                                                  | Partitioned by                                                                       | Configurable? |
|:---------------------------:|--------------------------------------------------------------|--------------------------------------------------------------------------------------|:-------------:|
|          `courses`          | Current number of courses.                                   | `visible` (`true`/`false`)                                                           |      no       |
|       `overdue_tasks`       | Number of tasks that should have run already but have not    | `type` (`adhoc`/`scheduled`)                                                         |      no       |
| `quiz_attempts_in_progress` | Number of ongoing quiz attempts with an approaching deadline | -                                                                                    |      yes      |
|       `user_accounts`       | Current number of user accounts                              | `auth` (available methods), `suspended` (`true`/`false`), `deleted` (`true`/`false`) |      no       |
|       `users_online`        | Number of users that have recently accessed the site         | `time_window` (last user access time to count, multiple configurable)                |      yes      |

Any Moodle component can add its own custom metrics.
(See the section "[Installing a custom metric](#installing-a-custom-metric)" for details.)
Once a metric is [registered](#registering-the-metric), it will be listed in the dashboard as well.

#### Metric configuration

Some metrics have their own specific configuration options.
🚧 TODO

#### Exporters

The pre-installed Prometheus exporter has its own settings under _Site administration_ > _Plugins_ > _Admin tools_ > _Monitoring_ > _Exporter Prometheus_.

The actual Prometheus endpoint is immediately accessible and can be reached at `/r.php/monitoringexporter_prometheus/metrics`.

That endpoint can be secured by specifying an access token in the `monitoringexporter_prometheus | prometheus_token` setting, which then must be provided in the `token` query parameter.
So if your Moodle web root is `https://example.com` and you set the `prometheus_token` to be `super-secure-secret`, the full URL will look like this:
`https://example.com/r.php/monitoringexporter_prometheus/metrics?token=super-secure-secret`

### Prometheus configuration

This assumes you have a Prometheus server already up and running.
All you need to do is to add a job to the `scrape_configs` section in your `prometheus.yml` like this:

```yaml
scrape_configs:
   # Choose whatever unique job name you like.
  - job_name: moodle
    # The default scheme is HTTP.
    scheme: https
    # If you have set an access token, provide it here as a query parameter.
    params:
      - token: ['super-secure-secret']
    # Specify the full endpoint path. The default is just '/metrics'.
    metrics_path: /r.php/monitoringexporter_prometheus/metrics
    # Specify the target host.
    static_configs:
      - targets: ['example.com']
```

If you are making use of tags to group specific metrics, you can filter for them by also specifying the `tag` query parameter.
Multiple tags can be specified by separating them with a comma.
For example, to only scrape metrics that have both the `hello` and the `world` tag, your `params` section would have look like this:

```yaml
    params:
      - token: ['super-secure-secret']
      - tag: ['hello,world']
```

For exhaustive details about the various config options, see the official [Prometheus documentation][prometheus docs config].

### Setting up a Grafana dashboard

🚧 TODO

### Installing a custom metric

In its most basic form, adding a custom metric consists of just four steps:
1. Defining the metric class
2. Adding a localized metric description
3. Registering the metric
4. Enabling the metric

The following is a simple example of a metric that counts the **total number of tasks** that have spawned since the Moodle instance was started.
It distinguishes between ad-hoc and scheduled tasks by using the `type` label.
(For this example, we assume PostgreSQL is used as the database backend.)

#### Defining the metric class

Let's say there is a `local_example` plugin and the metric class is supposed to live in its `classes/metrics` directory.

`classes/metrics/tasks_total.php`

```php
<?php

namespace local_example\metrics;

use core\lang_string;
use tool_monitoring\metric;
use tool_monitoring\metric_type;
use tool_monitoring\metric_value;

/**
 * Counts the total number of tasks that have spawned since the Moodle instance was started.
 */
class tasks_total extends metric {
    public static function get_type(): metric_type {
        return metric_type::COUNTER;
    }

    public static function get_description(): lang_string {
        return new lang_string('tasks_total_description', 'local_example');
    }

    public function calculate(): array {
        global $CFG, $DB;
        if ($DB->get_dbfamily() !== 'postgres') {
            return new metric_value(0);
        }
        $sql = "SELECT SUM(last_value)
                  FROM pg_sequences
                 WHERE sequencename = :sequence";
        $totaladhoc = $DB->get_field_sql($sql, ['sequence' => "{$CFG->prefix}task_adhoc_id_seq"]);
        $totalscheduled = $DB->get_field_sql($sql, ['sequence' => "{$CFG->prefix}task_scheduled_id_seq"]);
        return [
            new metric_value($totaladhoc, ['type' => 'adhoc']),
            new metric_value($totalscheduled, ['type' => 'scheduled']),
        ];
    }
}
```

#### Adding a localized metric description

The description is what is shown in the admin dashboard.
It is also what the `monitoringexporter_prometheus` exporter uses to generate its metric `HELP` string.

In our example above, we return the [localized string][moodle docs string api] with the ID `tasks_total_description`.
We just need to actually add the text to be displayed to the plugin's language file.

`lang/en/local_example.php`

```php
<?php

defined('MOODLE_INTERNAL') || die();
// ...
$string['tasks_total_description'] = 'Total number of tasks that have spawned since the Moodle instance was started.';
```

#### Registering the metric

The new metric class needs to be picked up by the `metric_collection` hook.
For this, we can use the `metric::collect` method as a hook callback function.
All we need to do is [register that callback][moodle docs hooks.db].

`db/hooks.php`

```php
<?php

use local_example\metrics\tasks_total;
use tool_monitoring\hook\metric_collection;

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    ['hook' => metric_collection::class, 'callback' => [tasks_total::class, 'collect']],
];
```

#### Enabling the metric

That is all there is to it.
By default, when a new metric is registered, it will be disabled, which means it is not meant to be exported.
To enable it, first navigate to the admin dashboard.
You should now see a new greyed-out entry in the overview table for the `tasks_total` metric.

All you need to do is click on the eye icon to enable the metric.
The table row should no longer be greyed out and the metric should now be exported.

### Making a metric configurable (advanced)

🚧 TODO

### Grouping metrics with tags (optional)

🚧 TODO

## Terminology

### Metric

A **metric** by popular definition, is a measure of something.
This is also true in `tool_monitoring`, but here a metric also

1. is an instance of a concrete [`metric`][. metric] sub-classs,
2. calculates/produces one or more _values_ (see "metric value") when called upon, and
3. has a name, description, and _type_ (see "metric type").

### Metric value

Any single scalar (i.e. `float|int`) value produced by a _metric_ is called a **metric value**.

Metric values can carry _labels_ (see "label") and are encapsulated by the [`metric_value`][. metric_value] class.

### Metric type

There are currently only two types of metrics that `tool_monitoring` supports, **gauges** and **counters**.

- A **gauge** is a metric with values that can increase or decrease over time.
- A **counter** is a special kind of gauge that must only ever increase.

The metric type is static and encapsulated by the [`metric_type`][. metric_type] enum.

### Label

A key-value-pair associated with a metric is referred to as a **label**.
The pair is also referred to as **label name** and **label value**; both are strings.

The primary purpose of labels is to add dimensionality to metrics, i.e. have one metric produce multiple scalar values at a time.
(See also the related [Prometheus data model][prometheus docs data model].)
Described another way, they allow you to group multiple different but related metrics under the same metric name and distinguish them by their labels.

Labels can also be used to supplement a metric with structured meta-data/information.

Although the labels are stored in the `metric_value` object, they are conceptually closely associated with a `metric` because they are typically not expected to change from one measurement/calculation to the next (or at least very rarely).

### Exporter

An **exporter** is a sub-plugin for `tool_monitoring` that provides metrics to a monitoring backend.

Some monitoring backends, such as Prometheus, are pull-based, meaning they periodically query their targets for metrics.
Exporters for these types of backends need to provide routes/endpoints that expose the desired metrics in a format that the monitoring backend can consume.
The included `monitoringexporter_prometheus` sub-plugin is implemented in this way for Prometheus.

There are also push-based monitoring backends that expect metrics to be sent to them periodically.
Exporters for those need to implement a way to stream metrics to the backend in the required format.

## Architecture

### Base `metric` class

To observe, measure, and report something of interest in a running Moodle instance, a concrete [`metric`][. metric] subclass must be defined.
The most important method to implement is `calculate`.
This is what will be called to produce the current value(s) of the metric.
Metrics must be instantiated to produce values.

### Hook `metric_collection`

For a `metric` subclass to find its way into the monitoring toolchain, it needs to be _collected_ by the [`metric_collection`][. hook/metric_collection] hook (see the Moodle documentation on the [Hook API][moodle docs hook api]).
It only allows `metric` instances to be _added_ and already collected ones to be _iterated_ over.
For convenience, the static `metric::collect` method can be used as the [hook callback][moodle docs hook callback], but you can use the `metric_collection` just like any other [hook instance][moodle docs hook instance].

### DB table and `registered_metric` wrapper

To allow all metrics to be individually enabled/disabled and more advanced metrics (see the `with_config` trait) to have their own persistent configuration, each concrete metric is associated with a row in the `tool_monitoring_metrics` database table.

The `registered_metric` class is an internal wrapper for metrics managed by `tool_monitoring` and maps instances to rows in the database table.

### Central `metrics_manager`

The linchpin of the monitoring toolchain is the [`metrics_manager`][. metrics_manager].
It [emits][moodle docs hook emitter] the `metric_collection` hook and synchronizes the internal metrics registry in the database.
Outside code can use the `metrics_manager` to retrieve and filter all currently registered metrics.

### Configurable metrics (advanced)

Metrics can be made configurable by using the [`with_config`][. with_config] trait.
This also requires the definition of a custom config class that implements the [`metric_config`][. metric_config] interface.

Custom metric configuration is stored as JSON in the associated database row.
Therefore, a config object must be de-/serializable from/to JSON.
Since the configuration is supposed to be managed via the admin panel, an extension to the config form must be provided as well.
Lastly, the config object must be constructable from that form's data and vice versa.

To avoid implementing the entire interface manually, the [`simple_metric_config`][. simple_metric_config] class serves a convenient base for simple configuration options.

### Exporter sub-plugins

To underscore the generic nature of `tool_monitoring`, exporters are intended to be provided as sub-plugins.
Exporter sub-plugins reside in the `exporter/` directory.
Other than that, there are no restrictions on what exactly an exporter must or cannot do.

The `monitoringexporter_prometheus` sub-plugin is included with `tool_monitoring` out of the box.
It uses Moodle's [Routing API][moodle docs routing api] to expose the Prometheus metrics endpoint.

## Copyright

© 2025 Daniel Fainberg, Martin Gauk, Sebastian Rupp, Malte Schmitz, Melanie Treitinger

---

`tool_monitoring` is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

`tool_monitoring` is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with `tool_monitoring`. If not, see <https://www.gnu.org/licenses/>.

[. hook/metric_collection]: classes/hook/metric_collection.php
[. metric]: classes/metric.php
[. metric_config]: classes/metric_config.php
[. metric_type]: classes/metric_type.php
[. metric_value]: classes/metric_value.php
[. metrics_manager]: classes/metrics_manager.php
[. simple_metric_config]: classes/simple_metric_config.php
[. with_config]: classes/with_config.php
[grafana oss home]: https://grafana.com/oss/grafana
[moodle docs hook api]: https://moodledev.io/docs/apis/core/hooks
[moodle docs hook callback]: https://moodledev.io/docs/apis/core/hooks#hook-callback
[moodle docs hook emitter]: https://moodledev.io/docs/apis/core/hooks#hook-emitter
[moodle docs hook instance]: https://moodledev.io/docs/apis/core/hooks#hook-instance
[moodle docs hooks.db]: https://moodledev.io/docs/apis/core/hooks#registering-of-hook-callbacks
[moodle docs plugin install]: https://docs.moodle.org/en/Installing_plugins#Installing_a_plugin
[moodle docs routing api]: https://moodledev.io/docs/apis/subsystems/routing
[moodle docs string api]: https://docs.moodle.org/dev/String_API
[moodle docs tag api]: https://moodledev.io/docs/apis/subsystems/tag
[moodle home]: https://moodle.com
[moodlemootdach 2025 votes]: https://moodlemootdach.org/mod/forum/discuss.php?d=7108
[moodlemootdach home]: https://moodlemootdach.org
[prometheus docs config]: https://prometheus.io/docs/prometheus/latest/configuration/configuration
[prometheus docs data model]: https://prometheus.io/docs/concepts/data_model
[prometheus docs instances]: https://prometheus.io/docs/concepts/jobs_instances
[prometheus home]: https://prometheus.io

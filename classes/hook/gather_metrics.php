<?php

namespace tool_monitoring\hook;

use stdClass;
use tool_monitoring\local\metrics\metric_interface;

#[\core\attribute\label('Hook dispatched at the very call on the metrics api.')]
#[\core\attribute\tags('metric')]
final class gather_metrics {

    private array $metrics = [];

    public function add_metric(string $metric) {
        $this->metrics[] = $metric;
    }

    public function get_metrics() {
        return $this->metrics;
    }
}
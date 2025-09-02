<?php

namespace tool_monitoring\hook;

#[\core\attribute\label('Hook dispatched at the very call on the metrics api.')]
#[\core\attribute\tags('metric')]
final class gather_metrics {

    private array $metrics = [];

    public function add_metric($metric) {
        $this->metrics[] = $metric;
    }

    public function get_metrics() {
        return $this->metrics;
    }
}
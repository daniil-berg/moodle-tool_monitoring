<?php

namespace tool_monitoring\hook;

#[\core\attribute\label('Hook dispatched at the very call on the metrics api.')]
#[\core\attribute\tags('metric')]
final class gather_metrics {

    private array $names = [];

    public function add_metric(string $name) {
        $this->names[] = $name;
    }

    public function getNames() {
        return $this->names;
    }
}
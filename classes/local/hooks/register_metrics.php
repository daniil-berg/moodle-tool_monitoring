<?php

namespace tool_monitoring\local\hooks;

class register_metrics {

    public static function callback(\tool_monitoring\hook\gather_metrics $hook): void {
        $hook->add_metric('dummy');
    }

        public static function callback2(\tool_monitoring\hook\gather_metrics $hook): void {
        $hook->add_metric('dummy2');
    }
}

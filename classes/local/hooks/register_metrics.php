<?php

namespace tool_monitoring\local\hooks;

class register_metrics {

    public static function callback(\tool_monitoring\hook\gather_metrics $hook): void {
        $hook->add_metric(\tool_monitoring\local\metrics\user_count::class);
    }

    // public static function callback2(\tool_monitoring\hook\gather_metrics $hook): void {
    //     $hook->add_metric('dummy2');
    // }
}

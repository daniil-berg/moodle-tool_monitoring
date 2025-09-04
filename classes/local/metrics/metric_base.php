<?php

namespace tool_monitoring\local\metrics;

use tool_monitoring\local\metrics\metric_interface;
use core\lang_string;

abstract class metric_base implements metric_interface {

    abstract public static function calculate(): array;

    abstract public static function get_description(): lang_string;

    abstract public static function get_type(): metric_type;

    public static function get_name(): string {
        return get_called_class();
    }

    public static function gather_metrics_callback(\tool_monitoring\hook\gather_metrics $hook): void {
        $hook->add_metric(static::class);
    }

    public static function get_allowed_label_names(): ?array {
        return null;
    }
}
<?php

namespace tool_monitoring\local\metrics;

use lang_string;

class user_count implements metric_interface {

    public static function get_name(): string {
        return 'user_count';
    }

    public static function get_type(): metric_type {
        return metric_type::GAUGE;
    }

    public static function get_description(): lang_string {
        return new lang_string('user_count_description', 'tool_monitoring');
    }

    public static function calculate(): int {
        global $DB;

        return $DB->count_records('user');
    }
}
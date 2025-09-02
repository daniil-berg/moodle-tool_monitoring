<?php

namespace tool_monitoring\local\metrics;

class user_count {

    public static function get_name() {
        return 'user_count';
    }

    public static function get_type() {
        return 'gauge';
    }

    public static function get_description() {
        return 'Number of total registered users';
    }

    public static function calculate() {
        global $DB;

        return $DB->count_records('user');
    }
}
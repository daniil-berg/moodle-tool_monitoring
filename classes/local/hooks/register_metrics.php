<?php

namespace tool_monitoring\local\hooks;

class register_metrics {

    public static function callback(\tool_monitoring\hook\gather_metrics $hook): void {
        $hook->add_metric(\tool_monitoring\local\metrics\num_users::class);
        $hook->add_metric(\tool_monitoring\local\metrics\num_overdue_tasks_adhoc::class);
        $hook->add_metric(\tool_monitoring\local\metrics\num_overdue_tasks_scheduled::class);
        $hook->add_metric(\tool_monitoring\local\metrics\num_quiz_attempts_in_progress::class);
        $hook->add_metric(\tool_monitoring\local\metrics\num_tasks_spawned_adhoc::class);
        $hook->add_metric(\tool_monitoring\local\metrics\num_tasks_spawned_scheduled::class);
        $hook->add_metric(\tool_monitoring\local\metrics\num_users_accessed::class);
    }
}

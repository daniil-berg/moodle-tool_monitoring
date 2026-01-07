<?php

namespace tool_monitoring;

interface configurable {
    public function set_config(): void;
    public function get_config(): metric_value;
}

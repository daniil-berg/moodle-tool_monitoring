<?php

namespace tool_monitoring;

interface metric_calculate_single {
    public function __invoke(): metric_value;
}

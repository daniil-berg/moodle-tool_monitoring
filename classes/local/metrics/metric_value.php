<?php

namespace tool_monitoring\local\metrics;

class metric_value {
    
    private $value;
    private $label;

    function __construct(int|float $value, array $label = []) {
        $this->value = $value;
        $this->label = $label;
    }

    function get_value() {
        return $this->value;
    }

    function get_label() {
        return $this->label;
    }
}
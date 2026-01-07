<?php

namespace tool_monitoring;

use IteratorIterator;
use Traversable;

abstract class metric_calculate_multiple extends IteratorIterator implements metric_value_iterator {
    abstract public function produce_values(): iterable|metric_value;

    public function __construct(Traversable $iterator) {
        parent::__construct($iterator);
    }

    public function current(): metric_value {
        return parent::current();
    }
}
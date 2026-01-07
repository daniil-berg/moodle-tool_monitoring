<?php

namespace tool_monitoring;

use Iterator;

interface metric_value_iterator extends Iterator {
    public function current(): metric_value;
}

<?php

require_once(__DIR__ . '/../../../config.php');

$hook = new \tool_monitoring\hook\gather_metrics();
\core\di::get(\core\hook\manager::class)->dispatch($hook);

$metrics = $hook->get_metrics();

var_dump($metrics);

foreach ($metrics as $metric) {
    var_dump($metric::calculate());
}
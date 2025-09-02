<?php

require_once(__DIR__ . '/../../../config.php');

$hook = new \tool_monitoring\hook\gather_metrics();
\core\di::get(\core\hook\manager::class)->dispatch($hook);

var_dump($hook->getNames());
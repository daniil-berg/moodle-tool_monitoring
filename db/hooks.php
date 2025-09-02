<?php
$callbacks = [
    [
        'hook' => tool_monitoring\hook\gather_metrics::class,
        'callback' => [
            \tool_monitoring\local\hooks\register_metrics::class,
            'callback',
        ],
    ],
    // [
    //     'hook' => tool_monitoring\hook\gather_metrics::class,
    //     'callback' => [
    //         \tool_monitoring\local\hooks\register_metrics::class,
    //         'callback2',
    //     ],
    // ],
];
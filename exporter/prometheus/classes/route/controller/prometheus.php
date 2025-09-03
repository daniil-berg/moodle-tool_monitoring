<?php

namespace exporter_prometheus\route\controller;

use core\router\route;
use core\router\route_controller;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use core\param;
use tool_monitoring\metrics_manager;

class prometheus {
    use route_controller;

    #[route(
        // Resolves to https://example.com/tool_monitoring/{tag}/prometheus
        path: '/{tag}/prometheus',
        pathtypes: [
            new path_parameter(
                name: 'tag',
                type: param::ALPHANUM,
            ),
        ],
        queryparams: [
            new query_parameter(
                name: 'token',
                type: param::RAW,
            ),
        ],
    )]
    public function prometheus_route(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        string                 $tag,
    ): ResponseInterface {
        $token = $request->getQueryParams()['token'] ?? null;

        $response = $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        $manager = new metrics_manager();
        $response->getBody()->write(
            \exporter_prometheus\export::export($manager->calculate_needed_metrics($tag))
        );

        return $response;
    }
}

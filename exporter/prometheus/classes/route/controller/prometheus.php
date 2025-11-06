<?php

namespace monitoringexporter_prometheus\route\controller;

use core\router\route;
use core\router\route_controller;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use core\param;
use tool_monitoring\metrics_manager;
use monitoringexporter_prometheus;

class prometheus {
    use route_controller;

    #[route(
        // Resolves to https://example.com/monitoringexporter_prometheus/{tag}/metrics
        path: '/{tag}/metrics',
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
        $expectedtoken = get_config('monitoringexporter_prometheus', 'prometheus_token');
        if ($expectedtoken && $token !== $expectedtoken) {
            return $response->withStatus(403);
        }

        $response = $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        $manager = new metrics_manager();
        $response->getBody()->write(
            monitoringexporter_prometheus\exporter::export($manager->get_metrics($tag))
        );

        return $response;
    }
}

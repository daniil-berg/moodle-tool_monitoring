<?php

namespace tool_monitoring\route\controller;

use core\router\route;
use core\router\route_controller;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use core\param;

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
        $response->getBody()->write("tag {$tag} token {$token}");

        return $response;
    }
}

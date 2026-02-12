<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of the {@see prometheus} class.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace monitoringexporter_prometheus\route\controller;

use core\exception\coding_exception;
use core\param;
use core\router\route;
use core\router\route_controller;
use core\router\schema\parameters\query_parameter;
use dml_exception;
use GuzzleHttp\Psr7\Utils;
use monitoringexporter_prometheus\exporter as prometheus_exporter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use tool_monitoring\metrics_manager;

/**
 * Defines the route for Prometheus to pull the current metrics.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prometheus {
    use route_controller;

    /**
     * Responds with the current metrics in the Prometheus exposition format.
     *
     * Resolves to `/monitoringexporter_prometheus/metrics`. Optional query parameters:
     * - `token` for authorization; must match the `prometheus_token` config value, if one was set.
     * - `tags` for filtering specific metrics.
     *
     * @see https://prometheus.io/docs/instrumenting/exposition_formats
     *
     * @param Request $request Incoming, server-side HTTP request.
     * @param Response $response Outgoing, server-side response; returned response object is derived from this.
     * @return Response Plain text response in the Prometheus format.
     * @throws coding_exception
     * @throws dml_exception
     *
     * {@noinspection PhpUnused}
     */
    #[route(
        title: 'Prometheus endpoint',
        path: '/metrics',
        method: ['GET'],
        queryparams: [
            new query_parameter(
                name: 'token',
                description: 'Authorization token as configured by the site admin.',
                type: param::RAW,
                default: '',
            ),
            new query_parameter(
                name: 'tag',
                description: 'If provided, only metrics that carry these tags (comma separated) are returned.',
                type: param::TAGLIST,
                default: null,
            ),
            // TODO: Consider providing an optional `lang` parameter for the `HELP` text.
        ],
    )]
    public function get_metrics(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        try {
            $expectedtoken = get_config('monitoringexporter_prometheus', 'prometheus_token');
        } catch (dml_exception $e) {
            debugging("Failed to get `prometheus_token` from config: {$e->getMessage()}");
            return $response->withStatus(500);
        }
        if ($expectedtoken && $params['token'] !== $expectedtoken) {
            return $response->withStatus(403);
        }
        $manager = new metrics_manager();
        if ($params['tag']) {
            $tags = explode(',', $params['tag']);
        } else {
            $tags = [];
        }
        $metrics = $manager->fetch(tagnames: $tags)->metrics;
        $body = Utils::streamFor(prometheus_exporter::export($metrics));
        return $response->withBody($body)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}

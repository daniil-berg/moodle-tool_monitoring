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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace monitoringexporter_prometheus\route\controller;

use core\di;
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
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\exceptions\tags_disabled;
use tool_monitoring\registered_metrics;

/**
 * Provides the route for Prometheus to pull the current metrics.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prometheus {
    use route_controller;

    /**
     * Responds with the current metrics in the Prometheus exposition format.
     *
     * Resolves to `/monitoringexporter_prometheus/metrics`.
     *
     * Expected headers:
     * - `Authorization: Bearer <token>` must match the `prometheus_token` config value, if one was set.
     *
     * Optional query parameters:
     * - `token` as fallback for a missing/malformed auth header; must match the `prometheus_token` config value, if one was set.
     * - `tag` for filtering specific metrics.
     *
     * Possible response codes for errors:
     * - **403** if the authorization `token` does not match the `prometheus_token` config value.
     * - **422** if the `tags` query parameter is invalid.
     * - **500** for coding or DB errors.
     *
     * Consider the status codes (not the response texts) part of the public API.
     *
     * @link https://prometheus.io/docs/instrumenting/exposition_formats Prometheus format documentation
     *
     * @param Request $request Incoming, server-side HTTP request.
     * @param Response $response Outgoing, server-side response; the returned response object is derived from this.
     * @return Response Plain text response in the Prometheus format.
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
        // Define a closure for conveniently deriving a new response object from the original.
        $makeresponse = fn (string $text, int $status = 200, string $contenttype = 'text/plain; charset=utf-8'): Response
        => $response->withHeader('Content-Type', $contenttype)->withStatus($status)->withBody(Utils::streamFor($text));
        // Ensure the config is valid.
        try {
            $expectedtoken = get_config('monitoringexporter_prometheus', 'prometheus_token');
            // @codeCoverageIgnoreStart
        } catch (dml_exception $e) {
            debugging("Failed to get `prometheus_token` from config: {$e->getMessage()}");
            return $makeresponse('Error in Prometheus exporter', 500);
            // @codeCoverageIgnoreEnd
        }
        // Check auth.
        if ($expectedtoken !== '') {
            if (preg_match('/^Bearer\s+(.+)$/i', $request->getHeaderLine('Authorization'), $matches)) {
                $token = $matches[1];
            } else {
                $token = $params['token'];
            }
            if (!hash_equals($expectedtoken, $token)) {
                return $makeresponse('Invalid auth token', 403);
            }
        }
        // Parse tags.
        if ($params['tag']) {
            $tagnames = explode(',', $params['tag']);
        } else {
            $tagnames = [];
        }
        // Get the relevant metrics.
        try {
            $metrics = di::get(registered_metrics::class)->filter(enabled: true, tagnames: $tagnames);
        } catch (tag_not_found | tags_disabled $e) {
            return $makeresponse($e->getMessage(), 422);
        } catch (coding_exception | dml_exception) {
            return $makeresponse('Error in Prometheus exporter', 500);
        }
        // Calculate and export the metrics.
        $text = prometheus_exporter::export(...$metrics);
        return $makeresponse($text);
    }
}

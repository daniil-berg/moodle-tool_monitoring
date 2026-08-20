<?php
// This file is part of the tool_monitoring plugin for Moodle - https://moodle.org/
//
// tool_monitoring is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// tool_monitoring is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with tool_monitoring.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Definition of the {@see prometheus_test} class.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

namespace monitoringexporter_prometheus\route\controller;

use advanced_testcase;
use core\di;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\exceptions\tags_disabled;
use tool_monitoring\local\testing\test_metric_tag;
use tool_monitoring\local\testing\test_registered_metric;
use tool_monitoring\local\testing\test_registered_metrics;
use tool_monitoring\metric_value;
use tool_monitoring\registered_metrics;

/**
 * Unit tests for the {@see prometheus} class.
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
#[CoversClass(prometheus::class)]
final class prometheus_test extends advanced_testcase {
    /**
     * Tests the {@see prometheus::get_metrics} route handler method.
     *
     * @param array $metrics Metrics to register for the test.
     * @param string $configtoken Access token to set in the config.
     * @param array $headers HTTP headers to send in the request.
     * @param array $queryparams Query parameters to send in the request.
     * @param int $expectedstatus Expected HTTP status code.
     * @param string $expectedbody Expected response body.
     */
    #[DataProvider('provider_test_get_metrics')]
    public function test_get_metrics(
        array $metrics,
        string $configtoken,
        array $headers,
        array $queryparams,
        int $expectedstatus,
        string $expectedbody,
    ): void {
        $this->resetAfterTest();
        di::set(registered_metrics::class, new test_registered_metrics(...$metrics));
        set_config('prometheus_token', $configtoken, 'monitoringexporter_prometheus');
        $request = new ServerRequest('GET', '/metrics');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $request = $request->withQueryParams($queryparams);
        $controller = new prometheus();
        $response = $controller->get_metrics($request, new Response());
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame($expectedstatus, $response->getStatusCode());
        self::assertSame($expectedbody, (string) $response->getBody());
    }

    /**
     * Provides test data for the {@see test_get_metrics} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_metrics(): array {
        $paramsdefault = ['token' => '', 'tag' => null];
        return [
            'No access token configured, provided tokens ignored' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => '',
                'headers'        => ['Authorization' => 'Bearer this_is_ignored'],
                'queryparams'    => [...$paramsdefault, 'token' => 'this_is_also_ignored'],
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_untagged counter\ntool_monitoring_untagged 42\n",
            ],
            'No access token configured, no credentials provided' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => '',
                'headers'        => [],
                'queryparams'    => $paramsdefault,
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_untagged counter\ntool_monitoring_untagged 42\n",
            ],
            'Disabled metrics never rendered' => [
                'metrics'        => [
                    new test_registered_metric('disabled', enabled: false, values: [new metric_value(3.14)]),
                    new test_registered_metric('untagged', values: [new metric_value(42)]),
                ],
                'configtoken'    => '',
                'headers'        => [],
                'queryparams'    => $paramsdefault,
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_untagged counter\ntool_monitoring_untagged 42\n",
            ],
            'Auth header "Bearer" with correct token' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => ['Authorization' => 'Bearer correct'],
                'queryparams'    => $paramsdefault,
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_untagged counter\ntool_monitoring_untagged 42\n",
            ],
            'Auth header "bEaReR" (case-insensitive) with correct token' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => ['Authorization' => 'bEaReR correct'],
                'queryparams'    => $paramsdefault,
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_untagged counter\ntool_monitoring_untagged 42\n",
            ],
            'Query parameter with correct token' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => [],
                'queryparams'    => [...$paramsdefault, 'token' => 'correct'],
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_untagged counter\ntool_monitoring_untagged 42\n",
            ],
            'Auth header with correct token, wrong token in query param' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => ['Authorization' => 'Bearer correct'],
                'queryparams'    => [...$paramsdefault, 'token' => 'wrong'],
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_untagged counter\ntool_monitoring_untagged 42\n",
            ],
            'Valid auth header but with wrong token, correct token in query param' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => ['Authorization' => 'Bearer wrong'],
                'queryparams'    => [...$paramsdefault, 'token' => 'correct'],
                'expectedstatus' => 403,
                'expectedbody'   => 'Invalid auth token',
            ],
            'Malformed auth header, correct token in query param' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => ['Authorization' => 'Basic deadbeef'],
                'queryparams'    => [...$paramsdefault, 'token' => 'correct'],
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_untagged counter\ntool_monitoring_untagged 42\n",
            ],
            'Malformed auth header, wrong token in query param' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => ['Authorization' => 'Basic deadbeef'],
                'queryparams'    => [...$paramsdefault, 'token' => 'wrong'],
                'expectedstatus' => 403,
                'expectedbody'   => 'Invalid auth token',
            ],
            'Missing any credentials' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => [],
                'queryparams'    => $paramsdefault,
                'expectedstatus' => 403,
                'expectedbody'   => 'Invalid auth token',
            ],
            'Wrong header only' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => ['Authorization' => 'Bearer wrong'],
                'queryparams'    => $paramsdefault,
                'expectedstatus' => 403,
                'expectedbody'   => 'Invalid auth token',
            ],
            'Wrong query only' => [
                'metrics'        => [new test_registered_metric('untagged', values: [new metric_value(42)])],
                'configtoken'    => 'correct',
                'headers'        => [],
                'queryparams'    => [...$paramsdefault, 'token' => 'wrong'],
                'expectedstatus' => 403,
                'expectedbody'   => 'Invalid auth token',
            ],
            'Matching tag filter' => [
                'metrics'        => [
                    new test_registered_metric('untagged', values: [new metric_value(42)]),
                    new test_registered_metric(
                        'tagged',
                        tags: ['foo' => new test_metric_tag('foo')],
                        values: [new metric_value(3.14)]
                    ),
                ],
                'configtoken'    => 'correct',
                'headers'        => ['Authorization' => 'Bearer correct'],
                'queryparams'    => [...$paramsdefault, 'tag' => 'foo'],
                'expectedstatus' => 200,
                'expectedbody'   => "# TYPE tool_monitoring_tagged counter\ntool_monitoring_tagged 3.14\n",
            ],
        ];
    }

    /**
     * Tests the {@see prometheus::get_metrics} route handler method when an exception is thrown during filtering.
     *
     * @param moodle_exception $exception The exception to throw.
     * @param int $expectedstatus Expected HTTP status code.
     * @param string $expectedbody Expected response body.
     */
    #[DataProvider('provider_test_get_metrics_exception')]
    public function test_get_metrics_exception(moodle_exception $exception, int $expectedstatus, string $expectedbody): void {
        $this->resetAfterTest();
        $metrics = $this->getMockBuilder(test_registered_metrics::class)->onlyMethods(['filter'])->getMock();
        $metrics->expects($this->once())->method('filter')->willThrowException($exception);
        di::set(registered_metrics::class, $metrics);
        $request = new ServerRequest('GET', '/metrics');
        $request = $request->withQueryParams(['token' => '', 'tag' => null]);
        $controller = new prometheus();
        $response = $controller->get_metrics($request, new Response());
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame($expectedstatus, $response->getStatusCode());
        self::assertSame($expectedbody, (string) $response->getBody());
    }

    /**
     * Provides test data for the {@see test_get_metrics_exception} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_get_metrics_exception(): array {
        return [
            'Filtering throws coding_exception' => [
                'exception'      => new coding_exception('test'),
                'expectedstatus' => 500,
                'expectedbody'   => 'Error in Prometheus exporter',
            ],
            'Filtering throws dml_exception' => [
                'exception'      => new dml_exception('test'),
                'expectedstatus' => 500,
                'expectedbody'   => 'Error in Prometheus exporter',
            ],
            'Filtering throws tag_not_found' => [
                'exception'      => new tag_not_found('foo', 'collection_name'),
                'expectedstatus' => 422,
                'expectedbody'   => 'No tag named "foo" exists in the "collection_name" collection.',
            ],
            'Filtering throws tags_disabled' => [
                'exception'      => new tags_disabled('tag_item_type'),
                'expectedstatus' => 422,
                'expectedbody'   => 'Tags are turned off globally or the "tag_item_type" tag area is disabled.',
            ],
        ];
    }
}

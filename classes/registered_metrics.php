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
 * Definition of the {@see registered_metrics} interface.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring;

use ArrayAccess;
use core\exception\coding_exception;
use dml_exception;
use tool_monitoring\exceptions\metric_not_found;
use tool_monitoring\exceptions\tag_not_found;
use tool_monitoring\exceptions\tags_disabled;
use tool_monitoring\hook\metric_collection;

/**
 * Encapsulates the consumable interface for all registered metrics.
 *
 * Provides array-like subscript access to {@see registered_metric} instances by their qualified name.
 * (See the {@see self::offsetExists `offsetExists`} and {@see self::offsetGet `offsetGet`} methods.)
 * **NOTE**: Access is read-only. Metrics cannot be added to or removed from the manager directly.
 * Consumers such as sub-plugin exporters or output renderers get an instance via Moodle's DI container:
 *
 * ```
 * $metrics = di::get(registered_metrics::class);
 * if (isset($metrics['my_metric'])) { // This works.
 *     $metric = $metrics['my_metric']; // This also works.
 *     unset($metrics['my_metric']); // Error!
 * }
 * $metrics['my_metric'] = $something; // Error!
 * ```
 *
 * Collected and registered metrics can be retrieved via the {@see self::filter `filter`} method.
 * Omitting any arguments will return all of them.
 *
 * ```
 * $metrics = di::get(registered_metrics::class);
 * // Only get enabled metrics that carry the 'foo' tag:
 * foreach ($metrics->filter(enabled: true, tagnames: ['foo']) as $qname => $metric) {
 *     // Now `$qname` is a string and `$metric` is a `registered_metric` object.
 * }
 * ```
 *
 * **This interface is a consumer-only contract.**
 * Plugins and sub-plugins should use it for annotation but are discouraged from implementing it.
 * Implementations risk source-breaking changes whenever methods or properties are added to the interface.
 *
 * @phpcs:disable moodle.Commenting.ValidTags.Invalid
 * @extends ArrayAccess<string, registered_metric>
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface registered_metrics extends ArrayAccess {
    /**
     * Produces registered metrics managed by the plugin.
     *
     * Will _not_ produce metrics that are not (yet) registered in the database, even if they were picked up by the
     * {@see metric_collection} hook.
     *
     * @param bool|null $enabled If `true`, the only enabled metrics will be returned; if `false`, only disabled ones;
     *                           passing `null` (default) disables this filter.
     * @param string[] $tagnames Names of tags to filter by. Only metrics that carry all the specified tags will be returned.
     *                           Names will be normalized before looking up the tags. An empty array (default) disables this filter.
     * @return array<string, registered_metric> Registered metrics indexed by their qualified name.
     * @throws coding_exception
     * @throws dml_exception
     * @throws tag_not_found At least one of the provided `$tagnames` does not match any existing metric tag.
     * @throws tags_disabled
     */
    public function filter(bool|null $enabled = null, array $tagnames = []): array;

    /**
     * Checks whether a metric with the given qualified name is registered.
     *
     * Will return `false` if no metric with the given qualified name is (yet) registered in the database, even if one was picked up
     * by the {@see metric_collection} hook.
     *
     * @param string $offset Qualified name of the metric to check.
     * @return bool `true` if the metric is registered, `false` otherwise.
     * @throws coding_exception
     */
    #[\Override]
    public function offsetExists(mixed $offset): bool;

    /**
     * Returns the registered metric with the given qualified name.
     *
     * Will _not_ return a metric not (yet) registered in the database, even if it was picked up by the {@see metric_collection}
     * hook.
     *
     * @param string $offset Qualified name of the metric to return.
     * @return registered_metric Metric with the given qualified name.
     * @throws coding_exception
     * @throws metric_not_found No metric with the given qualified name is registered.
     */
    #[\Override]
    public function offsetGet(mixed $offset): registered_metric;

    /**
     * Always throws an exception because the managed metrics are read-only.
     *
     * @param mixed $offset Ignored
     * @param mixed $value Ignored
     * @throws coding_exception
     */
    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void;

    /**
     * Always throws an exception because the managed metrics are read-only.
     *
     * @param mixed $offset Ignored
     * @throws coding_exception
     */
    #[\Override]
    public function offsetUnset(mixed $offset): void;
}

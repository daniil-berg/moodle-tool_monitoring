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
 * Definition of the {@see metrics_cache} class.
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

namespace tool_monitoring\local;

use core\exception\coding_exception;
use core_cache\application_cache;
use core_cache\cache;
use tool_monitoring\registered_metric;

/**
 * Internal cache manager for registered metrics.
 *
 * This class is just a wrapper around methods of the {@see cache} class, specifically for {@see registered_metric} objects, for
 * added convenience and type safety. **It is not part of the public API.**
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
final class metrics_cache {
    /**
     * Returns the underlying cache instance.
     *
     * @return application_cache
     */
    private static function make(): application_cache {
        return cache::make('tool_monitoring', 'metrics');
    }

    /**
     * Adds/updates the specified metrics in the cache.
     *
     * @param registered_metric ...$metrics Metrics to cache. If named arguments are passed, those keys **must** match the qualified
     *                                      names of the metrics.
     * @throws coding_exception
     */
    public static function set(registered_metric ...$metrics): void {
        if (array_is_list($metrics)) {
            $metrics = array_column($metrics, null, 'qualifiedname');
        }
        self::make()->set_many($metrics);
    }

    /**
     * Returns the metric with the specified qualified name from the cache.
     *
     * @param string $qualifiedname Qualified name of the metric to retrieve.
     * @return registered_metric|null Metric with the specified qualified name, or `null` if no such metric is registered.
     * @throws coding_exception
     */
    public static function get(string $qualifiedname): registered_metric|null {
        return self::make()->get($qualifiedname);
    }

    /**
     * Returns the metrics with the specified qualified names from the cache.
     *
     * @param string ...$qualifiednames Qualified names of the metrics to retrieve.
     * @return array<string, registered_metric|null> Associative array of `$qualifiednames` mapped to {@see registered_metric}
     *                                               instances and `null` for where no such metrics are registered.
     * @throws coding_exception
     */
    public static function get_many(string ...$qualifiednames): array {
        return self::make()->get_many($qualifiednames);
    }

    /**
     * Deletes the metrics with the specified qualified names from the cache.
     *
     * @param string ...$qualifiednames Qualified names of the metrics.
     * @return int Number of metrics that were deleted from the cache.
     * @throws coding_exception
     */
    public static function delete(string ...$qualifiednames): int {
        return self::make()->delete_many($qualifiednames);
    }

    /**
     * Purges the entire cache.
     *
     * @return bool `true` on success, `false` on failure.
     */
    public static function purge(): bool {
        return self::make()->purge();
    }
}

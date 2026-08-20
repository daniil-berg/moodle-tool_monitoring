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

namespace tool_monitoring\local\testing;

use core\exception\coding_exception;
use tool_monitoring\registered_metric;
use tool_monitoring\registered_metrics;

/**
 * Example implementation of {@see registered_metrics} that filters/returns metrics set in the constructor.
 *
 * **TESTING ONLY: This exists purely to run unit tests.**
 *
 * @codeCoverageIgnore
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
class test_registered_metrics implements registered_metrics {
    /** @var array<string, registered_metric> Stored metrics, indexed by qualified name. */
    private array $metrics;

    /**
     * Stores the given metrics to be returned by {@see self::filter} and array access.
     *
     * @param registered_metric ...$metrics Metrics to store.
     */
    public function __construct(registered_metric ...$metrics) {
        $this->metrics = array_column($metrics, null, 'qualifiedname');
    }

    #[\Override]
    public function filter(bool|null $enabled = null, array $tagnames = []): array {
        return array_filter(
            $this->metrics,
            fn (registered_metric $metric): bool
            => (is_null($enabled) || $metric->enabled === $enabled)
               && !array_diff($tagnames, array_keys($metric->tags)),
        );
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool {
        return isset($this->metrics[$offset]);
    }

    #[\Override]
    public function offsetGet(mixed $offset): registered_metric {
        return $this->metrics[$offset];
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void {
        throw new coding_exception('Cannot manually set metrics.');
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void {
        throw new coding_exception('Cannot manually unset metrics.');
    }
}

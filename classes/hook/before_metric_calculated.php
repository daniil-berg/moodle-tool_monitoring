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

namespace tool_monitoring\hook;

use core\hook\described_hook;

/**
 * The hook before_metric_calculated can be used to define pre-calculation behavior for metrics,
 * e.g. collecting data for meta-metrics
 *
 * @package   tool_monitoring
 * @copyright 2026 Konrad Moesch <konrad.moesch@uni-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class before_metric_calculated implements described_hook {
    #[\Override]
    public function __construct(
        /** @var string qualified name of the metric to be calculated */
        public readonly string $qualifiedname,
    ) {
    }
    #[\Override]
    public static function get_hook_description(): string {
        return 'Provides the ability to add custom pre-calculation behavior to metrics, e.g. for meta metrics';
    }

    #[\Override]
    public static function get_hook_tags(): array {
        return ['metric', 'monitoring', 'tool_monitoring', 'calculation', 'pre-calculation'];
    }
}

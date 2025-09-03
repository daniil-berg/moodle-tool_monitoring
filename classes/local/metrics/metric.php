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
 * Definition of the {@see metric_interface}.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauck <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring\local\metrics;

use core\exception\coding_exception;
use core\lang_string;

/**
 * Describes any available metric.
 */
abstract class metric {
    /**
     * Actually compute the value.
     *
     * @return void
     */
    abstract public function calculate(): void;

    /**
     * The actual values.
     * @var array
     */
    private $values = [];

    /**
     * Set the value.
     * @param float|int $value
     * @param array $labels
     * @return void
     */
    protected function set_value($value, $labels = []): void {
        if (count(static::get_labels()) !== count($labels)) {
            throw new coding_exception('Wrong number of labels');
        }
        $ref = &$this->values;
        foreach ($labels as $label) {
            if (!array_key_exists($label, $ref)) {
                $ref[$label] = [];
            }
            $ref = &$ref[$label];
        }
        $ref = $value;
    }

    /**
     * Helper function to retrieve labeled values.
     * @param mixed $array
     * @return array<array|mixed>[]
     */
    private function extract_values($array): array {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $extracted = $this->extract_values($value);
                foreach ($extracted as [$value, $keys]) {
                    $result[] = [$value, [$key, ...$keys]];
                }
            } else {
                $result[] = [$value, [$key]];
            }
        }
        return $result;
    }

    /**
     * Get the calculated value.
     * @return array
     */
    public function get_values(): array {
        return $this->extract_values($this->values);
    }

    /**
     * Return the label names used by this metric.
     *
     * @return string[]
     */
    public static function get_labels(): array {
        return [];
    }

    /**
     * Description of this metric.
     *
     * @return lang_string
     */
    abstract public static function get_description(): lang_string;

    /**
     * The name of this metric. Should be close to the class name.
     *
     * @return string
     */
    abstract public static function get_name(): string;

    /**
     * Type of the metric.
     *
     * @return metric_type
     */
    abstract public static function get_type(): metric_type;
}

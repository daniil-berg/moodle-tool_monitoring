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
 * Definition of the abstract {@see metric} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring;

use ArrayAccess;
use core\exception\coding_exception;
use Countable;
use Iterator;
use tool_monitoring\local\unqualified_class_name;

/**
 * Encapsulates labeled metric values in the form of an associative array.
 *
 * Labels must be associative arrays with label names as keys mapped to the respective label values.
 * Such a label then serves as an array key mapped to the actual metric value. The latter must be a float or int.
 *
 * Given a `$metric` instance of a concrete subclass, new metric values can then be assigned like so:
 * ```
 * $metric[['some_label' => 'foo', 'another_label' => 'spam']] = 42;
 * ```
 * In this example the label _names_ are `some_label` and `another_label`, the associated label _values_ are `foo` and
 * `spam`, together that _labels_ array is used as a key for the `$metric` instance and the actual _value of the metric_
 * with those labels is `42`.
 *
 * Concrete classes extending the {@see metric} class must implement the {@see calculate} method, in addition to
 * the {@see described_metric::get_description} and {@see described_metric::get_type} methods.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class metric implements ArrayAccess, Countable, Iterator, described_metric {
    use unqualified_class_name;

    /**
     * @var array<string, float|int> Internal store of serialized label arrays mapped to metric values.
     */
    private array $values = [];

    /**
     * Returns whether a metric value with the specified labels exists.
     *
     * @param array<string, string> $offset Associative array with label names as keys mapped to the respective values.
     * @return bool `true` if the metric value is present; `false` otherwise.
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->values[self::key_from_labels($offset)]);
    }

    /**
     * Returns the metric value with the specified labels.
     *
     * @param array<string, string> $offset Associative array with label names as keys mapped to the respective values.
     * @return float|int|null The metric value for the given labels or `null` if no value with those labels exists.
     */
    public function offsetGet(mixed $offset): float|int|null {
        return $this->values[self::key_from_labels($offset)] ?? null;
    }

    /**
     * Adds/updates a metric value with the specified labels.
     *
     * @param array<string, string> $offset Associative array with label names as keys mapped to the respective values.
     * @param float|int $value The metric value to set for the given labels.
     * @throws coding_exception Non-array type labels provided.
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        if (!is_array($offset)) {
            // TODO: Replace with custom exception type.
            throw new coding_exception('Metric labels must be an associative array.');
        }
        $this->values[self::key_from_labels($offset)] = $value;
    }

    /**
     * Removes the metric value with the specified labels.
     *
     * No-op if there is no metric value with the given labels.
     *
     * @param array<string, string> $offset Associative array with label names as keys mapped to the respective values.
     */
    public function offsetUnset(mixed $offset): void {
        unset($this->values[self::key_from_labels($offset)]);
    }

    /**
     * Returns the number of metric values stored in the instance.
     *
     * @return int Number of metric values.
     */
    public function count(): int {
        return count($this->values);
    }

    /**
     * Returns the metric value the iterator is currently pointing to.
     *
     * @return false|float|int Metric value; `false` if the iterator points beyond the end or no values are present.
     */
    public function current(): false|float|int {
        return current($this->values);
    }

    /**
     * Returns the labels of the current metric value.
     *
     * @return array<string, string>|null Metric labels; `false` if the iterator points beyond the end or no values are present.
     */
    public function key(): array|null {
        $key = key($this->values);
        return is_null($key) ? null : self::labels_from_key($key);
    }

    /**
     * Moves the iterator forward to the next metric value.
     */
    public function next(): void {
        next($this->values);
    }

    /**
     * Resets the iterator to the first metric value.
     */
    public function rewind(): void {
        reset($this->values);
    }

    /**
     * Returns whether the iterator currently points at a metric value.
     *
     * @return bool `true` when at a metric value; `false` when pointing beyond the end or no values are present.
     */
    public function valid(): bool {
        return !is_null(key($this->values));
    }

    /**
     * Turns an associative array of labels into a key for the metric array.
     *
     * Inverse to {@see labels_from_key}.
     *
     * @param array<string, string> $labels Associative array with label names as keys mapped to the respective values.
     * @return string Suitable key for the metric array derived from the labels.
     */
    private static function key_from_labels(array $labels): string {
        return serialize($labels);
    }

    /**
     * Turns a key in the metric array into an associative array of labels.
     *
     * Inverse to {@see key_from_labels}.
     *
     * @param string $key Key in the metric array.
     * @return array<string, string> Associative array with label names as keys mapped to the respective values.
     */
    private static function labels_from_key(string $key): array {
        return unserialize($key);
    }

    /**
     * Sets all relevant metric values on the instance.
     *
     * This method is what exporters will ultimately call before passing on the labeled metric values to their
     * respective monitoring service.
     *
     * Example implementation:
     * ```
     * class my_metric extends metric {
     *     public function calculate(): void {
     *         global $DB;
     *         // Maybe fetch values from the database.
     *         // Then assign those values to the metric.
     *         $this[['some_label' => 'foo']] = 42;
     *         $this[['some_label' => 'bar']] = 69420;
     *         $this[['some_label' => 'baz']] = 0;
     *     }
     *     // Implementations of other methods...
     * }
     * ```
     */
    abstract public function calculate(): void;
}

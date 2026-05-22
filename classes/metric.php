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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_monitoring;

use core\component;
use core\exception\coding_exception;
use core\lang_string;
use tool_monitoring\hook\metric_collection;
use Traversable;

/**
 * Base class for all metrics.
 *
 * Concrete subclasses only **need** to implement the {@see calculate} and {@see get_type} methods.
 *
 * Inheriting classes _may_ also override the {@see get_name} and {@see get_description} methods.
 *
 * @phpcs:disable moodle.Commenting.ValidTags.Invalid
 * @template TConf of metric_config
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
abstract class metric {
    /**
     * Constructor without any parameters.
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    final public function __construct() {}

    /**
     * Creates a new instance of the metric and adds it to the provided collection.
     *
     * Calls the hook's {@see metric_collection::add} method.
     *
     * @link https://moodledev.io/docs/apis/core/hooks#hook-callback Documentation: Hook callback
     *
     * @param metric_collection $hook Hook to pick up the metric.
     * @return static New metric instance.
     */
    public static function collect(metric_collection $hook): static {
        $instance = new static();
        $hook->add($instance);
        return $instance;
    }

    /**
     * Produces the current metric value(s).
     *
     * If the implementing metric only ever has a single value, this method can return just a single {@see metric_value} instance.
     * If multiple labeled values are available at any given time, this method should return them as an array or produce them in
     * another {@see Traversable} form.
     *
     * This method will be called to export values to the configured monitoring service(s).
     *
     * @param TConf|null $config Metric configuration, if any.
     * @return iterable<metric_value>|metric_value Singular metric value or an array or traversable object of metric values.
     */
    abstract public function calculate(metric_config|null $config = null): iterable|metric_value;

    /**
     * Returns the type of the metric.
     *
     * @return metric_type
     */
    abstract public function get_type(): metric_type;

    /**
     * Returns the localized description of the metric.
     *
     * Subclasses may override this. Defaults to a language string with the ID `"metric:{$name}_desc"` where `$name` is the
     * metric's name as returned by the {@see self::get_name `get_name`} method, residing in the language file of the defining
     * component as returned by the {@see self::get_component `get_component`} method.
     *
     * @return lang_string Metric description/help text.
     * @throws coding_exception
     */
    public function get_description(): lang_string {
        return new lang_string("metric:{$this->get_name()}_desc", $this->get_component());
    }

    /**
     * Returns the name of the metric to be used as an identifier.
     *
     * Subclasses may override this. Defaults to the unqualified class name. The name
     * - _should_ be descriptive,
     * - _must_ only consist of lowercase letters, digits, and underscores,
     * - _must not_ start with a digit,
     * - _must_ be unique for the defining component as returned by the {@see self::get_component `get_component`} method,
     * - _must_ be no longer than 100 characters.
     *
     * @return string Unique metric name/identifier.
     */
    public function get_name(): string {
        $name = static::class;
        if (($pos = strrpos($name, '\\')) === false) {
            return $name; // @codeCoverageIgnore
        }
        return substr($name, $pos + 1);
    }

    /**
     * Returns the name of the Moodle component/plugin, which defines the metric.
     *
     * @return string Moodle component name.
     */
    final public function get_component(): string {
        return component::get_component_from_classname(static::class);
    }
}

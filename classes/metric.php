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

use core\component;
use core\lang_string;
use MoodleQuickForm;
use stdClass;
use tool_monitoring\hook\metric_collection;
use Traversable;

/**
 * Base class for all metrics.
 *
 * Concrete subclasses only **need** to implement the {@see calculate}, {@see get_description} and {@see get_type} methods.
 *
 * Inheriting classes _may_ also override the {@see get_name} method to provide a custom identifier and the {@see validate_value}
 * method to perform simple checks on the {@see metric_value} objects yielded by an instance during iteration.
 *
 * For advanced use cases, if the metric should allow specific custom configuration via the admin panel,
 * the {@see add_config_form_elements} the {@see get_default_config_data} methods should also be overridden (in a compatible way).
 * For these use cases, this base class is generic in the type of the `$config` parameter of {@see calculate}.
 * Subclasses may narrow that type in an `extends` tag.
 *
 * @template ConfT of object = stdClass
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
abstract class metric {

    /**
     * Constructor without any parameters.
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
     * If the implementing class expects a specific `$config` type, it can be narrowed in an `extends` tag in the class' doc block.
     *
     * @param ConfT $config Current metric-specific config (if applicable).
     * @return iterable<metric_value>|metric_value Singular metric value or an array or traversable object of metric values.
     */
    abstract public function calculate(object $config): iterable|metric_value;

    /**
     * Returns the localized description of the metric.
     *
     * @return lang_string Metric description/help text.
     */
    abstract public static function get_description(): lang_string;

    /**
     * Returns the type of the metric.
     *
     * @return metric_type
     */
    abstract public static function get_type(): metric_type;

    /**
     * Returns the name of the metric to be used as an identifier.
     *
     * Subclasses may override this. It _should_ be descriptive and only consist of letters and underscores; it _must_ be unique for
     * the defining component as returned by {@see get_component}; it _must_ be a maximum of 100 characters long.
     * Defaults to the unqualified class name.
     *
     * @return string Unique metric name/identifier.
     */
    public static function get_name(): string {
        $name = static::class;
        if (($pos = strrpos($name, '\\')) === false) {
            return $name; // @codeCoverageIgnore
        }
        return substr($name, $pos + 1);
    }

    /**
     * Returns the name of the Moodle component, i.e. the plugin or core component, which defines this metric.
     *
     * @return string Moodle component name.
     */
    final public static function get_component(): string {
        return component::get_component_from_classname(static::class);
    }

    /**
     * Ensures that the provided metric value is valid.
     *
     * Inheriting classes may override this method to validate each {@see metric_value} before yielding it from the iterator.
     *
     * Overrides _should_ throw an exception for an invalid metric value.
     *
     * The default implementation is no-op.
     *
     * @param metric_value $metricvalue Metric value instance to be validated.
     * @return metric_value Valid metric value.
     */
    public static function validate_value(metric_value $metricvalue): metric_value {
        return $metricvalue;
    }

    /**
     * If the metric requires custom configuration, this method can be overridden to extend a {@see MoodleQuickForm} object.
     *
     * Implementations _should_ ensure that any added form fields are compatible with the default config data that is returned by
     * the {@see get_default_config_data} method, i.e. the keys of the returned array correspond to the added form field names.
     *
     * By default, this does nothing.
     *
     * @param MoodleQuickForm $mform Configuration form for the metric.
     */
    public static function add_config_form_elements(MoodleQuickForm $mform): void {
        // Do nothing.
    }

    /**
     * If the metric requires custom configuration, this method can be overridden to return a default config.
     *
     * Implementations _should_ ensure that the default config is compatible with the metric-specific config form fields added via
     * the {@see add_config_form_elements} method, i.e. the properties of the object correspond to the added form field names.
     * By default, returns an empty object.
     *
     * @return ConfT Default config data for the metric; empty if no specific config is available.
     */
    public static function get_default_config_data(): object {
        return new stdClass();
    }
}

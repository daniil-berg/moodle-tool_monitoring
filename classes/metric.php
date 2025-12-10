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
use core\exception\coding_exception;
use core\lang_string;
use dml_exception;
use IteratorAggregate;
use JsonException;
use Traversable;

/**
 * Base class for all metrics.
 *
 * Metric values can be retrieved by iterating over an instance of this class.
 *
 * Concrete subclasses only **need** to implement the {@see calculate}, {@see get_description} and {@see get_type} methods.
 *
 * Inheriting classes _may_ also override the {@see get_name} method to provide a custom identifier and the {@see validate_value}
 * method to perform simple checks on the {@see metric_value} objects yielded by an instance during iteration.
 *
 * For advanced use cases, if the metric should allow specific custom configuration via the admin panel, the {@see get_config_form}
 * and {@see get_default_config_data} methods should also be overridden (in a compatible way).
 *
 * @property-read metric_config $config Cached configuration of the metric; loaded from the database on first read access;
 *                                      to ensure it is up to date, call {@see load_config}.
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
abstract class metric implements IteratorAggregate {

    /** @var metric_config Configuration of the metric; guaranteed to be set before {@see calculate} is called. */
    private metric_config $config;

    /**
     * Produces the current metric value(s).
     *
     * If the implementing metric only ever has a single value, this method can return just a single {@see metric_value} instance.
     * If multiple labeled values are available at any given time, this method should return them as an array or produce them in
     * another {@see Traversable} form.
     *
     * This method will be called to export values to the configured monitoring service(s).
     *
     * @return iterable<metric_value>|metric_value Singular metric value or an array or traversable object of metric values.
     */
    abstract protected function calculate(): iterable|metric_value;

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
     * Returns the qualified name of the metric as a composite of its name and component.
     *
     * @return string Fully qualified metric name.
     */
    final public static function get_qualified_name(): string {
        return static::get_component() . '_' . static::get_name();
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
    protected static function validate_value(metric_value $metricvalue): metric_value {
        return $metricvalue;
    }

    /**
     * Produces the current {@see metric_value}s.
     *
     * This allows the metric instance to be iterated over in a `foreach` loop.
     *
     * @return Traversable<metric_value> Values of the metric.
     */
    final public function getIterator(): Traversable {
        $values = $this->calculate();
        if ($values instanceof metric_value) {
            $values = [$values];
        }
        foreach ($values as $metricvalue) {
            yield static::validate_value($metricvalue);
        }
    }

    /**
     * Fetches the current metric configuration from the database and saves it in the {@see self::$config} property.
     *
     * If no config for the metric is found in the database, a default config object is constructed and saved. In that case
     * the {@see get_default_config_data} method is called to set any optional metric-specific configuration values.
     *
     * @return metric_config The newly loaded config object.
     * @throws coding_exception Should not happen.
     * @throws dml_exception Unexpected error in the database query.
     * @throws JsonException Failed to (de-)serialize the config `data` value.
     */
    final public function load_config(): metric_config {
        $this->config = metric_config::for_metric($this);
        return $this->config;
    }

    /**
     * Special case for read access to the protected {@see config} property.
     *
     * TODO Remove once we can finally depend on PHP 8.4+ and use asymmetric visibility and a nice property `get`-hook.
     *
     * @param string $name Property name.
     * @return mixed Property value.
     * @throws coding_exception Should not happen.
     * @throws dml_exception Unexpected error in the database query.
     * @throws JsonException Failed to (de-)serialize the config `data` value.
     */
    final public function __get(string $name): mixed {
        if ($name === 'config') {
            if (!isset($this->config)) {
                $this->load_config();
            }
            return $this->config;
        }
        return $this->$name;
    }

    /**
     * Form definition for the metric configuration.
     *
     * If the metric requires custom configuration, this method should be overridden and an appropriately defined form object that
     * inherits from {@see form\config} should be returned. Any additional fields in the form's definition **must** be compatible
     * with the default config data returned by the {@see get_default_config_data} method. This means the keys of the returned array
     * must correspond exactly to the added form field names.
     * The parent {@see form\config::definition} method **must** be called in the form's `definition` method.
     *
     * By default, this does nothing more than instantiating a {@see form\config} object with the provided constructor arguments.
     *
     * @param mixed ...$args Arguments that have to be passed to the form constructor.
     */
    public static function get_config_form(...$args): form\config {
        return new form\config(...$args);
    }

    /**
     * Returns the default config data to be set for the {@see metric_config::data} of the metric.
     *
     * If the metric requires custom configuration, this method should be overridden and an associative array of configuration
     * field-value-pairs should be returned. These **must** be compatible with the metric-specific config form fields defined via
     * the {@see get_config_form} method, i.e. the keys of the returned array must correspond exactly to the added form field names.
     * By default, returns `null`.
     *
     * @return array<string, mixed>|null Default config data for the metric or `null` if no specific config is available.
     */
    public static function get_default_config_data(): array|null {
        return null;
    }
}

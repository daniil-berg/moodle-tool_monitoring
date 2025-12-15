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
use dml_missing_record_exception;
use Exception;
use IteratorAggregate;
use JsonException;
use MoodleQuickForm;
use tool_monitoring\hook\metrics_manager;
use tool_monitoring\local\metric_orm;
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
 * For these use cases, this base class is generic in terms of the {@see config} type, which can be narrowed in an `extends` tag.
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
abstract class metric implements IteratorAggregate {
    /** @use metric_orm<ConfT> */
    use metric_orm;

    /**
     * Registers the metric.
     *
     * This is the callback for the {@see metrics_manager} hook.
     *
     * Constructs a new instance of the metric and passes it to {@see metrics_manager::add_metric}.
     * If no entry in the database table exists (yet) for the metric, it is created first, before the hook method is called.
     *
     * @param metrics_manager $hook Hook picking up the metric.
     * @return static
     * @throws coding_exception Should not happen.
     * @throws dml_exception
     * @throws JsonException Failed to (de-)serialize the {@see config} value.
     */
    public static function register(metrics_manager $hook): static {
        global $DB;
        // Either fetch the existing DB entry or create a new one for the metric with this component & name.
        $conditions = ['component' => static::get_component(), 'name' => static::get_name()];
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                // Assume we already have a DB entry and construct the metric object from it.
                $metric = static::get($conditions);
            } catch (dml_missing_record_exception) {
                // There is no entry yet; construct the metric object first and then creat the DB entry for it.
                // Set the default config as defined in the subclass.
                $conditions['config'] = (object) static::get_default_config_data();
                $metric = static::from_untyped_object($conditions)->create();
            }
            $transaction->allow_commit();
        // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            if (!empty($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($e);
            }
            throw $e;
        // @codeCoverageIgnoreEnd
        }
        // Store the metric object in our manager.
        $hook->add_metric($metric);
        return $metric;
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
     * Form definition for the metric configuration.
     *
     * If the metric requires complex custom configuration, this method should be overridden. For simple cases
     * overriding {@see add_config_form_elements} is sufficient.
     *
     * Implementations of this methid should return an appropriately defined form object that
     * inherits from {@see form\config}. Any additional fields in the form's definition **must** be compatible
     * with the default config data returned by the {@see get_default_config_data} method. This means the keys of the returned array
     * must correspond exactly to the added form field names.
     * The parent {@see form\config::definition} method **must** be called in the form's `definition` method.
     *
     * By default, this does nothing more than instantiating a {@see form\config} object with the provided constructor arguments.
     *
     * @param mixed ...$args Arguments that have to be passed to the form constructor.
     * @return form\config Instance of the config form for the metric.
     */
    public static function get_config_form(...$args): form\config {
        return new form\config(...$args);
    }

    /**
     * If the metric requires custom configuration, this method should be overridden. For more complex cases where
     * you need access to the form object itself you can override {@see get_config_form} instead.
     *
     * Any additional fields in the form's definition **must** be compatible
     * with the default config data returned by the {@see get_default_config_data} method. This means the keys of the returned array
     * must correspond exactly to the added form field names.
     *
     * By default, this does nothing. There is no need to call the parent method when you override this empty stub.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public static function add_config_form_elements(MoodleQuickForm $mform) {
        // Do nothing.
    }

    /**
     * Returns the default {@see config} for the metric.
     *
     * If the metric requires custom configuration, this method should be overridden and an associative array of configuration
     * field-value-pairs should be returned. These **must** be compatible with the metric-specific config form fields defined via
     * the {@see get_config_form} method, i.e. the keys of the returned array must correspond exactly to the added form field names.
     * By default, returns an empty array.
     *
     * @return array<string, mixed> Default config data for the metric; empty if no specific config is available.
     */
    public static function get_default_config_data(): array {
        return [];
    }

    /**
     * Saves the metric's current {@see config} to the database.
     *
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     * @throws JsonException The {@see config} object could not be serialized.
     */
    public function save_config(): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $this->update(['config', 'timemodified', 'usermodified']);
        event\metric_config_updated::for_metric($this)->trigger();
        $transaction->allow_commit();
    }

    /**
     * Enables the metric making it available for calculation and export.
     *
     * No-op if the metric is already enabled.
     *
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     * @throws JsonException Should never happen.
     */
    public function enable(): void {
        global $DB;
        if ($this->enabled) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $this->enabled = true;
        $this->update(['enabled', 'timemodified', 'usermodified']);
        event\metric_enabled::for_metric($this)->trigger();
        $transaction->allow_commit();
    }

    /**
     * Disables the metric making it unavailable for calculation and export.
     *
     * No-op if the metric is already disabled.
     *
     * @throws coding_exception Should never happen.
     * @throws dml_exception
     * @throws JsonException Should never happen.
     */
    public function disable(): void {
        global $DB;
        if (!$this->enabled) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $this->enabled = false;
        $this->update(['enabled', 'timemodified', 'usermodified']);
        event\metric_disabled::for_metric($this)->trigger();
        $transaction->allow_commit();
    }
}

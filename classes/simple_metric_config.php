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
 * Definition of the abstract {@see simple_metric_config} class.
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
use moodleform;
use MoodleQuickForm;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;
use tool_monitoring\exceptions\form_data_value_missing;
use tool_monitoring\exceptions\json_invalid;
use tool_monitoring\exceptions\json_key_missing;
use tool_monitoring\exceptions\simple_metric_config_constructor_missing;
use tool_monitoring\form\config as config_form;

/**
 * Basic implementation of the {@see metric_config} interface.
 *
 * A concrete subclass must simply define a constructor with
 * {@link https://www.php.net/manual/en/language.oop5.decon.php#language.oop5.decon.constructor.promotion promoted parameters}.
 * Assuming all of those are public and there are no additional public properties on the config object, the JSON (de-)serialization
 * simply maps those properties to keys in a JSON object.
 *
 * The definition of the Moodle form fields and their validation logic are inferred from those properties as well.
 * For translatable field labels, a string with the ID `metric:<config-class-name>:<property-name>` must exist in the component
 * defining the config class. If additionally a string with the ID `metric:<config-class-name>:<property-name>_help` exists,
 * a help button is added to the form field with the corresponding text.
 *
 * Consider the following example config class defined in a plugin called `local_example`:
 *
 * ```
 * class my_metric_config extends simple_metric_config {
 *     public function __construct(
 *         public string $foo = 'bar',
 *         public float $spam = 3.14,
 *     ) {}
 * }
 * ```
 *
 * Resulting/expected JSON: `{"foo": "bar", "spam": 3.14}`.
 *
 * Resulting/expected form data: `['foo' => 'bar', 'spam' => '3.14']`.
 *
 * The Moodle form definition would be similar to this:
 * ```
 * $mform->addElement('text', 'foo', get_string('metric:my_metric_config:foo', 'local_example'));
 * $mform->addHelpButton('foo', 'metric:my_metric_config:foo', 'local_example');
 * $mform->setType('foo', PARAM_TEXT);
 * $mform->addRule('foo', null, 'required', null, 'client');
 *
 * $mform->addElement('text', 'spam', get_string('metric:my_metric_config:spam', 'local_example'));
 * $mform->addHelpButton('spam', 'metric:my_metric_config:spam', 'local_example');
 * $mform->setType('spam', PARAM_FLOAT);
 * $mform->addRule('spam', null, 'numeric', null, 'client');
 * $mform->addRule('spam', null, 'required', null, 'client');
 * ```
 *
 * For more advanced definition/validation options, override {@see extend_form_definition} and {@see extend_form_validation}.
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
abstract class simple_metric_config implements metric_config {
    /** @var array<string, array<string, ReflectionParameter>> Cache for config parameters indexed by config class name. */
    private static array $configparameters = [];

    /**
     * Reflects the class, analyzes the constructor of the config class, and returns all parameters indexed by name.
     *
     * Caches the result after the first call.
     *
     * @return array<string, ReflectionParameter> Constructor parameters indexed by name.
     * @throws simple_metric_config_constructor_missing
     */
    protected static function get_config_parameters(): array {
        if (isset(self::$configparameters[static::class])) {
            return self::$configparameters[static::class];
        }
        $class = new ReflectionClass(static::class);
        if (is_null($constructor = $class->getConstructor())) {
            throw new simple_metric_config_constructor_missing($class->getName());
        }
        $parameters = array_column(
            array:      $constructor->getParameters(),
            column_key: null,
            index_key:  'name',
        );
        self::$configparameters[static::class] = $parameters;
        return $parameters;
    }

    /**
     * Returns the instance as is, in effect turning every public property into a key-value-pair in the resulting JSON object.
     *
     * @return $this Same instance.
     */
    #[\Override]
    public function jsonSerialize(): static {
        return $this;
    }

    /**
     * Constructs a new instance from a JSON object.
     *
     * Assumes for every property of the class, a key with the same name exists in the JSON object.
     *
     * @param string $json String of a valid JSON object (not an array or any other type).
     * @return static New instance of the config class.
     * @throws json_invalid
     * @throws json_key_missing
     * @throws simple_metric_config_constructor_missing
     */
    #[\Override]
    public static function from_json(string $json): static {
        $data = json_decode($json, associative: true);
        if (empty($data) || !is_array($data) || array_is_list($data)) {
            throw new json_invalid();
        }
        $args = [];
        foreach (array_keys(self::get_config_parameters()) as $name) {
            if (!array_key_exists($name, $data)) {
                throw new json_key_missing($name);
            }
            $args[$name] = $data[$name];
        }
        return new static(...$args);
    }

    /**
     * Constructs a new instance from the (non-empty) output of {@see moodleform::get_data}.
     *
     * Assumes for every property of the config class, a property with the same name and a compatible type exists in the form data.
     *
     * @param stdClass $formdata Form data to use for construction.
     * @return static New instance of the config class.
     * @throws form_data_value_missing
     * @throws simple_metric_config_constructor_missing
     */
    #[\Override]
    public static function with_form_data(stdClass $formdata): static {
        $args = [];
        foreach (array_keys(self::get_config_parameters()) as $name) {
            if (!property_exists($formdata, $name)) {
                throw new form_data_value_missing($name);
            }
            $args[$name] = $formdata->$name;
        }
        return new static(...$args);
    }

    /**
     * Transforms an instance into an associative array of data that can be passed to {@see moodleform::set_data}.
     *
     * Simply casts the instance as an array, turning every public property into a key-value-pair in that array.
     *
     * @return array<string, mixed> Data to set on the config form.
     */
    #[\Override]
    public function to_form_data(): array {
        return (array) $this;
    }

    /**
     * Extends the definition of the configuration form.
     *
     * Called at the end of the {@see config_form::definition} method.
     *
     * Infers field names and types to set from the property declarations of the config class.
     * Form field descriptions are taken from {@see label} attributes on those properties.
     *
     * @param config_form $configform Metric configuration form being defined.
     * @param MoodleQuickForm $mform Underlying/wrapped Moodle form instance.
     * @throws coding_exception Should never happen.
     * @throws simple_metric_config_constructor_missing
     *
     * @link https://docs.moodle.org/dev/lib/formslib.php_Form_Definition Moodle docs on form definition
     */
    #[\Override]
    public static function extend_form_definition(config_form $configform, MoodleQuickForm $mform): void {
        $component = component::get_component_from_classname(static::class);
        // Get the unqualified class name.
        $cls = static::class;
        if (($pos = strrpos($cls, '\\')) !== false) {
            $cls = substr($cls, $pos + 1);
        }
        $stringmanager = get_string_manager();
        foreach (self::get_config_parameters() as $paramname => $param) {
            $labelid = "metric:$cls:$paramname";
            $label = new lang_string($labelid, $component);
            $paramtype = $param->getType();
            if ($paramtype instanceof ReflectionNamedType) {
                match ($paramtype->getName()) {
                    'int' => self::add_numeric_input_to_form($mform, $paramname, $label, PARAM_INT),
                    'float' => self::add_numeric_input_to_form($mform, $paramname, $label, PARAM_FLOAT),
                    default => self::add_text_input_to_form($mform, $paramname, $label),
                };
            } else {
                self::add_text_input_to_form($mform, $paramname, $label);
            }
            // Optionally, add a help button if the defining component has a corresponding language string.
            if ($stringmanager->string_exists("{$labelid}_help", $component)) {
                $mform->addHelpButton($paramname, $labelid, 'tool_monitoring');
            }
            // To avoid ugly errors about possibly missing constructor arguments, we make every field non-optional.
            $mform->addRule($paramname, null, 'required', null, 'client');
        }
    }

    /**
     * Convenience method to add a text input field to a Moodle form.
     *
     * @param MoodleQuickForm $mform Moodle form instance.
     * @param string $name Field name.
     * @param lang_string|null $label Field label.
     */
    protected static function add_text_input_to_form(
        MoodleQuickForm $mform,
        string $name,
        lang_string|null $label,
    ): void {
        $mform->addElement('text', $name, $label);
        $mform->setType($name, PARAM_TEXT);
    }

    /**
     * Convenience method to add a number input field to a Moodle form.
     *
     * Adds the `numeric` client-side validation rule to the field.
     *
     * @param MoodleQuickForm $mform Moodle form instance.
     * @param string $name Field name.
     * @param lang_string|null $label Field label.
     * @param string $type Field type, presumably `PARAM_INT` or `PARAM_FLOAT`.
     */
    protected static function add_numeric_input_to_form(
        MoodleQuickForm $mform,
        string $name,
        lang_string|null $label,
        string $type,
    ): void {
        $mform->addElement('text', $name, $label);
        $mform->setType($name, $type);
        $mform->addRule($name, null, 'numeric', null, 'client');
    }

    #[\Override]
    public static function extend_form_validation(array $data, config_form $configform, MoodleQuickForm $mform): array {
        return [];
    }
}

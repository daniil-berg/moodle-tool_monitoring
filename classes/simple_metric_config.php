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
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * Helper base class for simple metric configurations; fully implements the **{@see metric_config}** interface.
 *
 * During JSON serialization, the **public** properties of an instance are turned into the JSON object (saved in the DB).
 * See the {@see self::jsonSerialize `jsonSerialize`} method for details.
 * For JSON deserialization, the JSON object is expected to provide **keys that map to the constructor parameters** of the class.
 * See the {@see self::from_json `from_json`} method for details.
 *
 * The same logic applies to the {@see self::to_form_data `to_form_data`} and {@see self::with_form_data `with_form_data`} methods.
 * The former returns the **public** properties of a config instance, the latter expects the form data to have properties that
 * **map to the constructor parameters** of the class.
 *
 * This means, a concrete subclass can be simply defined as a dataclass that has a constructor with public
 * {@link https://www.php.net/manual/en/language.oop5.decon.php#language.oop5.decon.constructor.promotion promoted parameters}.
 * For example:
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
 * The definition of the Moodle form fields and their validation logic are inferred from those properties as well.
 * For translatable field labels, a string with the ID `metric:<config-class-name>:<property-name>` must exist in the component
 * defining the config class. If additionally a string with the ID `metric:<config-class-name>:<property-name>_help` exists,
 * a help button is added to the form field with the corresponding text.
 *
 * In a `local_example` plugin, the Moodle form definition for the example config above would be similar to this:
 *
 * ```
 * $mform->addElement('text', 'foo', get_string('metric:my_metric_config:foo', 'local_example'));
 * $mform->addHelpButton('foo', 'metric:my_metric_config:foo', 'local_example');
 * $mform->setType('foo', PARAM_TEXT);
 *
 * $mform->addElement('text', 'spam', get_string('metric:my_metric_config:spam', 'local_example'));
 * $mform->addHelpButton('spam', 'metric:my_metric_config:spam', 'local_example');
 * $mform->setType('spam', PARAM_FLOAT);
 * $mform->addRule('spam', null, 'numeric', null, 'client');
 * ```
 *
 * The automatic field inference currently supports the following constructor parameter types:
 * - `bool` gives an `advcheckbox`/`PARAM_BOOL` field.
 * - `float` gives a `text`/`PARAM_FLOAT` field with client-side numeric validation.
 * - `int` gives a `text`/`PARAM_INT` field with client-side numeric validation.
 * - `string` gives a `text`/`PARAM_TEXT` field.
 *
 * Any other type annotation is treated as `string`, which results in a `text`/`PARAM_TEXT` field without any validation.
 *
 * For more advanced definition and validation options, the {@see self::extend_form_definition `extend_form_definition`} and
 * {@see self::extend_form_validation `extend_form_validation`} methods can still be overridden/extended.
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
abstract class simple_metric_config implements metric_config {
    /** @var array<string, array<string, ReflectionParameter>> Cache for constructor parameters indexed by config class name. */
    private static array $constructorparameters = [];

    /**
     * Reflects the calling class, analyzes its constructor, and returns all parameters indexed by name.
     *
     * Caches the result after the first call.
     *
     * @return array<string, ReflectionParameter> Constructor parameters indexed by name.
     * @throws simple_metric_config_constructor_missing
     */
    private static function get_constructor_parameters(): array {
        if (isset(self::$constructorparameters[static::class])) {
            return self::$constructorparameters[static::class];
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
        self::$constructorparameters[static::class] = $parameters;
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
        foreach (self::get_constructor_parameters() as $name => $param) {
            if (array_key_exists($name, $data)) {
                $args[$name] = $data[$name];
            } else if (!$param->isOptional()) {
                throw new json_key_missing($name);
            }
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
        foreach (self::get_constructor_parameters() as $name => $param) {
            if (property_exists($formdata, $name)) {
                $args[$name] = $formdata->$name;
            } else if (!$param->isOptional()) {
                throw new form_data_value_missing($name);
            }
        }
        return new static(...$args);
    }

    /**
     * Transforms an instance into an associative array of data that can be passed to {@see moodleform::set_data}.
     *
     * Turns every **public** property into a key-value-pair in that array.
     *
     * @return array<string, mixed> Data to set on the config form.
     *
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
     */
    #[\Override]
    public function to_form_data(): array {
        // This is a dirty hack to quickly get _only_ the public properties.
        $closure = fn (simple_metric_config $config): array => get_object_vars($config);
        // Since `get_object_vars` is scope-aware, just calling it directly from an instance of a concrete subclass would also
        // return any protected properties set on that instance. We simulate a different scope by temporarily binding a closure to
        // an instance of an anonymous class; that closure gets the config instance as an argument and calls `get_object_vars`.
        return $closure->call(new class {}, $this);
    }

    /**
     * Extends the definition of the configuration form.
     *
     * Called at the end of the {@see config_form::definition} method.
     *
     * Infers field names and types to set from the property declarations of the config class.
     * Language string IDs for form field labels and optional help texts are derived from the property names as well.
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
        foreach (self::get_constructor_parameters() as $paramname => $param) {
            $labelid = "metric:$cls:$paramname";
            if (PHPUNIT_TEST) {
                $labelid = "testing:$labelid";
            }
            self::add_field_to_form($mform, $param, new lang_string($labelid, $component));
            // Optionally, add a help button if the defining component has a corresponding language string.
            if ($stringmanager->string_exists("{$labelid}_help", $component)) {
                $mform->addHelpButton($paramname, $labelid, $component);
            }
        }
    }

    /**
     * Convenience method to derive a form field from a function parameter and add it to a Moodle form.
     *
     * This method is called by the default implementation of {@see self::extend_form_definition `extend_form_definition`} for every
     * constructor parameter of the config class.
     * Subclasses may override/extend this method to modify if/how a field is set up for a given parameter, for example:
     *
     * ```
     * protected static function add_field_to_form(
     *     MoodleQuickForm $mform,
     *     ReflectionParameter $param,
     *     lang_string|null $label,
     * ): void {
     *     if ($param->name === 'specialfield') {
     *         // Do something special with this field.
     *     } else {
     *         parent::add_field_to_form($mform, $param, $label);
     *     }
     * }
     * ```
     *
     * @param MoodleQuickForm $mform Moodle form instance.
     * @param ReflectionParameter $param Reflected function parameter.
     * @param lang_string|null $label Field label.
     */
    protected static function add_field_to_form(
        MoodleQuickForm $mform,
        ReflectionParameter $param,
        lang_string|null $label,
    ): void {
        $paramtype = $param->getType();
        // TODO: Handle simple type union cases such as `int|null` for example.
        if ($paramtype instanceof ReflectionNamedType) {
            match ($paramtype->getName()) {
                'bool' => self::add_advanced_checkbox_to_form($mform, $param->name, $label),
                'float' => self::add_numeric_input_to_form($mform, $param->name, $label, PARAM_FLOAT),
                'int' => self::add_numeric_input_to_form($mform, $param->name, $label, PARAM_INT),
                default => self::add_text_input_to_form($mform, $param->name, $label),
            };
        } else {
            self::add_text_input_to_form($mform, $param->name, $label);
        }
    }

    /**
     * Convenience method to add an advanced checkbox to a Moodle form.
     *
     * @param MoodleQuickForm $mform Moodle form instance.
     * @param string $paramname Field name.
     * @param lang_string|null $label Field label.
     */
    protected static function add_advanced_checkbox_to_form(
        MoodleQuickForm $mform,
        string $paramname,
        lang_string|null $label,
    ): void {
        $mform->addElement('advcheckbox', $paramname, $label);
        $mform->setType($paramname, PARAM_BOOL);
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

    #[\Override]
    public static function extend_form_validation(array $data, config_form $configform, MoodleQuickForm $mform): array {
        return [];
    }
}

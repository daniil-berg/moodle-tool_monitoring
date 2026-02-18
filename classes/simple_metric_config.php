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

use core\attribute\label;
use core\exception\coding_exception;
use moodleform;
use MoodleQuickForm;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;

/**
 * Basic implementation of the {@see metric_config} interface.
 *
 * A concrete subclass must simply define a constructor with
 * {@link https://www.php.net/manual/en/language.oop5.decon.php#language.oop5.decon.constructor.promotion promoted parameters}.
 * Assuming all of those are public and there are no additional public properties on the config object, the JSON (de-)serialization
 * simply maps those properties to keys in a JSON object.
 * The extension of the Moodle form and its data handling is inferred from those properties as well.
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
abstract class simple_metric_config implements metric_config
{
    /** @var array<string, array<string, ReflectionParameter>> Cache for config parameters indexed by config class name. */
    private static array $configparameters = [];

    /**
     * Reflects the class, analyzes the constructor of the config class, and returns all parameters indexed by name.
     *
     * Caches the result after the first call.
     *
     * @return array<string, ReflectionParameter> Constructor parameters indexed by name.
     * @throws coding_exception
     */
    protected static function get_config_parameters(): array {
        if (isset(self::$configparameters[static::class])) {
            return self::$configparameters[static::class];
        }
        $class = new ReflectionClass(static::class);
        if (is_null($constructor = $class->getConstructor())) {
            // TODO: Use custom exception class.
            throw new coding_exception(get_string('error:no_constructor', 'tool_monitoring', $class->getName()));
        }
        if ($constructor->isPrivate()) {
            // TODO: Use custom exception class.
            throw new coding_exception(get_string('error:constructor_private', 'tool_monitoring', $class->getName()));
        }
        $parameters = array_column(
            array: $constructor->getParameters(),
            column_key: null,
            index_key: 'name',
        );
        self::$configparameters[static::class] = $parameters;
        return $parameters;
    }

    /**
     * Returns the instance as is, in effect turning every public property into a key-value-pair in the resulting JSON object.
     *
     * @return $this Same instance.
     */
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
     * @throws coding_exception JSON is not valid or not an object or missing config parameters.
     */
    public static function from_json(string $json): static {
        $data = json_decode($json, associative: true);
        if (empty($data) || !is_array($data) || array_is_list($data)) {
            throw new coding_exception(get_string('error:json_decode', 'tool_monitoring'));
        }
        $args = [];
        foreach (array_keys(self::get_config_parameters()) as $name) {
            if (!array_key_exists($name, $data)) {
                throw new coding_exception(get_string('error:missing_value_json', 'tool_monitoring', $name));
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
     * @throws coding_exception
     */
    public static function with_form_data(stdClass $formdata): static {
        $args = [];
        foreach (array_keys(self::get_config_parameters()) as $name) {
            if (!property_exists($formdata, $name)) {
                throw new coding_exception(get_string('error:missing_value_form_data', 'tool_monitoring', $name));
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
    public function to_form_data(): array {
        return (array) $this;
    }

    /**
     * Extends/modifies a {@see MoodleQuickForm} for the config.
     *
     * Infers field names and types to set from the property declarations of the config class.
     * Form field descriptions are taken from {@see label} attributes on those properties.
     *
     * TODO Parameter to form field inference is extremely rudimentary and just a proof of concept.4
     *
     * @param MoodleQuickForm $mform Configuration form.
     * @throws coding_exception
     */
    public static function extend_config_form(MoodleQuickForm $mform): void {
        foreach (self::get_config_parameters() as $name => $param) {
            $paramtype = $param->getType();
            if ($paramtype instanceof ReflectionNamedType) {
                $type = match ($paramtype->getName()) {
                    'int' => PARAM_INT,
                    'float' => PARAM_FLOAT,
                    default => PARAM_ALPHAEXT,
                };
            } else {
                $type = PARAM_ALPHAEXT;
            }
            /** @var label|null $labelattr */
            $labelattr = null;
            foreach ($param->getAttributes() as $attribute) {
                if ($attribute->getName() === label::class) {
                    $labelattr = $attribute->newInstance();
                    break;
                }
            }
            $mform->addElement('text', $name, $labelattr?->label);
            $mform->setType($name, $type);
        }
    }
}

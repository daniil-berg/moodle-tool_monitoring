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
 * Definition of the {@see tool_monitoring_exception} class.
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

namespace tool_monitoring\exceptions;

use core\component;
use core\exception\moodle_exception;

/**
 * Base exception class for the plugin.
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
abstract class tool_monitoring_exception extends moodle_exception {
    /** @var string String to prefix all error codes with. */
    public const ERROR_CODE_PREFIX = 'error:';

    /**
     * Calls the {@see moodle_exception} constructor with sensible default arguments.
     *
     * @param string|null $errorcode Key for the corresponding language string; if `null` (default), the unqualified class name
     *                               prefixed by {@see self::ERROR_CODE_PREFIX `ERROR_CODE_PREFIX`} is used.
     * @param string|null $module Name of the module; if `null` (default), the name of the component defining the class is used.
     * @param string $link The url where the user will be prompted to continue. If no url is provided, the user will be directed to
     *                     the site index page.
     * @param mixed $a Extra words and phrases that might be required in the error string.
     * @param string|null $debuginfo Optional debugging information.
     */
    public function __construct(
        string|null $errorcode = null,
        string|null $module = null,
        string $link = '',
        mixed $a = null,
        string|null $debuginfo = null,
    ) {
        if (is_null($errorcode)) {
            $classname = static::class;
            if (($pos = strrpos($classname, '\\')) !== false) {
                $classname = substr($classname, $pos + 1);
            }
            $errorcode = self::ERROR_CODE_PREFIX . $classname;
        }
        if (is_null($module)) {
            $module = component::get_component_from_classname(static::class) ?? 'tool_monitoring';
        }
        parent::__construct(
            errorcode: $errorcode,
            module: $module,
            link: $link,
            a: $a ?? [],
            debuginfo: $debuginfo,
        );
    }
}

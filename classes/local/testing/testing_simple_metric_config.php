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
 * Definition of the {@see testing_simple_metric_config} class.
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

namespace tool_monitoring\local\testing;

use stdClass;
use tool_monitoring\simple_metric_config;

/**
 * Extension of the {@see simple_metric_config} class for testing purposes.
 *
 * **TESTING ONLY: This exists purely to run unit tests.**
 * Some properties and constructor parameters do not make sense in a real-world context. They are used to test certain methods and
 * the goal of this class is to cover all code paths.
 *
 * @codeCoverageIgnore
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
class testing_simple_metric_config extends simple_metric_config {
    /** @var string Not promoted from the constructor. */
    public string $notpromotedstring;

    /**
     * @var string Something protected.
     * {@noinspection PhpUnused}
     */
    protected string $somethingprotected = 'protected';

    /**
     * @var string Something private.
     * {@noinspection PhpUnusedPrivateFieldInspection}
     */
    private string $somethingprivate = 'private';

    /**
     * Constructor with some variants for testing.
     *
     * @param string $publicstringrequired Required string; promoted to public property.
     * @param stdClass $publicobj Object with a default; promoted to public property.
     * @param int $protectedint Integer with a default; promoted to protected property.
     * @param float $privatereadonlyfloat Float with a default; promoted to private property.
     * @param bool $publicbool Boolean with a default; promoted to public property.
     * @param array|string|null $publicunion Union of types with a default; promoted to public property.
     * @param string $notpromotedstring String with a default; not promoted to any property.
     *
     * {@noinspection PhpPropertyOnlyWrittenInspection}
     */
    public function __construct(
        /** @var string Public string. */
        public string $publicstringrequired,
        /** @var stdClass Public object. */
        public stdClass $publicobj = new stdClass(),
        /** @var int Protected integer. */
        protected int $protectedint = 42,
        /** @var float Private float. */
        private readonly float $privatereadonlyfloat = 3.14,
        /** @var bool Public boolean. */
        public bool $publicbool = true,
        /** @var array|string|null Public union. */
        public array|string|null $publicunion = null,
        string $notpromotedstring = 'bar',
    ) {
        $this->notpromotedstring = $notpromotedstring;
    }
}

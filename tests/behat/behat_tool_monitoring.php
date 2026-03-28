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
 * Definition of the {@see behat_tool_monitoring} class.
 *
 * @package    tool_monitoring
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

use Behat\Step\Given;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat steps definitions.
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
class behat_tool_monitoring extends behat_base {
    /**
     * Enables/disables the `metrics` tag area for `tool_monitoring`.
     *
     * @param string $state "enabled" or "disabled".
     * @throws coding_exception
     * @throws dml_exception
     *
     * {@noinspection PhpUnused}
     */
    #[Given('/^the metrics tag area is "(?P<state>enabled|disabled)"$/')]
    public function the_tag_area_is(string $state): void {
        global $DB;
        $enabled = match ($state) {
            'enabled' => 1,
            'disabled' => 0,
            // This should not happen due to the RegEx group above, but this is a sanity check.
            default => throw new coding_exception("Invalid state: '$state'."),
        };
        $area = $DB->get_record(
            table: 'tag_area',
            conditions: ['itemtype' => 'metrics', 'component' => 'tool_monitoring'],
            strictness: MUST_EXIST,
        );
        core_tag_area::update($area, ['enabled' => $enabled]);
    }
}

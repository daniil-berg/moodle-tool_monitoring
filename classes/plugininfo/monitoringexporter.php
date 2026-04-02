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
 * Definition of the {@see monitoringexporter} class.
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

namespace tool_monitoring\plugininfo;

use admin_root;
use admin_settingpage;
use coding_exception;
use core\plugininfo\base;
use part_of_admin_tree;
use stdClass;

/**
 * Sub-plugin info class.
 *
 * @link https://docs.moodle.org/dev/Subplugins
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
class monitoringexporter extends base {
    #[\Override]
    public function get_settings_section_name(): string {
        return "{$this->type}_$this->name";
    }

    /**
     * Loads plugin settings to the settings tree.
     *
     * Sub-plugins can immediately add settings to their dedicated {@see admin_settingpage} via the `$settings` variable in their
     * own `settings.php` file. The file will be automatically included if the user has the `moodle/site:config` capability.
     *
     * @param part_of_admin_tree $adminroot Admin tree root.
     * @param string $parentnodename Name of the parent node in the tree.
     * @param bool $hassiteconfig Whether the current user has the `moodle/site:config` capability.
     * @throws coding_exception
     */
    #[\Override]
    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig): void {
        global $ADMIN;

        if (!$this->is_installed_and_upgraded()) {
            return;
        }
        if (!$hassiteconfig || !file_exists($this->full_path('settings.php'))) {
            return;
        }
        $settings = new admin_settingpage(
            name: $this->get_settings_section_name(),
            visiblename: $this->displayname,
            req_capability: 'moodle/site:config',
            hidden: $this->is_enabled() === false, // Can be `null`, therefore the identity comparison.
        );
        include($this->full_path('settings.php'));
        if ($settings->settings != new stdClass()) {
            // Only if settings were actually added to the page, do we want to add it to the tree.
            /** @var admin_root $ADMIN */
            $ADMIN->add($parentnodename, $settings);
        }
    }
}

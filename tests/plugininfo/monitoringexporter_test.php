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
 * Definition of the {@see monitoringexporter_test} class.
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

namespace tool_monitoring\plugininfo;

use admin_category;
use admin_root;
use admin_settingpage;
use advanced_testcase;
use core\exception\coding_exception;
use core_plugin_manager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the {@see monitoringexporter} class.
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
#[CoversClass(monitoringexporter::class)]
final class monitoringexporter_test extends advanced_testcase {
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once("$CFG->libdir/adminlib.php");
    }

    public function test_get_settings_section_name(): void {
        $plugininfo = new monitoringexporter();
        $plugininfo->type = 'monitoringexporter';
        $plugininfo->name = 'foo';
        self::assertSame('monitoringexporter_foo', $plugininfo->get_settings_section_name());
    }

    /**
     * Tests the {@see monitoringexporter::load_settings} method.
     *
     * @throws coding_exception
     */
    #[DataProvider('provider_test_load_settings')]
    public function test_load_settings(bool $hassiteconfig, string $rootdir, bool $installedandupgraded = true): void {
        // Set up a mock plugin info instance.
        $plugininfo = new monitoringexporter();
        $plugininfo->versiondisk = 3.14;
        if ($installedandupgraded) {
            $plugininfo->versiondb = 3.14;
        }
        $plugininfo->pluginman = core_plugin_manager::instance();
        $plugininfo->type = 'monitoringexporter';
        $plugininfo->name = 'foo';
        $plugininfo->displayname = 'Foo Exporter';
        $plugininfo->rootdir = $rootdir;
        // Create a mock admin root with a parent category for the mock plugin.
        $adminroot = new admin_root(fulltree: false);
        $parentnodename = 'tool_monitoring_exporters';
        $adminroot->add('root', new admin_category($parentnodename, 'Parent'));
        // Do the thing.
        $plugininfo->load_settings(
            adminroot: $adminroot,
            parentnodename: $parentnodename,
            hassiteconfig: $hassiteconfig,
        );
        $settingspage = $adminroot->locate($plugininfo->get_settings_section_name());
        if ($installedandupgraded && $hassiteconfig && file_exists($plugininfo->full_path('settings.php'))) {
            // Ensure the section was added and has the expected properties.
            self::assertInstanceOf(admin_settingpage::class, $settingspage);
            self::assertSame($plugininfo->get_settings_section_name(), $settingspage->name);
            self::assertSame($plugininfo->displayname, $settingspage->visiblename);
            self::assertSame(['moodle/site:config'], $settingspage->req_capability);
            self::assertFalse($settingspage->hidden);
        } else {
            // Check that nothing was added.
            self::assertNull($settingspage);
        }
    }

    /**
     * Provides test data for the {@see test_load_settings} method.
     *
     * @return array[] Arguments for the test method.
     */
    public static function provider_test_load_settings(): array {
        return [
            'Custom plugin settings available with config permission' => [
                'hassiteconfig' => true,
                'rootdir' => __DIR__ . '/../../exporter/prometheus', // Just a directory that has a `settings.php`.
            ],
            'No permission' => [
                'hassiteconfig' => false,
                'rootdir' => __DIR__ . '/../../exporter/prometheus', // Just a directory that has a `settings.php`.
            ],
            'No `settings.php` found' => [
                'hassiteconfig' => true,
                'rootdir' => 'foo/bar/baz',
            ],
            'Plugin not installed/upgraded' => [
                'hassiteconfig' => true,
                'rootdir' => __DIR__ . '/../../exporter/prometheus', // Just a directory that has a `settings.php`.
                'installedandupgraded' => false,
            ],
        ];
    }

    public function test_load_settings_not_installed(): void {

    }
}

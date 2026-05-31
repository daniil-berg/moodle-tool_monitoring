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
 * Definition of the {@see metric_tag} interface.
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

use moodle_url;

/**
 * Interface of a tag associated with a registered metric.
 *
 * **This interface is a consumer-only contract.**
 * Plugins and sub-plugins should use it for annotation but are discouraged from implementing it.
 * Implementations risk source-breaking changes whenever methods or properties are added to the interface.
 *
 * TODO Readonly-property PHPDoc annotations will be replaced by property `get`-hooks, once PHP 8.4 becomes the minimum requirement.
 *
 * @property-read int $id Tag ID.
 * @property-read string $rawname Tag name as set by the user.
 * @property-read string $name Normalized tag name.
 * @property-read int|null $taginstanceid Tag instance ID (link between tag and metric).
 * @property-read moodle_url $editurl URL to the tag editing page.
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
 * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace
 */
interface metric_tag {}

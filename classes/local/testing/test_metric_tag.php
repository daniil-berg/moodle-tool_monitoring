<?php
// This file is part of the tool_monitoring plugin for Moodle - https://moodle.org/
//
// tool_monitoring is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// tool_monitoring is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with tool_monitoring.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_monitoring\local\testing;

use core\exception\moodle_exception;
use moodle_url;
use tool_monitoring\metric_tag;

/**
 * Implementation of {@see metric_tag} for testing purposes.
 *
 * **TESTING ONLY: This exists purely to run unit tests.**
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
final readonly class test_metric_tag implements metric_tag {
    /** @var string Tag name as set by the user. */
    public string $rawname;

    /** @var moodle_url URL to the tag editing page. */
    public moodle_url $editurl;

    /**
     * Convenience constructor.
     *
     * Sets the {@see self::$rawname} property equal to {@see self::$name} by default.
     * Sets {@see self::$editurl} using the given ID.
     *
     * @param string $name Normalized tag name.
     * @param string|null $rawname Tag name as set by the user; passing `null` sets {@see self::$rawname} to {@see self::$name}.
     * @param int $id Tag ID.
     * @param int|null $taginstanceid Tag instance ID (link between tag and metric).
     * @throws moodle_exception
     */
    public function __construct(
        /** @var string Normalized tag name. */
        public string $name,
        string|null $rawname = null,
        /** @var int Tag ID. */
        public int $id = 0,
        /** @var int|null Tag instance ID (link between tag and metric). */
        public int|null $taginstanceid = null,
    ) {
        $this->rawname = $rawname ?? $name;
        $this->editurl = new moodle_url('/tag/edit.php', ['id' => $this->id]);
    }
}

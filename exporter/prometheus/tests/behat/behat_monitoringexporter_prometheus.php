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
 * Definition of the {@see behat_monitoringexporter_prometheus} class.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * {@noinspection PhpIllegalPsrClassPathInspection}
 */

use Behat\Gherkin\Node\TableNode;
use Behat\Step\When;

require_once(__DIR__ . '/../../../../../../../lib/behat/behat_base.php');

/**
 * Behat steps definitions.
 *
 * @package    monitoringexporter_prometheus
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_monitoringexporter_prometheus extends behat_base {
    /** @var string URL path to the Prometheus endpoint. */
    private const URL_PATH = '/r.php/monitoringexporter_prometheus/metrics';

    /**
     * Visits the Prometheus endpoint, optionally passing query parameters.
     *
     * @param TableNode|null $table Optional table of query parameters (name in the first column, value in the second).
     *
     * {@noinspection PhpUnused}
     */
    #[When('I call the Prometheus endpoint')]
    #[When('I call the Prometheus endpoint with the following query parameters:')]
    public function i_call_the_prometheus_endpoint(TableNode|null $table = null): void {
        $path = self::URL_PATH;
        if (!is_null($table)) {
            $path .= '?' . http_build_query($table->getRowsHash());
        }
        $this->getSession()->visit($this->locatePath($path));
    }
}

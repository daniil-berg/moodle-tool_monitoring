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
 * Toggle metric enabled state from the metrics overview.
 *
 * @module     tool_monitoring/metric_toggle
 * @copyright  2025 MootDACH DevCamp
 *             Daniel Fainberg <d.fainberg@tu-berlin.de>
 *             Martin Gauk <martin.gauk@tu-berlin.de>
 *             Sebastian Rupp <sr@artcodix.com>
 *             Malte Schmitz <mal.schmitz@uni-luebeck.de>
 *             Melanie Treitinger <melanie.treitinger@ruhr-uni-bochum.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Persists enabled state for one metric via Moodle web service.
 *
 * @param {string} qualifiedName
 * @param {boolean} enabled
 * @returns {Promise<void>}
 */
const setMetricEnabled = (qualifiedName, enabled) => {
    const [request] = Ajax.call([{
        methodname: 'tool_monitoring_set_metric_enabled',
        args: {
            metric: qualifiedName,
            enabled,
        },
    }]);
    return request;
};

/**
 * Applies the enabled state to row and icon.
 *
 * @param {HTMLElement} link
 * @param {boolean} enabled
 */
const applyState = (link, enabled) => {
    const row = link.closest('tr');
    const enabledIcon = link.querySelector('[data-region="enabled-icon"]');
    const disabledIcon = link.querySelector('[data-region="disabled-icon"]');

    row?.classList.toggle('dimmed_text', !enabled);
    enabledIcon?.classList.toggle('d-none', !enabled);
    disabledIcon?.classList.toggle('d-none', enabled);

    link.dataset.enabled = enabled ? '1' : '0';
};

/**
 * Calls the web service and updates UI for one metric toggle.
 *
 * @param {HTMLElement} link
 * @returns {Promise<void>}
 */
const toggleMetric = async(link) => {
    if (link.classList.contains('disabled')) {
        return;
    }
    const qualifiedName = link.dataset.metric;
    const nextEnabled = link.dataset.enabled === '0';
    link.classList.add('disabled');
    link.setAttribute('aria-disabled', 'true');
    try {
        await setMetricEnabled(qualifiedName, nextEnabled);
        applyState(link, nextEnabled);
    } catch (error) {
        Notification.exception(error);
    } finally {
        link.classList.remove('disabled');
        link.removeAttribute('aria-disabled');
    }
};

/**
 * Initializes click handling for the metrics overview page.
 */
export const init = () => {
    document.querySelectorAll('[data-action="toggle-metric"]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            toggleMetric(link);
        });
    });
};

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
 * Authorised progress updates without overlapping requests.
 * @module local_clonecategory/job_progress
 * @copyright 2026 Saddam Al-Slfi
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {confirm} from 'core/notification';
/**
 * Update authorised jobs and confirm destructive form actions.
 * @param {string} url Progress endpoint
 * @param {string} sesskey Session token
 * @param {string} failureMessage Localised retry message
 * @param {Object} labels Confirmation labels
 */
export const init = (url, sesskey, failureMessage, labels) => {
    document.querySelectorAll('[data-clone-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            confirm(labels.title, form.dataset.cloneConfirm, labels.yes, labels.cancel, () => form.submit());
        });
    });
    const cards = Array.from(document.querySelectorAll('[data-jobid]'));
    if (!cards.length) {
        return;
    }
    const active = new Set(['pending', 'running', 'rolling_back']);
    let stopped = false;
    let errors = 0;
    let timer;
    const poll = async() => {
        if (stopped) {
            return;
        }
        if (document.hidden) {
            timer = window.setTimeout(poll, 5000);
            return;
        }
        const controller = new AbortController();
        const deadline = window.setTimeout(() => controller.abort(), 15000);
        let next = 5000;
        try {
            const response = await fetch(url, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({sesskey}),
            });
            if (!response.ok) {
                throw new Error('Progress request failed');
            }
            const result = await response.json();
            if (!Array.isArray(result.jobs)) {
                throw new Error('Invalid progress response');
            }
            errors = 0;
            const warning = document.getElementById('clone-live-error');
            if (warning) {
                warning.hidden = true;
            }
            let running = false;
            result.jobs.forEach(job => {
                const card = cards.find(item => Number(item.dataset.jobid) === job.id);
                if (!card) {
                    return;
                }
                if (card.dataset.status !== job.status) {
                    stopped = true;
                    window.location.reload();
                    return;
                }
                running = running || active.has(job.status);
                const set = (name, value) => {
                    const node = card.querySelector('[data-field="' + name + '"]');
                    if (node) {
                        node.textContent = value;
                    }
                    return node;
                };
                set('status', job.statuslabel);
                set('currentstep', job.currentstep);
                set('categories', job.categoriescount + ' / ' + job.totalcategories);
                set('courses', job.coursescount + ' / ' + job.totalcourses);
                const progress = Math.max(0, Math.min(100, Number(job.progress)));
                const bar = set('progress', progress + '%');
                if (bar) {
                    bar.style.width = progress + '%';
                    bar.setAttribute('aria-valuenow', String(progress));
                }
            });
            if (!running) {
                stopped = true;
            }
        } catch (error) {
            errors++;
            next = Math.min(60000, 5000 * Math.pow(2, errors));
            const warning = document.getElementById('clone-live-error');
            if (warning) {
                warning.textContent = failureMessage;
                warning.hidden = false;
            }
        } finally {
            window.clearTimeout(deadline);
            if (!stopped) {
                timer = window.setTimeout(poll, next);
            }
        }
    };
    if (cards.some(card => active.has(card.dataset.status))) {
        timer = window.setTimeout(poll, 1000);
    }
    window.addEventListener('pagehide', () => {
        stopped = true;
        window.clearTimeout(timer);
    });
};

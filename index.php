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
 * Main management index page for local_clonecategory.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_clonecategory\manager;

require_login();
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$tab = optional_param('tab', 'clone', PARAM_ALPHA);
if (!in_array($tab, ['clone', 'tasks'], true)) {
    $tab = 'clone';
}
$canmanage = has_capability('local/clonecategory:managejobs', context_system::instance());
$sources = manager::get_category_options(true);
if (!$canmanage && !$sources) {
    require_capability('local/clonecategory:clone', context_system::instance());
}
if ($categoryid) {
    require_capability('local/clonecategory:clone', context_coursecat::instance($categoryid));
}
$context = $categoryid ? context_coursecat::instance($categoryid) : context_system::instance();
$url = new moodle_url('/local/clonecategory/index.php', ['tab' => $tab, 'categoryid' => $categoryid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->add_body_class('local-clonecategory-page');
$PAGE->set_title(get_string('clonecategory', 'local_clonecategory'));
$PAGE->set_heading(get_string('clone_page_title', 'local_clonecategory'));

// State-changing actions are POST only. A sesskey in a link is never enough.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }
    require_sesskey();
    $jobid = required_param('jobid', PARAM_INT);
    try {
        switch ($action) {
            case 'pause':
                manager::pause_job($jobid);
                break;
            case 'resume':
                manager::resume_job($jobid);
                break;
            case 'cancel':
                manager::cancel_job($jobid);
                break;
            case 'rollback':
                manager::rollback_job($jobid);
                break;
            case 'delete':
                manager::delete_job($jobid);
                break;
            default:
                throw new moodle_exception('invalidrequest');
        }
        redirect(
            $url,
            get_string('actioncomplete', 'local_clonecategory'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        redirect($url, s($e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$mform = new \local_clonecategory\form\clone_form($url, ['categoryid' => $categoryid]);
if ($tab === 'clone' && $mform->is_cancelled()) {
    redirect(new moodle_url('/course/management.php'));
} else if ($tab === 'clone' && ($data = $mform->get_data())) {
    try {
        manager::create_job(
            (int)$data->sourcecategory,
            (int)$data->targetcategory,
            $data->categorysuffix ?? '',
            $data->coursesuffix ?? '',
            (int)$USER->id,
            $data->clonemode
        );
        redirect(
            new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'categoryid' => $categoryid]),
            get_string('cloningsuccess', 'local_clonecategory'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        redirect($url, s($e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->requires->js_call_amd('local_clonecategory/job_progress', 'init', [
    (new moodle_url('/local/clonecategory/progress.php'))->out(false), sesskey(),
    get_string('liveupdatesfailed', 'local_clonecategory'),
        ['title' => get_string('confirm'), 'yes' => get_string('yes'), 'cancel' => get_string('cancel')],
]);
echo $OUTPUT->header();
echo html_writer::start_div('local-clonecategory-shell');
echo html_writer::div(html_writer::tag('p', get_string('pageintro', 'local_clonecategory')), 'local-clonecategory-hero');
print_tabs([[
    new tabobject(
        'clone',
        new moodle_url('/local/clonecategory/index.php', ['tab' => 'clone', 'categoryid' => $categoryid]),
        get_string('clonecategory', 'local_clonecategory')
    ),
    new tabobject(
        'tasks',
        new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'categoryid' => $categoryid]),
        get_string('scheduled_tasks', 'local_clonecategory')
    ),
]], $tab);

if ($tab === 'clone') {
    $mform->display();
} else {
    echo $OUTPUT->notification(get_string('cronrequired', 'local_clonecategory'), 'info');
    echo html_writer::div('', 'alert alert-warning', ['id' => 'clone-live-error', 'hidden' => 'hidden', 'role' => 'status']);
    $jobs = manager::get_visible_jobs();
    $latestjobid = (int)$DB->get_field_sql('SELECT MAX(id) FROM {local_clonecategory_jobs}');
    if (!$jobs) {
        echo html_writer::tag('p', get_string('no_jobs_found', 'local_clonecategory'));
    }
    foreach ($jobs as $job) {
        $details = html_writer::tag('h3', get_string('active_job_title', 'local_clonecategory', $job->id));
        $details .= html_writer::tag('p', get_string('mode_' . $job->clonemode, 'local_clonecategory'));
        $details .= html_writer::tag(
            'span',
            get_string('status_' . $job->status, 'local_clonecategory'),
            ['class' => 'badge bg-secondary badge-secondary', 'data-field' => 'status']
        );
        $details .= html_writer::div(html_writer::div($job->progress . '%', 'progress-bar', [
            'style' => 'width:' . (int)$job->progress . '%', 'role' => 'progressbar',
            'aria-label' => get_string('progress', 'local_clonecategory'),
            'aria-valuenow' => (int)$job->progress, 'aria-valuemin' => 0, 'aria-valuemax' => 100,
            'data-field' => 'progress',
        ]), 'progress my-3');
        $details .= html_writer::tag('p', s($job->currentstep ?? ''), ['data-field' => 'currentstep', 'class' => 'clone-job-step']);
        $details .= html_writer::tag('p', get_string('categories_copied', 'local_clonecategory') . ': ' .
            html_writer::tag('span', $job->categoriescount . ' / ' . $job->totalcategories, ['data-field' => 'categories']) .
            ' · ' . get_string('courses_copied', 'local_clonecategory') . ': ' .
            html_writer::tag('span', $job->coursescount . ' / ' . $job->totalcourses, ['data-field' => 'courses']));
        $buttons = [];
        if (in_array($job->status, [manager::STATUS_PENDING, manager::STATUS_RUNNING], true)) {
            $buttons['pause'] = 'btn_pause';
        }
        if (in_array($job->status, [manager::STATUS_PAUSED, manager::STATUS_FAILED], true)) {
            $buttons['resume'] = 'btn_resume';
        }
        if (in_array($job->status, [manager::STATUS_PENDING, manager::STATUS_RUNNING, manager::STATUS_PAUSED], true)) {
            $buttons['cancel'] = 'btn_cancel';
        }
        if (manager::can_rollback_job($job, $latestjobid)) {
            $buttons['rollback'] = 'btn_rollback';
        }
        if (!in_array($job->status, manager::active_states(), true)) {
            $buttons['delete'] = 'btn_delete';
        }
        $actions = '';
        foreach ($buttons as $name => $label) {
            $fields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'jobid', 'value' => $job->id]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $name]);
            $fields .= html_writer::tag(
                'button',
                get_string($label, 'local_clonecategory'),
                ['type' => 'submit', 'class' => 'btn ' . ($name === 'rollback' ? 'btn-outline-danger' : 'btn-outline-primary')]
            );
            $attributes = ['method' => 'post', 'action' => $url->out(false)];
            if (in_array($name, ['rollback', 'delete', 'cancel'], true)) {
                $attributes['data-clone-confirm'] = get_string('confirm_' . $name, 'local_clonecategory');
            }
            $actions .= html_writer::tag('form', $fields, $attributes);
        }
        $details .= html_writer::div($actions, 'clone-job-actions');
        echo html_writer::div($details, 'card mb-3 clone-job', [
            'data-jobid' => (int)$job->id, 'data-status' => $job->status,
        ]);
    }
}
echo html_writer::end_div();
echo $OUTPUT->footer();

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
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Suppress PHP 8.4 PEAR static call deprecation error during script shutdown.
$GLOBALS['_PEAR_destructor_object_list'] = [];
register_shutdown_function(function() {
    $GLOBALS['_PEAR_destructor_object_list'] = [];
});

require_once($CFG->libdir . '/adminlib.php');

use local_clonecategory\manager;

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$tab = optional_param('tab', 'clone', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$jobid = optional_param('jobid', 0, PARAM_INT);

$url = new moodle_url('/local/clonecategory/index.php', ['tab' => $tab]);
if ($categoryid) {
    $url->param('categoryid', $categoryid);
}

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('clonecategory', 'local_clonecategory'));
$PAGE->set_heading(get_string('clonecategory', 'local_clonecategory'));

require_login();
require_capability('moodle/category:manage', context_system::instance());

// Actions handling.
if ($action === 'pause' && $jobid && confirm_sesskey()) {
    manager::pause_job($jobid);
    redirect($url, get_string('job_paused_success', 'local_clonecategory'), null, \core\output\notification::NOTIFY_INFO);
} else if ($action === 'resume' && $jobid && confirm_sesskey()) {
    manager::resume_job($jobid);
    redirect($url, get_string('job_resumed_success', 'local_clonecategory'), null, \core\output\notification::NOTIFY_SUCCESS);
} else if ($action === 'rollback' && $jobid && confirm_sesskey()) {
    manager::rollback_job($jobid);
    redirect($url, get_string('job_rolled_back_success', 'local_clonecategory'), null, \core\output\notification::NOTIFY_SUCCESS);
} else if ($action === 'delete_job' && $jobid && confirm_sesskey()) {
    manager::delete_job($jobid);
    redirect($url, get_string('job_deleted_success', 'local_clonecategory'), null, \core\output\notification::NOTIFY_INFO);
} else if ($action === 'run_tasks' && confirm_sesskey()) {
    // Run tasks inline safely.
    \core_php_time_limit::raise();
    \core\session\manager::write_close();

    $olderrorlevel = error_reporting();
    error_reporting($olderrorlevel & ~E_DEPRECATED & ~E_STRICT);

    ob_start();
    echo get_string('task_starting', 'local_clonecategory');

    $tasks_run = 0;
    try {
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $taskclass = get_class($task);
            if ($taskclass === 'local_clonecategory\\task\\clone_category_task' || $taskclass === '\\local_clonecategory\\task\\clone_category_task') {
                $a = (object)['class' => $taskclass, 'id' => $task->get_id()];
                echo get_string('task_executing', 'local_clonecategory', $a);
                try {
                    $task->execute();
                    \core\task\manager::adhoc_task_complete($task);
                    echo get_string('task_completed_success', 'local_clonecategory');
                } catch (\Throwable $e) {
                    \core\task\manager::adhoc_task_failed($task);
                    echo get_string('task_failed', 'local_clonecategory', $e->getMessage());
                }
                $tasks_run++;
            } else {
                echo get_string('task_other_plugin', 'local_clonecategory', $taskclass);
                break;
            }
        }
    } finally {
        error_reporting($olderrorlevel);
    }

    if ($tasks_run === 0) {
        echo get_string('no_pending_tasks', 'local_clonecategory');
    }

    $fulloutput = ob_get_clean();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('force_run_tasks', 'local_clonecategory'));

    if (trim($fulloutput) === '') {
        $fulloutput = get_string('no_output_returned', 'local_clonecategory');
    }

    echo html_writer::tag('div', get_string('terminal_output', 'local_clonecategory'), ['class' => 'font-weight-bold mb-2']);
    echo html_writer::tag('pre', s($fulloutput), [
        'style' => 'background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: Consolas, monospace; direction: ltr; text-align: left;'
    ]);

    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks']), get_string('back_to_tasks', 'local_clonecategory'), ['class' => 'btn btn-primary']),
        ['class' => 'mt-4']
    );

    echo $OUTPUT->footer();
    die();
}

$mform = new \local_clonecategory\form\clone_form($url, ['categoryid' => $categoryid]);

// Clear PEAR destructors array and suppress deprecations during PHP shutdown (fixes PHP 8.4 PEAR static call issue).
register_shutdown_function(function() {
    $GLOBALS['_PEAR_destructor_object_list'] = [];
    @error_reporting(0);
});

if ($tab === 'clone' && $mform->is_cancelled()) {
    redirect(new moodle_url('/course/management.php'));
} else if ($tab === 'clone' && $data = $mform->get_data()) {
    try {
        manager::create_job(
            $data->sourcecategory,
            $data->targetcategory,
            $data->categorysuffix ?? '',
            $data->coursesuffix ?? '',
            $USER->id
        );
        redirect(new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks']), get_string('cloningsuccess', 'local_clonecategory'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Throwable $e) {
        redirect(new moodle_url('/local/clonecategory/index.php', ['tab' => 'clone']), $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('clone_page_title', 'local_clonecategory'));

// Render Tabs
$tabs = [
    new \tabobject('clone', new moodle_url('/local/clonecategory/index.php', ['tab' => 'clone']), get_string('clonecategory', 'local_clonecategory')),
    new \tabobject('tasks', new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks']), get_string('scheduled_tasks', 'local_clonecategory'))
];
print_tabs([$tabs], $tab);

if ($tab === 'clone') {
    $mform->display();
} else if ($tab === 'tasks') {
    global $DB;

    // Show Active Jobs
    $activejob = manager::get_active_job();
    if ($activejob) {
        echo html_writer::start_div('card mb-4 border-primary');
        echo html_writer::start_div('card-header bg-primary text-white font-weight-bold d-flex justify-content-between align-items-center');
        echo html_writer::span(get_string('active_job_title', 'local_clonecategory', $activejob->id));
        $statusbadge = match ($activejob->status) {
            manager::STATUS_RUNNING => html_writer::span(get_string('status_running', 'local_clonecategory'), 'badge badge-success bg-success'),
            manager::STATUS_PAUSED  => html_writer::span(get_string('status_paused', 'local_clonecategory'), 'badge badge-warning bg-warning text-dark'),
            default                 => html_writer::span(get_string('status_pending', 'local_clonecategory'), 'badge badge-info bg-info')
        };
        echo $statusbadge;
        echo html_writer::end_div();

        echo html_writer::start_div('card-body');

        // Progress Bar
        echo html_writer::tag('label', get_string('progress', 'local_clonecategory') . ": {$activejob->progress}%", ['class' => 'font-weight-bold']);
        echo html_writer::start_div('progress mb-3', ['style' => 'height: 25px;']);
        $pbarclass = ($activejob->status === manager::STATUS_PAUSED) ? 'bg-warning text-dark' : 'bg-primary progress-bar-striped progress-bar-animated';
        echo html_writer::div("{$activejob->progress}%", "progress-bar {$pbarclass}", [
            'role' => 'progressbar',
            'style' => "width: {$activejob->progress}%; font-weight: bold; line-height: 25px;",
            'aria-valuenow' => $activejob->progress,
            'aria-valuemin' => 0,
            'aria-valuemax' => 100
        ]);
        echo html_writer::end_div();

        // Statistics Grid
        echo html_writer::start_div('row mb-3');
        echo html_writer::div(
            html_writer::tag('strong', get_string('categories_copied', 'local_clonecategory') . ': ') . "{$activejob->categoriescount} / {$activejob->totalcategories}",
            'col-md-6'
        );
        echo html_writer::div(
            html_writer::tag('strong', get_string('courses_copied', 'local_clonecategory') . ': ') . "{$activejob->coursescount} / {$activejob->totalcourses}",
            'col-md-6'
        );
        echo html_writer::end_div();

        if (!empty($activejob->currentstep)) {
            echo html_writer::div(
                html_writer::tag('strong', get_string('current_step', 'local_clonecategory') . ': ') . s($activejob->currentstep),
                'alert alert-secondary py-2 mb-3'
            );
        }

        // Action Buttons for Active Job
        echo html_writer::start_div('d-flex gap-2');
        if ($activejob->status === manager::STATUS_RUNNING || $activejob->status === manager::STATUS_PENDING) {
            $pauseurl = new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'action' => 'pause', 'jobid' => $activejob->id, 'sesskey' => sesskey()]);
            echo html_writer::link($pauseurl, get_string('btn_pause', 'local_clonecategory'), ['class' => 'btn btn-warning mr-2']);
        } else if ($activejob->status === manager::STATUS_PAUSED) {
            $resumeurl = new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'action' => 'resume', 'jobid' => $activejob->id, 'sesskey' => sesskey()]);
            echo html_writer::link($resumeurl, get_string('btn_resume', 'local_clonecategory'), ['class' => 'btn btn-success mr-2']);
        }

        // Rollback button
        $rollbackurl = new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'action' => 'rollback', 'jobid' => $activejob->id, 'sesskey' => sesskey()]);
        echo html_writer::link(
            $rollbackurl,
            get_string('btn_rollback', 'local_clonecategory'),
            [
                'class' => 'btn btn-danger',
                'onclick' => "return confirm('" . addslashes_js(get_string('rollback_confirm', 'local_clonecategory')) . "');"
            ]
        );
        echo html_writer::end_div();

        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // Task Execution Toolbar
    echo html_writer::start_div('mb-3 d-flex justify-content-between align-items-center');
    $runurl = new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'action' => 'run_tasks', 'sesskey' => sesskey()]);
    echo html_writer::link($runurl, get_string('force_run_tasks', 'local_clonecategory'), ['class' => 'btn btn-outline-primary']);
    echo html_writer::end_div();

    // All Jobs History Table
    echo html_writer::tag('h3', get_string('all_jobs_history', 'local_clonecategory'));

    $jobs = $DB->get_records('local_clonecategory_jobs', null, 'id DESC', '*', 0, 50);
    if (empty($jobs)) {
        echo html_writer::tag('p', get_string('no_jobs_found', 'local_clonecategory'));
    } else {
        $table = new \html_table();
        $table->head = [
            get_string('id', 'local_clonecategory'),
            get_string('user', 'local_clonecategory'),
            get_string('status', 'local_clonecategory'),
            get_string('stats_categories', 'local_clonecategory'),
            get_string('stats_courses', 'local_clonecategory'),
            get_string('progress', 'local_clonecategory'),
            get_string('time_started', 'local_clonecategory'),
            get_string('actions', 'local_clonecategory'),
        ];

        foreach ($jobs as $j) {
            $user = $DB->get_record('user', ['id' => $j->userid], 'id, firstname, lastname');
            $username = $user ? fullname($user) : "User #{$j->userid}";

            $statusbadge = match ($j->status) {
                manager::STATUS_COMPLETED => html_writer::span(get_string('status_completed', 'local_clonecategory'), 'badge badge-success bg-success'),
                manager::STATUS_FAILED    => html_writer::span(get_string('status_failed', 'local_clonecategory'), 'badge badge-danger bg-danger'),
                manager::STATUS_PAUSED    => html_writer::span(get_string('status_paused', 'local_clonecategory'), 'badge badge-warning bg-warning text-dark'),
                manager::STATUS_RUNNING   => html_writer::span(get_string('status_running', 'local_clonecategory'), 'badge badge-primary bg-primary'),
                manager::STATUS_ROLLED_BACK => html_writer::span(get_string('status_rolled_back', 'local_clonecategory'), 'badge badge-secondary bg-secondary'),
                default                   => html_writer::span(get_string('status_pending', 'local_clonecategory'), 'badge badge-info bg-info'),
            };

            $actions = [];

            if ($j->status === manager::STATUS_PAUSED) {
                $resumeurl = new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'action' => 'resume', 'jobid' => $j->id, 'sesskey' => sesskey()]);
                $actions[] = html_writer::link($resumeurl, get_string('btn_resume', 'local_clonecategory'), ['class' => 'btn btn-sm btn-success mr-1']);
            }

            if (in_array($j->status, [manager::STATUS_COMPLETED, manager::STATUS_FAILED, manager::STATUS_PAUSED, manager::STATUS_RUNNING])) {
                $rollbackurl = new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'action' => 'rollback', 'jobid' => $j->id, 'sesskey' => sesskey()]);
                $actions[] = html_writer::link(
                    $rollbackurl,
                    get_string('btn_rollback', 'local_clonecategory'),
                    [
                        'class' => 'btn btn-sm btn-danger mr-1',
                        'onclick' => "return confirm('" . addslashes_js(get_string('rollback_confirm', 'local_clonecategory')) . "');"
                    ]
                );
            }

            $deleteurl = new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'action' => 'delete_job', 'jobid' => $j->id, 'sesskey' => sesskey()]);
            $actions[] = html_writer::link($deleteurl, get_string('btn_delete', 'local_clonecategory'), ['class' => 'btn btn-sm btn-outline-danger']);

            $table->data[] = [
                $j->id,
                $username,
                $statusbadge,
                "{$j->categoriescount} / {$j->totalcategories}",
                "{$j->coursescount} / {$j->totalcourses}",
                "{$j->progress}%",
                userdate($j->timecreated),
                implode(' ', $actions)
            ];
        }

        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();

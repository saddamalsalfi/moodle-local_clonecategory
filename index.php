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
require_once($CFG->libdir . '/adminlib.php');

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$tab = optional_param('tab', 'clone', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
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

if ($action === 'run_tasks' && confirm_sesskey()) {
    $cli = $CFG->dirroot . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'adhoc_task.php';
    if (!file_exists($cli)) {
        $cli = dirname($CFG->dirroot) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'adhoc_task.php';
    }

    // Run synchronously and wait for it to finish.
    // Run the tasks inline using Moodle's native API (Safe, Cross-platform, and no exec() required)
    \core_php_time_limit::raise();
    \core\session\manager::write_close();

    $output_lines = [];

    // Capture output
    ob_start();
    echo get_string('task_starting', 'local_clonecategory');

    $tasks_run = 0;
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
            // This task is not ours, put it back in the queue
            // We can't elegantly unlock it in older Moodles, so we just let the lock expire
            // Or we just break to avoid running other plugins' tasks.
            echo get_string('task_other_plugin', 'local_clonecategory', $taskclass);
            break;
        }
    }

    if ($tasks_run === 0) {
        echo get_string('no_pending_tasks', 'local_clonecategory');
    }

    $fulloutput = ob_get_clean();

    // Display the execution output directly to the user
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

if ($tab === 'clone' && $mform->is_cancelled()) {
    redirect(new moodle_url('/course/management.php'));
} else if ($tab === 'clone' && $data = $mform->get_data()) {
    $task = new \local_clonecategory\task\clone_category_task();
    $task->set_custom_data([
        'source_category_id' => $data->sourcecategory,
        'target_parent_id' => $data->targetcategory,
        'category_suffix' => $data->categorysuffix ?? '',
        'course_suffix' => $data->coursesuffix ?? '',
        'userid' => $USER->id
    ]);
    \core_task\manager::queue_adhoc_task($task);

    redirect(new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks']), get_string('cloningsuccess', 'local_clonecategory'), null, \core\output\notification::NOTIFY_SUCCESS);
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
    $queued = $DB->get_records('task_adhoc', ['classname' => '\\local_clonecategory\\task\\clone_category_task'], 'nextruntime ASC');

    echo html_writer::start_div('mb-3');
    $runurl = new moodle_url('/local/clonecategory/index.php', ['tab' => 'tasks', 'action' => 'run_tasks', 'sesskey' => sesskey()]);
    echo html_writer::link($runurl, get_string('force_run_tasks', 'local_clonecategory'), ['class' => 'btn btn-primary']);
    echo html_writer::end_div();

    echo html_writer::tag('h3', get_string('queued_tasks', 'local_clonecategory'));
    if (empty($queued)) {
        echo html_writer::tag('p', get_string('no_queued_tasks', 'local_clonecategory'));
    } else {
        $table = new \html_table();
        $table->head = [get_string('id', 'local_clonecategory'), get_string('nextruntime', 'tool_task'), get_string('faildelay', 'tool_task')];
        foreach ($queued as $q) {
            $table->data[] = [
                $q->id,
                userdate($q->nextruntime),
                $q->faildelay
            ];
        }
        echo html_writer::table($table);
    }

    // Completed Tasks
    $completed = $DB->get_records('task_log', ['classname' => '\\local_clonecategory\\task\\clone_category_task'], 'timestart DESC', '*', 0, 20);

    echo html_writer::tag('h3', get_string('completed_tasks', 'local_clonecategory'), ['class' => 'mt-4']);
    if (empty($completed)) {
        echo html_writer::tag('p', get_string('no_completed_tasks', 'local_clonecategory'));
    } else {
        $table = new \html_table();
        $table->head = [get_string('id', 'local_clonecategory'), get_string('time_started', 'local_clonecategory'), get_string('time_completed', 'local_clonecategory'), get_string('status', 'local_clonecategory')];
        foreach ($completed as $c) {
            $status = ($c->result == 0) ? html_writer::span(get_string('success', 'moodle'), 'badge badge-success bg-success') : html_writer::span(get_string('error', 'moodle'), 'badge badge-danger bg-danger');
            $table->data[] = [
                $c->id,
                userdate($c->timestart),
                userdate($c->timeend),
                $status
            ];
        }
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();

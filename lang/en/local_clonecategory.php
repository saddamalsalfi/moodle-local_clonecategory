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
 * English language strings for local_clonecategory.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Clone Category';
$string['clonecategory'] = 'Clone Category';
$string['sourcecategory'] = 'Source Category';
$string['targetcategory'] = 'Target Category (Parent)';
$string['targetcategory_help'] = 'Select the category where the cloned category will be placed. Choose "Top" to place it at the root level.';
$string['clone_button'] = 'Start Cloning';
$string['cloningsuccess'] = 'Category cloning process has been scheduled in the background. It will be completed soon.';
$string['privacy:metadata'] = 'The Clone Category plugin does not store any personal data.';
$string['task:clone_category_task'] = 'Clone category task';
$string['clone_page_title'] = 'Clone Categories and Courses';
$string['categorysuffix'] = 'Category Name Suffix';
$string['categorysuffix_help'] = 'Text to append to the cloned category name. Leave empty to copy with the exact same name.';
$string['coursesuffix'] = 'Course Name Suffix';
$string['coursesuffix_help'] = 'Text to append to the cloned course name. Leave empty to copy with the exact same name.';
$string['tasks_started'] = 'Background task execution command launched successfully.';
$string['scheduled_tasks'] = 'Scheduled Tasks';
$string['force_run_tasks'] = 'Force run tasks immediately';
$string['queued_tasks'] = 'Queued clone tasks';
$string['no_queued_tasks'] = 'No clone tasks currently queued.';
$string['completed_tasks'] = 'Previous clone tasks';
$string['no_completed_tasks'] = 'No previous clone tasks found.';
$string['status'] = 'Status';
$string['time_started'] = 'Time Started';
$string['time_completed'] = 'Time Completed';
$string['id'] = 'ID';
$string['default_suffix'] = ' - copy';
$string['task_starting'] = "Starting background task execution...\n";
$string['task_executing'] = "Executing adhoc task: {\$a->class} (ID: {\$a->id})...\n";
$string['task_completed_success'] = "-> Task completed successfully.\n\n";
$string['task_failed'] = "-> Task failed: {\$a}\n";
$string['task_other_plugin'] = "Found a task belonging to another plugin ({\$a}). Stopping execution to avoid interference.\n";
$string['no_pending_tasks'] = "No pending clone tasks were found.\n";
$string['no_output_returned'] = 'No output returned.';
$string['terminal_output'] = 'Terminal Output (Native Execution)';
$string['back_to_tasks'] = 'Back to Scheduled Tasks';

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
$string['privacy:metadata'] = 'The Clone Category plugin stores cloning job logs including user ID for auditing purposes.';
$string['privacy:metadata:local_clonecategory_jobs'] = 'Stores details and status of category cloning operations.';
$string['privacy:metadata:local_clonecategory_jobs:userid'] = 'The ID of the user who performed the cloning operation.';
$string['privacy:metadata:local_clonecategory_jobs:sourcecategoryid'] = 'The ID of the source category cloned.';
$string['privacy:metadata:local_clonecategory_jobs:targetparentid'] = 'The ID of the parent category target.';
$string['privacy:metadata:local_clonecategory_jobs:timecreated'] = 'The timestamp when the job was created.';

$string['task:clone_category_task'] = 'Clone category task';
$string['clone_page_title'] = 'Clone Categories and Courses';
$string['categorysuffix'] = 'Category Name Suffix';
$string['categorysuffix_help'] = 'Text to append to the cloned category name. Leave empty to copy with the exact same name.';
$string['coursesuffix'] = 'Course Name Suffix';
$string['coursesuffix_help'] = 'Text to append to the cloned course name. Leave empty to copy with the exact same name.';
$string['tasks_started'] = 'Background task execution command launched successfully.';
$string['scheduled_tasks'] = 'Scheduled Tasks & Operations';
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

$string['active_job_exists'] = 'A cloning operation is currently active. Please wait or pause/rollback it before starting a new one.';
$string['active_job_warning'] = 'Notice: There is currently an active or paused cloning process. Starting new cloning is disabled until the active job is completed or cancelled.';
$string['active_job_title'] = 'Active Cloning Operation #{$a}';
$string['progress'] = 'Progress';
$string['categories_copied'] = 'Categories Cloned';
$string['courses_copied'] = 'Courses Cloned';
$string['current_step'] = 'Current Step';
$string['btn_pause'] = 'Pause';
$string['btn_resume'] = 'Resume';
$string['btn_rollback'] = 'Rollback & Undo';
$string['btn_delete'] = 'Delete Log';
$string['rollback_confirm'] = 'Are you sure you want to completely rollback this cloning operation? All created categories and courses will be permanently deleted.';
$string['job_queued'] = 'Job queued in background';
$string['job_running'] = 'Processing category and course cloning...';
$string['job_paused_by_user'] = 'Paused by user';
$string['job_resumed'] = 'Resuming cloning process...';
$string['job_rolling_back'] = 'Rolling back created categories and courses...';
$string['job_rolled_back_success'] = 'Cloning operation was completely rolled back.';
$string['job_completed_success'] = 'Cloning completed successfully.';
$string['job_failed_error'] = 'Cloning failed: {$a}';
$string['job_cloned_item'] = 'Cloned {$a->type}: {$a->name}';
$string['status_running'] = 'Running';
$string['status_paused'] = 'Paused';
$string['status_pending'] = 'Pending';
$string['status_completed'] = 'Completed';
$string['status_failed'] = 'Failed';
$string['status_rolled_back'] = 'Rolled Back';
$string['all_jobs_history'] = 'Cloning Jobs History & Analytics';
$string['no_jobs_found'] = 'No cloning jobs logged yet.';
$string['user'] = 'User';
$string['stats_categories'] = 'Categories';
$string['stats_courses'] = 'Courses';
$string['actions'] = 'Actions';
$string['job_paused_success'] = 'Cloning job paused successfully.';
$string['job_resumed_success'] = 'Cloning job resumed.';
$string['job_deleted_success'] = 'Job record deleted.';
$string['error_lock_failed'] = 'Could not obtain lock for operation. Please try again.';
$string['error_rollback_not_latest'] = 'Sorry, rollback is only allowed for the most recent cloning job.';
$string['error_rollback_expired'] = 'Sorry, the 24-hour window for rolling back this job has expired.';

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
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actioncomplete'] = 'The action completed successfully.';
$string['actions'] = 'Actions';
$string['active_job_exists'] = 'A cloning operation is currently active. Please wait or pause/rollback it before starting a new one.';
$string['active_job_title'] = 'Active Cloning Operation #{$a}';
$string['active_job_warning'] = 'Notice: There is currently an active or paused cloning process. Starting new cloning is disabled until the active job is completed or cancelled.';
$string['all_jobs_history'] = 'Cloning Jobs History & Analytics';
$string['back_to_tasks'] = 'Back to Scheduled Tasks';
$string['btn_cancel'] = 'Cancel job';
$string['btn_delete'] = 'Delete Log';
$string['btn_pause'] = 'Pause';
$string['btn_resume'] = 'Resume';
$string['btn_rollback'] = 'Rollback & Undo';
$string['cannotdeleteactive'] = 'Stop the job and wait for its worker before deleting its audit record.';
$string['categories_copied'] = 'Categories Cloned';
$string['categorysuffix'] = 'Category Name Suffix';
$string['categorysuffix_help'] = 'Text to append to the cloned category name. Leave empty to copy with the exact same name.';
$string['choosecategory'] = 'Choose a category';
$string['clone_button'] = 'Start Cloning';
$string['clone_page_title'] = 'Clone Categories and Courses';
$string['clonecategory'] = 'Clone Category';
$string['clonecategory:clone'] = 'Clone category structures';
$string['clonecategory:managejobs'] = 'Manage all category cloning jobs';
$string['clonemode'] = 'Clone scope';
$string['clonemode_help'] = 'Categories only copies the category hierarchy without courses. Settings creates empty courses with names, general course settings and format options, but without activities, files, summaries or learner data. Full copies course content using Moodle backup and restore, without learner data. Third-party format settings may need separate verification.';
$string['cloningsuccess'] = 'Category cloning process has been scheduled in the background. It will be completed soon.';
$string['collapseall'] = 'Collapse all';
$string['completed_tasks'] = 'Previous clone tasks';
$string['confirm_cancel'] = 'Cancel this job? The current course may finish. Created items will be retained.';
$string['confirm_delete'] = 'Delete the audit record? Copied resources remain and rollback tracking is lost.';
$string['confirm_rollback'] = 'Delete the unchanged items created by this job? This cannot be undone.';
$string['courses_copied'] = 'Courses Cloned';
$string['coursesuffix'] = 'Course Name Suffix';
$string['coursesuffix_help'] = 'Text to append to the cloned course name. Leave empty to copy with the exact same name.';
$string['cronrequired'] = 'Cloning runs through Moodle cron. Pause and cancel take effect between safe work units. Retry queues a failed job; it does not run a long restore inside your browser.';
$string['current_step'] = 'Current Step';
$string['default_suffix'] = ' - copy';
$string['error_lock_failed'] = 'This job is still busy. Wait for the current operation to stop, then try again.';
$string['error_rollback_expired'] = 'Sorry, the 24-hour window for rolling back this job has expired.';
$string['error_rollback_not_latest'] = 'Sorry, rollback is only allowed for the most recent cloning job.';
$string['expandall'] = 'Expand all';
$string['force_run_tasks'] = 'Force run tasks immediately';
$string['id'] = 'ID';
$string['incompletemodified'] = 'An incomplete course has changed since the failed restore. Review it before retrying.';
$string['invalidclonemode'] = 'Choose a valid clone scope.';
$string['invaliddestination'] = 'Choose a valid destination outside the source category and its descendants.';
$string['invalidjobstate'] = 'This action is not available in the current job state.';
$string['job_cancelled'] = 'Cancelled. The current safe work unit may finish; created items remain until rollback.';
$string['job_cloned_item'] = 'Cloned {$a->type}: {$a->name}';
$string['job_completed_success'] = 'Cloning completed successfully.';
$string['job_deleted_success'] = 'Job record deleted.';
$string['job_failed_error'] = 'Cloning failed: {$a}';
$string['job_failed_safe'] = 'The job failed. Review the Moodle task log, then retry or rollback.';
$string['job_paused_by_user'] = 'Paused by user';
$string['job_paused_success'] = 'Cloning job paused successfully.';
$string['job_queued'] = 'Job queued in background';
$string['job_resumed'] = 'Resuming cloning process...';
$string['job_resumed_success'] = 'Cloning job resumed.';
$string['job_rolled_back_success'] = 'Cloning operation was completely rolled back.';
$string['job_rolling_back'] = 'Rolling back created categories and courses...';
$string['job_running'] = 'Processing category and course cloning...';
$string['liveupdatesfailed'] = 'Live updates are temporarily unavailable. Refresh the page or wait for reconnection.';
$string['locationheading'] = '2. Choose source and destination';
$string['mode_categories'] = 'Categories only';
$string['mode_full'] = 'Categories, courses and content';
$string['mode_settings'] = 'Categories and empty courses with settings';
$string['modenote'] = 'All modes exclude student enrolments, grades and submissions.';
$string['namingheading'] = '3. Name the new copies';
$string['no_completed_tasks'] = 'No previous clone tasks found.';
$string['no_jobs_found'] = 'No cloning jobs logged yet.';
$string['no_output_returned'] = 'No output returned.';
$string['no_pending_tasks'] = "No pending clone tasks were found.\n";
$string['no_queued_tasks'] = 'No clone tasks currently queued.';
$string['nosearchresults'] = 'No matching categories';
$string['pageintro'] = 'Prepare a new academic structure. Choose the scope, search for categories, and schedule your copy.';
$string['pluginname'] = 'Clone Category';
$string['privacy:metadata'] = 'The Clone Category plugin stores cloning job logs including user ID for auditing purposes.';
$string['privacy:metadata:items'] = 'Tracks resources created by a user-requested cloning operation.';
$string['privacy:metadata:items:fingerprint'] = 'Created-resource audit field: fingerprint.';
$string['privacy:metadata:items:itemid'] = 'Created-resource audit field: itemid.';
$string['privacy:metadata:items:itemtype'] = 'Created-resource audit field: itemtype.';
$string['privacy:metadata:items:jobid'] = 'Created-resource audit field: jobid.';
$string['privacy:metadata:items:sourceid'] = 'Created-resource audit field: sourceid.';
$string['privacy:metadata:items:status'] = 'Created-resource audit field: status.';
$string['privacy:metadata:items:timecreated'] = 'Created-resource audit field: timecreated.';
$string['privacy:metadata:jobs:categoriescount'] = 'Cloning audit field: categoriescount.';
$string['privacy:metadata:jobs:categorysuffix'] = 'Cloning audit field: categorysuffix.';
$string['privacy:metadata:jobs:clonemode'] = 'Cloning audit field: clonemode.';
$string['privacy:metadata:jobs:coursescount'] = 'Cloning audit field: coursescount.';
$string['privacy:metadata:jobs:coursesuffix'] = 'Cloning audit field: coursesuffix.';
$string['privacy:metadata:jobs:currentstep'] = 'Cloning audit field: currentstep.';
$string['privacy:metadata:jobs:progress'] = 'Cloning audit field: progress.';
$string['privacy:metadata:jobs:sourcecategoryid'] = 'Cloning audit field: sourcecategoryid.';
$string['privacy:metadata:jobs:status'] = 'Cloning audit field: status.';
$string['privacy:metadata:jobs:targetparentid'] = 'Cloning audit field: targetparentid.';
$string['privacy:metadata:jobs:timecreated'] = 'Cloning audit field: timecreated.';
$string['privacy:metadata:jobs:timefinished'] = 'Cloning audit field: timefinished.';
$string['privacy:metadata:jobs:timemodified'] = 'Cloning audit field: timemodified.';
$string['privacy:metadata:jobs:totalcategories'] = 'Cloning audit field: totalcategories.';
$string['privacy:metadata:jobs:totalcourses'] = 'Cloning audit field: totalcourses.';
$string['privacy:metadata:jobs:userid'] = 'Cloning audit field: userid.';
$string['privacy:metadata:local_clonecategory_jobs'] = 'Stores details and status of category cloning operations.';
$string['privacy:metadata:local_clonecategory_jobs:sourcecategoryid'] = 'The ID of the source category cloned.';
$string['privacy:metadata:local_clonecategory_jobs:targetparentid'] = 'The ID of the parent category target.';
$string['privacy:metadata:local_clonecategory_jobs:timecreated'] = 'The timestamp when the job was created.';
$string['privacy:metadata:local_clonecategory_jobs:userid'] = 'The ID of the user who performed the cloning operation.';
$string['progress'] = 'Progress';
$string['queued_tasks'] = 'Queued clone tasks';
$string['restoreprecheckfailed'] = 'Moodle restore prechecks reported errors. See the protected task log for details.';
$string['rollback_confirm'] = 'Are you sure you want to completely rollback this cloning operation? All created categories and courses will be permanently deleted.';
$string['rollbackfailed'] = 'Rollback could not finish. Tracking has been retained for review and retry.';
$string['rollbackmodified'] = 'Rollback blocked: a copied item has changed, contains additional content, or has no original snapshot. No untracked content will be deleted.';
$string['rollbackunavailable'] = 'Rollback is available only for the latest stopped job within 24 hours. Pause the worker and wait for it to stop first.';
$string['scheduled_tasks'] = 'Scheduled Tasks & Operations';
$string['scopeheading'] = '1. Choose what to copy';
$string['searchcategories'] = 'Search category names…';
$string['selectedcategory'] = 'Selected category';
$string['singlesourcehint'] = 'Choose one source only. Selecting another replaces the previous selection.';
$string['sourcecategory'] = 'Source Category';
$string['stats_categories'] = 'Categories';
$string['stats_courses'] = 'Courses';
$string['status'] = 'Status';
$string['status_cancelled'] = 'Cancelled';
$string['status_completed'] = 'Completed';
$string['status_failed'] = 'Failed';
$string['status_paused'] = 'Paused';
$string['status_pending'] = 'Pending';
$string['status_rollback_failed'] = 'Rollback incomplete';
$string['status_rolled_back'] = 'Rolled Back';
$string['status_rolling_back'] = 'Rolling back';
$string['status_running'] = 'Running';
$string['suffixlength'] = 'The suffix must not exceed 255 characters.';
$string['targetcategory'] = 'Target Category (Parent)';
$string['targetcategory_help'] = 'Select the category where the cloned category will be placed. Choose "Top" to place it at the root level.';
$string['task:clone_category_task'] = 'Clone category task';
$string['task_completed_success'] = "-> Task completed successfully.\n\n";
$string['task_executing'] = "Executing adhoc task: {\$a->class} (ID: {\$a->id})...\n";
$string['task_failed'] = "-> Task failed: {\$a}\n";
$string['task_other_plugin'] = "Found a task belonging to another plugin ({\$a}). Stopping execution to avoid interference.\n";
$string['task_starting'] = "Starting background task execution...\n";
$string['tasks_started'] = 'Background task execution command launched successfully.';
$string['terminal_output'] = 'Terminal Output (Native Execution)';
$string['time_completed'] = 'Time Completed';
$string['time_started'] = 'Time Started';
$string['trackeditemmissing'] = 'A tracked copied resource is missing. Review the operation before retrying.';
$string['user'] = 'User';

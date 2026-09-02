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
 * Adhoc task for cloning category and courses.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory\task;

use local_clonecategory\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Class clone_category_task
 *
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clone_category_task extends \core\task\adhoc_task {

    /**
     * Run category cloning task.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        $data = $this->get_custom_data();
        $jobid = $data->jobid ?? 0;

        if (!$jobid) {
            mtrace("No job ID provided for clone category task.");
            return;
        }

        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
        if (!$job) {
            mtrace("Job #{$jobid} not found.");
            return;
        }

        if ($job->status === manager::STATUS_PAUSED || $job->status === manager::STATUS_ROLLED_BACK) {
            mtrace("Job #{$jobid} status is '{$job->status}'. Aborting execution.");
            return;
        }

        // Set status to running.
        $job->status = manager::STATUS_RUNNING;
        $job->currentstep = get_string('job_running', 'local_clonecategory');
        $job->timemodified = time();
        $DB->update_record('local_clonecategory_jobs', $job);

        mtrace("Starting clone category task for Job #{$jobid} (Source ID: {$job->sourcecategoryid}, Target Parent: {$job->targetparentid})...");

        try {
            $this->clone_category($job, $job->sourcecategoryid, $job->targetparentid, true);

            // Re-fetch job to check final status.
            $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
            if ($job && $job->status === manager::STATUS_RUNNING) {
                $job->status = manager::STATUS_COMPLETED;
                $job->progress = 100;
                $job->currentstep = get_string('job_completed_success', 'local_clonecategory');
                $job->timemodified = time();
                $DB->update_record('local_clonecategory_jobs', $job);
                mtrace("Job #{$jobid} completed successfully.");
            }

        } catch (\Throwable $e) {
            $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
            if ($job && $job->status !== manager::STATUS_PAUSED) {
                $job->status = manager::STATUS_FAILED;
                $job->currentstep = get_string('job_failed_error', 'local_clonecategory', $e->getMessage());
                $job->timemodified = time();
                $DB->update_record('local_clonecategory_jobs', $job);
            }
            mtrace("Error executing Job #{$jobid}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Recursively clone category.
     *
     * @param \stdClass $job
     * @param int $sourcecatid
     * @param int $targetparentid
     * @param bool $isroot
     */
    private function clone_category(\stdClass $job, int $sourcecatid, int $targetparentid, bool $isroot = true) {
        global $DB;

        // Check if job was paused by user before doing heavy work.
        if ($this->is_job_paused($job->id)) {
            mtrace("Job #{$job->id} paused by user. Interrupting category clone.");
            return;
        }

        $sourcecat = \core_course_category::get($sourcecatid);

        // Check if category was already cloned in previous attempt (for pause/resume).
        $existingitem = $DB->get_record('local_clonecategory_items', [
            'jobid' => $job->id,
            'itemtype' => 'category',
            'sourceid' => $sourcecatid,
        ]);

        if ($existingitem && $DB->record_exists('course_categories', ['id' => $existingitem->itemid])) {
            $newcatid = $existingitem->itemid;
            mtrace("Skipping already cloned category: {$sourcecat->name} (New Cat ID: {$newcatid})");
        } else {
            mtrace("Cloning category: {$sourcecat->name}");

            $catdata = new \stdClass();
            $catdata->name = $sourcecat->name;
            if ($isroot && !empty($job->categorysuffix)) {
                $catdata->name .= $job->categorysuffix;
            }
            $catdata->parent = $targetparentid;
            $catdata->description = $sourcecat->description;
            $catdata->descriptionformat = $sourcecat->descriptionformat;
            $catdata->idnumber = ''; // Prevent idnumber collisions

            $newcat = \core_course_category::create($catdata);
            $newcatid = $newcat->id;

            // Log item in db.
            $item = new \stdClass();
            $item->jobid       = $job->id;
            $item->itemtype    = 'category';
            $item->itemid      = $newcatid;
            $item->sourceid    = $sourcecatid;
            $item->status      = 'completed';
            $item->timecreated = time();
            $DB->insert_record('local_clonecategory_items', $item);

            // Update job counts & progress.
            $this->increment_progress($job->id, 'category', $sourcecat->name);
        }

        // Clone courses in this category.
        $courses = $sourcecat->get_courses(['limit' => 0]);
        foreach ($courses as $course) {
            if ($this->is_job_paused($job->id)) {
                mtrace("Job #{$job->id} paused by user. Interrupting course clones.");
                return;
            }
            $this->clone_course($job, $course, $newcatid);
        }

        // Recursively clone subcategories.
        $subcats = $sourcecat->get_children();
        foreach ($subcats as $subcat) {
            if ($this->is_job_paused($job->id)) {
                mtrace("Job #{$job->id} paused by user. Interrupting subcategory clones.");
                return;
            }
            $this->clone_category($job, $subcat->id, $newcatid, false);
        }
    }

    /**
     * Clone single course.
     *
     * @param \stdClass $job
     * @param object $course
     * @param int $targetcategoryid
     */
    private function clone_course(\stdClass $job, $course, int $targetcategoryid) {
        global $CFG, $DB;

        // Check if course was already cloned.
        $existingitem = $DB->get_record('local_clonecategory_items', [
            'jobid' => $job->id,
            'itemtype' => 'course',
            'sourceid' => $course->id,
        ]);

        if ($existingitem && $DB->record_exists('course', ['id' => $existingitem->itemid])) {
            mtrace("  Skipping already cloned course: {$course->fullname}");
            return;
        }

        mtrace("  Cloning course: {$course->fullname}");

        // Raise execution limits.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        // Backup course.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_IMPORT, $job->userid);

        $plan = $bc->get_plan();
        if ($plan->setting_exists('users') && $plan->get_setting('users')->get_value()) {
            $plan->get_setting('users')->set_value(0);
        }
        if ($plan->setting_exists('role_assignments') && $plan->get_setting('role_assignments')->get_value()) {
            $plan->get_setting('role_assignments')->set_value(0);
        }
        if ($plan->setting_exists('enrolments') && $plan->get_setting('enrolments')->get_value()) {
            $plan->get_setting('enrolments')->set_value(0);
        }
        if ($plan->setting_exists('logs') && $plan->get_setting('logs')->get_value()) {
            $plan->get_setting('logs')->set_value(0);
        }

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore course.
        $coursesuffix = $job->coursesuffix ?? '';
        $newfullname = $course->fullname . $coursesuffix;
        $newshortname = $course->shortname . '_' . time();
        $newcourseid = \restore_dbops::create_new_course($newfullname, $newshortname, $targetcategoryid);

        $rc = new \restore_controller($backupid, $newcourseid,
            \backup::INTERACTIVE_NO, \backup::MODE_SAMESITE, $job->userid,
            \backup::TARGET_NEW_COURSE);

        $rcplan = $rc->get_plan();
        if ($rcplan->setting_exists('users') && $rcplan->get_setting('users')->get_value()) {
            $rcplan->get_setting('users')->set_value(0);
        }
        if ($rcplan->setting_exists('role_assignments') && $rcplan->get_setting('role_assignments')->get_value()) {
            $rcplan->get_setting('role_assignments')->set_value(0);
        }
        if ($rcplan->setting_exists('enrolments') && $rcplan->get_setting('enrolments')->get_value()) {
            $rcplan->get_setting('enrolments')->set_value(0);
        }
        if ($rcplan->setting_exists('logs') && $rcplan->get_setting('logs')->get_value()) {
            $rcplan->get_setting('logs')->set_value(0);
        }

        if ($rcplan->setting_exists('course_shortname')) {
            $rcplan->get_setting('course_shortname')->set_value($newshortname);
        }
        if ($rcplan->setting_exists('course_fullname')) {
            $rcplan->get_setting('course_fullname')->set_value($newfullname);
        }

        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Delete backup temp files.
        $tempdir = $CFG->tempdir . '/backup/' . $backupid;
        if (is_dir($tempdir)) {
            fulldelete($tempdir);
        }

        // Record item.
        $item = new \stdClass();
        $item->jobid       = $job->id;
        $item->itemtype    = 'course';
        $item->itemid      = $newcourseid;
        $item->sourceid    = $course->id;
        $item->status      = 'completed';
        $item->timecreated = time();
        $DB->insert_record('local_clonecategory_items', $item);

        $this->increment_progress($job->id, 'course', $course->fullname);
    }

    /**
     * Check if job has been paused in database.
     *
     * @param int $jobid
     * @return bool
     */
    private function is_job_paused(int $jobid): bool {
        global $DB;
        $status = $DB->get_field('local_clonecategory_jobs', 'status', ['id' => $jobid]);
        return ($status === manager::STATUS_PAUSED || $status === manager::STATUS_ROLLING_BACK);
    }

    /**
     * Increment job progress and current step description.
     *
     * @param int $jobid
     * @param string $type
     * @param string $itemname
     */
    private function increment_progress(int $jobid, string $type, string $itemname) {
        global $DB;
        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
        if (!$job) {
            return;
        }

        if ($type === 'category') {
            $job->categoriescount++;
        } else if ($type === 'course') {
            $job->coursescount++;
        }

        $totalitems = ($job->totalcategories + $job->totalcourses);
        $doneitems = ($job->categoriescount + $job->coursescount);
        $job->progress = ($totalitems > 0) ? (int)round(($doneitems / $totalitems) * 100) : 0;
        if ($job->progress > 99 && $doneitems < $totalitems) {
            $job->progress = 99;
        }

        $job->currentstep = get_string('job_cloned_item', 'local_clonecategory', (object)['type' => $type, 'name' => $itemname]);
        $job->timemodified = time();
        $DB->update_record('local_clonecategory_jobs', $job);
    }
}

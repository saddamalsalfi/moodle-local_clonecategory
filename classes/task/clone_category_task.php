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
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory\task;

use local_clonecategory\manager;

/**
 * Class clone_category_task
 *
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clone_category_task extends \core\task\adhoc_task {
    /** @var int Timestamp of last pause check to reduce DB polling. */
    private int $last_pause_check_time = 0;

    /** @var int Minimum seconds between pause checks. */
    private const PAUSE_CHECK_INTERVAL = 3;

    /** @var int Pending progress flushes. */
    private int $pending_progress = 0;

    /** @var int Batch size for progress DB writes. */
    private const PROGRESS_BATCH_SIZE = 3;

    /** @var string Last progress item name for deferred flush. */
    private string $last_progress_name = '';

    /** @var string Last progress item type for deferred flush. */
    private string $last_progress_type = '';

    /** @var array<int, bool> Cache of verified target categories for restore capability. */
    private array $verified_restore_categories = [];

    /** @var int Timestamp of last temp directory maintenance. */
    private int $last_temp_cleanup_time = 0;

    /** @var int Minimum interval in seconds between temp directory cleanups (5 minutes). */
    private const TEMP_CLEANUP_INTERVAL = 300;

    /**
     * Run category cloning task.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/filelib.php');
        $jobid = (int)($this->get_custom_data()->jobid ?? 0);
        if (!$jobid) {
            return;
        }
        $worker = manager::lock('worker');
        try {
            $lock = manager::lock('job_' . $jobid);
            try {
                $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
                // Failures require an explicit retry. Stale tasks may never restart a terminal job.
                if (!$job || !in_array($job->status, [manager::STATUS_PENDING, manager::STATUS_RUNNING], true)) {
                    return;
                }
                manager::validate_destination((int)$job->sourcecategoryid, (int)$job->targetparentid);
                manager::require_clone_access(
                    (int)$job->sourcecategoryid,
                    (int)$job->targetparentid,
                    $job->clonemode,
                    (int)$job->userid
                );
                $DB->execute('UPDATE {local_clonecategory_jobs} SET status = :running, currentstep = :message,
                    timemodified = :modified WHERE id = :id AND status IN (:pending, :oldrunning)', [
                        'running' => manager::STATUS_RUNNING, 'message' => get_string('job_running', 'local_clonecategory'),
                        'modified' => time(), 'id' => $jobid,
                        'pending' => manager::STATUS_PENDING, 'oldrunning' => manager::STATUS_RUNNING,
                    ]);
                if ($this->is_job_paused($jobid)) {
                    return;
                }
                $this->clone_category($job, (int)$job->sourcecategoryid, (int)$job->targetparentid, true);
                // Flush any remaining batched progress updates before marking complete.
                $this->flush_progress($jobid);
                // Only the worker's running state may become completed; preserve a pause/cancel request.
                $DB->execute('UPDATE {local_clonecategory_jobs}
                    SET status = :completed, progress = 100, currentstep = :message,
                        timemodified = :modified, timefinished = :finished
                    WHERE id = :id AND status = :running', [
                        'completed' => manager::STATUS_COMPLETED, 'running' => manager::STATUS_RUNNING,
                        'message' => get_string('job_completed_success', 'local_clonecategory'),
                        'modified' => time(), 'finished' => time(), 'id' => $jobid,
                    ]);
            } catch (\Throwable $e) {
                if ($DB->record_exists('local_clonecategory_jobs', ['id' => $jobid])) {
                    $DB->execute('UPDATE {local_clonecategory_jobs} SET status = :failed, currentstep = :message,
                        timemodified = :modified, timefinished = :finished
                        WHERE id = :id AND status IN (:running, :pending)', [
                            'failed' => manager::STATUS_FAILED, 'message' => get_string('job_failed_safe', 'local_clonecategory'),
                            'modified' => time(), 'finished' => time(), 'id' => $jobid,
                            'running' => manager::STATUS_RUNNING, 'pending' => manager::STATUS_PENDING,
                        ]);
                }
                // Details belong in cron's protected task log, never in a public-facing field.
                mtrace('Clone job ' . $jobid . ': ' . $e->getMessage());
                throw $e;
            } finally {
                $lock->release();
            }
        } finally {
            $worker->release();
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

        $sourcecontext = \context_coursecat::instance($sourcecatid);
        require_capability('local/clonecategory:clone', $sourcecontext, $job->userid);
        require_capability('moodle/category:manage', $sourcecontext, $job->userid);
        $sourcecat = \core_course_category::get($sourcecatid);

        // Check if category was already cloned in previous attempt (for pause/resume).
        $existingitem = $DB->get_record('local_clonecategory_items', [
            'jobid' => $job->id,
            'itemtype' => 'category',
            'sourceid' => $sourcecatid,
        ]);

        if ($existingitem && !$DB->record_exists('course_categories', ['id' => $existingitem->itemid])) {
            throw new \moodle_exception('trackeditemmissing', 'local_clonecategory');
        }
        if ($existingitem) {
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
            $catdata->idnumber = ''; // Prevent idnumber collisions.

            $catdata->visible = $sourcecat->visible;
            $catdata->name = \core_text::substr($catdata->name, 0, 255);
            $transaction = $DB->start_delegated_transaction();
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
            $fs = get_file_storage();
            $sourcecontext = \context_coursecat::instance($sourcecatid);
            $targetcontext = \context_coursecat::instance($newcatid);
            foreach ($fs->get_area_files($sourcecontext->id, 'coursecat', 'description', false, 'id', false) as $file) {
                $fs->create_file_from_storedfile(['contextid' => $targetcontext->id], $file);
            }
            $item->fingerprint = manager::fingerprint('category', (int)$newcatid);
            $DB->insert_record('local_clonecategory_items', $item);
            $transaction->allow_commit();

            // Update job counts & progress.
            $this->increment_progress($job->id, 'category', $sourcecat->name);
        }

        // Clone courses in this category.
        $courses = ($job->clonemode ?? manager::MODE_FULL) === manager::MODE_CATEGORIES
            ? [] : $sourcecat->get_courses(['limit' => 0]);
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
        // Release memory pressure between independent work units.
        $this->release_memory_pressure();
        $context = \context_course::instance($course->id);
        if ($job->clonemode === manager::MODE_FULL) {
            require_capability('moodle/backup:backupcourse', $context, $job->userid);
            if (!isset($this->verified_restore_categories[$targetcategoryid])) {
                require_capability('moodle/restore:restorecourse', \context_coursecat::instance($targetcategoryid), $job->userid);
                $this->verified_restore_categories[$targetcategoryid] = true;
            }
        }
        $existing = $DB->get_record(
            'local_clonecategory_items',
            ['jobid' => $job->id, 'itemtype' => 'course', 'sourceid' => $course->id]
        );
        if ($existing) {
            if ($existing->status === 'completed') {
                if (!$DB->record_exists('course', ['id' => $existing->itemid])) {
                    throw new \moodle_exception('trackeditemmissing', 'local_clonecategory');
                }
                return;
            }
            // An interrupted restore is never treated as completed or blindly reused.
            if ($DB->record_exists('course', ['id' => $existing->itemid])) {
                if (
                    empty($existing->fingerprint) || !hash_equals(
                        $existing->fingerprint,
                        manager::fingerprint('course', (int)$existing->itemid)
                    )
                ) {
                    throw new \moodle_exception('incompletemodified', 'local_clonecategory');
                }
                require_capability('moodle/course:delete', \context_course::instance($existing->itemid), $job->userid);
                manager::delete_owned_course((int)$existing->itemid);
            }
            $DB->delete_records('local_clonecategory_items', ['id' => $existing->id]);
        }
        if ($job->clonemode === manager::MODE_SETTINGS) {
            $this->clone_course_settings($job, $course, $targetcategoryid);
            return;
        }
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);
        $bc = null;
        $rc = null;
        $backupid = null;
        $itemid = null;
        $newcourseid = null;
        try {
            $bc = new \backup_controller(
                \backup::TYPE_1COURSE,
                $course->id,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $job->userid
            );
            $this->exclude_user_data($bc->get_plan());
            $backupid = $bc->get_backupid();
            $bc->execute_plan();
            if ($this->is_job_paused((int)$job->id)) {
                return;
            }
            $fullname = \core_text::substr($course->fullname . ($job->coursesuffix ?? ''), 0, 254);
            $shortname = $this->unique_shortname($course->shortname, (int)$job->id, (int)$course->id);
            $transaction = $DB->start_delegated_transaction();
            $newcourseid = \restore_dbops::create_new_course($fullname, $shortname, $targetcategoryid);
            $itemid = $DB->insert_record('local_clonecategory_items', (object)[
                'jobid' => $job->id, 'itemtype' => 'course', 'itemid' => $newcourseid,
                'sourceid' => $course->id, 'status' => 'in_progress', 'timecreated' => time(),
                'fingerprint' => manager::fingerprint('course', (int)$newcourseid),
            ]);
            $transaction->allow_commit();
            $rc = new \restore_controller(
                $backupid,
                $newcourseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_SAMESITE,
                $job->userid,
                \backup::TARGET_NEW_COURSE
            );
            $this->exclude_user_data($rc->get_plan());
            foreach (['course_shortname' => $shortname, 'course_fullname' => $fullname] as $name => $value) {
                if ($rc->get_plan()->setting_exists($name)) {
                    $rc->get_plan()->get_setting($name)->set_value($value);
                }
            }
            $this->restore_course($rc);
            // Restore may append its own copy suffix; retain the requested public names.
            update_course((object)['id' => $newcourseid, 'fullname' => $fullname, 'shortname' => $shortname]);
            $DB->update_record('local_clonecategory_items', (object)[
                'id' => $itemid, 'status' => 'completed',
                'fingerprint' => manager::fingerprint('course', (int)$newcourseid),
            ]);
            $this->increment_progress((int)$job->id, 'course', $course->fullname);
        } catch (\Throwable $e) {
            foreach ([$rc, $bc] as $controller) {
                if ($controller && $controller->get_status() < \backup::STATUS_FINISHED_ERR) {
                    try {
                        $controller->set_status(\backup::STATUS_FINISHED_ERR);
                    } catch (\Throwable $cleanup) {
                        mtrace('Failed controller finalisation: ' . $cleanup->getMessage());
                    }
                }
            }
            if ($itemid && $newcourseid && $DB->record_exists('course', ['id' => $newcourseid])) {
                $DB->update_record('local_clonecategory_items', (object)[
                    'id' => $itemid, 'status' => 'failed',
                    'fingerprint' => manager::fingerprint('course', (int)$newcourseid),
                ]);
            }
            throw $e;
        } finally {
            // Each cleanup is independent, so an exception never prevents release of another resource.
            foreach ([$rc, $bc] as $controller) {
                if ($controller) {
                    try {
                        $controller->destroy();
                    } catch (\Throwable $cleanup) {
                        mtrace('Controller cleanup: ' . $cleanup->getMessage());
                    }
                }
            }
            if ($backupid && is_dir($CFG->tempdir . '/backup/' . $backupid)) {
                fulldelete($CFG->tempdir . '/backup/' . $backupid);
            }
        }
    }

    /**
     * Check errors before restoration. Protected to allow failure-injection integration tests.
     * @param \restore_controller $controller Restore controller
     */
    protected function restore_course(\restore_controller $controller): void {
        if (!$controller->execute_precheck()) {
            $results = $controller->get_precheck_results();
            if (!empty($results['errors'])) {
                throw new \moodle_exception('restoreprecheckfailed', 'local_clonecategory');
            }
        }
        $controller->execute_plan();
    }

    /**
     * Exclude learner and enrolment data from the copy.
     *
     * @param object $plan Backup/restore plan
     */
    private function exclude_user_data($plan): void {
        // Core exclusions that are always safe.
        $settings = ['users', 'role_assignments', 'enrolments', 'logs', 'grade_histories'];
        // Extended exclusions: learner-generated artefacts that are not part
        // of the course template.  Each is guarded by setting_exists() so the
        // list is forward-compatible with Moodle versions that add or remove
        // backup settings.
        $extended = ['comments', 'badges', 'calendarevents', 'competencies',
            'contentbankcontent', 'legacyfiles'];
        foreach (array_merge($settings, $extended) as $name) {
            try {
                if ($plan->setting_exists($name) && $plan->get_setting($name)->get_value()) {
                    $plan->get_setting($name)->set_value(0);
                }
            } catch (\Exception $e) {
                // Some settings may be locked by dependencies; skip silently.
                continue;
            }
        }
    }

    /**
     * Generate an available short name for the copied course.
     *
     * @param string $name Source shortname
     * @param int $jobid Job
     * @param int $sourceid Source
     * @return string
     */
    private function unique_shortname(string $name, int $jobid, int $sourceid): string {
        global $DB;
        $base = \core_text::substr($name, 0, 190) . '_clone_' . $jobid . '_' . $sourceid;
        // Single query to fetch all colliding shortnames instead of looping.
        $existing = $DB->get_fieldset_select('course', 'shortname',
            $DB->sql_like('shortname', ':pattern'),
            ['pattern' => $DB->sql_like_escape($base) . '%']);
        $lookup = array_flip($existing);
        $candidate = $base;
        $counter = 0;
        while (isset($lookup[$candidate])) {
            $candidate = $base . '_' . ++$counter;
        }
        return $candidate;
    }

    /**
     * Create an empty course with its general settings and format options.
     * Activities, files, summaries, enrolments and learner data are not copied.
     *
     * @param \stdClass $job Clone job
     * @param object $course Source course
     * @param int $targetcategoryid Destination category
     */
    private function clone_course_settings(\stdClass $job, $course, int $targetcategoryid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $source = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        $data = new \stdClass();
        $fields = [
            'format', 'showgrades', 'newsitems', 'startdate', 'enddate', 'marker',
            'maxbytes', 'showreports', 'visible', 'groupmode', 'groupmodeforce',
            'lang', 'theme', 'enablecompletion', 'completionnotify',
            'showcompletionconditions', 'relativedatesmode', 'downloadcontent',
        ];
        foreach ($fields as $field) {
            if (property_exists($source, $field)) {
                $data->{$field} = $source->{$field};
            }
        }
        foreach (course_get_format($source)->get_format_options() as $name => $value) {
            if (!property_exists($data, $name)) {
                $data->{$name} = $value;
            }
        }
        $data->category = $targetcategoryid;
        $data->fullname = \core_text::substr($source->fullname . ($job->coursesuffix ?? ''), 0, 254);
        $data->shortname = $this->unique_shortname($source->shortname, (int)$job->id, (int)$source->id);
        $data->idnumber = '';
        $data->summary = '';
        $data->summaryformat = FORMAT_HTML;
        $data->defaultgroupingid = 0;

        $transaction = $DB->start_delegated_transaction();
        $newcourse = create_course($data);
        $itemid = $DB->insert_record('local_clonecategory_items', (object)[
            'jobid' => $job->id,
            'itemtype' => 'course',
            'itemid' => $newcourse->id,
            'sourceid' => $source->id,
            'status' => 'completed',
            'timecreated' => time(),
            'fingerprint' => manager::fingerprint('course', (int)$newcourse->id),
        ]);
        $this->increment_progress($job->id, 'course', $source->fullname);
        $transaction->allow_commit();
        // Events are persisted at commit; capture the baseline after those creation events.
        $DB->set_field(
            'local_clonecategory_items',
            'fingerprint',
            manager::fingerprint('course', (int)$newcourse->id),
            ['id' => $itemid]
        );
    }

    /**
     * Check if job has been paused in database.
     *
     * @param int $jobid
     * @return bool
     */
    private function is_job_paused(int $jobid): bool {
        global $DB;
        // Throttle DB polls to reduce query overhead during tight loops.
        $now = time();
        if ($now - $this->last_pause_check_time < self::PAUSE_CHECK_INTERVAL) {
            return false; // Assume still running until next check window.
        }
        $this->last_pause_check_time = $now;
        $status = $DB->get_field('local_clonecategory_jobs', 'status', ['id' => $jobid]);
        return $status !== manager::STATUS_RUNNING;
    }

    /**
     * Increment job progress and current step description.
     *
     * @param int $jobid
     * @param string $type
     * @param string $itemname
     */
    private function increment_progress(int $jobid, string $type, string $itemname) {
        $this->last_progress_type = $type;
        $this->last_progress_name = $itemname;
        $this->pending_progress++;
        if ($this->pending_progress >= self::PROGRESS_BATCH_SIZE) {
            $this->flush_progress($jobid);
        }
    }

    /**
     * Write accumulated progress to the database.
     *
     * @param int $jobid
     */
    private function flush_progress(int $jobid): void {
        global $DB;
        if ($this->pending_progress === 0) {
            return;
        }
        $this->pending_progress = 0;
        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
        if (!$job) {
            return;
        }
        $categories = $DB->count_records(
            'local_clonecategory_items',
            ['jobid' => $jobid, 'itemtype' => 'category', 'status' => 'completed']
        );
        $courses = $DB->count_records(
            'local_clonecategory_items',
            ['jobid' => $jobid, 'itemtype' => 'course', 'status' => 'completed']
        );
        $total = (int)$job->totalcategories + (int)$job->totalcourses;
        $record = (object)['id' => $jobid, 'categoriescount' => $categories, 'coursescount' => $courses,
            'progress' => $total ? min(99, (int)round(100 * ($categories + $courses) / $total)) : 0];
        if ($job->status === manager::STATUS_RUNNING && $this->last_progress_name !== '') {
            $record->currentstep = \core_text::substr(get_string(
                'job_cloned_item',
                'local_clonecategory',
                (object)['type' => $this->last_progress_type, 'name' => $this->last_progress_name]
            ), 0, 255);
        }
        $DB->update_record('local_clonecategory_jobs', $record);
    }

    /**
     * Release memory pressure between independent work units.
     *
     * Each course backup/restore cycle accumulates context objects and cyclic
     * references.  Cleaning these between units keeps peak memory stable as
     * the tree size grows.
     */
    private function release_memory_pressure(): void {
        // Reclaim cyclic references from the previous backup/restore cycle.
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        // Purge cached context instances that will be re-fetched as needed.
        \context_helper::reset_caches();
        // Periodically purge stale temporary directories.
        $this->periodic_temp_cleanup();
    }

    /**
     * Periodically clean up stale temporary backup directories left by abnormal terminations.
     */
    private function periodic_temp_cleanup(): void {
        global $CFG;
        $now = time();
        if ($now - $this->last_temp_cleanup_time < self::TEMP_CLEANUP_INTERVAL) {
            return;
        }
        $this->last_temp_cleanup_time = $now;
        $backupdir = $CFG->tempdir . '/backup';
        if (is_dir($backupdir)) {
            $handle = @opendir($backupdir);
            if ($handle) {
                while (($entry = readdir($handle)) !== false) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $path = $backupdir . '/' . $entry;
                    if (is_dir($path) && ($now - @filemtime($path)) > 1800) {
                        fulldelete($path);
                    }
                }
                closedir($handle);
            }
        }
    }
}

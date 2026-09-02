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
 * Manager class for handling clone category operations, state, locks, and rollbacks.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager class for local_clonecategory.
 *
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** Status constants */
    const STATUS_PENDING     = 'pending';
    const STATUS_RUNNING     = 'running';
    const STATUS_PAUSED      = 'paused';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_FAILED      = 'failed';
    const STATUS_ROLLING_BACK = 'rolling_back';
    const STATUS_ROLLED_BACK = 'rolled_back';

    /**
     * Check if a cloning job is currently active or paused.
     *
     * @return bool
     */
    public static function has_active_job(): bool {
        global $DB;
        $select = "status IN (:p, :r, :pa)";
        $params = [
            'p' => self::STATUS_PENDING,
            'r' => self::STATUS_RUNNING,
            'pa' => self::STATUS_PAUSED,
        ];
        return $DB->record_exists_select('local_clonecategory_jobs', $select, $params);
    }

    /**
     * Get the active job if one exists.
     *
     * @return \stdClass|false
     */
    public static function get_active_job() {
        global $DB;
        $select = "status IN (:p, :r, :pa)";
        $params = [
            'p' => self::STATUS_PENDING,
            'r' => self::STATUS_RUNNING,
            'pa' => self::STATUS_PAUSED,
        ];
        return $DB->get_record_select('local_clonecategory_jobs', $select, $params, '*', IGNORE_MULTIPLE);
    }

    /**
     * Create a new cloning job and queue its adhoc task.
     *
     * @param int $sourcecatid
     * @param int $targetparentid
     * @param string $catprefix
     * @param string $courseprefix
     * @param int $userid
     * @return int Job ID
     * @throws \moodle_exception
     */
    public static function create_job(int $sourcecatid, int $targetparentid, string $catprefix, string $courseprefix, int $userid): int {
        global $DB;

        if (self::has_active_job()) {
            throw new \moodle_exception('active_job_exists', 'local_clonecategory');
        }

        $sourcecat = \core_course_category::get($sourcecatid);

        // Count totals for progress reporting.
        $totals = self::count_category_tree($sourcecatid);

        $now = time();
        $job = new \stdClass();
        $job->userid           = $userid;
        $job->sourcecategoryid = $sourcecatid;
        $job->targetparentid   = $targetparentid;
        $job->categorysuffix   = $catprefix;
        $job->coursesuffix     = $courseprefix;
        $job->status           = self::STATUS_PENDING;
        $job->categoriescount  = 0;
        $job->coursescount     = 0;
        $job->totalcategories  = $totals['categories'];
        $job->totalcourses     = $totals['courses'];
        $job->progress         = 0;
        $job->currentstep      = get_string('job_queued', 'local_clonecategory');
        $job->timecreated      = $now;
        $job->timemodified     = $now;

        $jobid = $DB->insert_record('local_clonecategory_jobs', $job);

        // Queue adhoc task.
        $task = new \local_clonecategory\task\clone_category_task();
        $task->set_custom_data(['jobid' => $jobid]);
        \core\task\manager::queue_adhoc_task($task);

        return $jobid;
    }

    /**
     * Pause a running or pending job.
     *
     * @param int $jobid
     * @return bool
     */
    public static function pause_job(int $jobid): bool {
        global $DB;
        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
        if (!$job || in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_ROLLED_BACK])) {
            return false;
        }

        $job->status = self::STATUS_PAUSED;
        $job->currentstep = get_string('job_paused_by_user', 'local_clonecategory');
        $job->timemodified = time();
        return $DB->update_record('local_clonecategory_jobs', $job);
    }

    /**
     * Resume a paused job.
     *
     * @param int $jobid
     * @return bool
     */
    public static function resume_job(int $jobid): bool {
        global $DB;
        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
        if (!$job || $job->status !== self::STATUS_PAUSED) {
            return false;
        }

        $job->status = self::STATUS_PENDING;
        $job->currentstep = get_string('job_resumed', 'local_clonecategory');
        $job->timemodified = time();
        $DB->update_record('local_clonecategory_jobs', $job);

        // Queue task if not already queued.
        $task = new \local_clonecategory\task\clone_category_task();
        $task->set_custom_data(['jobid' => $jobid]);
        \core\task\manager::queue_adhoc_task($task);

        return true;
    }

    /**
     * Check if a job can be rolled back (only allowed for the latest job within 24h window).
     *
     * @param \stdClass $job
     * @param int $latestjobid
     * @return bool
     */
    public static function can_rollback_job(\stdClass $job, int $latestjobid): bool {
        if ((int)$job->id !== $latestjobid) {
            return false;
        }

        if (!in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_PAUSED, self::STATUS_RUNNING])) {
            return false;
        }

        $time = $job->timemodified ?: $job->timecreated;
        if ((time() - $time) > 86400) {
            return false;
        }

        return true;
    }

    /**
     * Rollback (Undo) a cloning job completely, deleting all created categories & courses.
     *
     * @param int $jobid
     * @return bool
     * @throws \moodle_exception
     */
    public static function rollback_job(int $jobid): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid]);
        if (!$job) {
            return false;
        }

        $latestjobid = (int)$DB->get_field_sql("SELECT MAX(id) FROM {local_clonecategory_jobs}");
        if ((int)$jobid !== $latestjobid) {
            throw new \moodle_exception('error_rollback_not_latest', 'local_clonecategory');
        }

        $time = $job->timemodified ?: $job->timecreated;
        if ((time() - $time) > 86400) {
            throw new \moodle_exception('error_rollback_expired', 'local_clonecategory');
        }

        // Lock to avoid race conditions.
        $factory = \core\lock\lock_config::get_lock_factory('local_clonecategory');
        $lock = $factory->get_lock('rollback_' . $jobid, 30);
        if (!$lock) {
            throw new \moodle_exception('error_lock_failed', 'local_clonecategory');
        }

        try {
            $job->status = self::STATUS_ROLLING_BACK;
            $job->currentstep = get_string('job_rolling_back', 'local_clonecategory');
            $job->timemodified = time();
            $DB->update_record('local_clonecategory_jobs', $job);

            // Fetch created items in reverse order of creation (courses first, then subcategories, then root category).
            $items = $DB->get_records('local_clonecategory_items', ['jobid' => $jobid], 'id DESC');

            foreach ($items as $item) {
                if ($item->itemtype === 'course') {
                    if ($DB->record_exists('course', ['id' => $item->itemid])) {
                        delete_course($item->itemid, false);
                    }
                } else if ($item->itemtype === 'category') {
                    if ($DB->record_exists('course_categories', ['id' => $item->itemid])) {
                        try {
                            $cat = \core_course_category::get($item->itemid);
                            $cat->delete_full(false);
                        } catch (\Throwable $e) {
                            // Fallback if category already deleted or missing.
                        }
                    }
                }
                $DB->delete_records('local_clonecategory_items', ['id' => $item->id]);
            }

            $job->status = self::STATUS_ROLLED_BACK;
            $job->progress = 0;
            $job->currentstep = get_string('job_rolled_back_success', 'local_clonecategory');
            $job->timemodified = time();
            $DB->update_record('local_clonecategory_jobs', $job);

        } finally {
            $lock->release();
        }

        return true;
    }

    /**
     * Delete job record from database.
     *
     * @param int $jobid
     * @return bool
     */
    public static function delete_job(int $jobid): bool {
        global $DB;
        $DB->delete_records('local_clonecategory_items', ['jobid' => $jobid]);
        return $DB->delete_records('local_clonecategory_jobs', ['id' => $jobid]);
    }

    /**
     * Count total categories and courses under a category tree.
     *
     * @param int $sourcecatid
     * @return array ['categories' => int, 'courses' => int]
     */
    public static function count_category_tree(int $sourcecatid): array {
        $catcount = 1;
        $coursecount = 0;

        try {
            $sourcecat = \core_course_category::get($sourcecatid);
            $coursecount += count($sourcecat->get_courses(['limit' => 0]));

            $subcats = $sourcecat->get_children();
            foreach ($subcats as $subcat) {
                $subres = self::count_category_tree($subcat->id);
                $catcount += $subres['categories'];
                $coursecount += $subres['courses'];
            }
        } catch (\Throwable $e) {
            // Ignore missing category during calculation.
        }

        return ['categories' => $catcount, 'courses' => $coursecount];
    }
}

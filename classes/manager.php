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

/**
 * Manager class for local_clonecategory.
 *
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Mode categories. */
    public const MODE_CATEGORIES = 'categories';
    /** @var string Mode settings. */
    public const MODE_SETTINGS = 'settings';
    /** @var string Mode full. */
    public const MODE_FULL = 'full';
    /** @var string Status pending. */
    public const STATUS_PENDING = 'pending';
    /** @var string Status running. */
    public const STATUS_RUNNING = 'running';
    /** @var string Status paused. */
    public const STATUS_PAUSED = 'paused';
    /** @var string Status completed. */
    public const STATUS_COMPLETED = 'completed';
    /** @var string Status failed. */
    public const STATUS_FAILED = 'failed';
    /** @var string Status cancelled. */
    public const STATUS_CANCELLED = 'cancelled';
    /** @var string Status rolling back. */
    public const STATUS_ROLLING_BACK = 'rolling_back';
    /** @var string Status rolled back. */
    public const STATUS_ROLLED_BACK = 'rolled_back';
    /** @var string Rollback stopped and requires review or retry. */
    public const STATUS_ROLLBACK_FAILED = 'rollback_failed';

    /**
     * List states which reserve the operation slot.
     *
     * @return array Active states
     */
    public static function active_states(): array {
        return [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_PAUSED, self::STATUS_ROLLING_BACK];
    }

    /**
     * Check whether another operation reserves the global slot.
     *
     * @return bool Whether a job reserves the global operation slot
     */
    public static function has_active_job(): bool {
        global $DB;
        [$sql, $params] = $DB->get_in_or_equal(self::active_states(), SQL_PARAMS_NAMED);
        return $DB->record_exists_select('local_clonecategory_jobs', "status $sql", $params);
    }

    /**
     * Find an active job visible to the current user.
     *
     * @return object|false Active job visible to the current user
     */
    public static function get_active_job() {
        foreach (self::get_visible_jobs() as $job) {
            if (in_array($job->status, self::active_states(), true)) {
                return $job;
            }
        }
        return false;
    }

    /**
     * Find authorised jobs for the current user.
     *
     * @return array Jobs visible to the current user
     */
    public static function get_visible_jobs(): array {
        global $DB, $USER;
        $conditions = has_capability('local/clonecategory:managejobs', \context_system::instance())
            ? [] : ['userid' => $USER->id];
        $jobs = $DB->get_records('local_clonecategory_jobs', $conditions, 'id DESC', '*', 0, 50);
        return array_filter($jobs, static function ($job) {
            return self::can_manage_job($job);
        });
    }

    /**
     * Acquire a shared operation lock; workers, retries, deletion and rollback use the same resource.
     * @param string $resource Resource name
     * @return \core\lock\lock
     */
    public static function lock(string $resource): \core\lock\lock {
        global $DB;
        static $factories = [];
        $key = \core\lock\lock_config::get_lock_factory_class() . ':' . spl_object_id($DB);
        if (!isset($factories[$key])) {
            $factories[$key] = \core\lock\lock_config::get_lock_factory('local_clonecategory');
        }
        $lock = $factories[$key]->get_lock($resource, 0);
        if (!$lock) {
            throw new \moodle_exception('error_lock_failed', 'local_clonecategory');
        }
        return $lock;
    }

    /**
     * Verify current permissions of the requesting user, including when a queued job runs later.
     * @param int $sourceid Source category
     * @param int $targetid Destination parent or root
     * @param string $mode Clone scope
     * @param int $userid Requesting user
     */
    public static function require_clone_access(int $sourceid, int $targetid, string $mode, int $userid): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
        $source = \context_coursecat::instance($sourceid);
        $target = $targetid ? \context_coursecat::instance($targetid) : \context_system::instance();
        require_capability('local/clonecategory:clone', $source, $user);
        require_capability('moodle/category:manage', $source, $user);
        require_capability('moodle/category:manage', $target, $user);
        if ($mode !== self::MODE_CATEGORIES) {
            require_capability('moodle/course:create', $target, $user);
        }
    }

    /**
     * Check whether the current user may inspect or control a job.
     *
     * @param \stdClass $job Job record
     * @return bool Whether the current user may inspect/control the job
     */
    public static function can_manage_job(\stdClass $job): bool {
        global $USER;
        if (has_capability('local/clonecategory:managejobs', \context_system::instance())) {
            return true;
        }
        if ((int)$job->userid !== (int)$USER->id) {
            return false;
        }
        try {
            self::require_clone_access(
                (int)$job->sourcecategoryid,
                (int)$job->targetparentid,
                $job->clonemode,
                (int)$USER->id
            );
            return true;
        } catch (\moodle_exception $e) {
            return false;
        }
    }

    /**
     * Require permission to manage the requested job.
     *
     * @param int $jobid Job ID
     * @return object Authorised job record
     */
    public static function require_job(int $jobid): \stdClass {
        global $DB;
        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid], '*', MUST_EXIST);
        if (!self::can_manage_job($job)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/clonecategory:managejobs',
                'nopermissions',
                ''
            );
        }
        return $job;
    }

    /**
     * Atomically create and enqueue a clone job.
     * @param int $sourcecatid Source
     * @param int $targetparentid Destination
     * @param string $catprefix Category suffix
     * @param string $courseprefix Course suffix
     * @param int $userid Requester
     * @param string $clonemode Scope
     * @return int Job ID
     */
    public static function create_job(
        int $sourcecatid,
        int $targetparentid,
        string $catprefix,
        string $courseprefix,
        int $userid,
        string $clonemode = self::MODE_FULL
    ): int {
        global $DB, $USER;
        if ($userid !== (int)$USER->id) {
            throw new \moodle_exception('invaliduser');
        }
        if (!in_array($clonemode, [self::MODE_CATEGORIES, self::MODE_SETTINGS, self::MODE_FULL], true)) {
            throw new \moodle_exception('invalidclonemode', 'local_clonecategory');
        }
        self::validate_destination($sourcecatid, $targetparentid);
        self::require_clone_access($sourcecatid, $targetparentid, $clonemode, $userid);
        if (\core_text::strlen($catprefix) > 255 || \core_text::strlen($courseprefix) > 255) {
            throw new \moodle_exception('suffixlength', 'local_clonecategory');
        }
        $lock = self::lock('create');
        $worker = null;
        try {
            $worker = self::lock('worker');
            if (self::has_active_job()) {
                throw new \moodle_exception('active_job_exists', 'local_clonecategory');
            }
            $transaction = $DB->start_delegated_transaction();
            $totals = self::count_category_tree($sourcecatid);
            $job = (object)[
                'userid' => $userid, 'clonemode' => $clonemode,
                'sourcecategoryid' => $sourcecatid, 'targetparentid' => $targetparentid,
                'categorysuffix' => $catprefix, 'coursesuffix' => $courseprefix,
                'status' => self::STATUS_PENDING, 'categoriescount' => 0, 'coursescount' => 0,
                'totalcategories' => $totals['categories'],
                'totalcourses' => $clonemode === self::MODE_CATEGORIES ? 0 : $totals['courses'],
                'progress' => 0, 'currentstep' => get_string('job_queued', 'local_clonecategory'),
                'timecreated' => time(), 'timemodified' => time(), 'timefinished' => 0,
            ];
            $jobid = $DB->insert_record('local_clonecategory_jobs', $job);
            self::queue_job($jobid, $userid);
            $transaction->allow_commit();
            return $jobid;
        } finally {
            if ($worker) {
                $worker->release();
            }
            $lock->release();
        }
    }

    /**
     * Queue a job under its requesting user.
     *
     * @param int $jobid Job
     * @param int $userid Actor
     */
    public static function queue_job(int $jobid, int $userid): void {
        $task = new \local_clonecategory\task\clone_category_task();
        $task->set_custom_data(['jobid' => $jobid]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Build searchable category options in hierarchy order without repeated ancestor names.
     *
     * @param bool $source Whether building source options
     * @return array Category IDs mapped to depth-prefixed names
     */
    public static function get_category_options(bool $source = false): array {
        global $DB;
        $allowed = \core_course_category::make_categories_list();
        $records = $DB->get_records('course_categories', null, '', 'id, name, depth');
        $options = [];
        foreach ($allowed as $id => $unusedpath) {
            if (!isset($records[$id])) {
                continue;
            }
            $context = \context_coursecat::instance($id);
            if (
                !has_capability('moodle/category:manage', $context) ||
                    ($source && !has_capability('local/clonecategory:clone', $context))
            ) {
                continue;
            }
            $category = $records[$id];
            $depth = max(0, (int)$category->depth - 1);
            $prefix = str_repeat('│ ', $depth) . '└─ ';
            $options[$id] = $prefix . format_string(
                $category->name,
                true,
                ['context' => \context_coursecat::instance($id)]
            );
        }
        return $options;
    }

    /**
     * Return visible categories for the collapsible selectors.
     *
     * @return array Category tree nodes
     */
    public static function get_category_tree(): array {
        global $DB;
        $allowed = \core_course_category::make_categories_list();
        $records = $DB->get_records('course_categories', null, '', 'id, name, parent');
        $nodes = [];
        foreach ($allowed as $id => $unusedpath) {
            if (isset($records[$id])) {
                $category = $records[$id];
                $nodes[] = [
                    'id' => (int)$id,
                    'parent' => (int)$category->parent,
                    'name' => html_entity_decode(strip_tags(format_string(
                        $category->name,
                        true,
                        ['context' => \context_coursecat::instance($id)]
                    )), ENT_QUOTES, 'UTF-8'),
                ];
            }
        }
        return $nodes;
    }

    /**
     * Reject a destination inside the source tree, which could recursively copy new items.
     *
     * @param int $sourcecatid Source category ID
     * @param int $targetparentid Destination parent ID, or zero for the root
     */
    public static function validate_destination(int $sourcecatid, int $targetparentid): void {
        \core_course_category::get($sourcecatid);
        if ($targetparentid !== 0) {
            $target = \core_course_category::get($targetparentid);
            $ancestors = explode('/', trim($target->path, '/'));
            if (in_array((string)$sourcecatid, $ancestors, true)) {
                throw new \moodle_exception('invaliddestination', 'local_clonecategory');
            }
        }
    }

    /**
     * Write only status fields, never stale counters from a concurrent worker.
     * @param int $jobid Job
     * @param string $status New status
     * @param string $message Localised status text
     */
    public static function set_status(int $jobid, string $status, string $message): void {
        global $DB;
        $record = (object)['id' => $jobid, 'status' => $status,
            'currentstep' => \core_text::substr($message, 0, 255), 'timemodified' => time()];
        if (
            in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED,
                self::STATUS_ROLLED_BACK], true)
        ) {
            $record->timefinished = time();
        } else if ($status === self::STATUS_PENDING) {
            $record->timefinished = 0;
        }
        $DB->update_record('local_clonecategory_jobs', $record);
    }

    /**
     * Atomically request a stop without overwriting a worker's completed state.
     * @param int $jobid Job
     * @param string $status Paused or cancelled
     * @param string $message Language key
     */
    public static function request_stop(int $jobid, string $status, string $message): void {
        global $DB;
        $DB->execute('UPDATE {local_clonecategory_jobs} SET status = :newstate, currentstep = :message,
            timemodified = :modified, timefinished = :finished WHERE id = :id AND status IN (:pending, :running, :paused)', [
                'newstate' => $status, 'message' => get_string($message, 'local_clonecategory'),
                'modified' => time(), 'finished' => $status === self::STATUS_CANCELLED ? time() : 0,
                'id' => $jobid, 'pending' => self::STATUS_PENDING,
                'running' => self::STATUS_RUNNING, 'paused' => self::STATUS_PAUSED,
            ]);
    }

    /**
     * Request that the worker pause at the next safe boundary.
     *
     * @param int $jobid Job
     * @return bool Pause requested
     */
    public static function pause_job(int $jobid): bool {
        $job = self::require_job($jobid);
        if (!in_array($job->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true)) {
            throw new \moodle_exception('invalidjobstate', 'local_clonecategory');
        }
        self::request_stop($jobid, self::STATUS_PAUSED, 'job_paused_by_user');
        return true;
    }

    /**
     * Cancel pending work at the next safe boundary.
     *
     * @param int $jobid Job
     * @return bool Cancellation requested
     */
    public static function cancel_job(int $jobid): bool {
        $job = self::require_job($jobid);
        if (!in_array($job->status, [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_PAUSED], true)) {
            throw new \moodle_exception('invalidjobstate', 'local_clonecategory');
        }
        self::request_stop($jobid, self::STATUS_CANCELLED, 'job_cancelled');
        return true;
    }

    /**
     * Requeue an authorised paused or failed job.
     *
     * @param int $jobid Job
     * @return bool Job queued
     */
    public static function resume_job(int $jobid): bool {
        global $DB;
        $global = self::lock('create');
        $worker = null;
        try {
            $worker = self::lock('worker');
            $lock = self::lock('job_' . $jobid);
            try {
                $job = self::require_job($jobid);
                if (!in_array($job->status, [self::STATUS_PAUSED, self::STATUS_FAILED], true)) {
                    throw new \moodle_exception('invalidjobstate', 'local_clonecategory');
                }
                [$sql, $params] = $DB->get_in_or_equal(self::active_states(), SQL_PARAMS_NAMED);
                $params['jobid'] = $jobid;
                if ($DB->record_exists_select('local_clonecategory_jobs', "id <> :jobid AND status $sql", $params)) {
                    throw new \moodle_exception('active_job_exists', 'local_clonecategory');
                }
                self::require_clone_access(
                    (int)$job->sourcecategoryid,
                    (int)$job->targetparentid,
                    $job->clonemode,
                    (int)$job->userid
                );
                $transaction = $DB->start_delegated_transaction();
                self::set_status($jobid, self::STATUS_PENDING, get_string('job_resumed', 'local_clonecategory'));
                self::queue_job($jobid, (int)$job->userid);
                $transaction->allow_commit();
            } finally {
                $lock->release();
            }
        } finally {
            if ($worker) {
                $worker->release();
            }
            $global->release();
        }
        return true;
    }

    /**
     * Check the rollback state, ownership and time window.
     *
     * @param \stdClass $job Job
     * @param int $latestjobid Latest visible job
     * @return bool
     */
    public static function can_rollback_job(\stdClass $job, int $latestjobid): bool {
        $anchor = $job->timefinished ?: $job->timecreated;
        return (int)$job->id === $latestjobid && self::can_manage_job($job)
            && in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_FAILED,
                self::STATUS_PAUSED, self::STATUS_CANCELLED, self::STATUS_ROLLBACK_FAILED], true) && time() - $anchor <= DAYSECS;
    }

    /**
     * Conservative fingerprint of owned resources; any detected edit blocks destructive rollback.
     * @param string $type Item type
     * @param int $id Item ID
     * @return string SHA-256 snapshot
     */
    public static function fingerprint(string $type, int $id): string {
        return hash('sha256', json_encode(self::fingerprint_data($type, $id)));
    }

    /**
     * Read a consistent resource snapshot, including pending standard log entries.
     *
     * @param string $type Item type
     * @param int $id Item ID
     * @return array Snapshot data
     */
    private static function fingerprint_data(string $type, int $id): array {
        global $DB;
        if ($type === 'category') {
            $record = $DB->get_record(
                'course_categories',
                ['id' => $id],
                'id, name, parent, description, descriptionformat, visible, idnumber',
                MUST_EXIST
            );
            $context = \context_coursecat::instance($id);
            $data = [$record, $DB->get_records('cohort', ['contextid' => $context->id], 'id'),
                $DB->get_records('question_categories', ['contextid' => $context->id], 'id'),
                $DB->get_records('contentbank_content', ['contextid' => $context->id], 'id'),
                $DB->get_records('role_assignments', ['contextid' => $context->id], 'id'),
                $DB->get_records('files', ['contextid' => $context->id], 'id')];
            return $data;
        }
        $record = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
        // Cache rebuilds and sibling reordering are not content edits.
        unset($record->cacherev, $record->sortorder);
        $data = [$record, $DB->get_records('course_sections', ['course' => $id], 'id'),
            $DB->get_records('course_modules', ['course' => $id], 'id'),
            $DB->get_records('enrol', ['courseid' => $id], 'id'),
            $DB->get_records('groups', ['courseid' => $id], 'id')];
        $context = \context_course::instance($id);
        $data[] = $DB->get_records('role_assignments', ['contextid' => $context->id], 'id');
        $data[] = $DB->get_records('course_format_options', ['courseid' => $id], 'id');
        $data[] = $DB->get_records('grade_items', ['courseid' => $id], 'id');
        $data[] = $DB->get_records('groupings', ['courseid' => $id], 'id');
        $data[] = $DB->get_records('customfield_data', ['contextid' => $context->id], 'id');
        $data[] = $DB->get_records('block_instances', ['parentcontextid' => $context->id], 'id');
        $data[] = $DB->get_records_sql('SELECT ue.* FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = ? ORDER BY ue.id', [$id]);
        $data[] = $DB->get_records_sql('SELECT gg.* FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid WHERE gi.courseid = ? ORDER BY gg.id', [$id]);
        // Standard logging buffers writes until shutdown, independently of DB commit.
        // Persist those entries before recording or checking a baseline.
        $readers = get_log_manager()->get_readers();
        if (isset($readers['logstore_standard']) && $readers['logstore_standard'] instanceof \logstore_standard\log\store) {
            $readers['logstore_standard']->flush();
        }
        $data[] = $DB->get_records_sql("SELECT id, eventname, userid, objectid, timecreated
            FROM {logstore_standard_log} WHERE courseid = :courseid AND crud IN ('c', 'u', 'd')
            AND eventname <> :created ORDER BY id", ['courseid' => $id, 'created' => '\\core\\event\\course_created']);
        foreach ($DB->get_records('course_modules', ['course' => $id], 'id') as $cm) {
            $module = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);
            $data[] = $DB->get_record($module, ['id' => $cm->instance]);
        }
        $data[] = $DB->get_records_sql(
            'SELECT f.* FROM {files} f JOIN {context} c ON c.id = f.contextid
            WHERE (c.id = :contextid OR c.path LIKE :path) ORDER BY f.id',
            ['contextid' => $context->id, 'path' => $context->path . '/%']
        );
        return $data;
    }

    /**
     * Verify a baseline, accepting only late creation logs from legacy empty-course jobs.
     *
     * Earlier releases captured the resource records before the standard log buffer was
     * flushed. Reconstruct that exact baseline by removing a suffix of creation events
     * for already tracked sections/enrol instances. All resource records and every other
     * mutation log remain in the comparison; never generate a fresh trusted baseline.
     *
     * @param \stdClass $item Tracked resource
     * @param \stdClass $job Owning job
     * @return bool Whether the original baseline matches
     */
    private static function matches_item_fingerprint(\stdClass $item, \stdClass $job): bool {
        if (empty($item->fingerprint)) {
            return false;
        }
        $data = self::fingerprint_data($item->itemtype, (int)$item->itemid);
        if (hash_equals($item->fingerprint, hash('sha256', json_encode($data)))) {
            return true;
        }
        if ($item->itemtype !== 'course' || $job->clonemode !== self::MODE_SETTINGS) {
            return false;
        }
        // Snapshot indices: sections 1, enrol instances 3, mutation logs 13.
        foreach (array_reverse($data[13], true) as $logid => $log) {
            if ((int)$log->userid !== (int)$job->userid || (int)$log->timecreated > (int)$item->timecreated) {
                continue;
            }
            $ownedcreation = ($log->eventname === '\\core\\event\\course_section_created' &&
                isset($data[1][$log->objectid])) ||
                ($log->eventname === '\\core\\event\\enrol_instance_created' && isset($data[3][$log->objectid]));
            if (!$ownedcreation) {
                continue;
            }
            unset($data[13][$logid]);
            if (hash_equals($item->fingerprint, hash('sha256', json_encode($data)))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Synchronously remove an owned copy using the core API for this Moodle version.
     *
     * @param int $courseid Copied course
     */
    public static function delete_owned_course(int $courseid): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        // Cleanup of a restore must not create a fresh recycle-bin backup.
        $course->deletesource = 'restore';
        $args = [$course, false];
        if ((new \ReflectionFunction('delete_course'))->getNumberOfParameters() >= 3) {
            // Moodle 5.3 introduced an asynchronous-deletion preference.
            $args[] = false;
        }
        if (delete_course(...$args) === false || $DB->record_exists('course', ['id' => $courseid])) {
            throw new \moodle_exception('rollbackfailed', 'local_clonecategory');
        }
    }

    /**
     * Rollback only recorded, unmodified items. Preflight all items before deleting any.
     * @param int $jobid Job
     * @return bool Complete
     */
    public static function rollback_job(int $jobid): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $global = self::lock('create');
        $worker = null;
        try {
            $worker = self::lock('worker');
            $lock = self::lock('job_' . $jobid);
            try {
                $job = self::require_job($jobid);
                $latest = (int)$DB->get_field_sql('SELECT MAX(id) FROM {local_clonecategory_jobs}');
                if (!self::can_rollback_job($job, $latest)) {
                    throw new \moodle_exception('rollbackunavailable', 'local_clonecategory');
                }
                $items = $DB->get_records('local_clonecategory_items', ['jobid' => $jobid], 'id DESC');
                $owned = ['course' => [], 'category' => []];
                foreach ($items as $item) {
                    $owned[$item->itemtype][] = (int)$item->itemid;
                }
                foreach ($items as $item) {
                    $table = $item->itemtype === 'course' ? 'course' : 'course_categories';
                    if (!$DB->record_exists($table, ['id' => $item->itemid])) {
                        continue;
                    }
                    if (
                        !self::matches_item_fingerprint($item, $job)
                    ) {
                        throw new \moodle_exception('rollbackmodified', 'local_clonecategory');
                    }
                    if ($item->itemtype === 'course') {
                        require_capability('moodle/course:delete', \context_course::instance($item->itemid));
                    } else {
                        require_capability('moodle/category:manage', \context_coursecat::instance($item->itemid));
                        foreach ($DB->get_records('course', ['category' => $item->itemid], '', 'id') as $course) {
                            if (!in_array((int)$course->id, $owned['course'], true)) {
                                throw new \moodle_exception('rollbackmodified', 'local_clonecategory');
                            }
                        }
                        foreach ($DB->get_records('course_categories', ['parent' => $item->itemid], '', 'id') as $cat) {
                            if (!in_array((int)$cat->id, $owned['category'], true)) {
                                throw new \moodle_exception('rollbackmodified', 'local_clonecategory');
                            }
                        }
                    }
                }
                self::set_status($jobid, self::STATUS_ROLLING_BACK, get_string('job_rolling_back', 'local_clonecategory'));
                // Courses first, then leaf categories. Never recursively delete an occupied category.
                foreach ($items as $item) {
                    $table = $item->itemtype === 'course' ? 'course' : 'course_categories';
                    if (
                        $DB->record_exists($table, ['id' => $item->itemid]) &&
                            !self::matches_item_fingerprint($item, $job)
                    ) {
                        throw new \moodle_exception('rollbackmodified', 'local_clonecategory');
                    }
                    if ($item->itemtype === 'course' && $DB->record_exists('course', ['id' => $item->itemid])) {
                        self::delete_owned_course((int)$item->itemid);
                    } else if (
                        $item->itemtype === 'category' &&
                            $DB->record_exists('course_categories', ['id' => $item->itemid])
                    ) {
                        if (
                            $DB->record_exists('course', ['category' => $item->itemid]) ||
                                $DB->record_exists('course_categories', ['parent' => $item->itemid])
                        ) {
                            throw new \moodle_exception('rollbackmodified', 'local_clonecategory');
                        }
                        \core_course_category::get($item->itemid)->delete_full(false);
                        if ($DB->record_exists('course_categories', ['id' => $item->itemid])) {
                            throw new \moodle_exception('rollbackfailed', 'local_clonecategory');
                        }
                    }
                    $DB->delete_records('local_clonecategory_items', ['id' => $item->id]);
                }
                self::set_status($jobid, self::STATUS_ROLLED_BACK, get_string('job_rolled_back_success', 'local_clonecategory'));
            } catch (\Throwable $e) {
                if (
                    isset($job) && $DB->get_field('local_clonecategory_jobs', 'status', ['id' => $jobid]) ===
                        self::STATUS_ROLLING_BACK
                ) {
                    self::set_status($jobid, self::STATUS_ROLLBACK_FAILED, get_string('rollbackfailed', 'local_clonecategory'));
                }
                throw $e;
            } finally {
                $lock->release();
            }
        } finally {
            if ($worker) {
                $worker->release();
            }
            $global->release();
        }
        return true;
    }

    /**
     * Delete the audit record of an inactive job.
     *
     * @param int $jobid Job
     * @return bool Deleted
     */
    public static function delete_job(int $jobid): bool {
        global $DB;
        $lock = self::lock('job_' . $jobid);
        try {
            $job = self::require_job($jobid);
            if (in_array($job->status, self::active_states(), true)) {
                throw new \moodle_exception('cannotdeleteactive', 'local_clonecategory');
            }
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records('local_clonecategory_items', ['jobid' => $jobid]);
            $DB->delete_records('local_clonecategory_jobs', ['id' => $jobid]);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        return true;
    }

    /**
     * Count the source resources for progress reporting.
     *
     * @param int $sourcecatid Source
     * @return array Counts
     */
    public static function count_category_tree(int $sourcecatid): array {
        $category = \core_course_category::get($sourcecatid);
        $counts = ['categories' => 1, 'courses' => count($category->get_courses(['limit' => 0]))];
        foreach ($category->get_children() as $child) {
            $subcounts = self::count_category_tree($child->id);
            $counts['categories'] += $subcounts['categories'];
            $counts['courses'] += $subcounts['courses'];
        }
        return $counts;
    }
}

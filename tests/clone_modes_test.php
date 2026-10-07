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
 * Tests for clone scopes and safe destination selection.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory;

/**
 * Clone scope integration tests.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_clonecategory\manager
 * @covers     \local_clonecategory\task\clone_category_task
 */
final class clone_modes_test extends \advanced_testcase {
    /**
     * Flush real buffered logs before PHPUnit resets the database between tests.
     */
    public function tearDown(): void {
        get_log_manager(true);
        parent::tearDown();
    }

    /**
     * Tree labels retain their own names without repeating ancestor paths.
     */
    public function test_category_options_use_tree_labels(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $root = $this->getDataGenerator()->create_category(['name' => 'Root category']);
        $child = $this->getDataGenerator()->create_category(['name' => 'Child category', 'parent' => $root->id]);
        $leaf = $this->getDataGenerator()->create_category(['name' => 'Leaf category', 'parent' => $child->id]);
        $options = manager::get_category_options();
        $this->assertSame('└─ Root category', $options[$root->id]);
        $this->assertSame('│ └─ Child category', $options[$child->id]);
        $this->assertSame('│ │ └─ Leaf category', $options[$leaf->id]);
    }

    /**
     * Categories-only mode must never create courses.
     */
    public function test_categories_only_excludes_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $source->id]);
        $this->getDataGenerator()->create_course(['category' => $child->id]);
        $jobid = manager::create_job($source->id, 0, ' copy', ' copy', get_admin()->id, manager::MODE_CATEGORIES);
        $task = new \local_clonecategory\task\clone_category_task();
        $task->set_custom_data(['jobid' => $jobid]);
        $this->expectOutputRegex('/Cloning category/');
        $task->execute();
        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid], '*', MUST_EXIST);
        $this->assertSame(manager::STATUS_COMPLETED, $job->status);
        $this->assertEquals(2, $job->categoriescount);
        $this->assertEquals(0, $job->totalcourses);
        $this->assertEquals(0, $job->coursescount);
        $this->assertEquals(0, $DB->count_records(
            'local_clonecategory_items',
            ['jobid' => $jobid, 'itemtype' => 'course']
        ));
    }

    /**
     * Settings mode copies general settings without activities or summary content.
     */
    public function test_settings_mode_creates_empty_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course([
            'category' => $source->id, 'showgrades' => 0, 'groupmode' => 1,
            'summary' => 'Source content that must not be copied',
        ]);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $jobid = manager::create_job($source->id, 0, ' copy', ' empty', get_admin()->id, manager::MODE_SETTINGS);
        $task = new \local_clonecategory\task\clone_category_task();
        $task->set_custom_data(['jobid' => $jobid]);
        $this->expectOutputRegex('/Cloning category/');
        $task->execute();
        $item = $DB->get_record(
            'local_clonecategory_items',
            ['jobid' => $jobid, 'itemtype' => 'course'],
            '*',
            MUST_EXIST
        );
        $copy = $DB->get_record('course', ['id' => $item->itemid], '*', MUST_EXIST);
        $this->assertSame($course->fullname . ' empty', $copy->fullname);
        $this->assertEquals(0, $copy->showgrades);
        $this->assertEquals(1, $copy->groupmode);
        $this->assertSame('', $copy->summary);
        $this->assertEquals(0, $DB->count_records('course_modules', ['course' => $copy->id]));
        $this->assertEquals(0, $DB->count_records('user_enrolments', ['userid' => get_admin()->id]));
        // Ordinary viewing must not prevent rollback of the empty copy.
        course_view(\context_course::instance($copy->id));
        get_fast_modinfo($copy->id);
        $this->assertSame($item->fingerprint, manager::fingerprint('course', (int)$copy->id));
        manager::rollback_job($jobid);
        $this->assertFalse($DB->record_exists('course', ['id' => $copy->id]));
    }

    /**
     * Buffered creation logs must be included before a new job records its baseline.
     */
    public function test_settings_rollback_after_log_shutdown_and_view(): void {
        global $DB;
        [$jobid, $item] = $this->create_buffered_settings_job();
        $this->assertGreaterThan(0, $DB->count_records('logstore_standard_log', ['courseid' => $item->itemid]));
        get_log_manager(true); // Simulate the end of the worker's process.
        course_view(\context_course::instance($item->itemid));
        get_fast_modinfo($item->itemid);
        $this->assertSame($item->fingerprint, manager::fingerprint('course', (int)$item->itemid));
        manager::rollback_job($jobid);
        $this->assertFalse($DB->record_exists('course', ['id' => $item->itemid]));
    }

    /**
     * A legacy baseline missing all creation logs remains verifiable without refreshing it.
     */
    public function test_legacy_empty_course_baseline_allows_late_creation_logs(): void {
        global $DB;
        [$jobid, $item] = $this->create_buffered_settings_job();
        $this->set_legacy_log_baseline($item, 0);
        course_view(\context_course::instance($item->itemid));
        manager::rollback_job($jobid);
        $this->assertFalse($DB->record_exists('course', ['id' => $item->itemid]));
    }

    /**
     * The buffer may have persisted a prefix before the legacy snapshot was recorded.
     */
    public function test_legacy_empty_course_baseline_with_partial_creation_logs(): void {
        global $DB;
        [$jobid, $item] = $this->create_buffered_settings_job();
        $this->set_legacy_log_baseline($item, 1);
        manager::rollback_job($jobid);
        $this->assertFalse($DB->record_exists('course', ['id' => $item->itemid]));
    }

    /**
     * Legacy recovery must still detect a mutation even if the public name is restored.
     */
    public function test_legacy_baseline_preserves_later_mutation_logs(): void {
        global $DB;
        [$jobid, $item] = $this->create_buffered_settings_job();
        $this->set_legacy_log_baseline($item, 0);
        $course = $DB->get_record('course', ['id' => $item->itemid], '*', MUST_EXIST);
        update_course((object)['id' => $course->id, 'fullname' => $course->fullname . ' edited']);
        update_course((object)['id' => $course->id, 'fullname' => $course->fullname]);
        try {
            manager::rollback_job($jobid);
            $this->fail('A mutation after cloning must block rollback.');
        } catch (\moodle_exception $e) {
            $this->assertSame('rollbackmodified', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('course', ['id' => $item->itemid]));
    }

    /**
     * Missing baselines must never be replaced with a newly trusted fingerprint.
     */
    public function test_legacy_recovery_does_not_accept_missing_baseline(): void {
        global $DB;
        [$jobid, $item] = $this->create_buffered_settings_job();
        $DB->set_field('local_clonecategory_items', 'fingerprint', '', ['id' => $item->id]);
        try {
            manager::rollback_job($jobid);
            $this->fail('An absent baseline must block rollback.');
        } catch (\moodle_exception $e) {
            $this->assertSame('rollbackmodified', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('course', ['id' => $item->itemid]));
    }

    /**
     * Create an empty copy with real standard logging and a large event buffer.
     *
     * @return array Job ID and copied course item
     */
    private function create_buffered_settings_job(): array {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 10000, 'logstore_standard');
        get_log_manager(true);
        $source = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $source->id]);
        $jobid = manager::create_job($source->id, 0, ' copy', ' empty', get_admin()->id, manager::MODE_SETTINGS);
        $task = new \local_clonecategory\task\clone_category_task();
        $task->set_custom_data(['jobid' => $jobid]);
        $this->expectOutputRegex('/Cloning category/');
        $task->execute();
        $item = $DB->get_record('local_clonecategory_items', ['jobid' => $jobid, 'itemtype' => 'course'], '*', MUST_EXIST);
        return [$jobid, $item];
    }

    /**
     * Reproduce the old fingerprint with only a prefix of initial mutation log entries.
     *
     * @param \stdClass $item Copied course item
     * @param int $keep Number of initial logs to retain
     */
    private function set_legacy_log_baseline(\stdClass $item, int $keep): void {
        global $DB;
        $method = new \ReflectionMethod(manager::class, 'fingerprint_data');
        $method->setAccessible(true);
        $data = $method->invoke(null, 'course', (int)$item->itemid);
        $this->assertGreaterThan(1, count($data[13]));
        $data[13] = array_slice($data[13], 0, $keep, true);
        $DB->set_field('local_clonecategory_items', 'fingerprint', hash('sha256', json_encode($data)), ['id' => $item->id]);
    }

    /**
     * A destination inside the source tree must be rejected.
     */
    public function test_descendant_destination_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $source->id]);
        $this->expectException(\moodle_exception::class);
        manager::create_job($source->id, $child->id, '', '', get_admin()->id);
    }

    /**
     * Preserve the full-copy default for existing callers and new jobs.
     */
    public function test_default_mode_is_full(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', get_admin()->id);
        $this->assertSame(
            manager::MODE_FULL,
            $DB->get_field('local_clonecategory_jobs', 'clonemode', ['id' => $jobid])
        );
    }
    /**
     * A new course inside the copied category must block the entire rollback.
     */
    public function test_rollback_preserves_untracked_content(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id, manager::MODE_CATEGORIES);
        $this->expectOutputRegex('/Cloning category/');
        $this->run_job($jobid);
        $item = $DB->get_record('local_clonecategory_items', ['jobid' => $jobid], '*', MUST_EXIST);
        $extra = $this->getDataGenerator()->create_course(['category' => $item->itemid]);
        try {
            manager::rollback_job($jobid);
            $this->fail('Rollback should refuse untracked content.');
        } catch (\moodle_exception $e) {
            $this->assertSame('rollbackmodified', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('course', ['id' => $extra->id]));
        $this->assertTrue($DB->record_exists('course_categories', ['id' => $item->itemid]));
    }

    /**
     * An unchanged tree may be safely rolled back.
     */
    public function test_rollback_deletes_unchanged_owned_items(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $source->id]);
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id, manager::MODE_SETTINGS);
        $this->expectOutputRegex('/Cloning category/');
        $this->run_job($jobid);
        manager::rollback_job($jobid);
        $this->assertSame(manager::STATUS_ROLLED_BACK, $DB->get_field('local_clonecategory_jobs', 'status', ['id' => $jobid]));
        $this->assertEquals(0, $DB->count_records('local_clonecategory_items', ['jobid' => $jobid]));
        $this->assertTrue($DB->record_exists('course_categories', ['id' => $source->id]));
    }

    /**
     * Deleting an active audit record is rejected.
     */
    public function test_active_job_cannot_be_deleted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        $this->expectException(\moodle_exception::class);
        manager::delete_job($jobid);
    }

    /**
     * A shared job lock prevents both rollback and retry while a worker is active.
     */
    public function test_retry_respects_worker_lock(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        manager::pause_job($jobid);
        $lock = manager::lock('job_' . $jobid);
        try {
            manager::resume_job($jobid);
            $this->fail('Retry must not start a second worker.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_lock_failed', $e->errorcode);
        } finally {
            $lock->release();
        }
    }

    /**
     * A cancelled task never starts again when a stale queue entry executes.
     */
    public function test_cancelled_task_is_not_restarted(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        manager::cancel_job($jobid);
        $this->run_job($jobid);
        $this->assertSame(manager::STATUS_CANCELLED, $DB->get_field('local_clonecategory_jobs', 'status', ['id' => $jobid]));
        $this->assertEquals(0, $DB->count_records('local_clonecategory_items', ['jobid' => $jobid]));
    }

    /**
     * A category-scoped manager can clone within the authorised tree but not at the system root.
     */
    public function test_scoped_manager_permissions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $parent = $this->getDataGenerator()->create_category();
        $source = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_coursecat::instance($parent->id);
        foreach (['local/clonecategory:clone', 'moodle/category:manage', 'moodle/course:create'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);
        $jobid = manager::create_job($source->id, $parent->id, '', '', (int)$user->id, manager::MODE_CATEGORIES);
        $this->assertGreaterThan(0, $jobid);
        $this->expectException(\required_capability_exception::class);
        manager::require_clone_access($source->id, 0, manager::MODE_CATEGORIES, (int)$user->id);
    }

    /**
     * Full copy keeps content, excludes learner enrolments, and is idempotent.
     */
    public function test_full_copy_and_stale_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $source->id]);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => 'Original content']);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        $this->expectOutputRegex('/Cloning category/');
        $this->run_job($jobid);
        $item = $DB->get_record('local_clonecategory_items', ['jobid' => $jobid, 'itemtype' => 'course'], '*', MUST_EXIST);
        $this->assertSame($course->fullname, $DB->get_field('course', 'fullname', ['id' => $item->itemid]));
        $this->assertEquals(1, $DB->count_records('page', ['course' => $item->itemid]));
        $this->assertEquals(0, $DB->count_records_sql('SELECT COUNT(ue.id) FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = ?', [$item->itemid]));
        $this->run_job($jobid);
        $this->assertEquals(1, $DB->count_records('local_clonecategory_items', ['jobid' => $jobid, 'itemtype' => 'course']));
        manager::rollback_job($jobid);
        $this->assertFalse($DB->record_exists('course', ['id' => $item->itemid]));
    }

    /**
     * Failed restores remain tracked, and a retry creates exactly one final copy.
     */
    public function test_failed_restore_is_tracked_and_retried(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $source->id]);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        $task = new class extends \local_clonecategory\task\clone_category_task {
            /**
             * Inject a restore failure.
             *
             * @param \restore_controller $controller Restore controller
             */
            protected function restore_course(\restore_controller $controller): void {
                throw new \moodle_exception('restoreprecheckfailed', 'local_clonecategory');
            }
        };
        $task->set_custom_data(['jobid' => $jobid]);
        $this->expectOutputRegex('/Clone job/');
        try {
            $task->execute();
            $this->fail('Injected restore error must be raised.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restoreprecheckfailed', $e->errorcode);
        }
        $item = $DB->get_record('local_clonecategory_items', ['jobid' => $jobid, 'itemtype' => 'course'], '*', MUST_EXIST);
        $this->assertSame('failed', $item->status);
        $failedid = $item->itemid;
        manager::resume_job($jobid);
        $this->run_job($jobid);
        $this->assertFalse($DB->record_exists('course', ['id' => $failedid]));
        $this->assertEquals(1, $DB->count_records('local_clonecategory_items', ['jobid' => $jobid, 'itemtype' => 'course']));
    }

    /**
     * A later edit to a copied course blocks rollback without deleting its category.
     */
    public function test_rollback_preserves_a_modified_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $source->id]);
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id, manager::MODE_SETTINGS);
        $this->expectOutputRegex('/Cloning category/');
        $this->run_job($jobid);
        $item = $DB->get_record('local_clonecategory_items', ['jobid' => $jobid, 'itemtype' => 'course'], '*', MUST_EXIST);
        $DB->set_field('course', 'fullname', 'Edited after copying', ['id' => $item->itemid]);
        try {
            manager::rollback_job($jobid);
            $this->fail('Changed content must be preserved.');
        } catch (\moodle_exception $e) {
            $this->assertSame('rollbackmodified', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('course', ['id' => $item->itemid]));
        $this->assertEquals(2, $DB->count_records('local_clonecategory_items', ['jobid' => $jobid]));
    }

    /**
     * Other users cannot inspect or control a job without the administrative capability.
     */
    public function test_job_access_is_scoped_to_its_owner(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame([], manager::get_visible_jobs());
        $this->expectException(\moodle_exception::class);
        manager::cancel_job($jobid);
    }

    /**
     * Invalid mode values are rejected before any job is created.
     */
    public function test_invalid_mode_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $this->expectException(\moodle_exception::class);
        manager::create_job($source->id, 0, '', '', (int)get_admin()->id, 'invalid');
    }

    /**
     * Revoking the owner's permissions before cron prevents any resource creation.
     */
    public function test_worker_rechecks_revoked_permissions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $parent = $this->getDataGenerator()->create_category();
        $source = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_coursecat::instance($parent->id);
        foreach (['local/clonecategory:clone', 'moodle/category:manage'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);
        $jobid = manager::create_job($source->id, $parent->id, '', '', (int)$user->id, manager::MODE_CATEGORIES);
        role_unassign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->expectOutputRegex('/Clone job/');
        try {
            $this->run_job($jobid);
            $this->fail('Queued work must honour permission revocation.');
        } catch (\required_capability_exception $e) {
            $this->assertSame(
                manager::STATUS_FAILED,
                $DB->get_field('local_clonecategory_jobs', 'status', ['id' => $jobid])
            );
        }
        $this->assertEquals(0, $DB->count_records('local_clonecategory_items', ['jobid' => $jobid]));
    }

    /**
     * A cancelled worker still reserves the slot until its current restore finishes.
     */
    public function test_cancelled_worker_blocks_a_new_start(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        manager::cancel_job($jobid);
        $lock = manager::lock('worker');
        try {
            manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
            $this->fail('An interrupted restore must retain its global operation slot.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_lock_failed', $e->errorcode);
        } finally {
            $lock->release();
        }
        $this->assertEquals(1, $DB->count_records('local_clonecategory_jobs'));
    }

    /**
     * An incomplete rollback cannot be accidentally restarted as a cloning operation.
     */
    public function test_incomplete_rollback_cannot_resume_cloning(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id, manager::MODE_CATEGORIES);
        $this->expectOutputRegex('/Cloning category/');
        $this->run_job($jobid);
        manager::set_status($jobid, manager::STATUS_ROLLBACK_FAILED, 'Review required');
        $job = $DB->get_record('local_clonecategory_jobs', ['id' => $jobid], '*', MUST_EXIST);
        $this->assertTrue(manager::can_rollback_job($job, $jobid));
        $this->expectException(\moodle_exception::class);
        manager::resume_job($jobid);
    }

    /**
     * Execute a job in the integration test.
     *
     * @param int $jobid Execute a test job
     */
    private function run_job(int $jobid): void {
        $task = new \local_clonecategory\task\clone_category_task();
        $task->set_custom_data(['jobid' => $jobid]);
        $task->execute();
    }
}

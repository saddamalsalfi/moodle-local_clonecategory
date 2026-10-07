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
 * Privacy export, erasure and worker-safety integration tests.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory;

use local_clonecategory\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy data is isolated by user and cannot disappear during an active restore.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_clonecategory\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Context discovery, export and erasure include both job and item tracking.
     */
    public function test_export_and_erase_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $userid = (int)get_admin()->id;
        $jobid = manager::create_job($source->id, 0, '', '', $userid, manager::MODE_CATEGORIES);
        $task = new \local_clonecategory\task\clone_category_task();
        $task->set_custom_data(['jobid' => $jobid]);
        $this->expectOutputRegex('/Cloning category/');
        $task->execute();
        $context = \context_system::instance();
        $contexts = provider::get_contexts_for_userid($userid);
        $this->assertEquals([$context->id], array_values($contexts->get_contextids()));
        $approved = new approved_contextlist(get_admin(), 'local_clonecategory', [$context->id]);
        provider::export_user_data($approved);
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_clonecategory'), (string)$jobid]);
        $this->assertEquals($userid, $data->userid);
        $this->assertCount(1, $data->items);
        $createdid = $data->items[0]->itemid;
        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists('local_clonecategory_jobs', ['id' => $jobid]));
        $this->assertFalse($DB->record_exists('local_clonecategory_items', ['jobid' => $jobid]));
        // Privacy erasure removes personal audit data, not institutional course resources.
        $this->assertTrue($DB->record_exists('course_categories', ['id' => $createdid]));
    }

    /**
     * An unapproved user is never included in a user's export or deletion.
     */
    public function test_bulk_erasure_is_scoped_to_approved_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        $other = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        provider::delete_data_for_users(new approved_userlist($context, 'local_clonecategory', [$other->id]));
        $this->assertTrue($DB->record_exists('local_clonecategory_jobs', ['id' => $jobid]));
        $users = new userlist($context, 'local_clonecategory');
        provider::get_users_in_context($users);
        $this->assertContains((int)get_admin()->id, $users->get_userids());
    }

    /**
     * A busy privacy erasure cancels work but preserves tracking until the worker stops.
     */
    public function test_erasure_refuses_to_delete_a_busy_job(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_category();
        $jobid = manager::create_job($source->id, 0, '', '', (int)get_admin()->id);
        $lock = manager::lock('job_' . $jobid);
        try {
            provider::delete_data_for_all_users_in_context(\context_system::instance());
            $this->fail('Busy worker tracking must be retained.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_lock_failed', $e->errorcode);
        } finally {
            $lock->release();
        }
        $this->assertTrue($DB->record_exists('local_clonecategory_jobs', ['id' => $jobid]));
        $this->assertSame(manager::STATUS_CANCELLED, $DB->get_field('local_clonecategory_jobs', 'status', ['id' => $jobid]));
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertFalse($DB->record_exists('local_clonecategory_jobs', ['id' => $jobid]));
    }
}

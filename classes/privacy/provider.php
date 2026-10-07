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
 * Privacy Subsystem implementation for local_clonecategory.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use local_clonecategory\manager;

/**
 * Metadata, export and erasure of job audit data.
 * Moodle core remains responsible for the created course resources and its task logs.
 * @copyright 2026 Saddam Al-Salfi
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe personal data in audit tables.
     *
     * @param collection $collection Metadata
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $jobs = [];
        foreach (
            ['userid', 'sourcecategoryid', 'targetparentid', 'clonemode', 'categorysuffix', 'coursesuffix',
                'status', 'categoriescount', 'coursescount', 'totalcategories', 'totalcourses', 'progress',
                'currentstep', 'timecreated', 'timemodified', 'timefinished'] as $field
        ) {
            $jobs[$field] = 'privacy:metadata:jobs:' . $field;
        }
        $items = [];
        foreach (['jobid', 'itemtype', 'itemid', 'sourceid', 'status', 'timecreated', 'fingerprint'] as $field) {
            $items[$field] = 'privacy:metadata:items:' . $field;
        }
        $collection->add_database_table('local_clonecategory_jobs', $jobs, 'privacy:metadata:local_clonecategory_jobs');
        $collection->add_database_table('local_clonecategory_items', $items, 'privacy:metadata:items');
        return $collection;
    }

    /**
     * Find contexts containing audit data for a user.
     *
     * @param int $userid User
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if ($DB->record_exists('local_clonecategory_jobs', ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Find users with audit data in the system context.
     *
     * @param userlist $userlist Requested context
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            $userlist->add_from_sql('userid', 'SELECT DISTINCT userid FROM {local_clonecategory_jobs} WHERE userid > 0', []);
        }
    }

    /**
     * Export authorised job and item audit data.
     *
     * @param approved_contextlist $contextlist Approved user and contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(\context_system::instance()->id, $contextlist->get_contextids())) {
            return;
        }
        $writer = writer::with_context(\context_system::instance());
        foreach ($DB->get_records('local_clonecategory_jobs', ['userid' => $contextlist->get_user()->id]) as $job) {
            $data = clone $job;
            $data->items = array_values($DB->get_records('local_clonecategory_items', ['jobid' => $job->id], 'id'));
            $writer->export_data([get_string('pluginname', 'local_clonecategory'), (string)$job->id], $data);
        }
    }

    /**
     * Erase audit data for all users in an approved context.
     *
     * @param \context $context Approved context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            self::purge_jobs(null);
        }
    }

    /**
     * Erase audit data for an approved user.
     *
     * @param approved_contextlist $contextlist Approved user and contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (in_array(\context_system::instance()->id, $contextlist->get_contextids())) {
            self::purge_jobs([(int)$contextlist->get_user()->id]);
        }
    }

    /**
     * Erase audit data for approved users.
     *
     * @param approved_userlist $userlist Approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel === CONTEXT_SYSTEM) {
            self::purge_jobs($userlist->get_userids());
        }
    }

    /**
     * Cancel jobs before erasure, and never erase tracking while a worker owns its lock.
     * A busy erasure fails explicitly and can be retried after the worker stops.
     * @param array|null $userids Users, or null for all users
     */
    private static function purge_jobs(?array $userids): void {
        global $DB;
        if ($userids === []) {
            return;
        }
        $global = manager::lock('create');
        $locks = [];
        try {
            if ($userids === null) {
                $jobs = $DB->get_records('local_clonecategory_jobs', null, 'id');
            } else {
                $jobs = $DB->get_records_list('local_clonecategory_jobs', 'userid', $userids, 'id');
            }
            foreach ($jobs as $job) {
                if (in_array($job->status, manager::active_states(), true)) {
                    manager::set_status(
                        (int)$job->id,
                        manager::STATUS_CANCELLED,
                        get_string('job_cancelled', 'local_clonecategory')
                    );
                }
                $locks[] = manager::lock('job_' . $job->id);
            }
            $transaction = $DB->start_delegated_transaction();
            foreach ($jobs as $job) {
                $DB->delete_records('local_clonecategory_items', ['jobid' => $job->id]);
                $DB->delete_records('local_clonecategory_jobs', ['id' => $job->id]);
            }
            $transaction->allow_commit();
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
            $global->release();
        }
    }
}

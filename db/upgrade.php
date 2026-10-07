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
 * Custom upgrade function for local_clonecategory.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom upgrade function for local_clonecategory.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_clonecategory_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026090200) {
        // Define table local_clonecategory_jobs to be created.
        $table = new xmldb_table('local_clonecategory_jobs');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcecategoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targetparentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('categorysuffix', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('coursesuffix', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('categoriescount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('coursescount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('totalcategories', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('totalcourses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('progress', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('currentstep', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_clonecategory_items to be created.
        $tableitems = new xmldb_table('local_clonecategory_items');

        $tableitems->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tableitems->add_field('jobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tableitems->add_field('itemtype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $tableitems->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tableitems->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tableitems->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'completed');
        $tableitems->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $tableitems->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $tableitems->add_key('jobid_fk', XMLDB_KEY_FOREIGN, ['jobid'], 'local_clonecategory_jobs', ['id']);

        if (!$dbman->table_exists($tableitems)) {
            $dbman->create_table($tableitems);
        }

        upgrade_plugin_savepoint(true, 2026090200, 'local', 'clonecategory');
    }

    if ($oldversion < 2026090202) {
        $table = new xmldb_table('local_clonecategory_jobs');
        $index = new xmldb_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if ($dbman->table_exists($table)) {
            $indexes = $DB->get_indexes('local_clonecategory_jobs');
            $existingindexname = $dbman->find_index_name($table, $index);

            // If an index on status exists but was created as unique, drop and recreate it as non-unique.
            if ($existingindexname && !empty($indexes[$existingindexname]['unique'])) {
                $oldindex = new xmldb_index($existingindexname, XMLDB_INDEX_UNIQUE, ['status']);
                $dbman->drop_index($table, $oldindex);
                $existingindexname = false;
            }

            if (!$existingindexname) {
                $dbman->add_index($table, $index);
            }
        }

        upgrade_plugin_savepoint(true, 2026090202, 'local', 'clonecategory');
    }

    if ($oldversion < 2026100700) {
        $table = new xmldb_table('local_clonecategory_jobs');
        $field = new xmldb_field('clonemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'full');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026100700, 'local', 'clonecategory');
    }

    if ($oldversion < 2026100703) {
        $table = new xmldb_table('local_clonecategory_jobs');
        $field = new xmldb_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_clonecategory_items');
        $field = new xmldb_field('fingerprint', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Never invent snapshots for old resources: their original state is unknown.
        upgrade_plugin_savepoint(true, 2026100703, 'local', 'clonecategory');
    }

    if ($oldversion < 2026100705) {
        // Stable release metadata only; no resource or schema changes are required.
        upgrade_plugin_savepoint(true, 2026100705, 'local', 'clonecategory');
    }

    return true;
}

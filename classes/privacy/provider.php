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
use core_privacy\local\metadata\provider as metadata_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for local_clonecategory.
 *
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider {

    /**
     * Return metadata for the plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_clonecategory_jobs',
            [
                'userid' => 'privacy:metadata:local_clonecategory_jobs:userid',
                'sourcecategoryid' => 'privacy:metadata:local_clonecategory_jobs:sourcecategoryid',
                'targetparentid' => 'privacy:metadata:local_clonecategory_jobs:targetparentid',
                'timecreated' => 'privacy:metadata:local_clonecategory_jobs:timecreated',
            ],
            'privacy:metadata:local_clonecategory_jobs'
        );

        return $collection;
    }
}

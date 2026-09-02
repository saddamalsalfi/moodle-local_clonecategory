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
 * Library of functions for local_clonecategory.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the category navigation tree to add clone category option.
 *
 * @param global_navigation $navigation
 * @param context $context
 */
function local_clonecategory_extend_navigation_category_settings($navigation, $context) {
    global $PAGE;
    if (has_capability('moodle/category:manage', $context)) {
        $url = new moodle_url('/local/clonecategory/index.php', array('categoryid' => $context->instanceid));
        $node = navigation_node::create(
            get_string('clonecategory', 'local_clonecategory'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'clonecategory',
            new pix_icon('t/copy', 'moodle')
        );
        $navigation->add_node($node);
    }
}

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
 * Form definition for cloning category.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Category clone form.
 *
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clone_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;
        $defaultcategory = $customdata['categoryid'] ?? 0;

        $categories = \core_course_category::make_categories_list();

        $mform->addElement('select', 'sourcecategory', get_string('sourcecategory', 'local_clonecategory'), $categories);
        $mform->addRule('sourcecategory', null, 'required', null, 'client');
        if ($defaultcategory) {
            $mform->setDefault('sourcecategory', $defaultcategory);
        }

        $targetcategories = [0 => get_string('top')] + $categories;
        $mform->addElement('select', 'targetcategory', get_string('targetcategory', 'local_clonecategory'), $targetcategories);
        $mform->addHelpButton('targetcategory', 'targetcategory', 'local_clonecategory');

        $mform->addElement('text', 'categorysuffix', get_string('categorysuffix', 'local_clonecategory'));
        $mform->setType('categorysuffix', PARAM_TEXT);
        $mform->setDefault('categorysuffix', get_string('default_suffix', 'local_clonecategory'));
        $mform->addHelpButton('categorysuffix', 'categorysuffix', 'local_clonecategory');

        $mform->addElement('text', 'coursesuffix', get_string('coursesuffix', 'local_clonecategory'));
        $mform->setType('coursesuffix', PARAM_TEXT);
        $mform->setDefault('coursesuffix', get_string('default_suffix', 'local_clonecategory'));
        $mform->addHelpButton('coursesuffix', 'coursesuffix', 'local_clonecategory');

        $this->add_action_buttons(true, get_string('clone_button', 'local_clonecategory'));
    }
}

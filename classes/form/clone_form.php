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
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory\form;

use local_clonecategory\manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Category clone form.
 *
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clone_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;
        $mform = $this->_form;
        $customdata = $this->_customdata;
        $defaultcategory = $customdata['categoryid'] ?? 0;

        $hasactive = manager::has_active_job();
        if ($hasactive) {
            $mform->addElement('html', \html_writer::div(
                get_string('active_job_warning', 'local_clonecategory'),
                'alert alert-warning font-weight-bold mb-3'
            ));
        }

        $categories = manager::get_category_options(true);

        $mform->addElement('header', 'scopeheader', get_string('scopeheading', 'local_clonecategory'));
        $modes = [];
        foreach ([manager::MODE_CATEGORIES, manager::MODE_SETTINGS, manager::MODE_FULL] as $mode) {
            $modes[] = $mform->createElement(
                'radio',
                'clonemode',
                '',
                get_string('mode_' . $mode, 'local_clonecategory'),
                $mode
            );
        }
        $mform->addGroup($modes, 'clonemodegroup', get_string('clonemode', 'local_clonecategory'), '<br>', false);
        $mform->setDefault('clonemode', manager::MODE_FULL);
        $mform->addHelpButton('clonemodegroup', 'clonemode', 'local_clonecategory');
        $mform->addElement('static', 'modenote', '', get_string('modenote', 'local_clonecategory'));
        $mform->addElement('header', 'locationheader', get_string('locationheading', 'local_clonecategory'));
        $mform->addElement(
            'select',
            'sourcecategory',
            get_string('sourcecategory', 'local_clonecategory'),
            ['' => get_string('choosecategory', 'local_clonecategory')] + $categories
        );
        $mform->addRule('sourcecategory', null, 'required', null, 'client');
        if ($defaultcategory) {
            $mform->setDefault('sourcecategory', $defaultcategory);
        }

        $targetcategories = manager::get_category_options();
        if (has_capability('moodle/category:manage', \context_system::instance())) {
            $targetcategories = [0 => get_string('top')] + $targetcategories;
        }
        $mform->addElement(
            'select',
            'targetcategory',
            get_string('targetcategory', 'local_clonecategory'),
            $targetcategories
        );
        $mform->setDefault('targetcategory', 0);
        $mform->addHelpButton('targetcategory', 'targetcategory', 'local_clonecategory');

        $mform->addElement('header', 'namingheader', get_string('namingheading', 'local_clonecategory'));
        $mform->addElement('text', 'categorysuffix', get_string('categorysuffix', 'local_clonecategory'));
        $mform->setType('categorysuffix', PARAM_TEXT);
        $mform->setDefault('categorysuffix', get_string('default_suffix', 'local_clonecategory'));
        $mform->addHelpButton('categorysuffix', 'categorysuffix', 'local_clonecategory');

        $mform->addElement('text', 'coursesuffix', get_string('coursesuffix', 'local_clonecategory'));
        $mform->setType('coursesuffix', PARAM_TEXT);
        $mform->setDefault('coursesuffix', get_string('default_suffix', 'local_clonecategory'));
        $mform->addHelpButton('coursesuffix', 'coursesuffix', 'local_clonecategory');

        $mform->disabledIf('coursesuffix', 'clonemode', 'eq', manager::MODE_CATEGORIES);

        if ($hasactive) {
            $mform->freeze(['clonemodegroup', 'sourcecategory', 'targetcategory', 'categorysuffix', 'coursesuffix']);
        } else {
            $this->add_action_buttons(true, get_string('clone_button', 'local_clonecategory'));
            $labels = [];
            foreach (
                ['searchcategories', 'sourcecategory', 'targetcategory', 'top',
                    'expandall', 'collapseall', 'nosearchresults', 'singlesourcehint', 'selectedcategory'] as $key
            ) {
                $labels[$key] = get_string($key, $key === 'top' ? 'moodle' : 'local_clonecategory');
            }
            $dataid = 'clonecategory-tree-data';
            $json = json_encode(
                ['nodes' => manager::get_category_tree(), 'labels' => $labels],
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
            $mform->addElement('html', \html_writer::tag(
                'script',
                $json,
                ['type' => 'application/json', 'id' => $dataid]
            ));
            $PAGE->requires->js_call_amd('local_clonecategory/category_tree', 'init', [$dataid]);
        }
    }

    /**
     * Validate the clone scope and destination on the server.
     *
     * @param array $data Submitted values
     * @param array $files Submitted files
     * @return array Validation errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (
            !in_array(
                $data['clonemode'] ?? '',
                [manager::MODE_CATEGORIES, manager::MODE_SETTINGS, manager::MODE_FULL],
                true
            )
        ) {
            $errors['clonemode'] = get_string('invalidclonemode', 'local_clonecategory');
        }
        try {
            manager::validate_destination((int)$data['sourcecategory'], (int)$data['targetcategory']);
        } catch (\moodle_exception $e) {
            $errors['targetcategory'] = get_string('invaliddestination', 'local_clonecategory');
        }
        foreach (['categorysuffix', 'coursesuffix'] as $field) {
            if (\core_text::strlen($data[$field] ?? '') > 255) {
                $errors[$field] = get_string('suffixlength', 'local_clonecategory');
            }
        }
        return $errors;
    }
}

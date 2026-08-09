<?php
namespace local_clonecategory\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class clone_form extends \moodleform
{
    public function definition()
    {
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
        $mform->setDefault('categorysuffix', ' - copy');
        $mform->addHelpButton('categorysuffix', 'categorysuffix', 'local_clonecategory');

        $mform->addElement('text', 'coursesuffix', get_string('coursesuffix', 'local_clonecategory'));
        $mform->setType('coursesuffix', PARAM_TEXT);
        $mform->setDefault('coursesuffix', ' - copy');
        $mform->addHelpButton('coursesuffix', 'coursesuffix', 'local_clonecategory');

        $this->add_action_buttons(true, get_string('clone_button', 'local_clonecategory'));
    }
}

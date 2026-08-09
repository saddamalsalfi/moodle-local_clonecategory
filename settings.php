<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('courses', new admin_externalpage(
        'local_clonecategory',
        get_string('clone_page_title', 'local_clonecategory'),
        new moodle_url('/local/clonecategory/index.php'),
        'moodle/category:manage'
    ));
}

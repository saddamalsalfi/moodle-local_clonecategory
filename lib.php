<?php
defined('MOODLE_INTERNAL') || die();

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

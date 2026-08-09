<?php
namespace local_clonecategory\task;
defined('MOODLE_INTERNAL') || die();

class clone_category_task extends \core\task\adhoc_task {
    public function execute() {
        global $CFG;
        
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');
        
        $data = $this->get_custom_data();
        $source_id = $data->source_category_id;
        $target_parent_id = $data->target_parent_id;
        $userid = $data->userid;
        $category_suffix = isset($data->category_suffix) ? $data->category_suffix : ' - نسخ';
        $course_suffix = isset($data->course_suffix) ? $data->course_suffix : ' - نسخ';
        
        mtrace("Starting clone category task from source ID {$source_id} to parent {$target_parent_id}...");
        
        $this->clone_category($source_id, $target_parent_id, $userid, $category_suffix, $course_suffix, true);
        
        mtrace("Clone category task completed successfully.");
    }
    
    private function clone_category($source_cat_id, $target_parent_id, $userid, $category_suffix, $course_suffix, $is_root = true) {
        $sourcecat = \core_course_category::get($source_cat_id);
        
        mtrace("Cloning category: {$sourcecat->name}");
        
        $catdata = new \stdClass();
        $catdata->name = $sourcecat->name;
        if ($is_root && $category_suffix !== '') {
            $catdata->name .= $category_suffix;
        }
        $catdata->parent = $target_parent_id;
        $catdata->description = $sourcecat->description;
        $catdata->descriptionformat = $sourcecat->descriptionformat;
        $catdata->idnumber = ''; // Prevent idnumber collisions
        
        $newcat = \core_course_category::create($catdata);
        
        mtrace("Created new category: {$newcat->name} (ID: {$newcat->id})");
        
        $courses = $sourcecat->get_courses(['limit' => 0]);
        foreach ($courses as $course) {
            $this->clone_course($course, $newcat->id, $userid, $course_suffix);
        }
        
        // Recursively clone subcategories
        $subcats = $sourcecat->get_children();
        foreach ($subcats as $subcat) {
            $this->clone_category($subcat->id, $newcat->id, $userid, $category_suffix, $course_suffix, false);
        }
    }
    
    private function clone_course($course, $targetcategoryid, $userid, $course_suffix) {
        global $CFG;
        
        mtrace("  Cloning course: {$course->fullname}");
        
        // Ensure time and memory limits are expanded
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);
        
        // Backup
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_IMPORT, $userid);
            
        $plan = $bc->get_plan();
        if ($plan->setting_exists('users') && $plan->get_setting('users')->get_value()) {
            $plan->get_setting('users')->set_value(0);
        }
        if ($plan->setting_exists('role_assignments') && $plan->get_setting('role_assignments')->get_value()) {
            $plan->get_setting('role_assignments')->set_value(0);
        }
        if ($plan->setting_exists('enrolments') && $plan->get_setting('enrolments')->get_value()) {
            $plan->get_setting('enrolments')->set_value(0);
        }
        if ($plan->setting_exists('logs') && $plan->get_setting('logs')->get_value()) {
            $plan->get_setting('logs')->set_value(0);
        }
        
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        
        mtrace("    Backup created with ID: {$backupid}");
        
        // Restore
        $newfullname = $course->fullname . $course_suffix;
        $newshortname = $course->shortname . '_' . time();
        $newcourseid = \restore_dbops::create_new_course($newfullname, $newshortname, $targetcategoryid);
        
        $rc = new \restore_controller($backupid, $newcourseid, 
            \backup::INTERACTIVE_NO, \backup::MODE_SAMESITE, $userid, 
            \backup::TARGET_NEW_COURSE);
            
        $rcplan = $rc->get_plan();
        if ($rcplan->setting_exists('users') && $rcplan->get_setting('users')->get_value()) {
            $rcplan->get_setting('users')->set_value(0);
        }
        if ($rcplan->setting_exists('role_assignments') && $rcplan->get_setting('role_assignments')->get_value()) {
            $rcplan->get_setting('role_assignments')->set_value(0);
        }
        if ($rcplan->setting_exists('enrolments') && $rcplan->get_setting('enrolments')->get_value()) {
            $rcplan->get_setting('enrolments')->set_value(0);
        }
        if ($rcplan->setting_exists('logs') && $rcplan->get_setting('logs')->get_value()) {
            $rcplan->get_setting('logs')->set_value(0);
        }
        
        // Ensure course names are correct in the restore plan as well
        if ($rcplan->setting_exists('course_shortname')) {
            $rcplan->get_setting('course_shortname')->set_value($newshortname);
        }
        if ($rcplan->setting_exists('course_fullname')) {
            $rcplan->get_setting('course_fullname')->set_value($newfullname);
        }
        
        $rc->execute_precheck();
        $rc->execute_plan();
        
        $rc->destroy();
        
        mtrace("    Course restored as ID: {$newcourseid}");
        
        // Delete backup temp files
        $tempdir = $CFG->tempdir . '/backup/' . $backupid;
        if (is_dir($tempdir)) {
            fulldelete($tempdir);
        }
    }
}

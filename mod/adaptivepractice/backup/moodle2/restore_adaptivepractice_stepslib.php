<?php
/**
 * Adaptive Practice restore steps
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete adaptivepractice structure for restore, with file and id annotations
 */
class restore_adaptivepractice_activity_structure_step extends restore_activity_structure_step
{

    protected function define_structure()
    {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('adaptivepractice', '/activity/adaptivepractice');
        $paths[] = new restore_path_element('category', '/activity/adaptivepractice/extracategories/category');

        if ($userinfo) {
            $paths[] = new restore_path_element('attempt', '/activity/adaptivepractice/attemptrecords/attempt');
            $paths[] = new restore_path_element('progress', '/activity/adaptivepractice/userprogresses/progress');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_adaptivepractice($data)
    {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Map the main category ID.
        $data->categoryid = $this->get_mappingid('question_category', $data->categoryid);

        $data->timecreated = time();
        $data->timemodified = time();

        // Insert the adaptivepractice record.
        $newid = $DB->insert_record('adaptivepractice', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newid);
    }

    protected function process_category($data)
    {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->adaptivepracticeid = $this->get_new_parentid('adaptivepractice');

        // Map the extra category ID.
        $data->categoryid = $this->get_mappingid('question_category', $data->categoryid);

        $newid = $DB->insert_record('adaptivepractice_categories', $data);
        $this->set_mapping('adaptivepractice_categories', $oldid, $newid);
    }

    protected function process_attempt($data)
    {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->adaptivepracticeid = $this->get_new_parentid('adaptivepractice');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->usageid = $this->get_mappingid('question_usage', $data->usageid);

        $newid = $DB->insert_record('adaptivepractice_attempts', $data);
        $this->set_mapping('adaptivepractice_attempts', $oldid, $newid);
    }

    protected function process_progress($data)
    {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->adaptivepracticeid = $this->get_new_parentid('adaptivepractice');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newid = $DB->insert_record('adaptivepractice_progress', $data);
        $this->set_mapping('adaptivepractice_progress', $oldid, $newid);
    }

    protected function after_execute()
    {
        // Add adaptivepractice related files, no files to restore.
        $this->add_related_files('mod_adaptivepractice', 'intro', null);
    }
}

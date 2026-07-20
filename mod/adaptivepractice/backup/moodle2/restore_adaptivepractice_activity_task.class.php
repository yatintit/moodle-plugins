<?php
/**
 * Adaptive Practice restore task helper
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/adaptivepractice/backup/moodle2/restore_adaptivepractice_stepslib.php');

/**
 * Task to restore adaptivepractice activity
 */
class restore_adaptivepractice_activity_task extends restore_activity_task
{

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings()
    {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps()
    {
        $this->add_step(new restore_adaptivepractice_activity_structure_step('adaptivepractice_structure', 'adaptivepractice.xml'));
    }

    /**
     * Define the decoding rules for links belonging to the activity to be applied
     * in all supported areas.
     */
    static public function define_decode_rules()
    {
        $rules = array();

        $rules[] = new restore_decode_rule('ADAPTIVEPRACTICEVIEWBYID', '/mod/adaptivepractice/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ADAPTIVEPRACTICEREPORT', '/mod/adaptivepractice/report.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ADAPTIVEPRACTICEQUESTIONS', '/mod/adaptivepractice/questions.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ADAPTIVEPRACTICEINDEX', '/mod/adaptivepractice/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be applied
     * in the activity itself.
     */
    static public function define_decode_contents()
    {
        $contents = array();

        $contents[] = new restore_decode_content('adaptivepractice', array('intro'), 'adaptivepractice');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be applied
     * in the activity itself.
     */
    static public function define_restore_log_rules()
    {
        $rules = array();

        $rules[] = new restore_log_rule('adaptivepractice', 'add', 'view.php?id={course_module}', '{adaptivepractice}');
        $rules[] = new restore_log_rule('adaptivepractice', 'update', 'view.php?id={course_module}', '{adaptivepractice}');
        $rules[] = new restore_log_rule('adaptivepractice', 'view', 'view.php?id={course_module}', '{adaptivepractice}');

        return $rules;
    }
}

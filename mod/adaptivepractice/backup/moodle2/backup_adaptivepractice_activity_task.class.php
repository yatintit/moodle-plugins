<?php
/**
 * Adaptive Practice backup task helper
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/adaptivepractice/backup/moodle2/backup_adaptivepractice_stepslib.php');

/**
 * Task to backup adaptivepractice activity
 */
class backup_adaptivepractice_activity_task extends backup_activity_task
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
        $this->add_step(new backup_adaptivepractice_activity_structure_step('adaptivepractice_structure', 'adaptivepractice.xml'));
    }

    /**
     * Code the files to include in the backup (indicated by setting)
     */
    static public function encode_content_links($content)
    {
        global $CFG;

        $base = preg_quote($CFG->wwwroot . '/mod/adaptivepractice', '/');

        // Link to the list of practices.
        $search = "/(" . $base . "\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@ADAPTIVEPRACTICEINDEX*$2@$', $content);

        // Link to practice view by moduleid.
        $search = "/(" . $base . "\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@ADAPTIVEPRACTICEVIEWBYID*$2@$', $content);

        // Link to report by moduleid.
        $search = "/(" . $base . "\/report.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@ADAPTIVEPRACTICEREPORT*$2@$', $content);

        // Link to questions by moduleid.
        $search = "/(" . $base . "\/questions.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@ADAPTIVEPRACTICEQUESTIONS*$2@$', $content);

        return $content;
    }
}

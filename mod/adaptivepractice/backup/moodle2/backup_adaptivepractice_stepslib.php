<?php
/**
 * Adaptive Practice backup steps
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete adaptivepractice structure for backup, with file and id annotations
 */
class backup_adaptivepractice_activity_structure_step extends backup_activity_structure_step
{

    protected function define_structure()
    {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $adaptivepractice = new backup_nested_element('adaptivepractice', array('id'), array(
            'course',
            'name',
            'intro',
            'introformat',
            'questionsperattempt',
            'adaptive',
            'competency_scale',
            'attempts',
            'gradepass',
            'grademethod',
            'questionsource',
            'random_easy',
            'random_medium',
            'random_hard',
            'categoryid',
            'timecreated',
            'timemodified'
        ));

        $extracategories = new backup_nested_element('extracategories');
        $category = new backup_nested_element('category', array('id'), array(
            'categoryid'
        ));

        $attemptrecords = new backup_nested_element('attemptrecords');
        $attempt = new backup_nested_element('attempt', array('id'), array(
            'userid',
            'usageid',
            'status',
            'current_difficulty',
            'score',
            'timefinish',
            'timecreated',
            'timemodified'
        ));

        $userprogresses = new backup_nested_element('userprogresses');
        $progress = new backup_nested_element('progress', array('id'), array(
            'userid',
            'competency',
            'timemodified'
        ));

        // Build the tree.
        $adaptivepractice->add_child($extracategories);
        $extracategories->add_child($category);

        $adaptivepractice->add_child($attemptrecords);
        $attemptrecords->add_child($attempt);

        $adaptivepractice->add_child($userprogresses);
        $userprogresses->add_child($progress);

        // Define sources.
        $adaptivepractice->set_source_table('adaptivepractice', array('id' => backup::VAR_ACTIVITYID));

        $category->set_source_table('adaptivepractice_categories', array('adaptivepracticeid' => backup::VAR_PARENTID));

        if ($userinfo) {
            $attempt->set_source_table('adaptivepractice_attempts', array('adaptivepracticeid' => backup::VAR_PARENTID));
            $progress->set_source_table('adaptivepractice_progress', array('adaptivepracticeid' => backup::VAR_PARENTID));
        }

        // Annotate user ids.
        $attempt->annotate_ids('user', 'userid');
        $progress->annotate_ids('user', 'userid');

        // Annotate question categories.
        $adaptivepractice->annotate_ids('question_category', 'categoryid');
        $category->annotate_ids('question_category', 'categoryid');

        // Annotate question usages.
        $attempt->annotate_ids('question_usage', 'usageid');

        // Define file annotations.
        $adaptivepractice->annotate_files('mod_adaptivepractice', 'intro', 'id');

        // Return the root element (adaptivepractice), wrapped into standard activity structure.
        return $this->prepare_activity_structure($adaptivepractice);
    }
}

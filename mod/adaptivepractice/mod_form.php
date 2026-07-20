<?php
/**
 * Settings form for Adaptive Practice
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/adaptivepractice/lib.php');

use core_question\local\bank\question_edit_contexts;



class mod_adaptivepractice_mod_form extends moodleform_mod
{

    public function definition()
    {
        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $this->standard_intro_elements();

        // Practice settings section.
        $mform->addElement('header', 'practice_settings', get_string('practice_settings', 'mod_adaptivepractice'));

        // Questions per attempt.
        $mform->addElement('text', 'questionsperattempt', get_string('questionsperattempt', 'mod_adaptivepractice'));
        $mform->setType('questionsperattempt', PARAM_INT);
        $mform->setDefault('questionsperattempt', 5);
        $mform->addRule('questionsperattempt', get_string('required'), 'required', null, 'client');

        // Adaptive logic toggle.
        $mform->addElement('selectyesno', 'adaptive', get_string('adaptive_logic', 'mod_adaptivepractice'));
        $mform->setDefault('adaptive', 1);

        // Competency scale.
        $mform->addElement('text', 'competency_scale', get_string('competency_scale', 'mod_adaptivepractice'));
        $mform->setType('competency_scale', PARAM_INT);
        $mform->setDefault('competency_scale', 100);

        // Attempts allowed.
        $options = array(0 => get_string('unlimited'));
        for ($i = 1; $i <= 10; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'attempts', get_string('attempts', 'quiz'), $options);
        $mform->setDefault('attempts', 0);

        // Grading method.
        $methods = array(
            ADAPTIVEPRACTICE_GRADEHIGHEST => get_string('gradehighest', 'quiz'),
            ADAPTIVEPRACTICE_GRADEAVERAGE => get_string('gradeaverage', 'quiz'),
            ADAPTIVEPRACTICE_ATTEMPTFIRST => get_string('attemptfirst', 'quiz'),
            ADAPTIVEPRACTICE_ATTEMPTLAST => get_string('attemptlast', 'quiz')
        );
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'quiz'), $methods);
        $mform->setDefault('grademethod', ADAPTIVEPRACTICE_GRADEHIGHEST);
        $mform->hideIf('grademethod', 'attempts', 'eq', 1);

        // Grade to pass.
        $this->standard_grading_coursemodule_elements();

        // Add standard completion elements.
        $this->standard_coursemodule_elements();

        // Add action buttons.
        $this->add_action_buttons();
    }
}

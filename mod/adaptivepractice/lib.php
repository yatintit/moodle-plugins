<?php
/**
 * Library of functions for Adaptive Practice
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Supports various Moodle features.
 *
 * @param string $feature FEATURE_xx constant for the help it supports.
 * @return mixed Constant or null.
 */
function adaptivepractice_supports($feature)
{
    switch ($feature) {
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return false;
        case FEATURE_CONTROLS_GRADE_VISIBILITY:
            return true;
        default:
            return null;
    }
}

/**
 * Add a new instance of adaptivepractice.
 *
 * @param stdClass $adaptivepractice
 * @return int
 */
function adaptivepractice_add_instance($adaptivepractice)
{
    global $DB;

    $adaptivepractice->timecreated = time();
    $adaptivepractice->timemodified = time();

    if (!isset($adaptivepractice->gradepass)) {
        $adaptivepractice->gradepass = 0;
    }

    $id = $DB->insert_record('adaptivepractice', $adaptivepractice);
    $adaptivepractice->id = $id;

    // Grade to pass.
    if (!empty($adaptivepractice->gradepass)) {
        $params = array('itemname' => $adaptivepractice->name, 'gradepass' => $adaptivepractice->gradepass);
        grade_update('mod_adaptivepractice', $adaptivepractice->course, 'mod', 'adaptivepractice', $id, 0, null, $params);
    }

    adaptivepractice_grade_item_update($adaptivepractice);
    return $id;
}


/**
 * Update an existing instance of adaptivepractice.
 *
 * @param stdClass $adaptivepractice
 * @return bool
 */
function adaptivepractice_update_instance($adaptivepractice)
{
    global $DB;

    $adaptivepractice->timemodified = time();
    $adaptivepractice->id = $adaptivepractice->instance;

    if (!isset($adaptivepractice->gradepass)) {
        $adaptivepractice->gradepass = 0;
    }

    $DB->update_record('adaptivepractice', $adaptivepractice);

    // Grade to pass.
    if (!empty($adaptivepractice->gradepass)) {
        $params = array('itemname' => $adaptivepractice->name, 'gradepass' => $adaptivepractice->gradepass);
        grade_update('mod_adaptivepractice', $adaptivepractice->course, 'mod', 'adaptivepractice', $adaptivepractice->id, 0, null, $params);
    }

    adaptivepractice_grade_item_update($adaptivepractice);
    return true;
}


/**
 * Delete an instance of adaptivepractice.
 *
 * @param int $id
 * @return bool
 */
function adaptivepractice_delete_instance($id)
{
    global $DB;

    if (!$adaptivepractice = $DB->get_record('adaptivepractice', array('id' => $id))) {
        return false;
    }

    // Delete Grade item.
    adaptivepractice_grade_item_delete($adaptivepractice);

    // Delete related attempts and progress.
    $DB->delete_records('adaptivepractice_categories', array('adaptivepracticeid' => $id));
    $DB->delete_records('adaptivepractice_attempts', array('adaptivepracticeid' => $id));
    $DB->delete_records('adaptivepractice_progress', array('adaptivepracticeid' => $id));
    $DB->delete_records('adaptivepractice', array('id' => $id));

    return true;
}

/**
 * Supports getting cm info.
 *
 * @param cm_info $cm
 */
function adaptivepractice_get_coursemodule_info($cm)
{
    global $DB;

    $dbinst = $DB->get_record('adaptivepractice', array('id' => $cm->instance), 'id, name, intro, introformat');
    if (!$dbinst) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $dbinst->name;
    if ($cm->showdescription) {
        $info->content = format_module_intro('adaptivepractice', $dbinst, $cm->id);
    }

    return $info;
}

/**
 * Returns the users with data in one adaptivepractice (for backups).
 *
 * @param int $adaptivepracticeid
 * @return array
 */
function adaptivepractice_get_participants($adaptivepracticeid)
{
    global $DB;

    return $DB->get_records_sql("SELECT DISTINCT userid FROM {adaptivepractice_attempts} WHERE adaptivepracticeid = ?", array($adaptivepracticeid));
}

/**
 * Grading method constants.
 */
define('ADAPTIVEPRACTICE_GRADEHIGHEST', 1);
define('ADAPTIVEPRACTICE_GRADEAVERAGE', 2);
define('ADAPTIVEPRACTICE_ATTEMPTFIRST', 3);
define('ADAPTIVEPRACTICE_ATTEMPTLAST', 4);

/**
 * Question source constants.
 */
define('ADAPTIVEPRACTICE_SOURCE_MANUAL', 0);
define('ADAPTIVEPRACTICE_SOURCE_RANDOM', 1);

/**
 * Update the grade item for this practice.
 *
 * @param stdClass $adaptivepractice
 * @return int
 */
function adaptivepractice_grade_item_update($adaptivepractice)
{
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = array('itemname' => $adaptivepractice->name);
    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademax'] = $adaptivepractice->competency_scale;
    $params['grademin'] = 0;

    return grade_update(
        'mod_adaptivepractice',
        $adaptivepractice->course,
        'mod',
        'adaptivepractice',
        $adaptivepractice->id,
        0,
        null,
        $params
    );
}

/**
 * Delete grade item for given adaptivepractice instance.
 *
 * @param stdClass $adaptivepractice
 * @return int
 */
function adaptivepractice_grade_item_delete($adaptivepractice)
{
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod_adaptivepractice', $adaptivepractice->course, 'mod', 'adaptivepractice',
        $adaptivepractice->id, 0, null, array('deleted' => 1));
}
/**
 * Update grades for a user.
 */
function adaptivepractice_update_grades($adaptivepractice, $userid = 0)
{
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($userid) {
        $params = [$adaptivepractice->id, $userid];
        $sql = "SELECT score FROM {adaptivepractice_attempts}
                WHERE adaptivepracticeid = ? AND userid = ? AND status = 'finished'";

        $attempts = $DB->get_fieldset_sql($sql, $params);

        if (empty($attempts)) {
            $rawgrade = null;
        } else {
            switch ($adaptivepractice->grademethod) {
                case ADAPTIVEPRACTICE_GRADEAVERAGE:
                    $rawgrade = array_sum($attempts) / count($attempts);
                    break;
                case ADAPTIVEPRACTICE_ATTEMPTFIRST:
                    $first = $DB->get_record_sql("SELECT score FROM {adaptivepractice_attempts}
                                                  WHERE adaptivepracticeid = ? AND userid = ? AND status = 'finished'
                                                  ORDER BY id ASC", $params, IGNORE_MULTIPLE);
                    $rawgrade = $first ? $first->score : null;
                    break;
                case ADAPTIVEPRACTICE_ATTEMPTLAST:
                    $last = $DB->get_record_sql("SELECT score FROM {adaptivepractice_attempts}
                                                 WHERE adaptivepracticeid = ? AND userid = ? AND status = 'finished'
                                                 ORDER BY id DESC", $params, IGNORE_MULTIPLE);
                    $rawgrade = $last ? $last->score : null;
                    break;
                case ADAPTIVEPRACTICE_GRADEHIGHEST:
                default:
                    $rawgrade = max($attempts);
                    break;
            }
        }

        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = $rawgrade;
        grade_update(
            'mod_adaptivepractice',
            $adaptivepractice->course,
            'mod',
            'adaptivepractice',
            $adaptivepractice->id,
            0,
            $grade
        );
    }
}

/**
 * Add the "Questions" and "Reports" tab to the module settings navigation.
 *
 * @param settings_navigation $settings The settings navigation object.
 * @param navigation_node $apnode The navigation node to add to.
 */
function adaptivepractice_extend_settings_navigation(settings_navigation $settings, navigation_node $apnode)
{
    global $PAGE;

    $context = $settings->get_page()->cm->context;

    if (has_capability('mod/adaptivepractice:manage', $context)) {
        $cm = $settings->get_page()->cm;

        // Questions tab.
        $url = new moodle_url('/mod/adaptivepractice/questions.php', array('id' => $cm->id));
        $apnode->add(get_string('questions', 'mod_adaptivepractice'), $url, navigation_node::TYPE_SETTING, null, 'questions');

        // Results tab.
        $resultsurl = new moodle_url('/mod/adaptivepractice/report.php', array('id' => $cm->id));
        $apnode->add(get_string('all_results', 'mod_adaptivepractice'), $resultsurl, navigation_node::TYPE_SETTING, null, 'results');

        // Question bank tab.
        $qbankurl = new moodle_url('/question/edit.php', array('cmid' => $cm->id));
        $apnode->add(get_string('questionbank', 'question'), $qbankurl, navigation_node::TYPE_SETTING, null, 'questionbank');
    }
}

/**
 * Obtains the completion state for a user.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @param int $userid
 * @param bool $type
 * @return bool
 */
function adaptivepractice_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    // Return true if they have at least one finished attempt.
    return $DB->record_exists('adaptivepractice_attempts', [
        'adaptivepracticeid' => $cm->instance,
        'userid' => $userid,
        'status' => 'finished'
    ]);
}

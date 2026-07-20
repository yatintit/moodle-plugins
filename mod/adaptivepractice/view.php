<?php
/**
 * View page for Adaptive Practice
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_adaptivepractice\helper;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.

$cm = get_coursemodule_from_id('adaptivepractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$adaptivepractice = $DB->get_record('adaptivepractice', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = \context_module::instance($cm->id);
require_capability('mod/adaptivepractice:view', $context);

// Mark as viewed for completion.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Trigger module viewed event.
$event = \mod_adaptivepractice\event\course_module_viewed::create(array(
    'objectid' => $adaptivepractice->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('adaptivepractice', $adaptivepractice);
$event->trigger();

// Page settings.
$PAGE->set_url('/mod/adaptivepractice/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($adaptivepractice->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('limitedwidth');

// Logic to prepare data for renderable.
$competency = helper::get_user_competency($adaptivepractice->id, $USER->id);
$difficulty = 'easy';
if ($competency >= 70) {
    $difficulty = 'hard';
} else if ($competency >= 40) {
    $difficulty = 'medium';
}

$categoryids = helper::get_category_ids($adaptivepractice->id);
$counts = (object) ['easy' => 0, 'medium' => 0, 'hard' => 0];
$has_questions = false;

if (!empty($categoryids)) {
    try {
        if ($adaptivepractice->questionsource == 1) { // ADAPTIVEPRACTICE_SOURCE_RANDOM
            $counts = (object) [
                'easy' => $adaptivepractice->random_easy,
                'medium' => $adaptivepractice->random_medium,
                'hard' => $adaptivepractice->random_hard
            ];
        } else {
            $counts = helper::get_difficulty_counts($categoryids);
        }
        $has_questions = ($counts->easy > 0 || $counts->medium > 0 || $counts->hard > 0);
    } catch (Exception $e) {
        $has_questions = false;
    }
}

$attempts = $DB->get_records(
    'adaptivepractice_attempts',
    ['adaptivepracticeid' => $adaptivepractice->id, 'userid' => $USER->id],
    'timecreated DESC'
);

$has_inprogress = false;
foreach ($attempts as $att) {
    if ($att->status === 'inprogress') {
        $has_inprogress = true;
        break;
    }
}

$can_start = true;
if ($adaptivepractice->attempts > 0 && count($attempts) >= $adaptivepractice->attempts) {
    if (!$has_inprogress) {
        $can_start = false;
    }
}

$data = [
    'competency' => $competency,
    'difficulty' => $difficulty,
    'counts' => $counts,
    'has_questions' => $has_questions,
    'attempts' => $attempts,
    'has_inprogress' => $has_inprogress,
    'can_start' => $can_start,
    'canmanage' => has_capability('mod/adaptivepractice:manage', $context)
];

$viewpage = new \mod_adaptivepractice\output\view_page($adaptivepractice, $cm, $data);
$renderer = $PAGE->get_renderer('mod_adaptivepractice');

echo $OUTPUT->header();
echo $renderer->render($viewpage);
echo $OUTPUT->footer();

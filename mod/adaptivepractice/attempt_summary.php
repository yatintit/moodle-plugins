<?php
/**
 * Summary/Review page for an Adaptive Practice attempt.
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/questionlib.php');

$id = required_param('id', PARAM_INT); // CM ID.
$attemptid = required_param('attemptid', PARAM_INT);

$cm = get_coursemodule_from_id('adaptivepractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$adaptivepractice = $DB->get_record('adaptivepractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$attempt = $DB->get_record('adaptivepractice_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);

if (!$attempt || $attempt->adaptivepracticeid != $adaptivepractice->id) {
    throw new moodle_exception('invalidattemptid', 'mod_adaptivepractice');
}

// Permission check.
if ($attempt->userid != $USER->id) {
    require_capability('mod/adaptivepractice:manage', $context);
}

// Mark as viewed for completion.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/adaptivepractice/attempt_summary.php', ['id' => $cm->id, 'attemptid' => $attemptid]);
$PAGE->set_title(format_string($adaptivepractice->name) . ': Attempt Review');
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

$quba = question_engine::load_questions_usage_by_activity($attempt->usageid);

// Data for renderable.
$backurl = (has_capability('mod/adaptivepractice:manage', $context) && $attempt->userid != $USER->id)
    ? new moodle_url('/mod/adaptivepractice/report.php', ['id' => $cm->id])
    : new moodle_url('/mod/adaptivepractice/view.php', ['id' => $cm->id]);

$renderable = new \mod_adaptivepractice\output\attempt_summary($attempt, $adaptivepractice, $cm, $quba, [
    'backurl' => $backurl->out(false)
]);
$renderer = $PAGE->get_renderer('mod_adaptivepractice');

echo $OUTPUT->header();
echo $renderer->render($renderable);
echo $OUTPUT->footer();

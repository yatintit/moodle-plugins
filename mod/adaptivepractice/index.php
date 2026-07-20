<?php
/**
 * Index page for Adaptive Practice - lists all sessions in a course.
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);

$PAGE->set_url('/mod/adaptivepractice/index.php', array('id' => $id));
$PAGE->set_title(format_string($course->fullname) . ': ' . get_string('modulenameplural', 'mod_adaptivepractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

$strname = get_string('name');
$strintro = get_string('intro', 'mod_adaptivepractice');
$strsessions = get_string('attempts_overview', 'mod_adaptivepractice');

$table = new html_table();
$table->head = array($strname, $strintro, $strsessions);
$table->align = array('left', 'left', 'center');

$modinfo = get_fast_modinfo($course);
if (isset($modinfo->instances['adaptivepractice'])) {
    foreach ($modinfo->instances['adaptivepractice'] as $cm) {
        if (!$cm->uservisible) {
            continue;
        }

        $link = html_writer::link(new moodle_url('/mod/adaptivepractice/view.php', array('id' => $cm->id)), format_string($cm->name));
        $intro = format_module_intro('adaptivepractice', $cm, $cm->id);

        $report_link = '';
        if (has_capability('mod/adaptivepractice:manage', $cm->context)) {
            $report_link = html_writer::link(new moodle_url('/mod/adaptivepractice/report.php', array('id' => $cm->id)), get_string('all_results', 'mod_adaptivepractice'), ['class' => 'btn btn-outline-primary btn-sm']);
        }

        $table->data[] = array($link, $intro, $report_link);
    }
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('no_questions_found', 'mod_adaptivepractice'), 'info');
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

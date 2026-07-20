<?php
/**
 * Report page for Adaptive Practice showing all student attempts.
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyright GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

define('PAGE_SIZE', 20);

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('adaptivepractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$adaptivepractice = $DB->get_record('adaptivepractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/adaptivepractice:manage', $context);

$PAGE->set_url('/mod/adaptivepractice/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($adaptivepractice->name) . ': All Results');
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->activityheader->disable();

// 1. Statistics calculation (Must be done before table init).
$stats_sql = "SELECT 
                COUNT(*) as total_attempts, 
                COUNT(DISTINCT userid) as unique_students,
                (SELECT COUNT(*) FROM {adaptivepractice_attempts} WHERE adaptivepracticeid = :apid2 AND status = 'finished') as total_finished
              FROM {adaptivepractice_attempts} 
              WHERE adaptivepracticeid = :apid";
$raw_stats = $DB->get_record_sql($stats_sql, ['apid' => $adaptivepractice->id, 'apid2' => $adaptivepractice->id]);

// Calculate average based on grading method using optimized query where possible.
$avg_score = 0;
if ($raw_stats->total_finished > 0) {
    // In Moodle we often calculate the final grade according to the activity settings.
    // For simplicity and performance, we'll average all finished scores for the dashboard average.
    $avg_score = $DB->get_field_sql("SELECT AVG(score) FROM {adaptivepractice_attempts} WHERE adaptivepracticeid = ? AND status = 'finished'", [$adaptivepractice->id]);
}

$stats = [
    'total_attempts' => $raw_stats->total_attempts,
    'unique_students' => $raw_stats->unique_students,
    'avg_score' => round($avg_score, 1),
    'total_finished' => $raw_stats->total_finished
];

// 2. Table initialization.
$table = new \mod_adaptivepractice\table\attempts_table('mod-adaptivepractice-report', $adaptivepractice, [], $cm->id);
$table->is_downloadable(true);

// 3. Handle Download request (Must be handled before any output).
$download = optional_param('download', '', PARAM_ALPHA);
if ($download) {
    $table->is_downloading($download, 'Adaptive_Practice_Report', 'Attempts');
    $table->setup();
    $table->setup_sql_query();
    $table->out(20, true);
    exit;
}

// 4. Handle Delete request.
if (optional_param('delete_selected', '', PARAM_TEXT) && confirm_sesskey()) {
    $attemptids = optional_param_array('attemptids', [], PARAM_INT);
    if (!empty($attemptids)) {
        foreach ($attemptids as $attemptid) {
            \mod_adaptivepractice\helper::delete_attempt($attemptid);
        }
        \core\notification::success(get_string('attempts_deleted', 'mod_adaptivepractice'));
    }
    redirect(new moodle_url('/mod/adaptivepractice/report.php', ['id' => $cm->id]));
}

// 5. Handle Reset request.
if (optional_param('treset', 0, PARAM_INT) || optional_param('tsreset', 0, PARAM_INT)) {
    if (method_exists($table, 'mark_table_to_reset')) {
        $table->mark_table_to_reset();
    }
    $table->setup();
    redirect(new moodle_url('/mod/adaptivepractice/report.php', ['id' => $cm->id]));
}

// 5. Normal HTML setup.
$table->show_download_buttons_at([]);
$table->setup();
$total_rows = $table->setup_sql_query();
$table->pagesize($total_rows > 0 ? PAGE_SIZE : 1, $total_rows);

// 6. Output capturing for template.
ob_start();
$table->print_initials_bar();
$initialbars = ob_get_clean();

ob_start();
$table->query_db(PAGE_SIZE);
$table->build_table();
$table->close_recordset();
$table->finish_output();
$tablehtml = ob_get_clean();

ob_start();
echo $table->download_buttons();
$downloadhtml = ob_get_clean();

// 7. Rendering.
echo $OUTPUT->header();

$data = [
    'id' => $cm->id,
    'backurl' => (new moodle_url('/mod/adaptivepractice/view.php', ['id' => $cm->id]))->out(false),
    'downloadhtml' => $downloadhtml,
    'initialbars' => $initialbars,
    'stats' => $stats,
    'sesskey' => sesskey()
];

$renderable = new \mod_adaptivepractice\output\report_page($adaptivepractice, [], $tablehtml, $data);
$renderer = $PAGE->get_renderer('mod_adaptivepractice');
echo $renderer->render($renderable);

echo $OUTPUT->footer();

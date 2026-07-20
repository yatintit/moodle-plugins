<?php
/**
 * Attempt page for Adaptive Practice — one question at a time with live progress bar.
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/questionlib.php');

use mod_adaptivepractice\helper;

$id = required_param('id', PARAM_INT);       // CM ID.
$finish = optional_param('finish', 0, PARAM_BOOL);
$slot = optional_param('slot', 1, PARAM_INT);  // Which question to show (1-based).

$cm = get_coursemodule_from_id('adaptivepractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$adaptivepractice = $DB->get_record('adaptivepractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/adaptivepractice:submit', $context);

$PAGE->set_url('/mod/adaptivepractice/attempt.php', ['id' => $cm->id, 'slot' => $slot]);
$PAGE->set_title(format_string($adaptivepractice->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// -------------------------------------------------------------------------
// FIND OR CREATE ATTEMPT.
// -------------------------------------------------------------------------
$attempt = $DB->get_record('adaptivepractice_attempts', [
    'adaptivepracticeid' => $adaptivepractice->id,
    'userid' => $USER->id,
    'status' => 'inprogress'
]);

if (!$attempt) {
    if ($finish) {
        redirect(new moodle_url('/mod/adaptivepractice/view.php', ['id' => $id]));
    }

    // Load questions from all assigned categories.
    $categoryids = helper::get_category_ids($adaptivepractice->id);
    $forced_diff = optional_param('force_difficulty', 'mixed', PARAM_ALPHA);

    if ($forced_diff === 'mixed') {
        if ($adaptivepractice->questionsource == ADAPTIVEPRACTICE_SOURCE_RANDOM) {
            $questions = helper::get_defined_random_questions(
                $categoryids,
                $adaptivepractice->random_easy,
                $adaptivepractice->random_medium,
                $adaptivepractice->random_hard
            );
            $difficulty = 'random';
        } else {
            $questions = helper::get_progressive_questions($categoryids, $adaptivepractice->questionsperattempt);
            $difficulty = 'mixed';
        }
    } else {
        $questions = helper::get_questions($categoryids, $forced_diff, $adaptivepractice->questionsperattempt);
        $difficulty = $forced_diff;
    }

    if (empty($questions)) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('no_questions_found', 'mod_adaptivepractice'), 'error');
        echo $OUTPUT->continue_button(new moodle_url('/mod/adaptivepractice/view.php', ['id' => $id]));
        echo $OUTPUT->footer();
        exit;
    }

    // Start Question Engine usage.
    $quba = helper::start_usage($context);
    helper::add_questions_to_usage($quba, $questions);
    \question_engine::save_questions_usage_by_activity($quba);

    // Save attempt record.
    $attempt = new stdClass();
    $attempt->adaptivepracticeid = $adaptivepractice->id;
    $attempt->userid = $USER->id;
    $attempt->usageid = $quba->get_id();
    $attempt->current_difficulty = $difficulty;
    $attempt->status = 'inprogress';
    $attempt->timecreated = time();
    $attempt->timemodified = time();
    $attempt->id = $DB->insert_record('adaptivepractice_attempts', $attempt);
}

$quba = \question_engine::load_questions_usage_by_activity($attempt->usageid);
$slots = $quba->get_slots();
$total = count($slots);

// Clamp slot to valid range.
$slot = max(1, min($slot, $total));

// -------------------------------------------------------------------------
// PROCESS SUBMISSION FOR THE CURRENT SLOT.
// -------------------------------------------------------------------------
$answered_slot = optional_param('answered_slot', 0, PARAM_INT);
$next_slot = $slot;

if (data_submitted() && confirm_sesskey()) {

    if ($finish) {
        // ---- FINISH SESSION ----
        $score = helper::process_attempt($attempt->id);
        redirect(new moodle_url('/mod/adaptivepractice/attempt_summary.php', ['id' => $id, 'attemptid' => $attempt->id]));
    }

    if ($answered_slot >= 1 && $answered_slot <= $total) {
        try {
            $quba->process_all_actions(time());
            \question_engine::save_questions_usage_by_activity($quba);
        } catch (\question_out_of_sequence_exception $e) {
            // Silently absorb the sequence check exception (likely due to double click or browser back button)
            // and redirect to the current slot to show the state.
        }

        // Stay on the same slot so they can see the feedback.
        // We only move forward when the user clicks the "Next Question" button which appears after answering.
        redirect(new moodle_url('/mod/adaptivepractice/attempt.php', ['id' => $id, 'slot' => $slot]));
    }
}

// -------------------------------------------------------------------------
// CALCULATE LIVE PROGRESS.
// -------------------------------------------------------------------------
$correct = 0;
$wrong = 0;
$answered = 0;
foreach ($slots as $s) {
    $state = $quba->get_question_state($s);
    if ($state->is_finished()) {
        $answered++;
        $mark = $quba->get_question_mark($s);
        $max = $quba->get_question_max_mark($s);
        if ($mark !== null && $mark >= $max) {
            $correct++;
        } else if ($mark !== null) {
            $wrong++;
        }
    }
}
$progress_pct = $total > 0 ? round(($answered / $total) * 100) : 0;
$score_pct = $answered > 0 ? round(($correct / $answered) * 100) : 0;

// -------------------------------------------------------------------------
// RENDER PAGE.
// -------------------------------------------------------------------------
echo $OUTPUT->header();

$diff_label = get_string('difficulty_' . $attempt->current_difficulty, 'mod_adaptivepractice');
echo html_writer::start_div('ap-session-container');

// ---- TOP BAR: session info & finish button ----
echo html_writer::start_div('ap-session-header d-flex justify-content-between align-items-center mb-3');
$diff_class = $attempt->current_difficulty;
echo html_writer::tag('h3', get_string('practice_session', 'mod_adaptivepractice') . ': ' . html_writer::tag('span', $diff_label, ['class' => 'ap-difficulty-label ' . $diff_class]), ['class' => 'mb-0']);
$finish_url = new moodle_url('/mod/adaptivepractice/attempt.php', ['id' => $id, 'slot' => $slot]);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $finish_url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'finish', 'value' => '1']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('finish_session', 'mod_adaptivepractice'), 'class' => 'btn btn-outline-danger btn-sm']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// ---- PROGRESS BAR ----
echo html_writer::start_div('ap-progress-section mb-4');

echo html_writer::start_div('d-flex justify-content-between mb-1');
$a = new stdClass();
$a->slot = $slot;
$a->total = $total;
echo html_writer::tag('small', get_string('question_x_of_y', 'mod_adaptivepractice', $a), ['class' => 'text-muted font-weight-bold']);
echo html_writer::tag(
    'small',
    html_writer::tag('span', '✔ ' . get_string('correct_count', 'mod_adaptivepractice', $correct), ['class' => 'text-success font-weight-bold']) . ' &nbsp; ' .
    html_writer::tag('span', '✘ ' . get_string('wrong_count', 'mod_adaptivepractice', $wrong), ['class' => 'text-danger font-weight-bold']),
    ['class' => '']
);
echo html_writer::end_div();

// Overall progress bar.
$color = '#6366f1'; // Mixed/Default Indigo
if ($attempt->current_difficulty === 'easy') {
    $color = '#0FB9B1';
} else if ($attempt->current_difficulty === 'medium') {
    $color = '#FF8A00';
} else if ($attempt->current_difficulty === 'hard') {
    $color = '#6C5CE7';
}

echo html_writer::start_div('progress mb-2', ['style' => 'height:20px; border-radius:10px; position:relative; overflow:hidden; background-color:#e9ecef;']);
echo html_writer::tag('div', $progress_pct . '%', [
    'class' => 'progress-bar',
    'role' => 'progressbar',
    'style' => 'width:' . $progress_pct . '%; background-color:' . $color . '; border-radius:10px; transition: width 0.6s ease; font-weight:bold; font-size: 0.85rem; line-height:20px;',
    'aria-valuenow' => $progress_pct,
    'aria-valuemin' => '0',
    'aria-valuemax' => '100',
]);
echo html_writer::end_div();

// Accuracy bar (correct vs wrong).
if ($answered > 0) {
    $correct_pct = round(($correct / $answered) * 100);
    $wrong_pct = 100 - $correct_pct;
    echo html_writer::start_div('progress mb-3', ['style' => 'height:8px;border-radius:8px;']);
    echo html_writer::tag('div', '', [
        'class' => 'progress-bar bg-success',
        'style' => 'width:' . $correct_pct . '%;'
    ]);
    echo html_writer::tag('div', '', [
        'class' => 'progress-bar bg-danger',
        'style' => 'width:' . $wrong_pct . '%;'
    ]);
    echo html_writer::end_div();
}

echo html_writer::end_div(); // ap-progress-section

// ---- QUESTION CARD ----
$current_slot = $slots[$slot - 1] ?? null;

if ($current_slot !== null) {
    $state = $quba->get_question_state($current_slot);
    $already_answered = $state->is_finished();

    $options = new question_display_options();
    $options->flags = question_display_options::HIDDEN;
    $options->marks = question_display_options::HIDDEN;

    if ($already_answered) {
        $mark = $quba->get_question_mark($current_slot);
        $max = $quba->get_question_max_mark($current_slot);
        $is_correct = ($mark !== null && $mark >= $max);

        $options->correctness = question_display_options::VISIBLE;

        if ($is_correct) {
            $options->rightanswer = question_display_options::HIDDEN;
            $options->feedback = question_display_options::VISIBLE;
            $options->generalfeedback = question_display_options::HIDDEN;
        } else {
            $options->rightanswer = question_display_options::HIDDEN;
            $options->feedback = question_display_options::HIDDEN;
            $options->generalfeedback = question_display_options::HIDDEN;
        }
    } else {
        // Hide feedback while answering.
        $options->feedback = question_display_options::HIDDEN;
        $options->generalfeedback = question_display_options::HIDDEN;
        $options->rightanswer = question_display_options::HIDDEN;
    }

    $action_url = new moodle_url('/mod/adaptivepractice/attempt.php', ['id' => $id]);

    echo html_writer::start_div('ap-question-card card shadow-sm mb-4');
    echo html_writer::start_div('card-body');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $action_url->out(false),
        'id' => 'ap_question_form',
        'class' => 'ap-question-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'slot', 'value' => $slot]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'answered_slot', 'value' => $current_slot]);

    // Render the question.
    echo $quba->render_question($current_slot, $options, $slot);

    // Custom prominent feedback alert below the question.
    if ($already_answered) {
        if ($is_correct) {
            echo html_writer::div(get_string('correct_alert', 'mod_adaptivepractice') . ' ' . get_string('feedback_correct', 'mod_adaptivepractice'), 'alert alert-success mt-1 mb-2 font-weight-bold text-center');
        } else {
            echo html_writer::div(get_string('incorrect_alert', 'mod_adaptivepractice'), 'alert alert-danger mt-1 mb-2 font-weight-bold text-center');

            // Render detailed feedback box for incorrect answers.
            $qa = $quba->get_question_attempt($current_slot);
            $question = $qa->get_question();

            // Combined feedback for incorrect response.
            $combined_feedback = "";
            if (!empty($question->incorrectfeedback)) {
                $combined_feedback = format_text($question->incorrectfeedback, $question->incorrectfeedbackformat, ['context' => $context->id]);
            }

            $explanation = $qa->rewrite_pluginfile_urls($question->generalfeedback, 'question', 'generalfeedback', $question->id);
            $explanation = format_text($explanation, $question->generalfeedbackformat, ['context' => $context->id, 'para' => true]);
            $correct_answer = $qa->get_right_answer_summary();

            echo html_writer::start_div('ap-explanation-box mt-3 mb-3 p-4 shadow-sm');

            // 1. Initial incorrectly-answered message (if any).
            if (!empty($combined_feedback)) {
                echo html_writer::tag('div', $combined_feedback, ['class' => 'mb-3', 'style' => 'line-height:2;']);
            } else {
                echo html_writer::tag('p', get_string('your_answer_is_incorrect', 'mod_adaptivepractice'), ['class' => 'text-muted mb-2']);
            }

            // 2. Explanation / General Feedback.
            if (!empty($explanation)) {
                echo html_writer::tag('div', html_writer::tag('strong', get_string('explanation', 'mod_adaptivepractice') . ': ') . $explanation, ['class' => 'mb-3', 'style' => 'line-height:1.6; color: #856404;']);
            }

            // 3. Correct Answer.
            echo html_writer::tag('p', html_writer::tag('strong', get_string('the_correct_answer_is', 'mod_adaptivepractice')) . $correct_answer, ['class' => 'small mt-2 font-weight-bold', 'style' => 'color: #856404;']);
            echo html_writer::end_div();
        }
    }

    // Buttons.
    echo html_writer::start_div('ap-question-actions d-flex justify-content-between align-items-center mt-4');

    // Previous link.
    if ($slot > 1) {
        $prev_url = new moodle_url('/mod/adaptivepractice/attempt.php', ['id' => $id, 'slot' => $slot - 1]);
        echo html_writer::link($prev_url, get_string('previous', 'mod_adaptivepractice'), ['class' => 'btn btn-outline-secondary btn-sm']);
    } else {
        echo html_writer::tag('span', '');
    }

    if (!$already_answered) {
        // This button proxies the question engine's Check button so process_all_actions()
        // receives the grading signal. The native Check button is visually hidden via CSS.
        echo html_writer::tag('button', get_string('submit_answer', 'mod_adaptivepractice'), [
            'type'    => 'button',
            'class'   => 'btn btn-primary btn-lg px-5',
            'id'      => 'ap_submit_btn',
            'onclick' => "var chk = document.querySelector('#ap_question_form .im-controls input[type=submit], #ap_question_form .im-controls button[type=submit]'); if (chk) { chk.click(); } else { document.getElementById('ap_question_form').submit(); }",
        ]);
    } else {
        // Already answered — show Next button.
        if ($slot < $total) {
            $next_url = new moodle_url('/mod/adaptivepractice/attempt.php', ['id' => $id, 'slot' => $slot + 1]);
            echo html_writer::link($next_url, get_string('next_question', 'mod_adaptivepractice'), ['class' => 'btn btn-success btn-lg px-5']);
        } else {
            // Last question answered — show Finish.
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'name' => 'finish',
                'value' => get_string('finish_and_see_results', 'mod_adaptivepractice'),
                'class' => 'btn btn-success btn-lg px-5',
            ]);
        }
    }

    echo html_writer::end_div(); // ap-question-actions
    echo html_writer::end_tag('form');
    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // ap-question-card
}

// ---- QUESTION NAVIGATION DOTS ----
echo html_writer::start_div('ap-slot-nav d-flex flex-wrap gap-2 mb-4');
foreach ($slots as $idx => $s) {
    $num = $idx + 1;
    $st = $quba->get_question_state($s);
    if ($st->is_finished()) {
        $mark_val = $quba->get_question_mark($s);
        $max_val = $quba->get_question_max_mark($s);
        $dot_class = ($mark_val !== null && $mark_val >= $max_val) ? 'ap-dot ap-dot-correct' : 'ap-dot ap-dot-wrong';
    } else {
        $dot_class = ($num === $slot) ? 'ap-dot ap-dot-current' : 'ap-dot ap-dot-unanswered';
    }
    $dot_url = new moodle_url('/mod/adaptivepractice/attempt.php', ['id' => $id, 'slot' => $num]);
    echo html_writer::link($dot_url, $num, ['class' => $dot_class, 'title' => get_string('question_num', 'mod_adaptivepractice', $num)]);
}
echo html_writer::end_div();

echo html_writer::end_div(); // ap-session-container

echo $OUTPUT->footer();

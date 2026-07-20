<?php

/**
 * Language strings for Adaptive Practice
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Adaptive Practice';
$string['modulename_help'] = 'Adaptive Practice allows students to practice questions tailored to their competency level.';
$string['modulenameplural'] = 'Adaptive Practices';
$string['pluginname'] = 'Adaptive Practice';
$string['pluginadministration'] = 'Adaptive Practice Administration';

$string['adaptivepractice:addinstance'] = 'Add a new adaptive practice';
$string['adaptivepractice:view'] = 'View adaptive practice';
$string['adaptivepractice:submit'] = 'Submit adaptive practice attempts';
$string['adaptivepractice:manage'] = 'Manage adaptive practice';

$string['questionsperattempt'] = 'Number of questions per attempt';
$string['questionsperattempt_help'] = 'Decide how many questions a student will face in a single session.';
$string['adaptive_logic'] = 'Enable adaptive difficulty';
$string['adaptive_logic_help'] = 'If enabled, questions will be selected based on the student\'s performance.';
$string['competency_scale'] = 'Competency scale (0-100)';
$string['question_category'] = 'Select question category';
$string['question_category_help'] = 'The category from which questions will be pulled.';

$string['start_attempt'] = 'Start Practice Session';
$string['continue_attempt'] = 'Continue Session';
$string['view_reports'] = 'View My Progress';
$string['no_questions_found'] = 'No questions found with required tags (Easy, Medium, Hard) in this category.';
$string['attempt_completed'] = 'Session Completed!';
$string['your_score'] = 'Your Score: {$a}%';
$string['current_competency'] = 'Your Current Competency: {$a}';
$string['difficulty_easy'] = 'Easy';
$string['difficulty_medium'] = 'Medium';
$string['difficulty_hard'] = 'Hard';
$string['difficulty_mixed'] = 'Full Practice';
$string['difficulty'] = 'Difficulty';

$string['feedback_correct'] = 'Well done! That\'s correct.';
$string['feedback_incorrect'] = 'Not quite. Here is some feedback:';
$string['questions'] = 'Questions';
$string['practice_settings'] = 'Practice Settings';
$string['save_changes'] = 'Save Changes';
$string['settings_saved'] = 'Questions settings saved successfully.';
$string['all_results'] = 'All Attempt Results';
$string['attempts_overview'] = 'Attempts Overview';
$string['back'] = 'Back';
$string['add_random_questions'] = 'Add Random Questions';
$string['number_of_questions'] = 'Number of questions';
$string['reached_max_attempts'] = 'You have reached the maximum allowed attempts ({$a}) for this practice.';
$string['invalidattemptid'] = 'Invalid Attempt ID';
$string['attempts_allowed'] = 'Attempts allowed';
$string['unlimited'] = 'Unlimited';
$string['difficulty_random'] = 'Randomized (2E, 2M, 2H)';

$string['cannoteditquestionswithattempts'] = 'You cannot change question settings or difficulty tiers because there are already attempts for this activity. You must delete all student attempts first.';

$string['practice_session'] = 'Practice Session';
$string['finish_session'] = 'Finish Session';
$string['question_x_of_y'] = 'Question {$a->slot} of {$a->total}';
$string['correct_count'] = '{$a} correct';
$string['wrong_count'] = '{$a} wrong';
$string['correct_alert'] = '✨ Correct!';
$string['incorrect_alert'] = '❌ Not quite. See Explanation below.';
$string['your_answer_is_incorrect'] = 'Your answer is incorrect.';
$string['explanation'] = 'Explanation';
$string['the_correct_answer_is'] = 'The correct answer is:';
$string['submit_answer'] = 'Submit Answer →';
$string['next_question'] = 'Next Question →';
$string['finish_and_see_results'] = '🏁 Finish & See Results';
$string['question_num'] = 'Question {$a}';
$string['previous'] = '← Previous';
$string['random_counts_saved_assigned'] = 'Random counts saved. Successfully assigned {$a} additional questions to tiers.';
$string['random_counts_saved_none'] = 'Random counts saved. No unassigned questions found to tag.';
$string['random_counts_saved_categories'] = 'Random counts saved, but no categories are selected. Please select one or more categories in Step 1 first, then try again.';
$string['random_counts_warning_short'] = '(Note: you requested {$a->requested} but only {$a->available} questions are available — remaining questions left unassigned.)';
$string['no_categories_selected'] = 'No categories selected yet. Please select one or more question categories from Step 1 above and click <strong>Update Categories</strong> to load questions.';
$string['no_questions_in_categories'] = 'No questions found in the selected categories. Please <a href="{$a}" target="_blank">open the Question Bank</a> and add questions to your selected categories first.';
$string['select_categories_first'] = 'Please select at least one category before auto-assigning questions.';
$string['attempts_deleted'] = 'Selected attempts have been deleted.';
$string['status'] = 'Status';
$string['status_finished'] = 'Finished';
$string['status_inprogress'] = 'In Progress';
$string['review'] = 'Review';
$string['duration'] = 'Duration';
$string['started'] = 'Started';
$string['completed'] = 'Completed';
$string['grade_percentage'] = 'Grade / {$a}';

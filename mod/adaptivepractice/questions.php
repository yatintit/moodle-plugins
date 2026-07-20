<?php
/**
 * Page for managing questions source in Adaptive Practice.
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_adaptivepractice\helper;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');

use core_question\local\bank\question_edit_contexts;

$id = required_param('id', PARAM_INT); // CM ID.
$bankid = optional_param('bankid', $id, PARAM_INT); // Bank CM ID.
$filter = optional_param('filter', 'all', PARAM_ALPHA);

$cm = get_coursemodule_from_id('adaptivepractice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$adaptivepractice = $DB->get_record('adaptivepractice', ['id' => $cm->instance], '*', MUST_EXIST);

$hasattempts = $DB->record_exists('adaptivepractice_attempts', ['adaptivepracticeid' => $adaptivepractice->id]);

// If there are attempts, and we are trying to save settings, show an error or prevent it.
if ($hasattempts && ($action == 'setcategory' || $action == 'settags' || $action == 'autorandom')) {
    throw new moodle_exception('cannoteditquestionswithattempts', 'mod_adaptivepractice', new moodle_url('/mod/adaptivepractice/questions.php', ['id' => $id]));
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/adaptivepractice:manage', $context);

$PAGE->set_url('/mod/adaptivepractice/questions.php', ['id' => $id]);
$PAGE->set_title(format_string($adaptivepractice->name) . ': ' . get_string('questions', 'mod_adaptivepractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$action = optional_param('action', '', PARAM_ALPHA);

// -------------------------------------------------------------------------
// HANDLE POST ACTIONS
// -------------------------------------------------------------------------
if (data_submitted() && confirm_sesskey()) {

    if ($action === 'setcategory') {
        $categoryids = optional_param_array('categoryids', [], PARAM_INT);
        
        if (!empty($categoryids)) {
            $existing_categoryids = helper::get_category_ids($adaptivepractice->id);
            
            // Merge with existing categories instead of replacing them
            $categoryids = array_values(array_unique(array_merge($existing_categoryids, $categoryids)));
        }

        $DB->delete_records('adaptivepractice_categories', ['adaptivepracticeid' => $adaptivepractice->id]);

        if (!empty($categoryids)) {
            $adaptivepractice->categoryid = array_shift($categoryids);
            $DB->update_record('adaptivepractice', $adaptivepractice);

            foreach ($categoryids as $catid) {
                $DB->insert_record('adaptivepractice_categories', [
                    'adaptivepracticeid' => $adaptivepractice->id,
                    'categoryid' => $catid
                ]);
            }
        }
        redirect($PAGE->url, get_string('settings_saved', 'mod_adaptivepractice'), 2);

    } else if ($action === 'removecategory') {
        $remove_catid = required_param('remove_catid', PARAM_INT);
        $existing_categoryids = helper::get_category_ids($adaptivepractice->id);
        
        // Remove the specified category ID
        $categoryids = array_diff($existing_categoryids, [$remove_catid]);
        
        $DB->delete_records('adaptivepractice_categories', ['adaptivepracticeid' => $adaptivepractice->id]);

        if (!empty($categoryids)) {
            $adaptivepractice->categoryid = array_shift($categoryids);
            $DB->update_record('adaptivepractice', $adaptivepractice);

            foreach ($categoryids as $catid) {
                $DB->insert_record('adaptivepractice_categories', [
                    'adaptivepracticeid' => $adaptivepractice->id,
                    'categoryid' => $catid
                ]);
            }
        } else {
            $adaptivepractice->categoryid = 0;
            $DB->update_record('adaptivepractice', $adaptivepractice);
        }
        redirect($PAGE->url, get_string('settings_saved', 'mod_adaptivepractice'), 2);

    } else if ($action === 'savetiers') {
        $tiers = optional_param_array('tier', [], PARAM_ALPHA);
        if (!empty($tiers)) {
            foreach ($tiers as $questionid => $tier) {
                $qbe_id = $DB->get_field_sql(
                    "SELECT qbe.id FROM {question_bank_entries} qbe
                     JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                     WHERE qv.questionid = ?",
                    [$questionid]
                );
                if ($qbe_id) {
                    $systemcontext = context_system::instance();

                    // Preserve all non-tier tags (e.g. custom tags on the question).
                    $currenttags = core_tag_tag::get_item_tags('core_question', 'question_bank_entry', $qbe_id);
                    $newtags = [];
                    foreach ($currenttags as $tag) {
                        if (!in_array(strtolower($tag->name), ['easy', 'medium', 'hard', 'ap_excluded'])) {
                            $newtags[] = $tag->name;
                        }
                    }

                    // 'none' = explicitly unassign — remove all tier tags, add nothing.
                    // 'exclude' = mark as excluded from practice.
                    // anything else (easy/medium/hard) = assign that tier.
                    if ($tier === 'exclude') {
                        $newtags[] = 'ap_excluded';
                    } else if (!empty($tier) && $tier !== 'none') {
                        $newtags[] = $tier; // easy | medium | hard
                    }
                    // If $tier === 'none' or empty: no tag added → clears existing tier.

                    core_tag_tag::set_item_tags('core_question', 'question_bank_entry', $qbe_id, $systemcontext, $newtags);
                }
            }
        }
        // Set source to Manual when tiers are manually saved.
        $adaptivepractice->questionsource = ADAPTIVEPRACTICE_SOURCE_MANUAL;
        $DB->update_record('adaptivepractice', $adaptivepractice);

        redirect($PAGE->url, get_string('settings_saved', 'mod_adaptivepractice'), 2);

    } else if ($action === 'addrandom') {
        $easy_count = optional_param('random_easy', 0, PARAM_INT);
        $medium_count = optional_param('random_medium', 0, PARAM_INT);
        $hard_count = optional_param('random_hard', 0, PARAM_INT);

        // Save counts and set source to Random.
        $adaptivepractice->questionsource = ADAPTIVEPRACTICE_SOURCE_RANDOM;
        $adaptivepractice->random_easy = $easy_count;
        $adaptivepractice->random_medium = $medium_count;
        $adaptivepractice->random_hard = $hard_count;
        $DB->update_record('adaptivepractice', $adaptivepractice);

        $selected_cat_ids = helper::get_category_ids($adaptivepractice->id);

        if (!empty($selected_cat_ids)) {
            $questions = helper::get_questions_with_tiers($selected_cat_ids);

            if (!empty($questions)) {
                $systemcontext = context_system::instance();
                $total_requested = $easy_count + $medium_count + $hard_count;
                $total_available = count($questions);


                // Step 2: Separate questions into pools based on their category name.
                $easy_pool = [];
                $medium_pool = [];
                $hard_pool = [];
                $other_pool = [];

                foreach ($questions as $q) {
                    $catname = strtolower($q->categoryname);
                    if (strpos($catname, 'easy') !== false) {
                        $easy_pool[] = $q;
                    } else if (strpos($catname, 'medium') !== false) {
                        $medium_pool[] = $q;
                    } else if (strpos($catname, 'hard') !== false) {
                        $hard_pool[] = $q;
                    } else {
                        $other_pool[] = $q;
                    }
                }

                // Shuffle pools for fair distribution if subset is requested.
                shuffle($easy_pool);
                shuffle($medium_pool);
                shuffle($hard_pool);
                shuffle($other_pool);

                $assigned = 0;
                $pools = [
                    'easy' => &$easy_pool,
                    'medium' => &$medium_pool,
                    'hard' => &$hard_pool
                ];
                $counts = [
                    'easy' => $easy_count,
                    'medium' => $medium_count,
                    'hard' => $hard_count
                ];

                foreach ($counts as $tier => $needed) {
                    if ($needed <= 0) {
                        continue;
                    }

                    $to_assign = [];
                    // Pull from primary pool first.
                    while ($needed > 0 && !empty($pools[$tier])) {
                        $to_assign[] = array_shift($pools[$tier]);
                        $needed--;
                    }

                    // Pull from other pool if needed.
                    while ($needed > 0 && !empty($other_pool)) {
                        $to_assign[] = array_shift($other_pool);
                        $needed--;
                    }

                    // Tag the selected questions.
                    foreach ($to_assign as $q) {
                        $qbe_id = $DB->get_field_sql(
                            "SELECT qbe.id FROM {question_bank_entries} qbe
                             JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                             WHERE qv.questionid = ?",
                            [$q->id]
                        );
                        if ($qbe_id) {
                            foreach (['easy', 'medium', 'hard', 'ap_excluded'] as $t) {
                                core_tag_tag::remove_item_tag('core_question', 'question_bank_entry', $qbe_id, $t, $systemcontext->id);
                            }
                            core_tag_tag::add_item_tag('core_question', 'question_bank_entry', $qbe_id, $systemcontext, $tier);
                            $assigned++;
                        }
                    }
                }

                // Build result message, warn if counts didn't match available questions.
                if ($total_requested > $total_available) {
                    $msg = get_string('random_counts_saved_assigned', 'mod_adaptivepractice', $assigned)
                         . ' ' . get_string('random_counts_warning_short', 'mod_adaptivepractice',
                             (object)['requested' => $total_requested, 'available' => $total_available]);
                } else {
                    $msg = get_string('random_counts_saved_assigned', 'mod_adaptivepractice', $assigned);
                }

                redirect($PAGE->url, $msg, 3);

            } else {
                redirect($PAGE->url, get_string('random_counts_saved_none', 'mod_adaptivepractice'), 3);
            }
        } else {
            redirect($PAGE->url, get_string('random_counts_saved_categories', 'mod_adaptivepractice'), 3);
        }
    }
}

// -------------------------------------------------------------------------
// PREPARE DATA FOR RENDERER
// -------------------------------------------------------------------------

// Category Selector Data with counts.
$all_categories = helper::get_all_categories_in_course($course->id, $id, $bankid);
$all_cat_ids = array_keys($all_categories);
$cat_qcounts = helper::get_categories_question_counts($all_cat_ids);

$selected_cat_ids = helper::get_category_ids($adaptivepractice->id);

$catgroups = [];
foreach ($all_categories as $qc) {
    $ctx = context::instance_by_id($qc->contextid);
    $ctxname = $ctx->get_context_name();
    if (!isset($catgroups[$ctxname])) {
        $catgroups[$ctxname] = [];
    }

    $count = isset($cat_qcounts[$qc->id]) ? $cat_qcounts[$qc->id]->qcount : 0;
    $label = format_string($qc->hierarchicalname) . " ({$count})";

    $catgroups[$ctxname][$qc->id] = $label;
}

// Questions Data.
$questions = helper::get_questions_with_tiers($selected_cat_ids);
$filtered_questions = [];
$counts = ['easy' => 0, 'medium' => 0, 'hard' => 0, 'unassigned' => 0, 'excluded' => 0];

$easy_cat_qcount = 0;
$medium_cat_qcount = 0;
$hard_cat_qcount = 0;

foreach ($questions as $q) {
    if (!empty($q->is_excluded)) {
        $counts['excluded']++;
        $tier = 'excluded';
    } else {
        $tier = strtolower($q->tiertag ?? '');
        if (empty($tier)) {
            $counts['unassigned']++;
        } else {
            $counts[$tier]++;
        }
    }

    if (
        ($filter === 'all' && empty($q->is_excluded)) ||
        ($filter === 'excluded' && !empty($q->is_excluded)) ||
        ($filter === 'unassigned' && empty($tier) && empty($q->is_excluded)) ||
        ($filter === $tier && empty($q->is_excluded))
    ) {
        $filtered_questions[] = $q;
    }

    // Count questions by category name for default counts.
    $catname = strtolower($q->categoryname);
    if (strpos($catname, 'easy') !== false) {
        $easy_cat_qcount++;
    } else if (strpos($catname, 'medium') !== false) {
        $medium_cat_qcount++;
    } else if (strpos($catname, 'hard') !== false) {
        $hard_cat_qcount++;
    }
}

// Build selected categories list with their formatted hierarchy names.
$selected_categories_list = [];
if (!empty($selected_cat_ids)) {
    list($insql, $params) = $DB->get_in_or_equal($selected_cat_ids);
    $selected_cats_records = $DB->get_records_select('question_categories', "id {$insql}", $params);
    foreach ($selected_cat_ids as $catid) {
        if (isset($selected_cats_records[$catid])) {
            $qc = $selected_cats_records[$catid];
            $name = '';
            if ($qc->parent != 0) {
                $parentname = $DB->get_field('question_categories', 'name', ['id' => $qc->parent]);
                if ($parentname) {
                    $name = format_string($parentname) . ' > ';
                }
            }
            $name .= format_string($qc->name);
            
            $selected_categories_list[] = [
                'value' => $catid,
                'name' => $name
            ];
        }
    }
}

$random_easy = $adaptivepractice->random_easy;
if ($random_easy == 0 && $easy_cat_qcount > 0) {
    $random_easy = $easy_cat_qcount;
}
$random_medium = $adaptivepractice->random_medium;
if ($random_medium == 0 && $medium_cat_qcount > 0) {
    $random_medium = $medium_cat_qcount;
}
$random_hard = $adaptivepractice->random_hard;
if ($random_hard == 0 && $hard_cat_qcount > 0) {
    $random_hard = $hard_cat_qcount;
}

$backurl = new moodle_url('/mod/adaptivepractice/view.php', ['id' => $cm->id]);
$qbankurl = new moodle_url('/question/edit.php', ['cmid' => $cm->id]);

$data = [
    'id' => $id,
    'backurl' => $backurl->out(false),
    'qbankurl' => $qbankurl->out(false),
    'catgroups' => $catgroups,
    'selected_vals' => $selected_cat_ids,
    'selected_cat_ids' => $selected_cat_ids,
    'selected_categories_list' => $selected_categories_list,
    'filtered_questions' => $filtered_questions,
    'total_count' => count($questions),
    'counts' => $counts,
    'filter' => $filter,
    'random_easy' => $random_easy,
    'random_medium' => $random_medium,
    'random_hard' => $random_hard,
    'questionsource' => $adaptivepractice->questionsource,
    'bankid' => $bankid,
    'current_bank_name' => get_coursemodule_from_id('', $bankid ?: $id)->name,
    'contextid' => $context->id,
    'courseid' => $course->id,
    'userid' => $USER->id,
    'has_attempts' => $hasattempts
];

$renderable = new \mod_adaptivepractice\output\questions_page($data);
$renderer = $PAGE->get_renderer('mod_adaptivepractice');

echo $OUTPUT->header();
echo $renderer->render($renderable);
echo $OUTPUT->footer();

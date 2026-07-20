<?php
/**
 * Helper class for Adaptive Practice
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivepractice;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use context;
use context_module;

/**
 * Helper class
 */
class helper
{

    /**
     * Get the current competency for a user.
     *
     * @param int $practiceid
     * @param int $userid
     * @return float
     */
    public static function get_user_competency(int $practiceid, int $userid): float
    {
        global $DB;
        $progress = $DB->get_record('adaptivepractice_progress', ['adaptivepracticeid' => $practiceid, 'userid' => $userid]);
        return $progress ? (float) $progress->competency : 0.0;
    }

    /**
     * Update the user's competency.
     *
     * @param int $practiceid
     * @param int $userid
     * @param float $new_score
     */
    public static function update_user_competency(int $practiceid, int $userid, float $new_score): void
    {
        global $DB;
        $progress = $DB->get_record('adaptivepractice_progress', ['adaptivepracticeid' => $practiceid, 'userid' => $userid]);

        if ($progress) {
            $new_competency = ($progress->competency * 0.3) + ($new_score * 0.7);
            $progress->competency = $new_competency;
            $progress->timemodified = time();
            $DB->update_record('adaptivepractice_progress', $progress);
        } else {
            $progress = new stdClass();
            $progress->adaptivepracticeid = $practiceid;
            $progress->userid = $userid;
            $progress->competency = $new_score;
            $progress->timemodified = time();
            $DB->insert_record('adaptivepractice_progress', $progress);
        }
    }

    /**
     * Select questions based on difficulty level and tags.
     *
     * @param array|int $categoryids
     * @param string $difficulty_level
     * @param int $count
     * @return array
     */
    public static function get_questions($categoryids, string $difficulty_level, int $count): array
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        if (!is_array($categoryids)) {
            $categoryids = [$categoryids];
        }
        if (empty($categoryids)) {
            return [];
        }

        // Expand to include subcategories.
        $expanded = [];
        foreach ($categoryids as $catid) {
            $expanded = array_merge($expanded, question_categorylist($catid));
        }
        $categoryids = array_unique(array_filter($expanded));
        if (empty($categoryids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($categoryids);
        $params = array_merge($inparams, [$difficulty_level]);

        $sql = "SELECT q.*
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {tag_instance} ti ON ti.itemid = qbe.id
                  JOIN {tag} t ON t.id = ti.tagid
                 WHERE qbe.questioncategoryid {$insql}
                   AND " . $DB->sql_compare_text('t.name') . " = " . $DB->sql_compare_text('?') . "
                   AND ti.component = 'core_question'
                   AND ti.itemtype = 'question_bank_entry'
                   AND qv.status = 'ready'
                   AND qv.version = (
                       SELECT MAX(qv2.version)
                       FROM {question_versions} qv2
                       WHERE qv2.questionbankentryid = qbe.id
                         AND qv2.status = 'ready'
                   )
                   AND q.parent = 0
                   AND NOT EXISTS (
                       SELECT 1 FROM {tag_instance} ti2
                       JOIN {tag} t2 ON t2.id = ti2.tagid
                       WHERE ti2.itemid = qbe.id
                         AND ti2.component = 'core_question'
                         AND ti2.itemtype = 'question_bank_entry'
                         AND " . $DB->sql_compare_text('t2.name') . " = " . $DB->sql_compare_text("'ap_excluded'") . "
                   )";

        $questions = $DB->get_records_sql($sql, $params);

        if (empty($questions)) {
            return [];
        }

        $questions = array_values($questions);
        shuffle($questions);
        return array_slice($questions, 0, $count);
    }

    /**
     * Get questions from specific levels (Easy, Medium, Hard) based on defined counts.
     *
     * @param array|int $categoryids
     * @param int $easy
     * @param int $medium
     * @param int $hard
     * @return array
     */
    public static function get_defined_random_questions($categoryids, int $easy, int $medium, int $hard): array
    {
        $all_questions = [];
        $levels = ['easy' => $easy, 'medium' => $medium, 'hard' => $hard];

        foreach ($levels as $level => $count) {
            if ($count <= 0) {
                continue;
            }

            $level_qs = self::get_questions($categoryids, $level, $count);
            foreach ($level_qs as $q) {
                $all_questions[] = $q;
            }
        }

        return $all_questions;
    }

    /**
     * Get questions from all levels (Easy, Medium, Hard) in a single list.
     *
     * @param array|int $categoryids
     * @param int $count
     * @return array
     */
    public static function get_progressive_questions($categoryids, int $count): array
    {
        $levels = ['easy', 'medium', 'hard'];
        $all_questions = [];

        $per_level = floor($count / 3);
        $remainder = $count % 3;

        foreach ($levels as $idx => $level) {
            $needed = (int) ($per_level + ($idx < $remainder ? 1 : 0));
            if ($needed <= 0) {
                continue;
            }

            $level_qs = self::get_questions($categoryids, $level, $needed);
            foreach ($level_qs as $q) {
                $all_questions[] = $q;
            }
        }

        return $all_questions;
    }

    /**
     * Get all category IDs assigned to an adaptive practice activity.
     *
     * @param int $adaptivepracticeid
     * @return array
     */
    public static function get_category_ids(int $adaptivepracticeid): array
    {
        global $DB;
        $main_cat = $DB->get_field('adaptivepractice', 'categoryid', ['id' => $adaptivepracticeid]);
        $extra_cats = $DB->get_fieldset_select('adaptivepractice_categories', 'categoryid', 'adaptivepracticeid = ?', [$adaptivepracticeid]);

        $all = array_unique(array_merge([$main_cat], $extra_cats));
        return array_values(array_filter($all));
    }

    /**
     * Start a new question engine usage.
     *
     * @param \context_module $context
     * @return \question_usage_by_activity
     */
    public static function start_usage(context_module $context): \question_usage_by_activity
    {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $quba = \question_engine::make_questions_usage_by_activity('mod_adaptivepractice', $context);
        $quba->set_preferred_behaviour('immediatefeedback');
        return $quba;
    }

    /**
     * Add questions to a usage.
     *
     * @param \question_usage_by_activity $quba
     * @param array $questions
     * @return array
     */
    public static function add_questions_to_usage(\question_usage_by_activity $quba, array $questions): array
    {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $slot = 1;
        $slots = [];
        foreach ($questions as $questiondata) {
            $question = \question_bank::load_question($questiondata->id);
            $quba->add_question($question, $question->defaultmark);
            $quba->start_question($slot);
            $slots[] = $slot;
            $slot++;
        }
        return $slots;
    }

    /**
     * Process attempt submission.
     *
     * @param int $attemptid
     * @return float
     */
    public static function process_attempt(int $attemptid): float
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $attempt = $DB->get_record('adaptivepractice_attempts', ['id' => $attemptid]);
        $quba = \question_engine::load_questions_usage_by_activity($attempt->usageid);

        $quba->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);

        $total_mark = $quba->get_total_mark();
        $max_mark = 0;
        foreach ($quba->get_slots() as $slot) {
            $max_mark += $quba->get_question_max_mark($slot);
        }
        $score = ($max_mark > 0) ? ($total_mark / $max_mark) * 100 : 0;

        $attempt->score = $score;
        $attempt->status = 'finished';
        $attempt->timefinish = time();
        $attempt->timemodified = time();
        $DB->update_record('adaptivepractice_attempts', $attempt);

        // Update global competency.
        self::update_user_competency($attempt->adaptivepracticeid, (int) $attempt->userid, (float) $score);

        // Update Gradebook.
        require_once(__DIR__ . '/../lib.php');
        $adaptivepractice = $DB->get_record('adaptivepractice', ['id' => $attempt->adaptivepracticeid]);
        adaptivepractice_update_grades($adaptivepractice, $attempt->userid);

        return (float) $score;
    }

    /**
     * Get all question categories in course contexts (hierarchically sorted).
     *
     * @param int $courseid
     * @param int $cmid
     * @param int|null $bankid
     * @return array
     */
    public static function get_all_categories_in_course(int $courseid, int $cmid, ?int $bankid = null): array
    {
        global $DB;
        $context = \context_module::instance($bankid ?: $cmid);
        $contextids = $context->get_parent_context_ids(true);

        list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_sql(
            "SELECT qc.*, ctx.contextlevel
               FROM {question_categories} qc
               JOIN {context} ctx ON ctx.id = qc.contextid
              WHERE qc.contextid {$insql}
             ORDER BY ctx.depth ASC, qc.parent, qc.sortorder, qc.name",
            $params
        );

        // Sort records hierarchically in PHP to ensure parents always appear before their children.
        return self::sort_categories_hierarchically($records);
    }

    /**
     * Recursive helper to sort categories into a tree-like order.
     *
     * @param array $records
     * @return array
     */
    private static function sort_categories_hierarchically(array $records): array
    {
        $children = [];
        $roots = [];
        foreach ($records as $r) {
            if ($r->parent == 0 || !isset($records[$r->parent])) {
                $roots[] = $r;
            } else {
                $children[$r->parent][] = $r;
            }
        }

        $result = [];
        // Local depth tracker to add indentation.
        $worker = function ($parents, $depth) use (&$worker, &$children, &$result) {
            foreach ($parents as $p) {
                // Add prefix based on depth for visual hierarchy.
                $p->hierarchicalname = str_repeat('-', $depth) . ' ' . $p->name;
                $result[$p->id] = $p;
                if (isset($children[$p->id])) {
                    $worker($children[$p->id], $depth + 2);
                }
            }
        };

        $worker($roots, 0);
        return $result;
    }

    /**
     * Get all questions in multiple categories with their tier tags.
     *
     * @param array|int $categoryids
     * @return array
     */
    public static function get_questions_with_tiers($categoryids): array
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        if (!is_array($categoryids)) {
            $categoryids = [$categoryids];
        }
        
        // Expand to include subcategories.
        $expanded = [];
        foreach ($categoryids as $catid) {
            $expanded = array_merge($expanded, question_categorylist($catid));
        }
        $categoryids = array_unique(array_filter($expanded));
        
        if (empty($categoryids)) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($categoryids);

        $sql = "SELECT q.id, q.name, q.qtype, qc.name as categoryname, qcp.name as parentcategoryname,
                       (SELECT LOWER(t.name)
                          FROM {tag} t
                          JOIN {tag_instance} ti ON ti.tagid = t.id
                         WHERE ti.itemid = qbe.id
                           AND ti.component = 'core_question'
                           AND ti.itemtype = 'question_bank_entry'
                           AND LOWER(t.name) IN ('easy', 'medium', 'hard')
                         LIMIT 1) AS tiertag,
                       (SELECT 1
                          FROM {tag} t
                          JOIN {tag_instance} ti ON ti.tagid = t.id
                         WHERE ti.itemid = qbe.id
                           AND ti.component = 'core_question'
                           AND ti.itemtype = 'question_bank_entry'
                           AND " . $DB->sql_compare_text('t.name') . " = " . $DB->sql_compare_text("'ap_excluded'") . ") AS is_excluded
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  LEFT JOIN {question_categories} qcp ON qcp.id = qc.parent AND qcp.parent <> 0
                 WHERE qbe.questioncategoryid {$insql}
                   AND qv.status = 'ready'
                   AND qv.version = (
                       SELECT MAX(qv2.version)
                       FROM {question_versions} qv2
                       WHERE qv2.questionbankentryid = qbe.id
                         AND qv2.status = 'ready'
                   )
                   AND q.parent = 0
                 ORDER BY q.name";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get question counts for a list of categories.
     *
     * @param array $categoryids
     * @return array
     */
    public static function get_categories_question_counts(array $categoryids): array
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        if (empty($categoryids)) {
            return [];
        }

        // Expand to include subcategories.
        $expanded = [];
        foreach ($categoryids as $catid) {
            $expanded = array_merge($expanded, question_categorylist($catid));
        }
        $categoryids = array_unique(array_filter($expanded));
        
        if (empty($categoryids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($categoryids);
        $sql = "SELECT qbe.questioncategoryid, COUNT(DISTINCT qbe.id) as qcount
                  FROM {question_bank_entries} qbe
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qbe.questioncategoryid {$insql}
                   AND qv.status = 'ready'
                   AND q.parent = 0
               GROUP BY qbe.questioncategoryid";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get question counts by difficulty for a list of categories.
     *
     * @param array $categoryids
     * @return \stdClass
     */
    public static function get_difficulty_counts(array $categoryids): stdClass
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        if (empty($categoryids)) {
            return (object) ['easy' => 0, 'medium' => 0, 'hard' => 0];
        }

        // Expand to include subcategories.
        $expanded = [];
        foreach ($categoryids as $catid) {
            $catlist = question_categorylist($catid);
            if (is_string($catlist) && strpos($catlist, ',') !== false) {
                $expanded = array_merge($expanded, explode(',', $catlist));
            } else if (is_array($catlist)) {
                $expanded = array_merge($expanded, $catlist);
            } else {
                $expanded[] = $catlist;
            }
        }
        $categoryids = array_unique(array_filter($expanded));
        
        if (empty($categoryids)) {
            return (object) ['easy' => 0, 'medium' => 0, 'hard' => 0];
        }

        list($insql, $params) = $DB->get_in_or_equal($categoryids);

        $sql = "SELECT 
                COUNT(DISTINCT CASE WHEN LOWER(" . $DB->sql_compare_text('t.name') . ") = 'easy' THEN qbe.id END) as easy,
                COUNT(DISTINCT CASE WHEN LOWER(" . $DB->sql_compare_text('t.name') . ") = 'medium' THEN qbe.id END) as medium,
                COUNT(DISTINCT CASE WHEN LOWER(" . $DB->sql_compare_text('t.name') . ") = 'hard' THEN qbe.id END) as hard
            FROM {question_bank_entries} qbe
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {tag_instance} ti ON ti.itemid = qbe.id
            JOIN {tag} t ON t.id = ti.tagid
            WHERE qbe.questioncategoryid {$insql}
              AND ti.component = 'core_question'
              AND ti.itemtype = 'question_bank_entry'
              AND qv.status = 'ready'
              AND qv.version = (
                  SELECT MAX(qv2.version)
                  FROM {question_versions} qv2
                  WHERE qv2.questionbankentryid = qbe.id
                    AND qv2.status = 'ready'
              )";

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Delete an attempt and update user competency and grades.
     *
     * @param int $attemptid
     * @return bool
     */
    public static function delete_attempt(int $attemptid): bool
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $attempt = $DB->get_record('adaptivepractice_attempts', ['id' => $attemptid]);
        if (!$attempt) {
            return false;
        }

        // 1. Delete question usage if exists.
        if (!empty($attempt->usageid)) {
            try {
                \question_engine::delete_questions_usage_by_activity($attempt->usageid);
            } catch (\Exception $e) {
                // Ignore errors if usage already deleted.
            }
        }

        // 2. Delete the attempt record.
        $DB->delete_records('adaptivepractice_attempts', ['id' => $attemptid]);

        // 3. Update user competency based on remaining attempts.
        $remaining = $DB->get_records(
            'adaptivepractice_attempts',
            ['adaptivepracticeid' => $attempt->adaptivepracticeid, 'userid' => $attempt->userid, 'status' => 'finished'],
            'timefinish ASC'
        );

        if (empty($remaining)) {
            $DB->delete_records('adaptivepractice_progress', ['adaptivepracticeid' => $attempt->adaptivepracticeid, 'userid' => $attempt->userid]);
        } else {
            $competency = 0;
            $first = true;
            foreach ($remaining as $r) {
                if ($first) {
                    $competency = (float) $r->score;
                    $first = false;
                } else {
                    $competency = ($competency * 0.3) + ($r->score * 0.7);
                }
            }
            $progress = $DB->get_record('adaptivepractice_progress', ['adaptivepracticeid' => $attempt->adaptivepracticeid, 'userid' => $attempt->userid]);
            if ($progress) {
                $progress->competency = $competency;
                $progress->timemodified = time();
                $DB->update_record('adaptivepractice_progress', $progress);
            } else {
                $progress = new stdClass();
                $progress->adaptivepracticeid = $attempt->adaptivepracticeid;
                $progress->userid = $attempt->userid;
                $progress->competency = $competency;
                $progress->timemodified = time();
                $DB->insert_record('adaptivepractice_progress', $progress);
            }
        }

        // 4. Update Gradebook.
        $adaptivepractice = $DB->get_record('adaptivepractice', ['id' => $attempt->adaptivepracticeid]);
        require_once(__DIR__ . '/../lib.php');
        adaptivepractice_update_grades($adaptivepractice, $attempt->userid);

        return true;
    }
}

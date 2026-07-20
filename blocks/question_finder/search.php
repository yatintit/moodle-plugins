<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX search endpoint for Question Finder block.
 *
 * Accepts POST param "q" (search text) and returns a JSON array
 * of matching questions across all courses.
 *
 * Response format:
 * {
 *   "results": [
 *     {
 *       "id":        (int)    question id,
 *       "name":      (string) question name,
 *       "qtype":     (string) question type,
 *       "category":  (string) category name (with parent prefix if any),
 *       "course":    (string) course fullname,
 *       "courseid":  (int),
 *       "courseurl": (string) URL to view the course,
 *       "editurl":   (string) URL to open this question in the Question Bank
 *     },
 *     ...
 *   ]
 * }
 *
 * On error:  { "error": "Error message" }
 *
 * @package    block_question_finder
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// -----------------------------------------------------------------------
// Security checks.
// -----------------------------------------------------------------------
require_login();
require_sesskey();

$syscontext = context_system::instance();

// Only admins or users with viewall question capability may search.
if (!has_capability('moodle/question:viewall', $syscontext)) {
    echo json_encode(['error' => get_string('error_permission', 'block_question_finder')]);
    die();
}

// -----------------------------------------------------------------------
// Get & validate input.
// -----------------------------------------------------------------------
$query = optional_param('q', '', PARAM_TEXT);
// Replace + with space if user typed it, and remove extra spaces.
$query = trim(str_replace('+', ' ', $query));

if (core_text::strlen($query) < 3) {
    echo json_encode(['error' => 'Please enter at least 3 characters to search.']);
    die();
}

// -----------------------------------------------------------------------
// Build the SQL query.
//
// We split the search query into separate words and require ALL words 
// to be present in either the question name OR the question text.
// -----------------------------------------------------------------------

$words = preg_split('/\s+/', $query);
$nameconditions = [];
$textconditions = [];
$params = [];
$i = 0;

foreach ($words as $word) {
    $searchterm = '%' . $DB->sql_like_escape($word) . '%';
    $nameconditions[] = $DB->sql_like('q.name', ':name'.$i, false);
    $textconditions[] = $DB->sql_like($DB->sql_cast_to_char('q.questiontext'), ':text'.$i, false);
    $params['name'.$i] = $searchterm;
    $params['text'.$i] = $searchterm;
    $i++;
}

$nameclause = implode(' AND ', $nameconditions);
$textclause = implode(' AND ', $textconditions);
$searchclause = "($nameclause) OR ($textclause)";

$sql = "
    SELECT
        q.id,
        q.name,
        q.qtype,
        qc.id         AS categoryid,
        qc.name       AS categoryname,
        qcp.name      AS parentcategoryname,
        ctx.id        AS contextid,
        ctx.contextlevel,
        ctx.instanceid,
        c.id          AS courseid,
        c.fullname    AS coursefullname,
        c.shortname   AS courseshortname
    FROM {question} q
    JOIN {question_versions} qv
        ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe
        ON qbe.id = qv.questionbankentryid
    JOIN {question_categories} qc
        ON qc.id = qbe.questioncategoryid
    LEFT JOIN {question_categories} qcp
        ON qcp.id = qc.parent AND qcp.parent <> 0
    JOIN {context} ctx
        ON ctx.id = qc.contextid
    LEFT JOIN {course} c
        ON (ctx.contextlevel = " . CONTEXT_COURSE . " AND ctx.instanceid = c.id)
        OR (ctx.contextlevel = " . CONTEXT_MODULE . " AND ctx.instanceid IN (
              SELECT cm.id FROM {course_modules} cm WHERE cm.course = c.id
            ))
    WHERE qv.status = 'ready'
      AND qv.version = (
            SELECT MAX(qv2.version)
            FROM {question_versions} qv2
            WHERE qv2.questionbankentryid = qbe.id
              AND qv2.status = 'ready'
          )
      AND q.parent = 0
      AND (
            $searchclause
          )
    ORDER BY q.name
    LIMIT 50
";

try {
    $rows = $DB->get_records_sql($sql, $params);
} catch (Exception $e) {
    // Fallback: simpler query without full-text on questiontext.
    $sql_simple = "
        SELECT
            q.id,
            q.name,
            q.qtype,
            qc.id         AS categoryid,
            qc.name       AS categoryname,
            qcp.name      AS parentcategoryname,
            ctx.id        AS contextid,
            ctx.contextlevel,
            ctx.instanceid,
            c.id          AS courseid,
            c.fullname    AS coursefullname,
            c.shortname   AS courseshortname
        FROM {question} q
        JOIN {question_versions} qv
            ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe
            ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc
            ON qc.id = qbe.questioncategoryid
        LEFT JOIN {question_categories} qcp
            ON qcp.id = qc.parent AND qcp.parent <> 0
        JOIN {context} ctx
            ON ctx.id = qc.contextid
        LEFT JOIN {course} c
            ON (ctx.contextlevel = " . CONTEXT_COURSE . " AND ctx.instanceid = c.id)
        WHERE qv.status = 'ready'
          AND q.parent = 0
          AND ($nameclause)
        ORDER BY q.name
        LIMIT 50
    ";
    $rows = $DB->get_records_sql($sql_simple, $params);
}

// -----------------------------------------------------------------------
// Build structured results.
// -----------------------------------------------------------------------
$results = [];

foreach ($rows as $row) {

    // Build the category display name (parent > child).
    $catname = !empty($row->parentcategoryname)
        ? format_string($row->parentcategoryname) . ' › ' . format_string($row->categoryname)
        : format_string($row->categoryname);

    // Determine the course name and URL.
    if (!empty($row->courseid) && !empty($row->coursefullname)) {
        $coursename = format_string($row->coursefullname);
        $courseurl  = (new moodle_url('/course/view.php', ['id' => $row->courseid]))->out(false);
        $courseid   = (int) $row->courseid;
    } else {
        // Question lives in system or other non-course context.
        $coursename = get_string('system_context', 'block_question_finder');
        $courseurl  = (new moodle_url('/admin/index.php'))->out(false);
        $courseid   = 0;
    }

    // Find a valid cmid to link directly to the question edit page.
    $cmid = 0;
    if ($row->contextlevel == CONTEXT_MODULE) {
        $cmid = $row->instanceid;
    } else if ($courseid > 0) {
        static $course_cmids = [];
        if (!isset($course_cmids[$courseid])) {
            $sqlcm = "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.course = :courseid AND m.name = 'qbank'";
            $foundcm = $DB->get_field_sql($sqlcm, ['courseid' => $courseid], IGNORE_MULTIPLE);
            if (!$foundcm) {
                $sqlcm = "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.course = :courseid AND m.name = 'quiz'";
                $foundcm = $DB->get_field_sql($sqlcm, ['courseid' => $courseid], IGNORE_MULTIPLE);
            }
            $course_cmids[$courseid] = $foundcm ? $foundcm : 0;
        }
        // Since get_field_sql might return a string/int, let's just grab the scalar.
        // Wait, IGNORE_MULTIPLE returns the field value. So it's a scalar.
        if (isset($course_cmids[$courseid]) && is_scalar($course_cmids[$courseid])) {
             $cmid = $course_cmids[$courseid];
        }
    }

    $filter_arr = [
        'category' => [
            'name' => 'category',
            'jointype' => 1,
            'values' => [(int)$row->categoryid],
            'filteroptions' => [['name' => 'includesubcategories', 'value' => false]]
        ],
        'hidden' => [
            'name' => 'hidden',
            'jointype' => 1,
            'values' => [0],
            'filteroptions' => []
        ],
        'qname' => [
            'name' => 'qname',
            'jointype' => 1,
            'values' => [$row->name],
            'filteroptions' => []
        ],
        'jointype' => 2
    ];

    if ($cmid) {
        $editurl = (new moodle_url('/question/bank/editquestion/question.php', [
            'id'   => $row->id,
            'cmid' => $cmid,
        ]))->out(false);
        $bankurl = (new moodle_url('/question/edit.php', [
            'cmid' => $cmid,
            'cat'  => $row->categoryid . ',' . $row->contextid,
            'filter' => json_encode($filter_arr)
        ]))->out(false);
    } else {
        $editurl = (new moodle_url('/question/edit.php', [
            'courseid'   => max(1, $courseid),
            'cat'        => $row->categoryid . ',' . $row->contextid,
        ]))->out(false);
        $bankurl = $editurl;
    }
    
    $previewurl = (new moodle_url('/question/bank/previewquestion/preview.php', [
        'id'   => $row->id
    ]))->out(false);

    $results[] = [
        'id'        => (int) $row->id,
        'name'      => format_string($row->name),
        'qtype'     => $row->qtype,
        'category'  => $catname,
        'course'    => $coursename,
        'courseid'  => $courseid,
        'courseurl' => $courseurl,
        'editurl'   => $editurl,
        'bankurl'   => $bankurl,
        'previewurl'=> $previewurl,
    ];
}

// -----------------------------------------------------------------------
// Return JSON.
// -----------------------------------------------------------------------
header('Content-Type: application/json');
echo json_encode(['results' => $results]);

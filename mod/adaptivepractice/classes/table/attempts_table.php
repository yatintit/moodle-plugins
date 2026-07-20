<?php
/**
 * Table for Adaptive Practice attempts
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyright GNU GPL v3 or later
 */

namespace mod_adaptivepractice\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Attempts table class
 */
class attempts_table extends \table_sql
{

    /** @var \stdClass $adaptivepractice */
    protected $adaptivepractice;

    /** @var array $best_map */
    protected $best_map;

    /**
     * Constructor
     *
     * @param string $uniqueid
     * @param \stdClass $adaptivepractice
     * @param array $best_map
     * @param int $cmid
     */
    public function __construct($uniqueid, $adaptivepractice, $best_map = [], $cmid = 0)
    {
        parent::__construct($uniqueid);
        $this->adaptivepractice = $adaptivepractice;
        $this->best_map = $best_map;

        $columns = ['checkbox', 'fullname', 'email', 'status', 'current_difficulty', 'timecreated', 'timefinish', 'duration', 'score', 'review'];
        $headers = [
            '<input type="checkbox" id="selectall-attempts" title="Select all">',
            get_string('firstname') . ' / ' . get_string('lastname'),
            get_string('email'),
            get_string('status', 'mod_adaptivepractice'),
            get_string('difficulty', 'mod_adaptivepractice'),
            get_string('started', 'mod_adaptivepractice'),
            get_string('completed', 'mod_adaptivepractice'),
            get_string('duration', 'mod_adaptivepractice'),
            get_string('grade_percentage', 'mod_adaptivepractice', $adaptivepractice->competency_scale),
            get_string('review', 'mod_adaptivepractice')
        ];

        if ($this->is_downloading()) {
            $columns = ['fullname', 'email', 'status', 'current_difficulty', 'timecreated', 'timefinish', 'duration', 'score'];
            $headers = [
                get_string('firstname') . ' / ' . get_string('lastname'),
                get_string('email'),
                get_string('status', 'mod_adaptivepractice'),
                get_string('difficulty', 'mod_adaptivepractice'),
                get_string('started', 'mod_adaptivepractice'),
                get_string('completed', 'mod_adaptivepractice'),
                get_string('duration', 'mod_adaptivepractice'),
                get_string('grade', 'quiz') . ' / 100'
            ];
        }

        $this->define_baseurl(new \moodle_url('/mod/adaptivepractice/report.php', ['id' => $cmid]));
        $this->show_download_buttons_at([]);

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('review');
        $this->no_sorting('duration');
        $this->no_sorting('email');
        $this->no_sorting('status');

        $this->set_attribute('class', 'table table-striped table-hover generaltable mt-3 w-100');
        if (method_exists($this, 'set_sticky_header')) {
            $this->set_sticky_header(false);
        }
        $this->set_attribute('data-not-sticky', 'true');
    }

    /**
     * Format checkbox column
     */
    public function col_checkbox($row)
    {
        $row = (array) $row;
        return \html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'name' => 'attemptids[]',
            'value' => $row['id'],
            'class' => 'attempt-checkbox'
        ]);
    }

    /**
     * Set up the SQL for the table based on filters.
     * 
     * @param int $userid Filter by specific user
     * @param string $status Filter by status ('finished', 'inprogress')
     */
    public function setup_sql_query()
    {
        global $DB;

        $fields = "a.id, a.userid, a.status, a.current_difficulty, a.timecreated, a.timefinish, a.usageid, a.score, 
                   u.firstname, u.lastname, u.email, u.picture, u.imagealt";
        $from = "{adaptivepractice_attempts} a LEFT JOIN {user} u ON u.id = a.userid";
        $where = "a.adaptivepracticeid = :apid";
        $params = ['apid' => $this->adaptivepractice->id];

        $this->set_sql($fields, $from, $where, $params);

        return $DB->count_records_sql("SELECT COUNT(*) FROM $from WHERE $where", $params);
    }

    /**
     * Format fullname column
     */
    public function col_fullname($row)
    {
        global $OUTPUT;
        $row = (array) $row;
        $user = (object) [
            'id' => $row['userid'],
            'firstname' => $row['firstname'] ?? '',
            'lastname' => $row['lastname'] ?? '',
            'picture' => $row['picture'] ?? 0,
            'imagealt' => $row['imagealt'] ?? '',
            'email' => $row['email'] ?? ''
        ];
        if ($this->is_downloading()) {
            return fullname($user);
        }
        return $OUTPUT->user_picture($user) . ' ' . fullname($user);
    }

    /**
     * Format status column
     */
    public function col_status($row)
    {
        $row = (array) $row;
        $status_text = (($row['status'] ?? '') === 'finished') ? get_string('status_finished', 'mod_adaptivepractice') : get_string('status_inprogress', 'mod_adaptivepractice');
        if ($this->is_downloading()) {
            return $status_text;
        }
        if (($row['status'] ?? '') === 'finished') {
            return '<span class="badge badge-success">' . $status_text . '</span>';
        }
        return '<span class="badge badge-warning">' . $status_text . '</span>';
    }

    /**
     * Format difficulty column
     */
    public function col_current_difficulty($row)
    {
        $row = (array) $row;
        return get_string('difficulty_' . ($row['current_difficulty'] ?? 'easy'), 'mod_adaptivepractice');
    }

    /**
     * Format duration column
     */
    public function col_duration($row)
    {
        $row = (array) $row;
        if (($row['status'] ?? '') === 'finished' && ($row['timefinish'] ?? 0) > ($row['timecreated'] ?? 0)) {
            return format_time($row['timefinish'] - $row['timecreated']);
        }
        return '-';
    }

    /**
     * Format started column
     */
    public function col_timecreated($row)
    {
        $row = (array) $row;
        return userdate($row['timecreated'] ?? 0);
    }

    /**
     * Format completed column
     */
    public function col_timefinish($row)
    {
        $row = (array) $row;
        return !empty($row['timefinish']) ? userdate($row['timefinish']) : '-';
    }

    /**
     * Format score column
     */
    public function col_score($row)
    {
        $row = (array) $row;
        if (($row['status'] ?? '') === 'finished') {
            $score_val = round($row['score'] ?? 0, 1);
            if ($this->is_downloading()) {
                return $score_val . '%';
            }
            $color_class = ($score_val >= ($this->adaptivepractice->gradepass ?? 50)) ? 'text-success' : 'text-danger';
            return \html_writer::tag('span', $score_val . '%', ['class' => $color_class . ' font-weight-bold']);
        }
        return '-';
    }

    /**
     * Format review column
     */
    public function col_review($row)
    {
        $row = (array) $row;
        $reviewurl = new \moodle_url('/mod/adaptivepractice/attempt_summary.php', [
            'id' => $this->baseurl->param('id'),
            'attemptid' => $row['id']
        ]);
        return \html_writer::link($reviewurl, get_string('review', 'mod_adaptivepractice'), ['class' => 'btn btn-outline-primary btn-sm']);
    }

    /**
     * Override format_row to apply classes for best attempts
     */
    public function format_row($row)
    {
        $row_arr = (array) $row;
        $userid = $row_arr['userid'];
        $is_best = false;

        if (isset($this->best_map[$userid . '_id'])) {
            // Precise match by ID.
            if ($row_arr['id'] == $this->best_map[$userid . '_id']) {
                $is_best = true;
            }
        } else if (isset($this->best_map[$userid])) {
            // Fallback to score match.
            if (($row_arr['status'] ?? '') === 'finished' && (float) ($row_arr['score'] ?? 0) >= $this->best_map[$userid]) {
                $is_best = true;
            }
        }

        if ($is_best) {
            $this->row_class = 'table-primary font-weight-bold';
        } else {
            $this->row_class = '';
        }
        return parent::format_row($row);
    }

    /**
     * Override download buttons to limit to CSV, Excel, and PDF.
     */
    public function download_buttons()
    {
        global $OUTPUT;

        if ($this->is_downloadable() && !$this->is_downloading()) {
            $base = $this->baseurl->out_omit_querystring();
            $params = $this->baseurl->params();

            // Available formats: 'csv', 'excel', 'pdf'.
            $formats = [
                'csv' => 'Comma separated values (.csv)',
                'excel' => 'Microsoft Excel (.xlsx)',
                'pdf' => 'Portable Document Format (.pdf)'
            ];
            $options = [];
            foreach ($formats as $value => $label) {
                $options[] = [
                    'value' => $value,
                    'label' => $label,
                ];
            }

            $data = [
                'label' => get_string('downloadas', 'table'),
                'base' => $base,
                'name' => 'download',
                'params' => [],
                'options' => $options,
                'sesskey' => sesskey(),
                'submit' => get_string('download'),
            ];

            foreach ($params as $key => $value) {
                $data['params'][] = ['name' => $key, 'value' => $value];
            }

            return $OUTPUT->render_from_template('core/dataformat_selector', $data);
        }
        return '';
    }
}

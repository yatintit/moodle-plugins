<?php
/**
 * Renderable for the view page
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivepractice\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;

/**
 * View page renderable
 */
class view_page implements renderable, templatable
{

    /** @var stdClass $adaptivepractice activity instance */
    protected $adaptivepractice;

    /** @var stdClass $cm course module */
    protected $cm;

    /** @var array $data additional data for template */
    protected $data;

    /**
     * Constructor
     *
     * @param stdClass $adaptivepractice
     * @param stdClass $cm
     * @param array $data
     */
    public function __construct($adaptivepractice, $cm, $data = [])
    {
        $this->adaptivepractice = $adaptivepractice;
        $this->cm = $cm;
        $this->data = $data;
    }

    /**
     * Export data for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output)
    {
        $data = new stdClass();
        $data->name = format_string($this->adaptivepractice->name);
        $data->intro = format_module_intro('adaptivepractice', $this->adaptivepractice, $this->cm->id);

        // Competency info.
        $data->competency = round($this->data['competency'], 1);
        $data->difficulty = ucfirst($this->data['difficulty']);

        // Question counts.
        $data->counts = $this->data['counts'];

        // Session info.
        $data->questionsperattempt = $this->adaptivepractice->questionsperattempt;
        $data->questionsource = $this->adaptivepractice->questionsource;
        $data->is_random_source = ($this->adaptivepractice->questionsource == ADAPTIVEPRACTICE_SOURCE_RANDOM);
        if ($data->is_random_source) {
            $data->session_mode_label = "Randomized ({$this->adaptivepractice->random_easy}E, {$this->adaptivepractice->random_medium}M, {$this->adaptivepractice->random_hard}H)";
            $data->questionsperattempt = $this->adaptivepractice->random_easy + $this->adaptivepractice->random_medium + $this->adaptivepractice->random_hard;
        } else {
            $data->session_mode_label = "Tiered (Auto-distributed)";
        }

        // Navigation / Actions.
        $data->canmanage = $this->data['canmanage'] ?? false;
        if ($data->canmanage) {
            $data->questionsurl = (new moodle_url('/mod/adaptivepractice/questions.php', ['id' => $this->cm->id]))->out(false);
            $data->reporturl = (new moodle_url('/mod/adaptivepractice/report.php', ['id' => $this->cm->id]))->out(false);

            if ($this->adaptivepractice->categoryid) {
                $data->addquestionurl = (new moodle_url('/question/bank/editquestion/addquestion.php', [
                    'category' => $this->adaptivepractice->categoryid,
                    'cmid' => $this->cm->id,
                    'returnurl' => (new moodle_url('/mod/adaptivepractice/view.php', ['id' => $this->cm->id]))->out_as_local_url()
                ]))->out(false);
            }
        }

        // Start session form.
        $data->hasquestions = $this->data['has_questions'];
        $data->canstart = $this->data['can_start'];
        if ($data->hasquestions && $data->canstart) {
            $data->attempturl = (new moodle_url('/mod/adaptivepractice/attempt.php'))->out(false);
            $data->cmid = $this->cm->id;
            if ($this->data['has_inprogress']) {
                $data->startlabel = 'Resume Practice Session';
                $data->starticon = 'fa-play-circle';
            } else {
                $data->startlabel = get_string('start_attempt', 'mod_adaptivepractice');
                $data->starticon = 'fa-play';
            }
        } else if (!$data->canstart) {
            $data->attemptserror = get_string('reached_max_attempts', 'mod_adaptivepractice', $this->adaptivepractice->attempts);
        }

        // Previous attempts.
        $data->hasattempts = !empty($this->data['attempts']);
        if ($data->hasattempts) {
            $data->previousattempts = [];
            foreach ($this->data['attempts'] as $attempt) {
                $a = new stdClass();
                $a->date = userdate($attempt->timecreated);
                $a->difficulty = get_string('difficulty_' . $attempt->current_difficulty, 'mod_adaptivepractice');

                if ($attempt->status == 'finished') {
                    $score_val = round($attempt->score, 1);
                    $color_class = ($score_val >= $this->adaptivepractice->gradepass) ? 'text-success' : 'text-danger';
                    $a->score = $score_val . '%';
                    $a->scoreclass = $color_class . ' font-weight-bold';
                    $a->reviewurl = (new moodle_url('/mod/adaptivepractice/attempt_summary.php', [
                        'id' => $this->cm->id,
                        'attemptid' => $attempt->id
                    ]))->out(false);
                    $a->reviewlabel = 'Review 👁️';

                    // Badge for this attempt row.
                    global $CFG;
                    $base = $CFG->wwwroot . '/mod/adaptivepractice/pix/';
                    if ($score_val >= 90) {
                        $a->has_badge      = true;
                        $a->badge_label    = '🥇 Gold Medal';
                        $a->badge_image_url = $base . 'gold.png';
                        $a->badge_color    = '#FFD700';
                    } else if ($score_val >= 75) {
                        $a->has_badge      = true;
                        $a->badge_label    = '🥈 Silver Medal';
                        $a->badge_image_url = $base . 'silver.png';
                        $a->badge_color    = '#C0C0C0';
                    } else if ($score_val >= 60) {
                        $a->has_badge      = true;
                        $a->badge_label    = '🥉 Bronze Medal';
                        $a->badge_image_url = $base . 'bronze.png';
                        $a->badge_color    = '#CD7F32';
                    } else {
                        $a->has_badge = false;
                        $a->badge_label = '';
                        $a->badge_image_url = '';
                        $a->badge_color = '';
                    }
                } else {
                    $a->score = 'In Progress';
                    $a->scoreclass = 'text-warning font-weight-bold';
                    $a->reviewurl = (new moodle_url('/mod/adaptivepractice/attempt.php', [
                        'id' => $this->cm->id
                    ]))->out(false);
                    $a->reviewlabel = 'Resume 🚀';
                    $a->has_badge = false;
                    $a->badge_label = '';
                    $a->badge_image_url = '';
                    $a->badge_color = '';
                }

                $data->previousattempts[] = $a;
            }

            // Grading method and final grade.
            $methods = [
                ADAPTIVEPRACTICE_GRADEHIGHEST => get_string('gradehighest', 'quiz'),
                ADAPTIVEPRACTICE_GRADEAVERAGE => get_string('gradeaverage', 'quiz'),
                ADAPTIVEPRACTICE_ATTEMPTFIRST => get_string('attemptfirst', 'quiz'),
                ADAPTIVEPRACTICE_ATTEMPTLAST => get_string('attemptlast', 'quiz')
            ];
            $data->grademethod = $methods[$this->adaptivepractice->grademethod] ?? '';

            // Get final grade from gradebook.
            global $CFG, $USER;
            require_once($CFG->libdir . '/gradelib.php');
            $grade_item = \grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => 'adaptivepractice',
                'iteminstance' => $this->adaptivepractice->id,
                'courseid' => $this->adaptivepractice->course
            ]);
            $data->finalgrade = '-';
            if ($grade_item) {
                $grade_grade = \grade_grade::fetch([
                    'itemid' => $grade_item->id,
                    'userid' => $USER->id
                ]);
                if ($grade_grade && !is_null($grade_grade->finalgrade)) {
                    $data->finalgrade = round($grade_grade->finalgrade, 1) . '%';
                }
            }
        }

        return $data;
    }
}

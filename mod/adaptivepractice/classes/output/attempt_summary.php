<?php
/**
 * Renderable for attempt summary page
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
 * Attempt summary renderable
 */
class attempt_summary implements renderable, templatable
{

    /** @var stdClass $attempt */
    protected $attempt;

    /** @var stdClass $adaptivepractice */
    protected $adaptivepractice;

    /** @var stdClass $cm */
    protected $cm;

    /** @var mixed $quba */
    protected $quba;

    /** @var array $data */
    protected $data;

    /**
     * Constructor
     */
    public function __construct($attempt, $adaptivepractice, $cm, $quba, $data = [])
    {
        $this->attempt = $attempt;
        $this->adaptivepractice = $adaptivepractice;
        $this->cm = $cm;
        $this->quba = $quba;
        $this->data = $data;
    }

    /**
     * Export for template
     */
    public function export_for_template(renderer_base $output)
    {
        global $DB;

        $data = new stdClass();
        $data->backurl = $this->data['backurl'];

        $user = $DB->get_record('user', ['id' => $this->attempt->userid]);
        $data->user = new stdClass();
        $data->user->fullname = fullname($user);
        $data->user->picture = $output->user_picture($user, ['size' => 64]);
        $data->user->date = userdate($this->attempt->timecreated);

        $data->score = round($this->attempt->score, 1);
        $data->scorecolor = $this->attempt->score >= 70 ? 'text-success' : ($this->attempt->score >= 40 ? 'text-warning' : 'text-danger');
        $data->difficulty = ucfirst($this->attempt->current_difficulty);

        $data->status = ucfirst($this->attempt->status);
        $data->statusclass = ($this->attempt->status === 'finished') ? 'badge-success' : 'badge-warning';

        // Continue Practice button (shown when score < 70).
        $data->score_below_70 = ($this->attempt->score < 70);
        $data->continue_url = (new moodle_url('/mod/adaptivepractice/view.php', ['id' => $this->cm->id]))->out(false);

        // Score = 100 popup: show a congratulation message based on the current difficulty level.
        $data->score_is_100 = ($this->attempt->score >= 100);
        if ($data->score_is_100) {
            $currentDifficulty = strtolower($this->attempt->current_difficulty);
            if ($currentDifficulty === 'easy') {
                $data->popup_title = '🎉 Easy Level Passed!';
                $data->popup_message = 'Congratulations! You have passed the Easy level. You can now practice Medium level questions to continue your learning journey.';
                $data->popup_next = 'Try Medium Level';
            } else if ($currentDifficulty === 'medium') {
                $data->popup_title = '🏆 Medium Level Passed!';
                $data->popup_message = 'Amazing work! You have mastered the Medium level. Challenge yourself with Hard level questions to reach the next milestone!';
                $data->popup_next = 'Try Hard Level';
            } else if ($currentDifficulty === 'hard') {
                $data->popup_title = '🌟 Hard Level Conquered!';
                $data->popup_message = 'Outstanding! You have completed the Hard level. Keep practising across all levels to achieve full mastery!';
                $data->popup_next = 'Full Practice';
            } else {
                $data->popup_title = '🥇 Perfect Score!';
                $data->popup_message = 'Incredible! You scored 100%. Keep practising to maintain your mastery!';
                $data->popup_next = 'Continue Practising';
            }
        }

        // Badge logic: Bronze (60–74), Silver (75–89.5), Gold (≥90).
        $badge = $this->get_badge_info($this->attempt->score);
        $data->has_badge       = $badge['has_badge'];
        $data->badge_name      = $badge['name'] ?? '';
        $data->badge_image_url = $badge['image_url'] ?? '';
        $data->badge_color     = $badge['color'] ?? '';
        $data->badge_label     = $badge['label'] ?? '';

        $data->questions = [];
        $slots = $this->quba->get_slots();

        $options = new \question_display_options();
        $options->flags = \question_display_options::HIDDEN;
        $options->marks = \question_display_options::VISIBLE;
        $options->marksdp = 1;
        $options->correctness = \question_display_options::VISIBLE;
        $options->feedback = \question_display_options::VISIBLE;
        $options->generalfeedback = \question_display_options::VISIBLE;
        $options->rightanswer = \question_display_options::VISIBLE;
        $options->manualcomment = \question_display_options::VISIBLE;

        foreach ($slots as $slot) {
            $q = new stdClass();
            $q->slot = $slot;
            $mark = $this->quba->get_question_mark($slot);
            $max = $this->quba->get_question_max_mark($slot);
            $q->mark = round($mark ?? 0, 1);
            $q->max = round($max, 1);

            $is_right = ($mark !== null && $mark >= $max);
            $q->badgeclass = $is_right ? 'badge-success' : ($mark > 0 ? 'badge-warning' : 'badge-danger');

            $q->content = $this->quba->render_question($slot, $options, $slot);
            $data->questions[] = $q;
        }

        return $data;
    }

    /**
     * Return badge info array for a given score.
     *
     * Grade ranges:
     *   Bronze : 60 <= score <= 74
     *   Silver : 75 <= score <= 89.5
     *   Gold   : score >= 90
     *
     * @param float $score 0–100
     * @return array
     */
    public function get_badge_info($score)
    {
        global $CFG;
        $base = $CFG->wwwroot . '/mod/adaptivepractice/pix/';

        if ($score >= 90) {
            return [
                'has_badge' => true,
                'name'      => 'Gold Medal',
                'label'     => '🥇 Gold Medal',
                'color'     => '#FFD700',
                'image_url' => $base . 'gold.png',
            ];
        } else if ($score >= 75) {
            return [
                'has_badge' => true,
                'name'      => 'Silver Medal',
                'label'     => '🥈 Silver Medal',
                'color'     => '#C0C0C0',
                'image_url' => $base . 'silver.png',
            ];
        } else if ($score >= 60) {
            return [
                'has_badge' => true,
                'name'      => 'Bronze Medal',
                'label'     => '🥉 Bronze Medal',
                'color'     => '#CD7F32',
                'image_url' => $base . 'bronze.png',
            ];
        }

        return ['has_badge' => false];
    }
}

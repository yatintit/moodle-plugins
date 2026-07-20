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

        // Course / skill-chooser URL for the "Choose Next Skill" button.
        $data->choose_skill_url = (new moodle_url('/mod/adaptivepractice/view.php', ['id' => $this->cm->id]))->out(false);

        // Achievement popup: show for every medal earner (score >= 60) with per-difficulty messaging.
        // Popup is shown for Gold (>=90), Silver (>=75), and Bronze (>=60).
        $score         = $this->attempt->score;
        $currentDiff   = strtolower($this->attempt->current_difficulty);
        $data->score_is_100 = ($score >= 60); // Re-used mustache flag — now means "has achievement popup"

        if ($data->score_is_100) {
            // Build next-difficulty URL (pre-selects difficulty in the practice form).
            if ($currentDiff === 'easy') {
                $data->popup_title   = '🎉 Easy Level Passed!';
                $data->popup_message = 'Great job! You passed the Easy level. Ready to step it up? Try Medium level questions to continue your learning journey.';
                $data->popup_next    = 'Try Medium Level';
                $data->next_url      = (new moodle_url('/mod/adaptivepractice/attempt.php', [
                    'id'               => $this->cm->id,
                    'force_difficulty' => 'medium',
                ]))->out(false);
            } else if ($currentDiff === 'medium') {
                $data->popup_title   = '🏆 Medium Level Passed!';
                $data->popup_message = 'Amazing work! You mastered the Medium level. Challenge yourself with Hard level questions to reach the next milestone!';
                $data->popup_next    = 'Try Hard Level';
                $data->next_url      = (new moodle_url('/mod/adaptivepractice/attempt.php', [
                    'id'               => $this->cm->id,
                    'force_difficulty' => 'hard',
                ]))->out(false);
            } else if ($currentDiff === 'hard') {
                $data->popup_title   = '🌟 Hard Level Conquered!';
                $data->popup_message = 'Outstanding! You completed the Hard level. Keep practising across all levels to achieve full mastery!';
                $data->popup_next    = 'Full Practice';
                $data->next_url      = (new moodle_url('/mod/adaptivepractice/attempt.php', [
                    'id'               => $this->cm->id,
                    'force_difficulty' => 'mixed',
                ]))->out(false);
            } else {
                // Mixed / random session.
                $data->popup_title   = '🥇 Excellent Score!';
                $data->popup_message = 'Incredible result! You scored ' . round($score, 1) . '%. Keep practising to sharpen your mastery!';
                $data->popup_next    = 'Continue Practising';
                $data->next_url      = (new moodle_url('/mod/adaptivepractice/attempt.php', [
                    'id'               => $this->cm->id,
                    'force_difficulty' => 'mixed',
                ]))->out(false);
            }
        }

        // Practise Again URL — restart the same difficulty level directly.
        $data->practise_again_url = (new moodle_url('/mod/adaptivepractice/attempt.php', [
            'id'               => $this->cm->id,
            'force_difficulty' => $currentDiff ?: 'mixed',
        ]))->out(false);

        // Activity view page URL — used by "Back to Activity" button.
        $data->view_url = (new moodle_url('/mod/adaptivepractice/view.php', ['id' => $this->cm->id]))->out(false);

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
                'color'     => '#8C9196',
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

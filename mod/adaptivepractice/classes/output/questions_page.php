<?php
/**
 * Renderable for the questions management page
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

/**
 * Questions page renderable
 */
class questions_page implements renderable, templatable
{

    /** @var array $data */
    protected $data;

    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Export for template
     */
    public function export_for_template(renderer_base $output)
    {
        $data = new stdClass();
        $data->id = $this->data['id'];
        $data->backurl = $this->data['backurl'];
        $data->qbankurl = $this->data['qbankurl'];
        $data->sesskey = sesskey();
        $data->bankid = $this->data['bankid'];
        $data->current_bank_name = $this->data['current_bank_name'];
        $data->has_attempts = $this->data['has_attempts'];
        
        // Render bank switcher widget into HTML.
        $widget = new \core_question\output\switch_question_bank($this->data['id'], $this->data['courseid'], $this->data['userid']);
        $data->bankswitcher_html = $output->render($widget);

        // Selected categories list.
        $data->selected_categories_list = [];
        if (!empty($this->data['selected_categories_list'])) {
            foreach ($this->data['selected_categories_list'] as $cat) {
                $selected_cat = new stdClass();
                $selected_cat->value = $cat['value'];
                $selected_cat->name = $cat['name'];
                $selected_cat->id = $this->data['id'];
                $selected_cat->sesskey = sesskey();
                $selected_cat->has_attempts = $this->data['has_attempts'];
                $data->selected_categories_list[] = $selected_cat;
            }
        }
        $data->has_selected_categories = !empty($data->selected_categories_list);

        // Category selector data.
        $data->catgroups = [];
        foreach ($this->data['catgroups'] as $groupname => $opts) {
            $group = new stdClass();
            $group->name = $groupname;
            $group->options = [];
            foreach ($opts as $val => $label) {
                $opt = new stdClass();
                $opt->value = $val;
                $opt->label = $label;
                $opt->selected = in_array($val, $this->data['selected_vals']);
                $group->options[] = $opt;
            }
            $data->catgroups[] = $group;
        }

        // Questions data.
        $data->qbankurl = $this->data['qbankurl'];
        $data->has_categories = !empty($this->data['selected_cat_ids']);
        $data->has_no_categories = empty($this->data['selected_cat_ids']);
        $data->num_categories = count($this->data['selected_cat_ids']);
        $data->num_questions = count($this->data['filtered_questions']);
        $data->num_total = $this->data['total_count'] ?? 0;
        $data->has_questions = $data->num_total > 0;
        $data->has_no_questions = ($data->has_categories && $data->num_total === 0);
        $data->is_filtered = ($data->num_questions !== $data->num_total);

        $data->questions = [];
        $i = 1;
        foreach ($this->data['filtered_questions'] as $q) {
            $question = new stdClass();
            $question->index = $i++;
            $question->id = $q->id;
            $question->name = format_string($q->name);
            $question->category = !empty($q->parentcategoryname) ? format_string($q->parentcategoryname) . ' > ' : '';
            $question->category .= format_string($q->categoryname);
            $question->type = $q->qtype;
            $question->tag = $q->tiertag ?? '';

            $question->tiers = [
                ['value' => 'none',    'label' => '↩ Unassign (No Tier)',         'selected' => (empty($question->tag) || strtolower($question->tag) === 'none') && empty($q->is_excluded)],
                ['value' => 'easy',    'label' => '🟢 Easy',                      'selected' => strtolower($question->tag) == 'easy'],
                ['value' => 'medium',  'label' => '🟡 Medium',                    'selected' => strtolower($question->tag) == 'medium'],
                ['value' => 'hard',    'label' => '🔴 Hard',                      'selected' => strtolower($question->tag) == 'hard'],
                ['value' => 'exclude', 'label' => '❌ Exclude from Practice',     'selected' => !empty($q->is_excluded)],
            ];

            $data->questions[] = $question;
        }

        // Summary counts.
        $data->counts = $this->data['counts'];

        // Filter options.
        $data->filter = $this->data['filter'];
        $filteropts = [
            'all' => '— All Questions —',
            'easy' => '🟢 Easy',
            'medium' => '🟡 Medium',
            'hard' => '🔴 Hard',
            'unassigned' => '⚪ Unassigned',
            'excluded' => '❌ Excluded'
        ];
        $data->filteroptions = [];
        foreach ($filteropts as $val => $label) {
            $data->filteroptions[] = [
                'value' => $val,
                'label' => $label,
                'selected' => ($data->filter == $val)
            ];
        }

        $data->random_easy = $this->data['random_easy'];
        $data->random_medium = $this->data['random_medium'];
        $data->random_hard = $this->data['random_hard'];
        $data->is_random_source = ($this->data['questionsource'] == ADAPTIVEPRACTICE_SOURCE_RANDOM);

        return $data;
    }
}

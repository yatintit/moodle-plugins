<?php
/**
 * Renderable for the report page
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
 * Report page renderable
 */
class report_page implements renderable, templatable
{

    /** @var stdClass $adaptivepractice */
    protected $adaptivepractice;

    /** @var array $attempts */
    protected $attempts;

    /** @var string $tablehtml */
    protected $tablehtml;

    /** @var array $data */
    protected $data;

    /**
     * Constructor
     */
    public function __construct($adaptivepractice, $attempts, $tablehtml, $data = [])
    {
        $this->adaptivepractice = $adaptivepractice;
        $this->attempts = $attempts;
        $this->tablehtml = $tablehtml;
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
        $data->tablehtml = $this->tablehtml;
        $data->downloadhtml = $this->data['downloadhtml'] ?? '';
        $data->initialbars = $this->data['initialbars'] ?? '';
        $data->stats = $this->data['stats'] ?? null;
        $data->sesskey = $this->data['sesskey'] ?? '';

        // Process filters.
        if (isset($this->data['filters'])) {
            $data->filters = new stdClass();
            $data->filters->user = [];
            foreach ($this->data['filters']['user']['options'] as $val => $label) {
                $data->filters->user[] = [
                    'value' => $val,
                    'label' => $label,
                    'selected' => ($val == $this->data['filters']['user']['selected'])
                ];
            }

            $data->filters->status = [];
            foreach ($this->data['filters']['status']['options'] as $val => $label) {
                $data->filters->status[] = [
                    'value' => $val,
                    'label' => $label,
                    'selected' => ($val == $this->data['filters']['status']['selected'])
                ];
            }
        }

        return $data;
    }
}

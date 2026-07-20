<?php
/**
 * Renderer for Adaptive Practice
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivepractice\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use renderable;

/**
 * Renderer class
 */
class renderer extends plugin_renderer_base
{

    /**
     * Render the view page.
     *
     * @param \mod_adaptivepractice\output\view_page $page
     * @return string
     */
    public function render_view_page(\mod_adaptivepractice\output\view_page $page)
    {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_adaptivepractice/view_page', $data);
    }

    /**
     * Render the attempt summary page.
     *
     * @param \mod_adaptivepractice\output\attempt_summary $page
     * @return string
     */
    public function render_attempt_summary(\mod_adaptivepractice\output\attempt_summary $page)
    {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_adaptivepractice/attempt_summary', $data);
    }

    /**
     * Render the report page.
     *
     * @param \mod_adaptivepractice\output\report_page $page
     * @return string
     */
    public function render_report_page(\mod_adaptivepractice\output\report_page $page)
    {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_adaptivepractice/report_page', $data);
    }

    /**
     * Render the questions management page.
     *
     * @param \mod_adaptivepractice\output\questions_page $page
     * @return string
     */
    public function render_questions_page(\mod_adaptivepractice\output\questions_page $page)
    {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_adaptivepractice/questions_page', $data);
    }
}

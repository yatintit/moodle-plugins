<?php
/**
 * Event for attempt started
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivepractice\event;

defined('MOODLE_INTERNAL') || die();

class attempt_started extends \core\event\base
{
    protected function init()
    {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'adaptivepractice_attempts';
    }

    public static function get_name()
    {
        return "Practice attempt started";
    }

    public function get_description()
    {
        return "The user with id '$this->userid' started an adaptive practice attempt for the activity with id '$this->instanceid'.";
    }

    public function get_url()
    {
        return new \moodle_url('/mod/adaptivepractice/attempt.php', array('id' => $this->contextinstanceid));
    }
}

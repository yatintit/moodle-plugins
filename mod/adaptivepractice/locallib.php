<?php
/**
 * Local library for Adaptive Practice
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_adaptivepractice\helper;

/**
 * Get the current competency for a user.
 * @deprecated since 0.1.2 - use \mod_adaptivepractice\helper::get_user_competency instead
 */
function adaptivepractice_get_user_competency($practiceid, $userid)
{
    return helper::get_user_competency($practiceid, $userid);
}

/**
 * Update the user's competency.
 * @deprecated since 0.1.2 - use \mod_adaptivepractice\helper::update_user_competency instead
 */
function adaptivepractice_update_user_competency($practiceid, $userid, $new_score)
{
    helper::update_user_competency($practiceid, $userid, $new_score);
}

/**
 * Select questions based on difficulty level and tags.
 * @deprecated since 0.1.2 - use \mod_adaptivepractice\helper::get_questions instead
 */
function adaptivepractice_get_questions($categoryids, $difficulty_level, $count)
{
    return helper::get_questions($categoryids, $difficulty_level, $count);
}

/**
 * Get questions from all levels (Easy, Medium, Hard).
 * @deprecated since 0.1.2 - use \mod_adaptivepractice\helper::get_progressive_questions instead
 */
function adaptivepractice_get_progressive_questions($categoryids, $count)
{
    return helper::get_progressive_questions($categoryids, $count);
}

/**
 * Get all category IDs assigned to activity.
 * @deprecated since 0.1.2 - use \mod_adaptivepractice\helper::get_category_ids instead
 */
function adaptivepractice_get_category_ids($adaptivepracticeid)
{
    return helper::get_category_ids($adaptivepracticeid);
}

/**
 * Start a new question engine usage.
 * @deprecated since 0.1.2 - use \mod_adaptivepractice\helper::start_usage instead
 */
function adaptivepractice_start_usage($context)
{
    return helper::start_usage($context);
}

/**
 * Add questions to a usage.
 * @deprecated since 0.1.2 - use \mod_adaptivepractice\helper::add_questions_to_usage instead
 */
function adaptivepractice_add_questions_to_usage($quba, $questions)
{
    return helper::add_questions_to_usage($quba, $questions);
}

/**
 * Process attempt submission.
 * @deprecated since 0.1.2 - use \mod_adaptivepractice\helper::process_attempt instead
 */
function adaptivepractice_process_attempt($attemptid)
{
    return helper::process_attempt($attemptid);
}

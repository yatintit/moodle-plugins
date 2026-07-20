<?php

/**
 * Adaptive Practice Activity version information
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_adaptivepractice';
$plugin->version = 2026030905; // Added Random Counts support.
$plugin->requires = 2022041900; // Requires Moodle 4.0 minimum
$plugin->supported = [400, 502]; // Supported from Moodle 4.0 to 5.2+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v0.1.1';

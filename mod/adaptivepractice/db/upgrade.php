<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Adaptive Practice module upgrade code
 *
 * @package   mod_adaptivepractice
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute Adaptive Practice module upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_adaptivepractice_upgrade($oldversion)
{
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026022512) {
        // Define table adaptivepractice_categories to be created.
        $table = new xmldb_table('adaptivepractice_categories');

        // Adding fields to table adaptivepractice_categories.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('adaptivepracticeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table adaptivepractice_categories.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('adaptivepracticeid', XMLDB_KEY_FOREIGN, array('adaptivepracticeid'), 'adaptivepractice', array('id'));

        // Conditionally create the table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Save progress.
        upgrade_mod_savepoint(true, 2026022513, 'adaptivepractice');
    }

    if ($oldversion < 2026022601) {
        // Define table adaptivepractice to be modified.
        $table = new xmldb_table('adaptivepractice');

        // Adding fields to table adaptivepractice.
        $field = new xmldb_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'competency_scale');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('gradepass', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0.00000', 'attempts');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Save progress.
        upgrade_mod_savepoint(true, 2026022601, 'adaptivepractice');
    }

    if ($oldversion < 2026022602) {
        // Define table adaptivepractice_attempts to be modified.
        $table = new xmldb_table('adaptivepractice_attempts');

        // Adding fields to table adaptivepractice_attempts.
        $field = new xmldb_field('timefinish', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'score');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Save progress.
        upgrade_mod_savepoint(true, 2026022602, 'adaptivepractice');
    }

    if ($oldversion < 2026030901) {
        // Define table adaptivepractice to be modified.
        $table = new xmldb_table('adaptivepractice');

        // Adding fields to table adaptivepractice.
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'gradepass');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Save progress.
        upgrade_mod_savepoint(true, 2026030901, 'adaptivepractice');
    }

    if ($oldversion < 2026030902) {
        // Define table adaptivepractice to be modified.
        $table = new xmldb_table('adaptivepractice');

        // Adding fields to table adaptivepractice.
        $field_src = new xmldb_field('questionsource', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'grademethod');
        if (!$dbman->field_exists($table, $field_src)) {
            $dbman->add_field($table, $field_src);
        }
        $field_re = new xmldb_field('random_easy', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'questionsource');
        if (!$dbman->field_exists($table, $field_re)) {
            $dbman->add_field($table, $field_re);
        }
        $field_rm = new xmldb_field('random_medium', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'random_easy');
        if (!$dbman->field_exists($table, $field_rm)) {
            $dbman->add_field($table, $field_rm);
        }
        $field_rh = new xmldb_field('random_hard', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'random_medium');
        if (!$dbman->field_exists($table, $field_rh)) {
            $dbman->add_field($table, $field_rh);
        }

        // Save progress.
        upgrade_mod_savepoint(true, 2026030902, 'adaptivepractice');
    }

    return true;
}

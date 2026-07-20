<?php
/**
 * CLI script to check question counts.
 *
 * @package    mod_adaptivepractice
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array('help' => false),
    array('h' => 'help')
);

if ($options['help']) {
    $help =
        "Quickly check question counts across categories.
Options:
-h, --help            Print out this help
";
    echo $help;
    die;
}

cli_writeln("Category ID | Question Count");
cli_writeln("--------------------------");

$counts = $DB->get_records_sql("SELECT qbe.questioncategoryid, COUNT(*) as c FROM {question_bank_entries} qbe GROUP BY qbe.questioncategoryid");
foreach ($counts as $cat) {
    cli_writeln(sprintf("%-11d | %-14d", $cat->questioncategoryid, $cat->c));
}

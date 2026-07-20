<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/helper.php');

$cmid = 30; 
$cm = get_coursemodule_from_id('adaptivepractice', $cmid);
echo "Using CMID: {$cmid}\n";
$categoryids = \mod_adaptivepractice\helper::get_category_ids($cm->instance);

print_r($categoryids);

$counts = \mod_adaptivepractice\helper::get_difficulty_counts($categoryids);
print_r($counts);

if (empty($categoryids)) {
    echo "No categories found for this instance.\n";
    exit;
}

list($insql, $params) = $DB->get_in_or_equal($categoryids);
$sql = "SELECT qbe.id, t.name, qbe.questioncategoryid
          FROM {question_bank_entries} qbe
          JOIN {tag_instance} ti ON ti.itemid = qbe.id
          JOIN {tag} t ON t.id = ti.tagid
         WHERE qbe.questioncategoryid {$insql}
           AND ti.component = 'core_question'
           AND ti.itemtype = 'question_bank_entry'
         ORDER BY qbe.id";
$records = $DB->get_records_sql($sql, $params);
foreach ($records as $r) {
    echo "QBE: {$r->id}, Tag: {$r->name}, Cat: {$r->questioncategoryid}\n";
}

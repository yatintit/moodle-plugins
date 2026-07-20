<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$moduleid = $DB->get_field('modules', 'id', ['name' => 'adaptivepractice']);
$cm = $DB->get_record_sql("SELECT cm.id FROM {course_modules} cm 
                        JOIN {adaptivepractice} ap ON ap.id = cm.instance
                        JOIN {adaptivepractice_categories} apc ON apc.adaptivepracticeid = ap.id
                        WHERE cm.module = ? LIMIT 1", [$moduleid]);

if (!$cm) {
    // If no extra categories, just take the first one with a categoryid.
    $cm = $DB->get_record_sql("SELECT cm.id FROM {course_modules} cm 
                        JOIN {adaptivepractice} ap ON ap.id = cm.instance
                        WHERE cm.module = ? AND ap.categoryid > 0 LIMIT 1", [$moduleid]);
}

if ($cm) {
    echo $cm->id;
} else {
    echo "0";
}

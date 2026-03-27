<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

global $PAGE, $USER;
$USER = get_admin();
$PAGE->set_context(context_course::instance(2));
$PAGE->set_course(get_course(2));
$PAGE->set_pagetype('course-view-topics');
$PAGE->set_url(new moodle_url('/course/view.php', ['id' => 2]));

$output = local_sm_graphics_plugin_before_footer();
echo 'Output length: ' . strlen($output) . PHP_EOL;
if (strlen($output) > 0) {
    echo 'Contains source div: ' . (strpos($output, 'smgp-course-page-source') !== false ? 'YES' : 'NO') . PHP_EOL;
} else {
    echo 'No output generated!' . PHP_EOL;
}

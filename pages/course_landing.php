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
 * Course landing page — shows program content and info before entering the course.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();

$courseid = required_param('id', PARAM_INT);

global $CFG, $OUTPUT, $PAGE, $DB;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/course_landing.php', ['id' => $courseid]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');

$renderer = new \local_sm_graphics_plugin\output\course_landing_renderer();
$context = $renderer->get_context($courseid);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/course_landing', $context);

$PAGE->requires->js_call_amd('local_sm_graphics_plugin/course_landing', 'init', [$courseid]);

echo $OUTPUT->footer();

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
 * My Courses page — enrolled courses with progress, continue learning.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();

use theme_smartmind\dashboard_helper;

global $CFG, $OUTPUT, $PAGE, $USER, $DB;

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/mycourses.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('mycourses_title', 'local_sm_graphics_plugin'));
$PAGE->set_heading(get_string('mycourses_title', 'local_sm_graphics_plugin'));
$PAGE->set_pagelayout('standard');

// Completed courses.
$rawcompleted = \local_sm_graphics_plugin\external\get_completed_courses::execute();
$completedcourses = dashboard_helper::format_finished_courses($rawcompleted);
$completedids = array_column($rawcompleted, 'id');

// Enrolled courses — active only (excludes completed).
$rawcourses = enrol_get_my_courses('*', 'fullname ASC');
$enrolledcourses = dashboard_helper::format_active_courses($rawcourses, $completedids);

// Enrich each enrolled course with continue-learning URL and category/shortname.
foreach ($enrolledcourses as &$ec) {
    $courseid = $ec['id'];
    $course = get_course($courseid);

    // Shortname.
    $ec['shortname'] = format_string($course->shortname);

    // Catalogue category name.
    $catlink = $DB->get_record('local_smgp_course_category', ['courseid' => $courseid]);
    $ec['categoryname'] = '';
    if ($catlink) {
        $cat = $DB->get_record('local_smgp_categories', ['id' => $catlink->categoryid]);
        if ($cat) {
            $ec['categoryname'] = format_string($cat->name);
        }
    }

    // Activity progress: "Recurso X de Y".
    $modinfo = get_fast_modinfo($course);
    $completion = new completion_info($course);
    $cms = $modinfo->get_cms();
    $totalactivities = 0;
    $completedactivities = 0;
    foreach ($cms as $cm) {
        if (!$cm->uservisible || $cm->modname === 'label') {
            continue;
        }
        $totalactivities++;
        $compdata = $completion->get_data($cm, true, $USER->id);
        if ($compdata->completionstate != COMPLETION_INCOMPLETE) {
            $completedactivities++;
        }
    }
    $ec['completed_activities'] = $completedactivities;
    $ec['total_activities'] = $totalactivities;

    // Continue URL — same logic as dashboard continue learning.
    $ec['continueurl'] = (new moodle_url('/course/view.php', ['id' => $courseid, 'smgp_enter' => 1]))->out(false);

    // Find last viewed activity to build smgp_cmid param.
    $lastcmid = $DB->get_field_sql(
        "SELECT contextinstanceid
           FROM {logstore_standard_log}
          WHERE courseid = :cid AND userid = :uid
            AND action = 'viewed' AND target = 'course_module'
         ORDER BY timecreated DESC
         LIMIT 1",
        ['cid' => $courseid, 'uid' => $USER->id]
    );
    if ($lastcmid) {
        $ec['continueurl'] = (new moodle_url('/course/view.php', [
            'id' => $courseid,
            'smgp_enter' => 1,
            'smgp_cmid' => (int)$lastcmid,
        ]))->out(false);
    }

    $ec['status'] = 'inprogress';
}
unset($ec);

// Mark completed courses.
foreach ($completedcourses as &$cc) {
    $courseid = $cc['id'];
    $course = get_course($courseid);
    $cc['shortname'] = format_string($course->shortname);

    $catlink = $DB->get_record('local_smgp_course_category', ['courseid' => $courseid]);
    $cc['categoryname'] = '';
    if ($catlink) {
        $cat = $DB->get_record('local_smgp_categories', ['id' => $catlink->categoryid]);
        if ($cat) {
            $cc['categoryname'] = format_string($cat->name);
        }
    }

    $cc['progress'] = 100;
    $cc['hasprogress'] = true;
    $modinfo = get_fast_modinfo($course);
    $totalactivities = 0;
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->uservisible && $cm->modname !== 'label') {
            $totalactivities++;
        }
    }
    $cc['completed_activities'] = $totalactivities;
    $cc['total_activities'] = $totalactivities;
    $cc['continueurl'] = (new moodle_url('/course/view.php', ['id' => $courseid, 'smgp_enter' => 1]))->out(false);
    $cc['status'] = 'completed';
}
unset($cc);

$allcourses = array_merge($enrolledcourses, $completedcourses);

$context = [
    'enrolledcourses'     => $enrolledcourses,
    'hasenrolledcourses'  => !empty($enrolledcourses),
    'completedcourses'    => $completedcourses,
    'hascompletedcourses' => !empty($completedcourses),
    'allcourses'          => $allcourses,
    'hasallcourses'       => !empty($allcourses),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/mycourses_page', $context);
echo $OUTPUT->footer();

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
 * Custom dashboard layout for SmartMind theme.
 *
 * @package   theme_smartmind
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
theme_smartmind_rename_primary_nav($primarymenu);

// Company manager sidebar + student nav injection.
$companymanagernav = theme_smartmind_get_companymanager_nav();
theme_smartmind_inject_student_nav($primarymenu, $companymanagernav, $PAGE);

$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions()
    && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

// Override page heading to match nav label.
$PAGE->set_heading(get_string('nav_dashboard', 'local_sm_graphics_plugin'));

// Dashboard course lists.
use theme_smartmind\dashboard_helper;

$enrolledcourses    = [];
$completedcourses   = [];
$viewedcourses      = [];
$recommendedcourses = [];
$continuelearning   = [];

if (isloggedin() && !isguestuser()) {
    // Completed courses — from course_completions table.
    $rawcompleted = \local_sm_graphics_plugin\external\get_completed_courses::execute();
    $completedcourses = dashboard_helper::format_finished_courses($rawcompleted);
    $completedids = array_column($rawcompleted, 'id');

    // Enrolled courses — active only (excludes completed).
    $rawcourses = enrol_get_my_courses('*', 'fullname ASC');
    $enrolledcourses = dashboard_helper::format_active_courses($rawcourses, $completedids);

    // Browsed courses — visited landing page but not enrolled.
    $rawbrowsed = \local_sm_graphics_plugin\external\get_browsed_courses::execute(6);
    $viewedcourses = dashboard_helper::format_browsed_courses($rawbrowsed);

    // TODO: Replace with real API call for recommended courses.
    $recommendedcourses = dashboard_helper::get_fake_recommended_courses();

    // Continue Learning — last accessed course + last viewed activity.
    $lastaccessed = \local_sm_graphics_plugin\external\get_last_accessed::execute();
    if ($lastaccessed['course']) {
        $continuelearning = dashboard_helper::format_continue_learning($lastaccessed['course'], $lastaccessed['cminfo']);
    }
}

// User full name for the welcome message.
global $USER;
$userfullname = fullname($USER);

// Enrolled count for dashboard subtitle.
$enrolledcount = count($enrolledcourses);
$completedcount = count($completedcourses);

// Stats: training hours placeholder (Moodle doesn't track this natively).
$stathours = '—';

// Catalog URL for "view all" links.
$catalogurl = (new moodle_url('/'))->out(false);

$templatecontext = [
    'sitename' => format_string(
        $SITE->shortname, true,
        ['context' => context_course::instance(SITEID), "escape" => false]
    ),
    'userfullname' => $userfullname,
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'iscompanymanager' => !empty($companymanagernav),
    'companymanagernav' => $companymanagernav,
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    'isdashboard' => true,
    'enrolledcount' => $enrolledcount,
    'catalogurl' => $catalogurl,
    // Stats row.
    'stat_enrolled' => $enrolledcount,
    'stat_completed' => $completedcount,
    'stat_hours' => $stathours,
    'stat_certs' => $completedcount,
    // Continue learning card (kept for backwards compat, no longer rendered as hero).
    'continuelearning_data' => $continuelearning,
    'hascontinuelearning' => !empty($continuelearning),
    // Dashboard course lists — each section gets its own array.
    // enrolled: real data from enrol_get_my_courses.
    // completed, viewed, recommended: populated later via their own data sources.
    'enrolledcourses' => $enrolledcourses,
    'hasenrolledcourses' => !empty($enrolledcourses),
    'enrolledcoursesscrollable' => count($enrolledcourses) > 3,
    'completedcourses' => $completedcourses,
    'hascompletedcourses' => !empty($completedcourses),
    'completedcoursesscrollable' => count($completedcourses) > 3,
    'viewedcourses' => $viewedcourses,
    'hasviewedcourses' => !empty($viewedcourses),
    'viewedcoursesscrollable' => count($viewedcourses) > 3,
    'recommendedcourses' => $recommendedcourses,
    'hasrecommendedcourses' => !empty($recommendedcourses),
    'recommendedcoursesscrollable' => count($recommendedcourses) > 3,
];

echo $OUTPUT->render_from_template('theme_smartmind/mydashboard', $templatecontext);

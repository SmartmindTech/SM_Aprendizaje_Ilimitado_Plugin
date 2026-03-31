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
 * A drawer based layout for the boost theme.
 *
 * @package   theme_smartmind
 * @copyright 2021 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB;

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = false;
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

// Company manager custom sidebar navigation (needed early for student nav check).
$companymanagernav = theme_smartmind_get_companymanager_nav();

// Inject "Grades & Certificates" nav for students only.
theme_smartmind_inject_student_nav($primarymenu, $companymanagernav, $PAGE);

// Ensure only one nav item is active — custom items (sm-*) take priority.
theme_smartmind_fix_active_nav($primarymenu);

// DEBUG: dump user menu structure to a temp file.
file_put_contents(__DIR__ . '/usermenu_debug.json', json_encode($primarymenu['user'], JSON_PRETTY_PRINT));
// theme_smartmind_filter_usermenu($primarymenu);

$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);
if ($PAGE->pagelayout === 'frontpage' && !empty($headercontent['title'])) {
    $headercontent['title'] = get_string('coursecatalogue', 'theme_smartmind');
}

$iscourseall = strpos($PAGE->pagetype, 'course-view-') === 0
    && $PAGE->course->id != SITEID
    && $PAGE->pagelayout === 'course';

$isdashboard = $PAGE->pagelayout === 'mydashboard';
$isfrontpage = $PAGE->pagelayout === 'frontpage';

// Course banner — shown on all course-related pages.
$iscoursepage = $PAGE->course->id != SITEID
    && in_array($PAGE->pagelayout, ['course', 'incourse', 'report', 'admin']);
$coursebanner = [];
if ($iscoursepage) {
    $bannercontext = context_course::instance($PAGE->course->id);
    $bannercourse = $PAGE->course;
    $bannercourseobj = new core_course_list_element($bannercourse);

    // Course image — try cache first, then overview files.
    $bannerimage = \core_course\external\course_summary_exporter::get_course_image($bannercourse);
    if (empty($bannerimage)) {
        $bannerimage = '';
        foreach ($bannercourseobj->get_course_overviewfiles() as $file) {
            $bannerimage = moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                null, $file->get_filepath(), $file->get_filename()
            )->out();
            break;
        }
    }

    // Category name.
    $bannercategory = core_course_category::get($bannercourse->category, IGNORE_MISSING);
    $bannercategoryname = $bannercategory ? $bannercategory->get_formatted_name() : '';

    // Teacher names.
    $bannerteachers = get_enrolled_users($bannercontext, 'moodle/course:editcoursecontent');
    $bannerteachernames = [];
    foreach ($bannerteachers as $t) {
        $bannerteachernames[] = fullname($t);
    }

    // Student count.
    $bannerstudentcount = count_enrolled_users($bannercontext);

    // Dates.
    $bannerstartdate = $bannercourse->startdate ? userdate($bannercourse->startdate, '%d/%m/%y') : '';
    $bannertimemodified = $bannercourse->timemodified ? userdate($bannercourse->timemodified, '%d/%m/%y') : '';

    $coursebanner = [
        'fullname' => format_string($bannercourse->fullname),
        'courseimage' => $bannerimage,
        'hascourseimage' => !empty($bannerimage),
        'categoryname' => $bannercategoryname,
        'teachername' => !empty($bannerteachernames) ? implode(', ', $bannerteachernames) : '',
        'startdate' => $bannerstartdate,
        'timemodified' => $bannertimemodified,
        'studentcount' => $bannerstudentcount,
    ];
}

// Course categories for frontpage catalogue (from DB).
$coursecategories = [];
if ($isfrontpage) {
    $dbcats = $DB->get_records('local_smgp_categories', null, 'sortorder ASC');
    // DEBUG: remove after confirming images load.
    if (debugging()) {
        foreach ($dbcats as $tmpcat) {
            error_log('SMGP_CAT: id=' . $tmpcat->id . ' name=' . $tmpcat->name . ' image_url=' . $tmpcat->image_url);
        }
    }
    foreach ($dbcats as $cat) {
        $catimage = '';
        if (!empty($cat->image_url)) {
            $catimage = $CFG->wwwroot . '/theme/smartmind/pix/categories/' . $cat->image_url . '.jpg';
        }
        $coursecategories[] = [
            'id'    => $cat->id,
            'name'  => format_string($cat->name),
            'image' => $catimage,
        ];
    }
}

// Fetch all visible courses for frontpage catalogue.
$allcourses = [];
if ($isfrontpage) {
    $courses = get_courses();
    foreach ($courses as $course) {
        if ($course->id == SITEID || !$course->visible) {
            continue;
        }
        $courseobj = new core_course_list_element($course);
        $courseimage = '';
        foreach ($courseobj->get_course_overviewfiles() as $file) {
            $courseimage = moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                null, $file->get_filepath(), $file->get_filename()
            )->out();
            break;
        }
        // TODO: implement real enrolment check and enrol action.
        $isenrolled = isloggedin() && !isguestuser() && is_enrolled(context_course::instance($course->id));
        $isloggedin = isloggedin() && !isguestuser();

        // Get enrolled user count
        $context = context_course::instance($course->id);
        $enrolledcount = count_enrolled_users($context);

        // Get teachers (name + count)
        $teachers = get_enrolled_users($context, 'mod/assigment:grade');
        $teachercount = count($teachers);
        $teachername = '';
        if (!empty($teachers)) {
            $firstteacher = reset($teachers);
            $teachername = fullname($firstteacher);
        }

        // Get activity count
        $modinfo = get_fast_modinfo($course);
        $activitycount = count($modinfo->get_cms());

        // Get course dates
        $startdate = $course->startdate ? userdate($course->startdate, '%d %b %Y') : '';
        $enddate = $course->enddate ? userdate($course->enddate, '%d %b %Y') : '';

        // Get catalogue category for this course.
        $coursecatrec = $DB->get_record('local_smgp_course_category', ['courseid' => $course->id]);
        $coursecatid = $coursecatrec ? $coursecatrec->categoryid : 0;
        $coursecatname = '';
        if ($coursecatid && !empty($coursecategories)) {
            foreach ($coursecategories as $cc) {
                if ($cc['id'] == $coursecatid) {
                    $coursecatname = $cc['name'];
                    break;
                }
            }
        }

        // Consider a course "new" if created within the last 30 days.
        $isnew = ($course->timecreated > (time() - 30 * DAYSECS));

        $allcourses[] = [
            'id' => $course->id,
            'categoryid' => $coursecatid,
            'categoryname' => $coursecatname,
            'shortname' => format_string($course->shortname),
            'fullname' => format_string($course->fullname),
            'summary' => format_text($course->summary, $course->summaryformat, ['noclean' => true, 'para' => false]),
            'courseimage' => $courseimage,
            'viewurl' => (new moodle_url('/local/sm_graphics_plugin/pages/course_landing.php', ['id' => $course->id]))->out(),
            'isenrolled' => $isenrolled,
            'isloggedin' => $isloggedin,
            'enrollurl' => (new moodle_url('/enrol/index.php', ['id' => $course->id]))->out(),
            'teachercount' => $teachercount,
            'teachername' => $teachername,
            'studentcount' => $enrolledcount,
            'activitycount' => $activitycount,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'isnew' => $isnew,
        ];
    }
}

// Fetch enrolled courses for dashboard "continue learning" widget.
$mycourses = [];
if ($isdashboard && isloggedin() && !isguestuser()) {
    $enrolledcourses = enrol_get_my_courses('*', 'fullname ASC');
    foreach ($enrolledcourses as $course) {
        if (!$course->visible) {
            continue;
        }
        $courseobj = new core_course_list_element($course);
        $courseimage = '';
        foreach ($courseobj->get_course_overviewfiles() as $file) {
            $courseimage = moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                null, $file->get_filepath(), $file->get_filename()
            )->out();
            break;
        }
        $coursecontext = context_course::instance($course->id);
        $enrolledcount = count_enrolled_users($coursecontext);
        $modinfo = get_fast_modinfo($course);
        $activitycount = count($modinfo->get_cms());
        $startdate = $course->startdate ? userdate($course->startdate, '%d %b %Y') : '';
        $enddate = $course->enddate ? userdate($course->enddate, '%d %b %Y') : '';

        $mycourses[] = [
            'id' => $course->id,
            'fullname' => format_string($course->fullname),
            'summary' => format_text($course->summary, $course->summaryformat, ['noclean' => true, 'para' => false]),
            'courseimage' => $courseimage,
            'viewurl' => (new moodle_url('/local/sm_graphics_plugin/pages/course_landing.php', ['id' => $course->id]))->out(),
            'isenrolled' => true,
            'isloggedin' => true,
            'enrollurl' => '',
            'teachercount' => 0,
            'studentcount' => $enrolledcount,
            'activitycount' => $activitycount,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ];
    }
}

// $companymanagernav already defined above (before student nav injection).

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
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
    'iscourseall' => $iscourseall,
    'isdashboard' => $isdashboard,
    'isfrontpage' => $isfrontpage,
    'allcourses' => $allcourses,
    'hasallcourses' => !empty($allcourses),
    'coursecount' => count($allcourses),
    'coursecategories' => $coursecategories,
    'hascoursecategories' => !empty($coursecategories),
    'mycourses' => $mycourses,
    'hasmycourses' => !empty($mycourses),
    'iscoursepage' => $iscoursepage,
    'coursebanner' => $coursebanner,
    'companyselector' => theme_smartmind_get_company_selector(),
    'hascompanyselector' => !empty(theme_smartmind_get_company_selector()),
];

echo $OUTPUT->render_from_template('theme_smartmind/drawers', $templatecontext);

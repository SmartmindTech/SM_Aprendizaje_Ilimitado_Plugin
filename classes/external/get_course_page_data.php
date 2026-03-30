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

namespace local_sm_graphics_plugin\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * Returns course page data (wraps course_page_renderer::get_context).
 */
class get_course_page_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $context = \context_system::instance();
        self::validate_context($context);

        // The course_page_renderer reads from $PAGE->course, so set it up.
        $course = get_course($courseid);
        $PAGE->set_course($course);

        $renderer = new \local_sm_graphics_plugin\output\course_page_renderer();
        return $renderer->get_context();
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'            => new external_value(PARAM_INT, 'Course ID'),
            'coursename'          => new external_value(PARAM_TEXT, 'Course full name'),
            'courseshortname'     => new external_value(PARAM_TEXT, 'Course short name'),
            'coursesummary'       => new external_value(PARAM_RAW, 'Course summary HTML'),
            'hassummary'          => new external_value(PARAM_BOOL, 'Has summary content'),
            'dashboardurl'        => new external_value(PARAM_RAW, 'Dashboard URL'),
            'mycoursesurl'        => new external_value(PARAM_RAW, 'My courses URL'),
            'teachercount'        => new external_value(PARAM_INT, 'Number of teachers'),
            'studentcount'        => new external_value(PARAM_INT, 'Number of students'),
            'activitycount'       => new external_value(PARAM_INT, 'Total activity count'),
            'sectioncount'        => new external_value(PARAM_INT, 'Number of sections'),
            'overallprogress'     => new external_value(PARAM_INT, 'Overall progress 0-100'),
            'completedactivities' => new external_value(PARAM_INT, 'Number of completed activities'),
            'totalactivities'     => new external_value(PARAM_INT, 'Total number of activities'),
            'teachers'            => new external_multiple_structure(
                new external_single_structure([
                    'fullname'  => new external_value(PARAM_TEXT, 'Teacher full name'),
                    'role'      => new external_value(PARAM_TEXT, 'Teacher role name'),
                    'avatarurl' => new external_value(PARAM_RAW, 'Teacher avatar URL'),
                ])
            ),
            'hasteachers'         => new external_value(PARAM_BOOL, 'Has teachers'),
            'sections'            => new external_multiple_structure(
                new external_single_structure([
                    'id'             => new external_value(PARAM_INT, 'Section ID'),
                    'number'         => new external_value(PARAM_INT, 'Section number'),
                    'name'           => new external_value(PARAM_TEXT, 'Section name'),
                    'summary'        => new external_value(PARAM_RAW, 'Section summary HTML'),
                    'completedcount' => new external_value(PARAM_INT, 'Completed activities in section'),
                    'totalcount'     => new external_value(PARAM_INT, 'Total trackable activities in section'),
                    'progress'       => new external_value(PARAM_INT, 'Section progress 0-100'),
                    'activities'     => new external_multiple_structure(
                        new external_single_structure([
                            'cmid'              => new external_value(PARAM_INT, 'Course module ID'),
                            'name'              => new external_value(PARAM_TEXT, 'Activity name'),
                            'modname'           => new external_value(PARAM_TEXT, 'Module name'),
                            'modtypelabel'      => new external_value(PARAM_TEXT, 'Translated module type label'),
                            'url'               => new external_value(PARAM_RAW, 'Activity URL'),
                            'iconclass'         => new external_value(PARAM_TEXT, 'Icon CSS class'),
                            'iscomplete'        => new external_value(PARAM_BOOL, 'Is activity complete'),
                            'islabel'           => new external_value(PARAM_BOOL, 'Is a label activity'),
                            'isforum'           => new external_value(PARAM_BOOL, 'Is a forum activity'),
                            'visible'           => new external_value(PARAM_BOOL, 'Is visible'),
                            'index'             => new external_value(PARAM_INT, 'Global activity index'),
                            'duration'          => new external_value(PARAM_INT, 'Duration in seconds'),
                            'sectionname'       => new external_value(PARAM_TEXT, 'Parent section name'),
                            'sectionindex'      => new external_value(PARAM_INT, 'Parent section index'),
                            'resourceindex'     => new external_value(PARAM_INT, 'Resource index within section'),
                            'sectiontotalcount' => new external_value(PARAM_INT, 'Total activities in parent section'),
                        ])
                    ),
                    'hasactivities'  => new external_value(PARAM_BOOL, 'Has activities'),
                    'isfirst'        => new external_value(PARAM_BOOL, 'Is the first section'),
                    'hastrackable'   => new external_value(PARAM_BOOL, 'Has trackable activities'),
                ])
            ),
            'sectionsjson'        => new external_value(PARAM_RAW, 'Sections data as JSON string'),
            'grades'              => new external_multiple_structure(
                new external_single_structure([
                    'name'         => new external_value(PARAM_TEXT, 'Grade item name'),
                    'grade'        => new external_value(PARAM_TEXT, 'Grade display string'),
                    'grademax'     => new external_value(PARAM_FLOAT, 'Maximum grade'),
                    'percentage'   => new external_value(PARAM_INT, 'Grade percentage 0-100'),
                    'hasgrade'     => new external_value(PARAM_BOOL, 'Has a grade value'),
                    'activitytype' => new external_value(PARAM_TEXT, 'Activity module type'),
                ])
            ),
            'hasgrades'           => new external_value(PARAM_BOOL, 'Has grade items'),
            'coursetotal'         => new external_value(PARAM_TEXT, 'Course total grade string'),
            'hascoursetotal'      => new external_value(PARAM_BOOL, 'Has course total grade'),
            'canpost'             => new external_value(PARAM_BOOL, 'Can post comments'),
            'candeleteany'        => new external_value(PARAM_BOOL, 'Can delete any comment'),
            'isteacher'           => new external_value(PARAM_BOOL, 'Is a teacher'),
            'userid'              => new external_value(PARAM_INT, 'Current user ID'),
            'userfullname'        => new external_value(PARAM_TEXT, 'Current user full name'),
        ]);
    }
}

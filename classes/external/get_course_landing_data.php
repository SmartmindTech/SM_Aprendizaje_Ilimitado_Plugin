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
 * Returns course landing page data (wraps course_landing_renderer::get_context).
 */
class get_course_landing_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $context = \context_system::instance();
        self::validate_context($context);

        $renderer = new \local_sm_graphics_plugin\output\course_landing_renderer();
        return $renderer->get_context($courseid);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'           => new external_value(PARAM_INT, 'Course ID'),
            'coursename'         => new external_value(PARAM_TEXT, 'Course name'),
            'coursesummary'      => new external_value(PARAM_RAW, 'Course summary HTML'),
            'hassummary'         => new external_value(PARAM_BOOL, 'Has summary content'),
            'courseimageurl'     => new external_value(PARAM_RAW, 'Course image URL'),
            'hasimage'           => new external_value(PARAM_BOOL, 'Has course image'),
            'sections'           => new external_multiple_structure(
                new external_single_structure([
                    'name'           => new external_value(PARAM_TEXT, 'Section name'),
                    'number'         => new external_value(PARAM_INT, 'Section number'),
                    'activity_count' => new external_value(PARAM_INT, 'Number of activities'),
                    'activities'     => new external_multiple_structure(
                        new external_single_structure([
                            'cmid'         => new external_value(PARAM_INT, 'Course module ID'),
                            'name'         => new external_value(PARAM_TEXT, 'Activity name'),
                            'iconclass'    => new external_value(PARAM_TEXT, 'Icon CSS class'),
                            'modtypelabel' => new external_value(PARAM_TEXT, 'Module type label'),
                        ])
                    ),
                    'hasactivities'  => new external_value(PARAM_BOOL, 'Has activities'),
                ])
            ),
            'section_count'      => new external_value(PARAM_INT, 'Number of sections'),
            'total_activities'   => new external_value(PARAM_INT, 'Total activities'),
            'duration_hours'     => new external_value(PARAM_FLOAT, 'Duration in hours'),
            'has_duration'       => new external_value(PARAM_BOOL, 'Has duration set'),
            'language'           => new external_value(PARAM_TEXT, 'Course language'),
            'sepe_code'          => new external_value(PARAM_TEXT, 'SEPE code'),
            'has_sepe'           => new external_value(PARAM_BOOL, 'Has SEPE code'),
            'smartmind_code'     => new external_value(PARAM_TEXT, 'SmartMind code'),
            'has_smartmind_code' => new external_value(PARAM_BOOL, 'Has SmartMind code'),
            'course_category'    => new external_value(PARAM_TEXT, 'Category name'),
            'has_course_category'=> new external_value(PARAM_BOOL, 'Has category'),
            'is_enrolled'        => new external_value(PARAM_BOOL, 'User is enrolled or has edit'),
            'is_enrolled_real'   => new external_value(PARAM_BOOL, 'User is really enrolled'),
            'course_view_url'    => new external_value(PARAM_RAW, 'Course view URL'),
            'level'              => new external_value(PARAM_TEXT, 'Difficulty level'),
            'level_label'        => new external_value(PARAM_TEXT, 'Translated level label'),
            'completion_pct'     => new external_value(PARAM_INT, 'Completion percentage threshold'),
            'canedit'            => new external_value(PARAM_BOOL, 'Can edit course'),
            'edit_course_url'    => new external_value(PARAM_RAW, 'Edit course URL'),
            'progress'           => new external_value(PARAM_INT, 'User progress 0-100'),
            'has_started'        => new external_value(PARAM_BOOL, 'User has started course'),
            'next_activity_name' => new external_value(PARAM_TEXT, 'Next activity name'),
            'has_next_activity'  => new external_value(PARAM_BOOL, 'Has next activity'),
        ]);
    }
}

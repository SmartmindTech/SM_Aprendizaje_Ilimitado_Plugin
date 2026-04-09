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

        // Record this visit for non-enrolled users so the dashboard's
        // "Vistos recientemente" section can surface it. The legacy hook
        // in lib.php only fires on /enrol/index.php (pagetype enrol-index),
        // which the SPA bypasses entirely — so we hook here instead.
        self::track_browsing($courseid);

        $renderer = new \local_sm_graphics_plugin\output\course_landing_renderer();
        return $renderer->get_context($courseid);
    }

    /**
     * Upsert a row into {local_smgp_course_browsing} when a non-enrolled
     * user opens the SPA course landing. Mirrors the lib.php enrol-index
     * hook one-to-one so the dashboard's recently-viewed list works
     * regardless of whether the visit came through the legacy enrolment
     * page or the new SPA landing.
     *
     * Silently no-ops for guests, the site course, invalid courses, or
     * users who are already enrolled (those already appear under "Seguir
     * aprendiendo" so we don't want to duplicate them in the recently
     * viewed list).
     *
     * @param int $courseid Course the user just opened in the SPA.
     */
    private static function track_browsing(int $courseid): void {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        if ($courseid <= 0 || $courseid == SITEID) {
            return;
        }

        try {
            $coursecontext = \context_course::instance($courseid);
        } catch (\dml_exception $e) {
            return;
        }

        if (is_enrolled($coursecontext, $USER->id, '', true)) {
            return;
        }

        $existing = $DB->get_record('local_smgp_course_browsing', [
            'userid'   => $USER->id,
            'courseid' => $courseid,
        ]);
        if ($existing) {
            $existing->timeaccess = time();
            $DB->update_record('local_smgp_course_browsing', $existing);
        } else {
            $DB->insert_record('local_smgp_course_browsing', (object) [
                'userid'     => $USER->id,
                'courseid'   => $courseid,
                'timeaccess' => time(),
            ]);
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'           => new external_value(PARAM_INT, 'Course ID'),
            'coursename'         => new external_value(PARAM_TEXT, 'Course name'),
            'dashboard_url'      => new external_value(PARAM_RAW, 'Dashboard URL'),
            'coursesummary'      => new external_value(PARAM_RAW, 'Course summary HTML'),
            'hassummary'         => new external_value(PARAM_BOOL, 'Has summary content'),
            'courseimageurl'     => new external_value(PARAM_RAW, 'Course image URL'),
            'hasimage'           => new external_value(PARAM_BOOL, 'Has course image'),
            'sections'           => new external_multiple_structure(
                new external_single_structure([
                    'name'             => new external_value(PARAM_TEXT, 'Section name'),
                    'number'           => new external_value(PARAM_INT, 'Section number'),
                    'section_number'   => new external_value(PARAM_INT, 'Visible section index'),
                    'activity_count'   => new external_value(PARAM_INT, 'Number of activities'),
                    'completed_count'  => new external_value(PARAM_INT, 'Completed activities'),
                    'section_progress' => new external_value(PARAM_INT, 'Section progress percentage'),
                    'activities'       => new external_multiple_structure(
                        new external_single_structure([
                            'cmid'             => new external_value(PARAM_INT, 'Course module ID'),
                            'name'             => new external_value(PARAM_TEXT, 'Activity name'),
                            'iconclass'        => new external_value(PARAM_TEXT, 'Icon CSS class'),
                            'modtypelabel'     => new external_value(PARAM_TEXT, 'Module type label'),
                            'type_color'       => new external_value(PARAM_TEXT, 'Color token for the activity badge'),
                            'iscomplete'       => new external_value(PARAM_BOOL, 'Activity complete'),
                            'iscurrent'        => new external_value(PARAM_BOOL, 'Current activity indicator'),
                            'duration_minutes' => new external_value(PARAM_INT, 'Estimated duration in minutes'),
                            'has_duration'     => new external_value(PARAM_BOOL, 'Has duration set'),
                            'edit_url'         => new external_value(PARAM_RAW, 'Edit activity URL'),
                        ])
                    ),
                    'hasactivities'    => new external_value(PARAM_BOOL, 'Has activities'),
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
            'next_activity_url'  => new external_value(PARAM_RAW, 'Next activity URL'),
            'has_next_activity'  => new external_value(PARAM_BOOL, 'Has next activity'),
            'ring_circumference' => new external_value(PARAM_FLOAT, 'Progress ring circumference'),
            'ring_offset'        => new external_value(PARAM_FLOAT, 'Progress ring offset'),
            'content_types'      => new external_multiple_structure(
                new external_single_structure([
                    'type_label' => new external_value(PARAM_TEXT, 'Content type label'),
                    'type_icon'  => new external_value(PARAM_TEXT, 'Content type icon class'),
                    'type_color' => new external_value(PARAM_TEXT, 'Content type color token'),
                    'type_count' => new external_value(PARAM_INT, 'Count of activities of this type'),
                ])
            ),
            'has_content_types'      => new external_value(PARAM_BOOL, 'Has content type breakdown'),
            'total_completed'        => new external_value(PARAM_INT, 'Total completed activities'),
            'remaining_duration_min' => new external_value(PARAM_INT, 'Remaining duration in minutes'),
            'has_remaining_duration' => new external_value(PARAM_BOOL, 'Has remaining duration'),
            'objectives'             => new external_multiple_structure(
                new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'Objective text'),
                ])
            ),
            'has_objectives'         => new external_value(PARAM_BOOL, 'Has learning objectives'),
            'summaries_i18n'         => new external_multiple_structure(
                new external_single_structure([
                    'lang'    => new external_value(PARAM_ALPHAEXT, 'Language code'),
                    'summary' => new external_value(PARAM_RAW, 'Summary HTML for this language'),
                ]),
                'All-language summaries for instant client-side switching',
                VALUE_DEFAULT,
                []
            ),
            'objectives_i18n'        => new external_multiple_structure(
                new external_single_structure([
                    'lang'       => new external_value(PARAM_ALPHAEXT, 'Language code'),
                    'objectives' => new external_multiple_structure(
                        new external_single_structure([
                            'text' => new external_value(PARAM_TEXT, 'Objective text'),
                        ]),
                        'Objectives for this language',
                        VALUE_DEFAULT,
                        []
                    ),
                ]),
                'All-language objectives for instant client-side switching',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}

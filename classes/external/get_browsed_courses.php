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
 * Get courses the current user has browsed but is not enrolled in.
 *
 * Queries the local_smgp_course_browsing table and excludes
 * any course the user is currently enrolled in.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * External function to get browsed (not enrolled) courses.
 */
class get_browsed_courses extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'limit' => new external_value(PARAM_INT, 'Max courses to return', VALUE_DEFAULT, 6),
        ]);
    }

    /**
     * Execute: get browsed courses excluding enrolled ones.
     *
     * Returns the same shape as get_dashboard_data's course struct so the
     * dashboard's reusable card components can render either source without
     * branching on field availability.
     *
     * @param int $limit Max number of courses to return.
     * @return array Array of card-ready course arrays.
     */
    public static function execute(int $limit = 6): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['limit' => $limit]);
        $limit = $params['limit'];

        // Required by the format_string() / core_course_list_element calls
        // inside format_course() — those use $PAGE->context, which is only
        // wired up after validate_context() is called.
        $context = \context_system::instance();
        self::validate_context($context);

        $courses = $DB->get_records_sql(
            "SELECT c.*, cb.timeaccess
               FROM {local_smgp_course_browsing} cb
               JOIN {course} c ON c.id = cb.courseid
              WHERE cb.userid = :userid
                AND c.visible = 1
                AND NOT EXISTS (
                    SELECT 1
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.userid = cb.userid
                       AND e.courseid = c.id
                )
           ORDER BY cb.timeaccess DESC",
            ['userid' => $USER->id],
            0,
            $limit
        );

        $out = [];
        foreach ($courses as $course) {
            $out[] = self::format_course($course);
        }
        return $out;
    }

    /**
     * Build a card-ready course array. Mirrors get_dashboard_data::build_base()
     * so the dashboard can reuse the same TS type and Vue components.
     *
     * @param \stdClass $course Course record (must include ->timeaccess from the join).
     * @return array
     */
    private static function format_course(\stdClass $course): array {
        global $DB;
        $courseobj = new \core_course_list_element($course);

        $imageurl = '';
        foreach ($courseobj->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                $imageurl = \moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                    null, $file->get_filepath(), $file->get_filename()
                )->out(false);
                break;
            }
        }

        $categoryname = '';
        if (!empty($course->category)) {
            $cat = \core_course_category::get($course->category, IGNORE_MISSING, true);
            $categoryname = $cat ? format_string($cat->name) : '';
        }

        // SmartMind category.
        $smcategory = '';
        if ($DB->get_manager()->table_exists('local_smgp_course_category')
                && $DB->get_manager()->table_exists('local_smgp_categories')) {
            $smcat = $DB->get_record_sql(
                "SELECT c.name
                   FROM {local_smgp_course_category} cc
                   JOIN {local_smgp_categories} c ON c.id = cc.categoryid
                  WHERE cc.courseid = :cid
                  ORDER BY c.sortorder ASC
                  LIMIT 1",
                ['cid' => $course->id]
            );
            if ($smcat) {
                $smcategory = format_string($smcat->name);
            }
        }

        return [
            'id'                 => (int) $course->id,
            'fullname'           => format_string($course->fullname),
            'shortname'          => format_string($course->shortname),
            'categoryname'       => $categoryname,
            'sm_category'        => $smcategory,
            'image'              => $imageurl,
            'progress'           => 0,
            'lastcmid'           => 0,
            'lastaccess'         => (int) ($course->timeaccess ?? 0),
            'timecompleted'      => 0,
            'timecompleted_text' => '',
            'grade'              => '',
            'grademax'           => '',
            'hasgrade'           => false,
        ];
    }

    /**
     * Define return type. Mirrors get_dashboard_data's coursestruct so the
     * frontend can use a single TypeScript type for both endpoints.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'                 => new external_value(PARAM_INT, 'Course ID'),
                'fullname'           => new external_value(PARAM_TEXT, 'Course full name'),
                'shortname'          => new external_value(PARAM_TEXT, 'Course short name'),
                'categoryname'       => new external_value(PARAM_TEXT, 'Moodle category name'),
                'sm_category'        => new external_value(PARAM_TEXT, 'SmartMind category name'),
                'image'              => new external_value(PARAM_RAW, 'Course image URL'),
                'progress'           => new external_value(PARAM_INT, 'Progress 0-100 (always 0 for browsed)'),
                'lastcmid'           => new external_value(PARAM_INT, 'Last viewed activity cmid (0 for browsed)'),
                'lastaccess'         => new external_value(PARAM_INT, 'Last browse timestamp'),
                'timecompleted'      => new external_value(PARAM_INT, 'Completion timestamp (0 for browsed)'),
                'timecompleted_text' => new external_value(PARAM_TEXT, 'Formatted completion date'),
                'grade'              => new external_value(PARAM_TEXT, 'Final grade'),
                'grademax'           => new external_value(PARAM_TEXT, 'Max grade'),
                'hasgrade'           => new external_value(PARAM_BOOL, 'Whether a grade is available'),
            ])
        );
    }
}

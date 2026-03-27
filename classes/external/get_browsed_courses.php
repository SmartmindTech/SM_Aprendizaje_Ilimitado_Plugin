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
     * @param int $limit Max number of courses to return.
     * @return array Array of course stdClass records.
     */
    public static function execute(int $limit = 6): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['limit' => $limit]);
        $limit = $params['limit'];

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

        return array_values($courses);
    }

    /**
     * Define return type.
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID'),
                'timeaccess' => new external_value(PARAM_INT, 'Last browse timestamp'),
            ])
        );
    }
}

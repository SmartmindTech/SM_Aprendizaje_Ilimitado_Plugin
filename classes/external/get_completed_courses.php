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
 * Get the courses that the current user has completed.
 *
 * Returns course records joined with completion data (timecompleted).
 * Used by the dashboard "completed courses" section.
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
 * External function to get completed courses for the current user.
 */
class get_completed_courses extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute: get all completed courses for the current user.
     *
     * Each returned course record includes a ->timecompleted property.
     *
     * @return array Array of course stdClass records with timecompleted.
     */
    public static function execute(): array {
        global $DB, $USER;

        $userid = $USER->id;
        $completed = [];
        $foundids = [];

        // 1. Moodle standard completion.
        $records = $DB->get_records_sql(
            "SELECT c.*, cc.timecompleted
               FROM {course} c
               JOIN {course_completions} cc ON cc.course = c.id
              WHERE cc.userid = :userid
                AND cc.timecompleted > 0
                AND c.visible = 1
           ORDER BY cc.timecompleted DESC",
            ['userid' => $userid]
        );
        foreach ($records as $r) {
            $completed[$r->id] = $r;
            $foundids[$r->id] = true;
        }

        // 2. IOMAD track completion.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_iomad_track')) {
            $iomadrecords = $DB->get_records_sql(
                "SELECT c.*, lit.timecompleted
                   FROM {course} c
                   JOIN {local_iomad_track} lit ON lit.courseid = c.id
                  WHERE lit.userid = :userid
                    AND lit.timecompleted > 0
                    AND c.visible = 1
               ORDER BY lit.timecompleted DESC",
                ['userid' => $userid]
            );
            foreach ($iomadrecords as $r) {
                if (empty($foundids[$r->id])) {
                    $completed[$r->id] = $r;
                    $foundids[$r->id] = true;
                }
            }
        }

        // 3. Fallback: 100% progress on enrolled courses.
        $enrolled = enrol_get_my_courses('*', 'fullname ASC');
        foreach ($enrolled as $course) {
            if (!$course->visible || !empty($foundids[$course->id])) {
                continue;
            }
            $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
            if ($progress !== null && round($progress) >= 100) {
                $course->timecompleted = 0;
                $completed[$course->id] = $course;
            }
        }

        return array_values($completed);
    }

    /**
     * Define return type.
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID'),
                'timecompleted' => new external_value(PARAM_INT, 'Completion timestamp'),
            ])
        );
    }
}

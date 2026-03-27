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
 * Get the last accessed course and last viewed activity for the current user.
 *
 * Used by the dashboard "continue learning" section.
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
use external_value;

/**
 * External function to get the last accessed course and activity.
 */
class get_last_accessed extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute: get last accessed course + last viewed activity.
     *
     * @return array {course: stdClass|null, cminfo: cm_info|null}
     */
    public static function execute(): array {
        global $DB, $USER;

        $result = ['course' => null, 'cminfo' => null];

        // Last accessed course.
        $lastaccess = $DB->get_record_sql(
            'SELECT courseid, timeaccess
               FROM {user_lastaccess}
              WHERE userid = :userid
           ORDER BY timeaccess DESC
              LIMIT 1',
            ['userid' => $USER->id]
        );

        if (!$lastaccess) {
            return $result;
        }

        $course = get_course($lastaccess->courseid);
        if (!$course || !$course->visible) {
            return $result;
        }

        $result['course'] = $course;

        // Last viewed activity in that course.
        $lastactivitylog = $DB->get_record_sql(
            'SELECT contextinstanceid AS cmid
               FROM {logstore_standard_log}
              WHERE courseid = :courseid
                AND userid = :userid
                AND action = :action
                AND target = :target
           ORDER BY timecreated DESC
              LIMIT 1',
            [
                'courseid' => $course->id,
                'userid'   => $USER->id,
                'action'   => 'viewed',
                'target'   => 'course_module',
            ]
        );

        if ($lastactivitylog) {
            $modinfo = get_fast_modinfo($course);
            try {
                $result['cminfo'] = $modinfo->get_cm($lastactivitylog->cmid);
            } catch (\moodle_exception $e) {
                // Activity may have been deleted since it was logged.
            }
        }

        return $result;
    }

    /**
     * Define return type.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Last accessed course ID', VALUE_OPTIONAL),
        ]);
    }
}

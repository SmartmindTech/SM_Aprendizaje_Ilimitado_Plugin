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
 * Returns My Courses page data: enrolled (in-progress) and completed courses
 * with progress, activity counts, continue URLs, and category info.
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
 * External function to get My Courses page data for the current user.
 */
class get_mycourses_data extends external_api {

    /**
     * Define parameters — none required.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute: build the full My Courses page data.
     *
     * Replicates the data preparation from pages/mycourses.php:
     * - Fetches completed courses via get_completed_courses.
     * - Fetches enrolled courses, filtering out completed ones.
     * - Enriches each course with progress, activity counts, continue URL,
     *   category name, shortname, and status.
     *
     * @return array Template-ready data with enrolledcourses and completedcourses.
     */
    public static function execute(): array {
        global $DB, $USER;

        $context = \context_system::instance();
        self::validate_context($context);

        // ── Completed courses ──────────────────────────────────────────
        $rawcompleted = \local_sm_graphics_plugin\external\get_completed_courses::execute();
        $completedids = array_column($rawcompleted, 'id');

        // Format completed courses via dashboard_helper.
        $completedformatted = \theme_smartmind\dashboard_helper::format_finished_courses($rawcompleted);

        // Enrich each completed course.
        $completedcourses = [];
        foreach ($completedformatted as $cc) {
            $courseid = $cc['id'];
            $course = get_course($courseid);
            $cc['shortname'] = format_string($course->shortname);

            // Catalogue category name from plugin tables.
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
            $cc['continueurl'] = (new \moodle_url('/course/view.php', [
                'id' => $courseid,
                'smgp_enter' => 1,
            ]))->out(false);
            $cc['status'] = 'completed';
            $cc['image'] = $cc['courseimage'] ?? '';

            $completedcourses[] = $cc;
        }

        // ── Enrolled (active / in-progress) courses ────────────────────
        $rawcourses = enrol_get_my_courses('*', 'fullname ASC');
        $enrolledformatted = \theme_smartmind\dashboard_helper::format_active_courses($rawcourses, $completedids);

        $enrolledcourses = [];
        foreach ($enrolledformatted as $ec) {
            $courseid = $ec['id'];
            $course = get_course($courseid);

            // Shortname.
            $ec['shortname'] = format_string($course->shortname);

            // Catalogue category name from plugin tables.
            $catlink = $DB->get_record('local_smgp_course_category', ['courseid' => $courseid]);
            $ec['categoryname'] = '';
            if ($catlink) {
                $cat = $DB->get_record('local_smgp_categories', ['id' => $catlink->categoryid]);
                if ($cat) {
                    $ec['categoryname'] = format_string($cat->name);
                }
            }

            // Activity progress counts.
            $modinfo = get_fast_modinfo($course);
            $completion = new \completion_info($course);
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

            // Continue URL — default to course view.
            $ec['continueurl'] = (new \moodle_url('/course/view.php', [
                'id' => $courseid,
                'smgp_enter' => 1,
            ]))->out(false);

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
                $ec['continueurl'] = (new \moodle_url('/course/view.php', [
                    'id' => $courseid,
                    'smgp_enter' => 1,
                    'smgp_cmid' => (int) $lastcmid,
                ]))->out(false);
            }

            $ec['status'] = 'inprogress';
            $ec['image'] = $ec['courseimage'] ?? '';

            $enrolledcourses[] = $ec;
        }

        return [
            'enrolledcourses'     => $enrolledcourses,
            'hasenrolledcourses'  => !empty($enrolledcourses),
            'completedcourses'    => $completedcourses,
            'hascompletedcourses' => !empty($completedcourses),
        ];
    }

    /**
     * Define the return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $coursestructure = new external_single_structure([
            'id'                   => new external_value(PARAM_INT, 'Course ID'),
            'fullname'             => new external_value(PARAM_TEXT, 'Course full name'),
            'shortname'            => new external_value(PARAM_TEXT, 'Course short name'),
            'categoryname'         => new external_value(PARAM_TEXT, 'Catalogue category name'),
            'image'                => new external_value(PARAM_RAW, 'Course image URL'),
            'progress'             => new external_value(PARAM_INT, 'Progress percentage 0-100'),
            'hasprogress'          => new external_value(PARAM_BOOL, 'Whether progress is available'),
            'completed_activities' => new external_value(PARAM_INT, 'Number of completed activities'),
            'total_activities'     => new external_value(PARAM_INT, 'Total number of activities'),
            'continueurl'          => new external_value(PARAM_RAW, 'URL to continue/resume the course'),
            'status'               => new external_value(PARAM_TEXT, 'Course status: inprogress or completed'),
        ]);

        return new external_single_structure([
            'enrolledcourses'     => new external_multiple_structure($coursestructure, 'Active enrolled courses'),
            'hasenrolledcourses'  => new external_value(PARAM_BOOL, 'Whether there are active enrolled courses'),
            'completedcourses'    => new external_multiple_structure(
                // Completed courses use the same structure.
                new external_single_structure([
                    'id'                   => new external_value(PARAM_INT, 'Course ID'),
                    'fullname'             => new external_value(PARAM_TEXT, 'Course full name'),
                    'shortname'            => new external_value(PARAM_TEXT, 'Course short name'),
                    'categoryname'         => new external_value(PARAM_TEXT, 'Catalogue category name'),
                    'image'                => new external_value(PARAM_RAW, 'Course image URL'),
                    'progress'             => new external_value(PARAM_INT, 'Progress percentage 0-100'),
                    'hasprogress'          => new external_value(PARAM_BOOL, 'Whether progress is available'),
                    'completed_activities' => new external_value(PARAM_INT, 'Number of completed activities'),
                    'total_activities'     => new external_value(PARAM_INT, 'Total number of activities'),
                    'continueurl'          => new external_value(PARAM_RAW, 'URL to continue/resume the course'),
                    'status'               => new external_value(PARAM_TEXT, 'Course status: inprogress or completed'),
                ]),
                'Completed courses'
            ),
            'hascompletedcourses' => new external_value(PARAM_BOOL, 'Whether there are completed courses'),
        ]);
    }
}

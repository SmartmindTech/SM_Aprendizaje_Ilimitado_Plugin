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
use external_value;

/**
 * Set the duration for a single activity (admin only).
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_activity_duration extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'duration' => new external_value(PARAM_INT, 'Duration in minutes'),
        ]);
    }

    public static function execute(int $cmid, int $duration): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'duration' => $duration,
        ]);
        $cmid = $params['cmid'];
        $duration = max(0, $params['duration']);

        $syscontext = \context_system::instance();
        self::validate_context($syscontext);

        // Get course ID from course_modules.
        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course', MUST_EXIST);
        $coursecontext = \context_course::instance($cm->course);

        // Only admins/editors can set durations.
        require_capability('moodle/course:update', $coursecontext);

        $now = time();
        $existing = $DB->get_record('local_smgp_activity_duration', ['cmid' => $cmid]);

        if ($existing) {
            $existing->duration_minutes = $duration;
            $existing->estimation_source = 'manual';
            $existing->timemodified = $now;
            $DB->update_record('local_smgp_activity_duration', $existing);
        } else {
            $DB->insert_record('local_smgp_activity_duration', (object) [
                'cmid' => $cmid,
                'courseid' => $cm->course,
                'duration_minutes' => $duration,
                'estimation_source' => 'manual',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        // Also update the course total duration in course_meta.
        self::update_course_total($cm->course);

        return ['success' => true, 'duration' => $duration];
    }

    /**
     * Recalculate and store total course hours from all activity durations.
     */
    private static function update_course_total(int $courseid): void {
        global $DB;

        $totalminutes = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(duration_minutes), 0) FROM {local_smgp_activity_duration} WHERE courseid = :cid",
            ['cid' => $courseid]
        );
        $totalhours = (int) ceil($totalminutes / 60);

        $meta = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
        if ($meta) {
            $meta->duration_hours = $totalhours;
            $meta->timemodified = time();
            $DB->update_record('local_smgp_course_meta', $meta);
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the duration was saved'),
            'duration' => new external_value(PARAM_INT, 'Saved duration in minutes'),
        ]);
    }
}

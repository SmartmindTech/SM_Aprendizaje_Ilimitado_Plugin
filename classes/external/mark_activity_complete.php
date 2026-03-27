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
 * AJAX: Mark an activity as complete.
 *
 * Used by the course page player to trigger completion for activities
 * whose progress is tracked client-side (e.g., video resources).
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind
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
 * External function to mark an activity as complete.
 */
class mark_activity_complete extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Execute: mark the activity as complete for the current user.
     *
     * @param int $cmid Course module ID.
     * @return array {success, message}
     */
    public static function execute(int $cmid): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid = $params['cmid'];

        $cm = get_coursemodule_from_id('', $cmid, 0, true, MUST_EXIST);
        $course = get_course($cm->course);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cmid);
        $completion = new \completion_info($course);

        if ($completion->is_enabled($cminfo) == COMPLETION_TRACKING_NONE) {
            // For activities without Moodle completion (labels, etc.),
            // write a log entry so the log-based progress fallback marks them as viewed.
            $exists = $DB->record_exists_sql(
                "SELECT 1 FROM {logstore_standard_log}
                 WHERE action = 'viewed' AND target = 'course_module'
                   AND contextinstanceid = :cmid AND userid = :userid",
                ['cmid' => $cmid, 'userid' => $USER->id]
            );
            if (!$exists) {
                $DB->insert_record('logstore_standard_log', (object)[
                    'eventname'         => '\\local_sm_graphics_plugin\\event\\activity_viewed',
                    'component'         => 'local_sm_graphics_plugin',
                    'action'            => 'viewed',
                    'target'            => 'course_module',
                    'crud'              => 'r',
                    'edulevel'          => 2,
                    'anonymous'         => 0,
                    'objecttable'       => $cminfo->modname,
                    'objectid'          => $cm->instance,
                    'contextid'         => $context->id,
                    'contextlevel'      => CONTEXT_MODULE,
                    'contextinstanceid' => $cmid,
                    'userid'            => $USER->id,
                    'courseid'          => $course->id,
                    'timecreated'       => time(),
                    'origin'            => 'web',
                    'ip'                => getremoteaddr(),
                ]);
            }
            return ['success' => true, 'message' => 'Marked as viewed (log-based)'];
        }

        $data = $completion->get_data($cminfo, true, $USER->id);
        if (in_array($data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
            return ['success' => true, 'message' => 'Already complete'];
        }

        $completion->update_state($cminfo, COMPLETION_COMPLETE, $USER->id);

        return ['success' => true, 'message' => 'Marked complete'];
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether completion was set'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}

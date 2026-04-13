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
 * AJAX: Finish a SCORM attempt (LMSFinish / Terminate).
 *
 * Marks the attempt complete and triggers Moodle's completion update.
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

class finish_scorm_attempt extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID'),
            'scoid'   => new external_value(PARAM_INT, 'SCO ID'),
            'attempt' => new external_value(PARAM_INT, 'Attempt number'),
        ]);
    }

    public static function execute(int $cmid, int $scoid, int $attempt): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'scoid' => $scoid, 'attempt' => $attempt,
        ]);

        $cm = get_coursemodule_from_id('scorm', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Verify SCO.
        $sco = $DB->get_record('scorm_scoes', [
            'id' => $params['scoid'], 'scorm' => $scorm->id,
        ], '*', MUST_EXIST);

        // Set exit status if not already set.
        try {
            scorm_insert_track(
                $USER->id, $scorm->id, $sco->id,
                $params['attempt'], 'cmi.core.exit', 'suspend'
            );
        } catch (\Exception $e) {
            // Non-fatal.
        }

        // Trigger course_module_viewed event.
        $event = \mod_scorm\event\course_module_viewed::create([
            'objectid' => $scorm->id,
            'context'  => $context,
        ]);
        $event->add_record_snapshot('scorm', $scorm);
        $event->trigger();

        // Update completion.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm);
        }

        return ['success' => true, 'message' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether finish succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

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
 * AJAX: Submit a choice response (mod_choice).
 *
 * Wraps mod_choice_submit_choice_response so grades and completion
 * flow through Moodle's normal pipeline.
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
use external_multiple_structure;
use external_value;

class submit_choice_response extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'      => new external_value(PARAM_INT, 'Course module ID'),
            'responses'  => new external_multiple_structure(
                new external_value(PARAM_INT, 'Option ID'),
                'Selected option IDs'
            ),
        ]);
    }

    public static function execute(int $cmid, array $responses): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'responses' => $responses,
        ]);
        $cmid = $params['cmid'];
        $responses = $params['responses'];

        $cm = get_coursemodule_from_id('choice', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/choice/lib.php');

        $choice = $DB->get_record('choice', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Check if closed.
        if ($choice->timeclose > 0 && time() > $choice->timeclose) {
            return ['success' => false, 'message' => 'Choice is closed'];
        }

        // Delete existing answers if updating.
        $existing = $DB->get_records('choice_answers', [
            'choiceid' => $choice->id,
            'userid'   => $USER->id,
        ]);

        if (!empty($existing) && !$choice->allowupdate) {
            return ['success' => false, 'message' => 'Cannot update response'];
        }

        // Use Moodle's choice_user_submit_response for proper event handling.
        choice_user_submit_response($responses, $choice, $USER->id, $course, $cm);

        // Trigger completion.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        return ['success' => true, 'message' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the submission succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

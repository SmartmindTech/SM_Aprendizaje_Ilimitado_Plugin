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
 * AJAX: Submit a feedback page (mod_feedback).
 *
 * Saves responses for one page and either advances to the next page
 * or finalizes the feedback submission.
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

class submit_feedback_page extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'pagenum'    => new external_value(PARAM_INT, 'Current page number (0-based)'),
            'responses'  => new external_multiple_structure(
                new external_single_structure([
                    'itemid' => new external_value(PARAM_INT, 'Feedback item ID'),
                    'value'  => new external_value(PARAM_RAW, 'Response value'),
                ]),
                'Responses for this page'
            ),
            'finish'     => new external_value(PARAM_BOOL, 'True to finalize the feedback', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(int $cmid, int $pagenum, array $responses, bool $finish = false): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'pagenum' => $pagenum,
            'responses' => $responses, 'finish' => $finish,
        ]);

        $cm = get_coursemodule_from_id('feedback', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/feedback/lib.php');

        $feedback = $DB->get_record('feedback', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Already complete?
        if ($DB->record_exists('feedback_completed', ['feedback' => $feedback->id, 'userid' => $USER->id])) {
            return ['success' => false, 'message' => 'Feedback already completed', 'iscomplete' => true];
        }

        // Get or create tmp completion record.
        $tmpcompletion = $DB->get_record('feedback_completedtmp', [
            'feedback' => $feedback->id,
            'userid'   => $USER->id,
        ]);
        if (!$tmpcompletion) {
            $tmpcompletion = new \stdClass();
            $tmpcompletion->feedback = $feedback->id;
            $tmpcompletion->userid = $USER->id;
            $tmpcompletion->guestid = '';
            $tmpcompletion->timemodified = time();
            $tmpcompletion->anonymous_response = $feedback->anonymous;
            $tmpcompletion->id = $DB->insert_record('feedback_completedtmp', $tmpcompletion);
        }

        // Save responses for this page.
        foreach ($params['responses'] as $resp) {
            // Check if already saved for this item.
            $existing = $DB->get_record('feedback_valuetmp', [
                'completed' => $tmpcompletion->id,
                'item'      => $resp['itemid'],
            ]);
            if ($existing) {
                $existing->value = $resp['value'];
                $DB->update_record('feedback_valuetmp', $existing);
            } else {
                $record = new \stdClass();
                $record->completed = $tmpcompletion->id;
                $record->item = $resp['itemid'];
                $record->course_id = $course->id;
                $record->value = $resp['value'];
                $DB->insert_record('feedback_valuetmp', $record);
            }
        }

        $iscomplete = false;
        if ($params['finish']) {
            // Move from tmp to completed.
            $completed = new \stdClass();
            $completed->feedback = $feedback->id;
            $completed->userid = $USER->id;
            $completed->timemodified = time();
            $completed->anonymous_response = $feedback->anonymous;
            $completed->id = $DB->insert_record('feedback_completed', $completed);

            // Copy values.
            $tmpvalues = $DB->get_records('feedback_valuetmp', ['completed' => $tmpcompletion->id]);
            foreach ($tmpvalues as $v) {
                $val = new \stdClass();
                $val->completed = $completed->id;
                $val->item = $v->item;
                $val->course_id = $v->course_id;
                $val->value = $v->value;
                $DB->insert_record('feedback_value', $val);
            }

            // Clean up tmp.
            $DB->delete_records('feedback_valuetmp', ['completed' => $tmpcompletion->id]);
            $DB->delete_records('feedback_completedtmp', ['id' => $tmpcompletion->id]);

            // Trigger events.
            $event = \mod_feedback\event\response_submitted::create([
                'objectid' => $completed->id,
                'context'  => $context,
                'relateduserid' => $USER->id,
                'anonymous' => ($feedback->anonymous == FEEDBACK_ANONYMOUS_YES),
                'other' => ['cmid' => $cm->id, 'instanceid' => $feedback->id, 'anonymous' => $feedback->anonymous],
            ]);
            $event->trigger();

            // Completion.
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $iscomplete = true;
        }

        return ['success' => true, 'message' => '', 'iscomplete' => $iscomplete];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'Whether the submission succeeded'),
            'message'    => new external_value(PARAM_TEXT, 'Error message if failed'),
            'iscomplete' => new external_value(PARAM_BOOL, 'Whether feedback is now complete'),
        ]);
    }
}

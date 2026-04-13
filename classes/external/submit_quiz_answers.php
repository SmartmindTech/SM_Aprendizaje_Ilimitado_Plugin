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
 * AJAX: Submit answers for a quiz page (mod_quiz).
 *
 * Saves answers for the current page's questions and optionally
 * navigates to the next page. Uses Moodle's question engine
 * (question_engine::process_action) so grades compute correctly.
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

class submit_quiz_answers extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'answers'   => new external_multiple_structure(
                new external_single_structure([
                    'slot'          => new external_value(PARAM_INT, 'Question slot number'),
                    'sequencecheck' => new external_value(PARAM_INT, 'Sequence check count'),
                    'response'      => new external_multiple_structure(
                        new external_single_structure([
                            'name'  => new external_value(PARAM_RAW, 'Response field name'),
                            'value' => new external_value(PARAM_RAW, 'Response field value'),
                        ]),
                        'Response key-value pairs'
                    ),
                ]),
                'Answers for each question slot on this page'
            ),
            'nextpage' => new external_value(PARAM_INT, 'Page to navigate to (-1 = stay on current)', VALUE_DEFAULT, -1),
        ]);
    }

    public static function execute(int $attemptid, array $answers, int $nextpage = -1): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid, 'answers' => $answers, 'nextpage' => $nextpage,
        ]);

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        if ($attempt->userid != $USER->id) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }
        if ($attempt->state !== 'inprogress') {
            return ['success' => false, 'message' => 'Attempt is not in progress'];
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, true, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Load the question usage.
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        // Process each answer.
        foreach ($params['answers'] as $ans) {
            $slot = $ans['slot'];
            // Build the response data array.
            $responsedata = [];
            foreach ($ans['response'] as $kv) {
                $responsedata[$kv['name']] = $kv['value'];
            }
            // Add sequence check.
            $responsedata['-sequencecheck'] = $ans['sequencecheck'];

            try {
                $quba->process_action($slot, $responsedata);
            } catch (\Exception $e) {
                debugging('Quiz answer processing error slot ' . $slot . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Save.
        \question_engine::save_questions_usage_by_activity($quba);

        // Update current page if navigating.
        if ($params['nextpage'] >= 0) {
            $attempt->currentpage = $params['nextpage'];
        }
        $attempt->timemodified = time();
        $DB->update_record('quiz_attempts', $attempt);

        return ['success' => true, 'message' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether answers were saved'),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

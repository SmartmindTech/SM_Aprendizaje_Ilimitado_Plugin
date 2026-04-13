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
 * AJAX: Get quiz attempt review data (mod_quiz).
 *
 * Returns questions with correct/incorrect indicators and feedback
 * for a finished attempt.
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

class get_quiz_review extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
        ]);
    }

    public static function execute(int $attemptid): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
        ]);

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        if ($attempt->userid != $USER->id) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, true, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Load question usage.
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        $questions = [];
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');

        foreach ($slots as $slotrecord) {
            $slot = (int) $slotrecord->slot;
            try {
                $qa = $quba->get_question_attempt($slot);
                $question = $qa->get_question();
                $qtype = $question->get_type_name();

                $state = $qa->get_state();
                $fraction = $qa->get_fraction();
                $mark = $qa->get_mark();
                $maxmark = $qa->get_max_mark();

                $qdata = [
                    'slot'        => $slot,
                    'type'        => $qtype,
                    'text'        => format_text($question->questiontext, $question->questiontextformat, ['context' => $context]),
                    'state'       => $state->get_css_class(),
                    'statelabel'  => $state->default_string(true),
                    'iscorrect'   => $state->is_correct(),
                    'ispartial'   => $state->is_partially_correct(),
                    'mark'        => $mark !== null ? round($mark, 2) : null,
                    'maxmark'     => round($maxmark, 2),
                    'fraction'    => $fraction !== null ? round($fraction, 4) : null,
                ];

                // Get the response summary.
                $qdata['responsesummary'] = $qa->get_response_summary();

                // Get the correct answer.
                $qdata['rightanswer'] = $qa->get_right_answer_summary();

                // Get specific feedback if available.
                $behaviour = $qa->get_behaviour();
                if (method_exists($behaviour, 'get_feedback')) {
                    $feedback = $qa->get_specific_feedback();
                    if ($feedback) {
                        $qdata['specificfeedback'] = $feedback;
                    }
                }

                // General feedback.
                if (!empty($question->generalfeedback)) {
                    $qdata['generalfeedback'] = format_text(
                        $question->generalfeedback,
                        $question->generalfeedbackformat,
                        ['context' => $context]
                    );
                }

                $questions[] = $qdata;
            } catch (\Exception $e) {
                debugging('Quiz review error slot ' . $slot . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Calculate overall grade.
        $grade = null;
        if ($attempt->sumgrades !== null && $quiz->sumgrades > 0) {
            $grade = round($quiz->grade * ($attempt->sumgrades / $quiz->sumgrades), 2);
        }

        return [
            'attemptid'  => (int) $attempt->id,
            'state'      => $attempt->state,
            'grade'      => $grade,
            'grademax'   => (float) $quiz->grade,
            'timestarted' => (int) $attempt->timestart,
            'timefinished' => (int) $attempt->timefinish,
            'questions'  => $questions,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid'    => new external_value(PARAM_INT, 'Attempt ID'),
            'state'        => new external_value(PARAM_ALPHA, 'Attempt state'),
            'grade'        => new external_value(PARAM_FLOAT, 'Final grade', VALUE_OPTIONAL),
            'grademax'     => new external_value(PARAM_FLOAT, 'Maximum grade'),
            'timestarted'  => new external_value(PARAM_INT, 'Start timestamp'),
            'timefinished' => new external_value(PARAM_INT, 'Finish timestamp'),
            'questions'    => new external_multiple_structure(
                new external_single_structure([
                    'slot'             => new external_value(PARAM_INT, 'Slot number'),
                    'type'             => new external_value(PARAM_TEXT, 'Question type'),
                    'text'             => new external_value(PARAM_RAW, 'Question text HTML'),
                    'state'            => new external_value(PARAM_TEXT, 'State CSS class'),
                    'statelabel'       => new external_value(PARAM_TEXT, 'State label'),
                    'iscorrect'        => new external_value(PARAM_BOOL, 'Is correct'),
                    'ispartial'        => new external_value(PARAM_BOOL, 'Is partially correct'),
                    'mark'             => new external_value(PARAM_FLOAT, 'Achieved mark', VALUE_OPTIONAL),
                    'maxmark'          => new external_value(PARAM_FLOAT, 'Maximum mark'),
                    'fraction'         => new external_value(PARAM_FLOAT, 'Fraction correct', VALUE_OPTIONAL),
                    'responsesummary'  => new external_value(PARAM_RAW, 'User response summary', VALUE_OPTIONAL),
                    'rightanswer'      => new external_value(PARAM_RAW, 'Correct answer summary', VALUE_OPTIONAL),
                    'specificfeedback' => new external_value(PARAM_RAW, 'Specific feedback HTML', VALUE_OPTIONAL),
                    'generalfeedback'  => new external_value(PARAM_RAW, 'General feedback HTML', VALUE_OPTIONAL),
                ]),
                'Review questions with feedback'
            ),
        ]);
    }
}

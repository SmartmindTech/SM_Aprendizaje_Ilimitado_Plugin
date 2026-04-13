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
 * AJAX: Finish a quiz attempt (mod_quiz).
 *
 * Finalizes the attempt, triggers grading through Moodle's question engine,
 * and updates the gradebook. Returns the final grade.
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

class finish_quiz_attempt extends external_api {

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
        require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');

        $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        if ($attempt->userid != $USER->id) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }
        if ($attempt->state !== 'inprogress') {
            return ['success' => false, 'grade' => null, 'message' => 'Attempt not in progress'];
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, true, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Load the question usage and finish all questions.
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $quba->finish_all_questions(time());
        \question_engine::save_questions_usage_by_activity($quba);

        // Calculate total grade from question usage.
        $totalmark = $quba->get_total_mark();

        // Update attempt record.
        $attempt->state = 'finished';
        $attempt->timefinish = time();
        $attempt->timemodified = time();
        $attempt->sumgrades = $totalmark;
        $DB->update_record('quiz_attempts', $attempt);

        // Update quiz grades table.
        $grade = null;
        if ($quiz->sumgrades > 0) {
            $grade = round($quiz->grade * ($totalmark / $quiz->sumgrades), 5);
        }

        // Insert/update quiz_grades.
        $quizgrade = $DB->get_record('quiz_grades', [
            'quiz'   => $quiz->id,
            'userid' => $USER->id,
        ]);
        if ($quizgrade) {
            // Apply grade method.
            switch ($quiz->grademethod) {
                case 1: // Highest grade.
                    $quizgrade->grade = max($quizgrade->grade, $grade ?? 0);
                    break;
                case 2: // Average.
                    $allattempts = $DB->get_records('quiz_attempts', [
                        'quiz' => $quiz->id, 'userid' => $USER->id,
                        'state' => 'finished', 'preview' => 0,
                    ]);
                    $total = 0;
                    $count = 0;
                    foreach ($allattempts as $a) {
                        if ($a->sumgrades !== null && $quiz->sumgrades > 0) {
                            $total += $quiz->grade * ($a->sumgrades / $quiz->sumgrades);
                            $count++;
                        }
                    }
                    $quizgrade->grade = $count > 0 ? round($total / $count, 5) : 0;
                    break;
                case 3: // First attempt.
                    // Don't update.
                    break;
                case 4: // Last attempt.
                    $quizgrade->grade = $grade ?? 0;
                    break;
            }
            $quizgrade->timemodified = time();
            $DB->update_record('quiz_grades', $quizgrade);
        } else {
            $quizgrade = new \stdClass();
            $quizgrade->quiz = $quiz->id;
            $quizgrade->userid = $USER->id;
            $quizgrade->grade = $grade ?? 0;
            $quizgrade->timemodified = time();
            $DB->insert_record('quiz_grades', $quizgrade);
        }

        // Update Moodle gradebook.
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        quiz_update_grades($quiz, $USER->id);

        // Completion.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        // Trigger event.
        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid' => $attempt->id,
            'context'  => $context,
            'relateduserid' => $USER->id,
            'other' => ['quizid' => $quiz->id, 'submitterid' => $USER->id],
        ]);
        $event->trigger();

        return [
            'success' => true,
            'grade'   => $grade,
            'message' => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether finish succeeded'),
            'grade'   => new external_value(PARAM_FLOAT, 'Final grade', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

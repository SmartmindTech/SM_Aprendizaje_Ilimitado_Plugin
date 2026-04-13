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
 * AJAX: Start a new quiz attempt (mod_quiz).
 *
 * Wraps Moodle's quiz_prepare_and_start_new_attempt() so the attempt
 * is properly initialized in the question engine, and grades/completion
 * flow through the standard pipeline.
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

class start_quiz_attempt extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid = $params['cmid'];

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Check for existing in-progress attempt.
        $existing = $DB->get_record_sql(
            "SELECT id, attempt FROM {quiz_attempts}
             WHERE quiz = :quizid AND userid = :userid AND state = 'inprogress'
             ORDER BY attempt DESC LIMIT 1",
            ['quizid' => $quiz->id, 'userid' => $USER->id]
        );
        if ($existing) {
            return [
                'success'   => true,
                'attemptid' => (int) $existing->id,
                'message'   => 'Resumed existing attempt',
            ];
        }

        // Check attempt limit.
        $attemptsused = $DB->count_records('quiz_attempts', [
            'quiz'   => $quiz->id,
            'userid' => $USER->id,
            'preview' => 0,
        ]);
        if ($quiz->attempts > 0 && $attemptsused >= $quiz->attempts) {
            return ['success' => false, 'attemptid' => 0, 'message' => 'No attempts remaining'];
        }

        // Get the last completed attempt number.
        $lastattemptnum = (int) $DB->get_field_sql(
            "SELECT MAX(attempt) FROM {quiz_attempts}
             WHERE quiz = :quizid AND userid = :userid AND preview = 0",
            ['quizid' => $quiz->id, 'userid' => $USER->id]
        );

        // Use Moodle's quiz API to properly start the attempt.
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $USER->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $context);
        $quba->set_preferred_behaviour($quiz->preferredbehaviour);

        // Create the attempt record.
        $attempt = new \stdClass();
        $attempt->quiz = $quiz->id;
        $attempt->userid = $USER->id;
        $attempt->preview = 0;
        $attempt->layout = '';
        $attempt->attempt = $lastattemptnum + 1;
        $attempt->timestart = time();
        $attempt->timefinish = 0;
        $attempt->timemodified = time();
        $attempt->timemodifiedoffline = 0;
        $attempt->state = 'inprogress';
        $attempt->currentpage = 0;
        $attempt->sumgrades = null;
        $attempt->gradednotificationsenttime = null;

        // Add questions to the usage.
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
        $layout = [];
        $lastpage = 0;
        foreach ($slots as $slotrecord) {
            // Look up the question for this slot.
            $qref = $DB->get_record_sql(
                "SELECT qbe.id AS entryid, qv.questionid
                 FROM {question_references} qr
                 JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                 JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                 WHERE qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                   AND qr.itemid = :slotid
                 ORDER BY qv.version DESC LIMIT 1",
                ['slotid' => $slotrecord->id]
            );
            if (!$qref) continue;

            $question = \question_bank::load_question($qref->questionid);
            $slot = $quba->add_question($question, $slotrecord->maxmark);
            $quba->start_question($slot);

            // Build layout: "slotnum" for questions, "0" for page breaks.
            if ($slotrecord->page > $lastpage && !empty($layout)) {
                $layout[] = 0; // Page break.
                $lastpage = $slotrecord->page;
            }
            $layout[] = $slot;
        }

        // Save question usage.
        \question_engine::save_questions_usage_by_activity($quba);
        $attempt->uniqueid = $quba->get_id();
        $attempt->layout = implode(',', $layout);
        $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

        // Trigger event.
        $event = \mod_quiz\event\attempt_started::create([
            'objectid' => $attempt->id,
            'context'  => $context,
            'relateduserid' => $USER->id,
            'other' => ['quizid' => $quiz->id],
        ]);
        $event->trigger();

        return [
            'success'   => true,
            'attemptid' => (int) $attempt->id,
            'message'   => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL, 'Whether attempt was started'),
            'attemptid' => new external_value(PARAM_INT, 'New attempt ID'),
            'message'   => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

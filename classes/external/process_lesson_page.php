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
 * AJAX: Process a lesson page answer (mod_lesson).
 *
 * Records the student's answer and returns the next page to display
 * based on the lesson's branching logic.
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

class process_lesson_page extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'     => new external_value(PARAM_INT, 'Course module ID'),
            'pageid'   => new external_value(PARAM_INT, 'Current lesson page ID'),
            'answerid' => new external_value(PARAM_INT, 'Selected answer ID (for branch/question pages)', VALUE_DEFAULT, 0),
            'response' => new external_value(PARAM_RAW, 'Text response (for essay/short answer)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $cmid, int $pageid, int $answerid = 0, string $response = ''): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'pageid' => $pageid,
            'answerid' => $answerid, 'response' => $response,
        ]);

        $cm = get_coursemodule_from_id('lesson', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/lesson/locallib.php');

        $lesson = $DB->get_record('lesson', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $page = $DB->get_record('lesson_pages', ['id' => $params['pageid'], 'lessonid' => $lesson->id], '*', MUST_EXIST);

        $nextpageid = 0;
        $iscorrect = false;
        $feedback = '';

        // Content/branch table pages (qtype = 20) — navigation only.
        if ((int)$page->qtype === 20) {
            // Record branch visit.
            $branch = new \stdClass();
            $branch->lessonid = $lesson->id;
            $branch->userid = $USER->id;
            $branch->pageid = $page->id;
            $branch->retry = 0;
            $branch->flag = 0;
            $branch->timeseen = time();
            $DB->insert_record('lesson_branch', $branch);

            // Determine next page from the selected answer's jumpto.
            if ($params['answerid'] > 0) {
                $answer = $DB->get_record('lesson_answers', ['id' => $params['answerid']]);
                if ($answer) {
                    $nextpageid = self::resolve_jump($answer->jumpto, $page, $lesson);
                }
            }
        } else {
            // Question pages.
            $answer = null;
            if ($params['answerid'] > 0) {
                $answer = $DB->get_record('lesson_answers', ['id' => $params['answerid']]);
            }

            // Record the attempt.
            $attempt = new \stdClass();
            $attempt->lessonid = $lesson->id;
            $attempt->pageid = $page->id;
            $attempt->userid = $USER->id;
            $attempt->answerid = $params['answerid'];
            $attempt->retry = self::get_current_retry($lesson->id, $USER->id);
            $attempt->correct = 0;
            $attempt->useranswer = $params['response'] ?: ($answer ? $answer->answer : '');
            $attempt->timeseen = time();

            if ($answer) {
                // For multichoice/truefalse, check if answer is correct (score > 0).
                $iscorrect = ((float)($answer->score ?? 0)) > 0;
                $attempt->correct = $iscorrect ? 1 : 0;
                $nextpageid = self::resolve_jump($answer->jumpto, $page, $lesson);

                if (!empty($answer->response)) {
                    $feedback = format_text($answer->response, $answer->responseformat, ['context' => $context]);
                }
            }

            $DB->insert_record('lesson_attempts', $attempt);

            // Also record as branch visit for progress tracking.
            if (!$DB->record_exists('lesson_branch', [
                'lessonid' => $lesson->id, 'userid' => $USER->id, 'pageid' => $page->id,
            ])) {
                $branch = new \stdClass();
                $branch->lessonid = $lesson->id;
                $branch->userid = $USER->id;
                $branch->pageid = $page->id;
                $branch->retry = 0;
                $branch->flag = 0;
                $branch->timeseen = time();
                $DB->insert_record('lesson_branch', $branch);
            }
        }

        // Check if lesson is complete (next page = end of lesson).
        $lessonfinished = ($nextpageid == -9); // LESSON_EOL
        if ($lessonfinished) {
            // Record grade.
            self::finalize_lesson_grade($lesson, $course, $cm, $context);
            $nextpageid = 0;
        }

        return [
            'nextpageid'     => $nextpageid,
            'iscorrect'      => $iscorrect,
            'feedback'       => $feedback,
            'lessonfinished' => $lessonfinished,
        ];
    }

    /**
     * Resolve a jump value to an actual page ID.
     */
    private static function resolve_jump(int $jumpto, $currentpage, $lesson): int {
        global $DB;

        switch ($jumpto) {
            case 0: // LESSON_THISPAGE — stay on current page.
                return (int)$currentpage->id;
            case -1: // LESSON_NEXTPAGE.
                $next = $DB->get_record_sql(
                    "SELECT id FROM {lesson_pages}
                     WHERE lessonid = :lessonid AND ordering > :ordering
                     ORDER BY ordering ASC LIMIT 1",
                    ['lessonid' => $lesson->id, 'ordering' => $currentpage->ordering]
                );
                return $next ? (int)$next->id : -9; // -9 = LESSON_EOL.
            case -2: // LESSON_PREVIOUSPAGE.
                $prev = $DB->get_record_sql(
                    "SELECT id FROM {lesson_pages}
                     WHERE lessonid = :lessonid AND ordering < :ordering
                     ORDER BY ordering DESC LIMIT 1",
                    ['lessonid' => $lesson->id, 'ordering' => $currentpage->ordering]
                );
                return $prev ? (int)$prev->id : (int)$currentpage->id;
            case -9: // LESSON_EOL.
                return -9;
            case -40: // LESSON_UNSEEN — random unseen branch page.
                return (int)$currentpage->id; // Simplified fallback.
            default:
                // Direct page ID reference.
                if ($jumpto > 0) {
                    return $jumpto;
                }
                return (int)$currentpage->id;
        }
    }

    /**
     * Get the current retry number for a user in a lesson.
     */
    private static function get_current_retry(int $lessonid, int $userid): int {
        global $DB;
        $max = $DB->get_field_sql(
            "SELECT MAX(retry) FROM {lesson_attempts}
             WHERE lessonid = :lessonid AND userid = :userid",
            ['lessonid' => $lessonid, 'userid' => $userid]
        );
        return (int)($max ?? 0);
    }

    /**
     * Calculate and record the lesson grade when the lesson finishes.
     */
    private static function finalize_lesson_grade($lesson, $course, $cm, $context): void {
        global $DB, $USER, $CFG;

        // Count correct/total from attempts.
        $retry = self::get_current_retry($lesson->id, $USER->id);
        $total = $DB->count_records('lesson_attempts', [
            'lessonid' => $lesson->id,
            'userid'   => $USER->id,
            'retry'    => $retry,
        ]);
        $correct = $DB->count_records('lesson_attempts', [
            'lessonid' => $lesson->id,
            'userid'   => $USER->id,
            'retry'    => $retry,
            'correct'  => 1,
        ]);

        $grade = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

        // Insert lesson grade record.
        $graderecord = new \stdClass();
        $graderecord->lessonid = $lesson->id;
        $graderecord->userid = $USER->id;
        $graderecord->grade = $grade;
        $graderecord->late = 0;
        $graderecord->completed = time();
        $DB->insert_record('lesson_grades', $graderecord);

        // Update gradebook.
        require_once($CFG->dirroot . '/mod/lesson/lib.php');
        lesson_update_grades($lesson, $USER->id);

        // Completion.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        // Trigger event.
        $event = \mod_lesson\event\lesson_ended::create([
            'objectid' => $lesson->id,
            'context'  => $context,
            'relateduserid' => $USER->id,
        ]);
        $event->trigger();
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'nextpageid'     => new external_value(PARAM_INT, 'Next page ID (0 = lesson end)'),
            'iscorrect'      => new external_value(PARAM_BOOL, 'Whether answer was correct'),
            'feedback'       => new external_value(PARAM_RAW, 'Answer feedback HTML'),
            'lessonfinished' => new external_value(PARAM_BOOL, 'Lesson has ended'),
        ]);
    }
}

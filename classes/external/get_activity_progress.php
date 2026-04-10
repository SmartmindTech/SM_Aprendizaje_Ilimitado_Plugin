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
 * Lightweight progress-only endpoint for the course player's polling loop.
 *
 * Returns completion counts for a single activity (cmid) without any
 * content rendering. Called every ~10 seconds while the player is open
 * to update the progress bar and sidebar rings.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_activity_progress extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid = $params['cmid'];

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $course = get_course($cm->course);
        $modname = $cm->modname;

        // Check completion state.
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info($course);
        $iscomplete = false;
        if ($completion->is_enabled()) {
            $modinfo = get_fast_modinfo($course);
            try {
                $cminfo = $modinfo->get_cm($cm->id);
                $data = $completion->get_data($cminfo, true, $USER->id);
                $iscomplete = ($data->completionstate == COMPLETION_COMPLETE
                    || $data->completionstate == COMPLETION_COMPLETE_PASS);
            } catch (\Throwable $ignore) {
            }
        }

        // Get granular item progress using the same logic as get_activity_content.
        $completeditems = 0;
        $totalitems = 0;

        try {
            switch ($modname) {
                case 'scorm':
                    $scorm = $DB->get_record('scorm', ['id' => $cm->instance], 'id', MUST_EXIST);
                    // Use the same slide detection the tracking JS injection uses.
                    $totalitems = \local_sm_graphics_plugin\scorm\slidecount::detect($cm->id, $scorm->id);
                    $completeditems = self::get_scorm_current_slide($scorm->id, $USER->id);
                    break;

                case 'book':
                    $book = $DB->get_record('book', ['id' => $cm->instance], 'id', MUST_EXIST);
                    $totalitems = $DB->count_records('book_chapters', ['bookid' => $book->id, 'hidden' => 0]);
                    $completeditems = (int) $DB->count_records_sql(
                        "SELECT COUNT(DISTINCT objectid) FROM {logstore_standard_log}
                         WHERE component = 'mod_book' AND eventname = :eventname
                         AND userid = :userid AND contextinstanceid = :cmid",
                        ['eventname' => '\\mod_book\\event\\chapter_viewed',
                         'userid' => $USER->id, 'cmid' => $cm->id]
                    );
                    break;

                case 'quiz':
                    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id', MUST_EXIST);
                    $totalitems = (int) $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
                    // Subtract description questions.
                    $desccount = (int) $DB->count_records_sql(
                        "SELECT COUNT(DISTINCT qs.id)
                         FROM {quiz_slots} qs
                         JOIN {question_references} qr ON qr.component = 'mod_quiz'
                             AND qr.questionarea = 'slot' AND qr.itemid = qs.id
                         JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                         JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                         JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'description'
                         WHERE qs.quizid = :quizid",
                        ['quizid' => $quiz->id]
                    );
                    $totalitems -= $desccount;
                    $attempt = $DB->get_record_sql(
                        "SELECT id FROM {quiz_attempts}
                         WHERE quiz = :quizid AND userid = :userid AND state = 'inprogress'
                         ORDER BY attempt DESC LIMIT 1",
                        ['quizid' => $quiz->id, 'userid' => $USER->id]
                    );
                    if ($attempt) {
                        $completeditems = (int) $DB->count_records_select(
                            'question_attempts',
                            "questionusageid = (SELECT uniqueid FROM {quiz_attempts} WHERE id = :attemptid)
                             AND responsesummary IS NOT NULL AND responsesummary <> ''",
                            ['attemptid' => $attempt->id]
                        );
                    }
                    break;

                case 'lesson':
                    $lesson = $DB->get_record('lesson', ['id' => $cm->instance], 'id', MUST_EXIST);
                    $totalitems = $DB->count_records('lesson_pages', ['lessonid' => $lesson->id]);
                    $completeditems = (int) $DB->count_records_sql(
                        "SELECT COUNT(DISTINCT pageid) FROM {lesson_branch}
                         WHERE lessonid = :lessonid AND userid = :userid",
                        ['lessonid' => $lesson->id, 'userid' => $USER->id]
                    );
                    break;
            }
        } catch (\Throwable $ignore) {
        }

        return [
            'cmid'           => $cmid,
            'completeditems' => max($completeditems, 0),
            'totalitems'     => max($totalitems, 0),
            'iscomplete'     => $iscomplete,
        ];
    }

    /**
     * Get the current/furthest slide position for a SCORM activity.
     *
     * Checks multiple SCORM elements and parses vendor-specific formats:
     *   - cmi.core.lesson_location / cmi.location (iSpring, Basic, Generic)
     *   - cmi.suspend_data (Captivate: cs=N, Rise360: JSON lesson index)
     *   - cmi.core.score.raw (progress hint when no position data)
     *
     * Returns the MAXIMUM position found across all sources (furthest progress).
     */
    private static function get_scorm_current_slide(int $scormid, int $userid): int {
        global $DB;

        $maxslide = 0;

        // Read all relevant SCORM elements in one query.
        $elements = ['cmi.core.lesson_location', 'cmi.location', 'cmi.suspend_data', 'cmi.core.score.raw'];
        $elementsin = "'" . implode("','", $elements) . "'";

        $rows = [];
        if ($DB->get_manager()->table_exists('scorm_scoes_value')) {
            $rows = $DB->get_records_sql(
                "SELECT se.element, sv.value
                   FROM {scorm_scoes_value} sv
                   JOIN {scorm_attempt} sa ON sv.attemptid = sa.id
                   JOIN {scorm_element} se ON sv.elementid = se.id
                  WHERE sa.scormid = :scormid
                    AND sa.userid = :userid
                    AND se.element IN ({$elementsin})
                  ORDER BY sa.attempt DESC",
                ['scormid' => $scormid, 'userid' => $userid]
            );
        } else if ($DB->get_manager()->table_exists('scorm_scoes_track')) {
            $rows = $DB->get_records_sql(
                "SELECT element, value
                   FROM {scorm_scoes_track}
                  WHERE scormid = :scormid
                    AND userid = :userid
                    AND element IN ({$elementsin})
                  ORDER BY timemodified DESC",
                ['scormid' => $scormid, 'userid' => $userid]
            );
        }

        foreach ($rows as $row) {
            $el = $row->element;
            $val = trim($row->value ?? '');
            if ($val === '') {
                continue;
            }

            if ($el === 'cmi.core.lesson_location' || $el === 'cmi.location') {
                $slide = self::parse_slide_number($val);
                if ($slide > $maxslide) {
                    $maxslide = $slide;
                }
            } else if ($el === 'cmi.suspend_data') {
                // Captivate format: cs=N,vs=...
                if (preg_match('/\bcs=(\d+)/', $val, $m)) {
                    $slide = (int) $m[1];
                    if ($slide > $maxslide) {
                        $maxslide = $slide;
                    }
                }
                // Rise 360: lesson_N or section_N in JSON-like data.
                if (preg_match('/lesson[_:](\d+)/i', $val, $m)) {
                    $slide = (int) $m[1] + 1; // Rise uses 0-based.
                    if ($slide > $maxslide) {
                        $maxslide = $slide;
                    }
                }
            } else if ($el === 'cmi.core.score.raw') {
                // Score as a rough progress hint — some packages set score
                // proportionally to slides viewed.
                $score = (float) $val;
                if ($score > 0 && $score <= 100) {
                    // We don't know total here, but this is a fallback.
                    // The tracking JS sends the accurate value via postMessage.
                }
            }
        }

        return $maxslide;
    }

    /**
     * Parse a slide number from a lesson_location string.
     *
     * Supports: pure number ("5"), fraction ("5/139"), Articulate
     * ("slide5"), trailing number ("scene1_slide5"), Rise section
     * ("section_3").
     */
    private static function parse_slide_number(string $val): int {
        $val = trim($val);
        // Fraction: "5/139"
        if (preg_match('/^(\d+)\//', $val, $m)) {
            return (int) $m[1];
        }
        // Pure number: "5"
        if (preg_match('/^\d+$/', $val)) {
            return (int) $val;
        }
        // Articulate: "slide5" or "#/slides/xxx"
        if (preg_match('/slide(\d+)/i', $val, $m)) {
            return (int) $m[1];
        }
        // Rise 360: "section_3" (0-based → add 1)
        if (preg_match('/section[_:](\d+)/i', $val, $m)) {
            return (int) $m[1] + 1;
        }
        // Trailing number fallback
        if (preg_match('/(\d+)$/', $val, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'           => new external_value(PARAM_INT, 'Course module ID'),
            'completeditems' => new external_value(PARAM_INT, 'Completed sub-items (slides/pages/questions)'),
            'totalitems'     => new external_value(PARAM_INT, 'Total sub-items'),
            'iscomplete'     => new external_value(PARAM_BOOL, 'Activity fully completed'),
        ]);
    }
}

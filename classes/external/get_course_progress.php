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
 * AJAX: Get course completion progress for all activities.
 *
 * Returns the completion status and fractional progress for every trackable
 * activity in a course, plus overall progress percentage. Used by the course
 * page player to sync sidebar/header progress in real time.
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

/**
 * External function to get course completion progress.
 */
class get_course_progress extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Execute: get completion data for all activities.
     *
     * @param int $courseid Course ID.
     * @return array {activities[], completedcount, totalcount, overallprogress}
     */
    public static function execute(int $courseid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $context = \context_course::instance($courseid);
        self::validate_context($context);

        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);

        $activities = [];
        $completedcount = 0;
        $totalcount = 0;

        // Pre-fetch viewed events for log-based fallback (single query).
        $viewedcmids = [];
        try {
            $logrecords = $DB->get_records_sql(
                "SELECT DISTINCT contextinstanceid
                 FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND userid = :userid
                   AND action = 'viewed'
                   AND target = 'course_module'",
                ['courseid' => $courseid, 'userid' => $USER->id]
            );
            foreach ($logrecords as $rec) {
                $viewedcmids[(int)$rec->contextinstanceid] = true;
            }
        } catch (\Exception $e) {
            // Log store may not be available — fallback is best-effort.
        }

        // Collect cmids by module type for batch progress queries.
        $cmidsbytype = [];
        $cminfomap = [];
        foreach ($modinfo->get_cms() as $cminfo) {
            if ($cminfo->modname === 'forum' || !$cminfo->uservisible) {
                continue;
            }
            $cmidsbytype[$cminfo->modname][$cminfo->id] = $cminfo->instance;
            $cminfomap[$cminfo->id] = $cminfo;
        }

        // Batch-query fractional progress for all activities.
        $progressmap = self::calculate_activity_progress($DB, $USER->id, $courseid, $cmidsbytype, $modinfo);

        // Activity types with granular item-level progress (slide/chapter/page/question).
        $granulartypes = ['scorm', 'book', 'quiz', 'lesson'];

        foreach ($cminfomap as $cmid => $cminfo) {
            // Activities without completion tracking: use log-based fallback.
            if ($completion->is_enabled($cminfo) == COMPLETION_TRACKING_NONE) {
                $viewed = isset($viewedcmids[$cminfo->id]) ? 1 : 0;

                // For granular types, always use fractional progress (never "viewed = complete").
                if (in_array($cminfo->modname, $granulartypes)) {
                    $progress = isset($progressmap[$cmid]) ? $progressmap[$cmid] : 0.0;
                    $iscomplete = ($progress >= 1.0) ? 1 : 0;
                } else {
                    $progress = $viewed ? 1.0 : 0.0;
                    $iscomplete = $viewed;
                }

                $activities[] = [
                    'cmid'      => $cminfo->id,
                    'completed' => $iscomplete,
                    'progress'  => round($progress, 4),
                ];
                $totalcount++;
                if ($iscomplete) {
                    $completedcount++;
                }
                continue;
            }

            $data = $completion->get_data($cminfo, true, $USER->id);
            $iscomplete = in_array($data->completionstate, [
                COMPLETION_COMPLETE,
                COMPLETION_COMPLETE_PASS,
            ]);

            // Merge completion + item progress.
            // Complete → 1.0 (override). Has item progress → fractional. Otherwise → 0.0.
            if ($iscomplete) {
                $progress = 1.0;
            } else {
                $progress = isset($progressmap[$cmid]) ? $progressmap[$cmid] : 0.0;

                // Auto-complete: when fractional progress reaches 1.0, mark as complete.
                // Skip SCORM — SCORM handles its own completion via the SCORM API.
                if ($progress >= 1.0 && $cminfo->modname !== 'scorm') {
                    try {
                        $completion->update_state($cminfo, COMPLETION_COMPLETE, $USER->id);
                        $iscomplete = true;
                    } catch (\Exception $e) {
                        debugging('Auto-complete error for cmid ' . $cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
            }

            $activities[] = [
                'cmid'      => $cminfo->id,
                'completed' => $iscomplete ? 1 : 0,
                'progress'  => round($progress, 4),
            ];

            $totalcount++;
            if ($iscomplete) {
                $completedcount++;
            }
        }

        // Overall progress from fractional sum.
        $progresssum = 0.0;
        foreach ($activities as $a) {
            $progresssum += $a['progress'];
        }
        $overallprogress = $totalcount > 0 ? (int)round(($progresssum / $totalcount) * 100) : 0;

        return [
            'activities'      => $activities,
            'completedcount'  => $completedcount,
            'totalcount'      => $totalcount,
            'overallprogress' => $overallprogress,
        ];
    }

    /**
     * Batch-query fractional progress for all activities in a course.
     *
     * Returns a map of cmid => float (0.0 to 1.0) for activities that have
     * item-level progress (book chapters, quiz questions, lesson pages, SCORM slides).
     *
     * @param \moodle_database $DB Database object.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param array $cmidsbytype Map of modname => [cmid => instanceid].
     * @param \course_modinfo $modinfo Course module info.
     * @return array<int, float> cmid => progress (0.0 to 1.0).
     */
    private static function calculate_activity_progress($DB, int $userid, int $courseid, array $cmidsbytype, $modinfo): array {
        $progress = [];

        // --- Book: viewedChapters / totalChapters ---
        if (!empty($cmidsbytype['book'])) {
            $progress = array_replace($progress, self::calculate_book_progress($DB, $userid, $cmidsbytype['book']));
        }

        // --- Quiz: answeredQuestions / totalSlots ---
        if (!empty($cmidsbytype['quiz'])) {
            $progress = array_replace($progress, self::calculate_quiz_progress($DB, $userid, $cmidsbytype['quiz']));
        }

        // --- Lesson: viewedPages / totalPages ---
        if (!empty($cmidsbytype['lesson'])) {
            $progress = array_replace($progress, self::calculate_lesson_progress($DB, $userid, $cmidsbytype['lesson']));
        }

        // --- SCORM: currentSlide / totalSlides ---
        if (!empty($cmidsbytype['scorm'])) {
            $progress = array_replace($progress, self::calculate_scorm_progress($DB, $userid, $cmidsbytype['scorm'], $modinfo));
        }

        return $progress;
    }

    /**
     * Calculate fractional progress for book activities.
     *
     * @param \moodle_database $DB Database object.
     * @param int $userid User ID.
     * @param array $cmids Map of cmid => instanceid.
     * @return array<int, float> cmid => progress.
     */
    private static function calculate_book_progress($DB, int $userid, array $cmids): array {
        $progress = [];

        if (empty($cmids)) {
            return $progress;
        }

        // Batch: total chapters per book.
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($cmids), SQL_PARAMS_NAMED);
        $totals = $DB->get_records_sql(
            "SELECT cm.id AS cmid, COUNT(bc.id) AS total
             FROM {course_modules} cm
             JOIN {book_chapters} bc ON bc.bookid = cm.instance AND bc.hidden = 0
             WHERE cm.id $insql
             GROUP BY cm.id",
            $inparams
        );

        // Batch: viewed chapters per user per cm.
        list($insql2, $inparams2) = $DB->get_in_or_equal(array_keys($cmids), SQL_PARAMS_NAMED);
        $inparams2['userid'] = $userid;
        $inparams2['eventname'] = '\\mod_book\\event\\chapter_viewed';
        $viewed = $DB->get_records_sql(
            "SELECT contextinstanceid AS cmid, COUNT(DISTINCT objectid) AS viewed
             FROM {logstore_standard_log}
             WHERE contextinstanceid $insql2
               AND userid = :userid
               AND eventname = :eventname
             GROUP BY contextinstanceid",
            $inparams2
        );

        foreach ($cmids as $cmid => $instanceid) {
            $total = isset($totals[$cmid]) ? (int)$totals[$cmid]->total : 0;
            $done = isset($viewed[$cmid]) ? (int)$viewed[$cmid]->viewed : 0;
            if ($total > 0) {
                $progress[$cmid] = min($done / $total, 1.0);
            }
        }

        return $progress;
    }

    /**
     * Calculate fractional progress for quiz activities.
     *
     * @param \moodle_database $DB Database object.
     * @param int $userid User ID.
     * @param array $cmids Map of cmid => instanceid.
     * @return array<int, float> cmid => progress.
     */
    private static function calculate_quiz_progress($DB, int $userid, array $cmids): array {
        $progress = [];

        if (empty($cmids)) {
            return $progress;
        }

        // Batch: total slots per quiz (minus description-type questions).
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($cmids), SQL_PARAMS_NAMED);
        $totals = $DB->get_records_sql(
            "SELECT cm.id AS cmid, COUNT(qs.id) AS total
             FROM {course_modules} cm
             JOIN {quiz_slots} qs ON qs.quizid = cm.instance
             WHERE cm.id $insql
             GROUP BY cm.id",
            $inparams
        );

        // Per quiz: page position and answered questions in latest in-progress attempt.
        foreach ($cmids as $cmid => $instanceid) {
            $total = isset($totals[$cmid]) ? (int)$totals[$cmid]->total : 0;
            if ($total <= 0) {
                continue;
            }

            try {
                $attempt = $DB->get_record_sql(
                    "SELECT id, currentpage FROM {quiz_attempts}
                     WHERE quiz = :quizid AND userid = :userid AND state = 'inprogress'
                     ORDER BY attempt DESC LIMIT 1",
                    ['quizid' => $instanceid, 'userid' => $userid]
                );

                if ($attempt) {
                    // Page-position-based progress (currentpage is 0-based, quiz_slots.page is 1-based).
                    $pagenum = $attempt->currentpage + 1;
                    $slotsuptopage = (int) $DB->count_records_select(
                        'quiz_slots',
                        "quizid = :quizid AND page <= :page",
                        ['quizid' => $instanceid, 'page' => $pagenum]
                    );
                    $pageprogress = $slotsuptopage / $total;

                    // Answer-based progress (questions with submitted responses).
                    $answered = $DB->count_records_select(
                        'question_attempts',
                        "questionusageid = (SELECT uniqueid FROM {quiz_attempts} WHERE id = :attemptid)
                         AND responsesummary IS NOT NULL AND responsesummary <> ''",
                        ['attemptid' => $attempt->id]
                    );
                    $answerprogress = $answered / $total;

                    // Use the higher of page position or answered questions.
                    $progress[$cmid] = min(max($pageprogress, $answerprogress), 1.0);
                }
            } catch (\Exception $e) {
                debugging('Quiz progress error for cmid ' . $cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $progress;
    }

    /**
     * Calculate fractional progress for lesson activities.
     *
     * @param \moodle_database $DB Database object.
     * @param int $userid User ID.
     * @param array $cmids Map of cmid => instanceid.
     * @return array<int, float> cmid => progress.
     */
    private static function calculate_lesson_progress($DB, int $userid, array $cmids): array {
        $progress = [];

        if (empty($cmids)) {
            return $progress;
        }

        // Batch: total pages per lesson.
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($cmids), SQL_PARAMS_NAMED);
        $totals = $DB->get_records_sql(
            "SELECT cm.id AS cmid, COUNT(lp.id) AS total
             FROM {course_modules} cm
             JOIN {lesson_pages} lp ON lp.lessonid = cm.instance
             WHERE cm.id $insql
             GROUP BY cm.id",
            $inparams
        );

        // Per lesson: unique viewed pages.
        foreach ($cmids as $cmid => $instanceid) {
            $total = isset($totals[$cmid]) ? (int)$totals[$cmid]->total : 0;
            if ($total <= 0) {
                continue;
            }

            try {
                $viewedpages = $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT pageid) FROM {lesson_branch}
                     WHERE lessonid = :lessonid AND userid = :userid",
                    ['lessonid' => $instanceid, 'userid' => $userid]
                );
                if ($viewedpages > 0) {
                    $progress[$cmid] = min($viewedpages / $total, 1.0);
                }
            } catch (\Exception $e) {
                debugging('Lesson progress error for cmid ' . $cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $progress;
    }

    /**
     * Calculate fractional progress for SCORM activities.
     *
     * @param \moodle_database $DB Database object.
     * @param int $userid User ID.
     * @param array $cmids Map of cmid => instanceid.
     * @param \course_modinfo $modinfo Course module info.
     * @return array<int, float> cmid => progress.
     */
    private static function calculate_scorm_progress($DB, int $userid, array $cmids, $modinfo): array {
        $progress = [];

        foreach ($cmids as $cmid => $instanceid) {
            try {
                $context = \context_module::instance($cmid);
                $total = get_activity_content::detect_scorm_slides($context, $instanceid);
                $current = get_activity_content::get_scorm_current_slide($instanceid, $userid, $cmid);

                if ($total > 0 && $current > 0) {
                    $progress[$cmid] = min($current / $total, 1.0);
                }
            } catch (\Exception $e) {
                debugging('SCORM progress error for cmid ' . $cmid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $progress;
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activities' => new external_multiple_structure(
                new external_single_structure([
                    'cmid'      => new external_value(PARAM_INT, 'Course module ID'),
                    'completed' => new external_value(PARAM_INT, '1 if completed, 0 otherwise'),
                    'progress'  => new external_value(PARAM_FLOAT, 'Activity progress 0.0 to 1.0'),
                ])
            ),
            'completedcount'  => new external_value(PARAM_INT, 'Total completed activities'),
            'totalcount'      => new external_value(PARAM_INT, 'Total trackable activities'),
            'overallprogress' => new external_value(PARAM_INT, 'Overall progress percentage (0-100)'),
        ]);
    }
}

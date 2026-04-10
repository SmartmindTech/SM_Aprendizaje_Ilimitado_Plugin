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

namespace local_sm_graphics_plugin\gamification;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared SQL fragment for filtering completions to "trackable" activities.
 *
 * The SmartMind course player only renders activities that represent real
 * learning content — forums and labels are excluded from the navigable
 * list (see CoursePlayerActivity / player.vue flatActivities). The
 * gamification metrics (XP, streak, hours, missions, achievements) need
 * to follow the same definition so that the numbers the user sees match
 * what they actually do in the player.
 *
 * Usage:
 *
 *   $cf  = completion_filter::build('cmc');
 *   $sql = "SELECT COUNT(*)
 *             FROM {course_modules_completion} cmc
 *             {$cf['join']}
 *            WHERE cmc.userid = :uid AND cmc.completionstate >= 1
 *              AND {$cf['where']}";
 *   $params = ['uid' => $userid] + $cf['params'];
 *   $count  = $DB->count_records_sql($sql, $params);
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_filter {

    /**
     * Module names that the SmartMind player does NOT show as standalone
     * activities and that therefore should not count toward gamification.
     *
     * - forum: discussion threads, may have completion criteria but the
     *   player skips them entirely.
     * - label: text-only "section break" widgets, never actual activities.
     */
    public const EXCLUDED_MODULES = ['forum', 'label'];

    /**
     * Build the JOIN + WHERE pieces needed to filter a query on
     * {course_modules_completion} so it only counts trackable activities.
     *
     * @param string $cmcAlias    Alias used in the caller's query for the
     *                            {course_modules_completion} table.
     * @param string $paramPrefix Prefix for the generated named placeholders,
     *                            in case the caller already uses :uid / :start
     *                            and we don't want collisions.
     * @return array{join: string, where: string, params: array<string, mixed>}
     */
    public static function build(string $cmcAlias = 'cmc', string $paramPrefix = 'cf_'): array {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal(
            self::EXCLUDED_MODULES,
            SQL_PARAMS_NAMED,
            $paramPrefix . 'mod',
            false // false = NOT IN
        );

        return [
            'join'   => "JOIN {course_modules} {$paramPrefix}cm
                              ON {$paramPrefix}cm.id = {$cmcAlias}.coursemoduleid
                         JOIN {modules} {$paramPrefix}m
                              ON {$paramPrefix}m.id = {$paramPrefix}cm.module",
            'where'  => "{$paramPrefix}m.name $insql",
            'params' => $inparams,
        ];
    }

    /**
     * Course progress percentage (0-100) for a single user, considering ONLY
     * trackable activities (i.e. those the SmartMind player shows). Mirrors
     * the behaviour of \core_completion\progress::get_course_progress_percentage()
     * but with our exclusion list applied so the percentage matches the
     * activity counts the user sees in the player.
     *
     * Counts:
     *   - course modules in this course
     *   - that are not deleted
     *   - that are visible to the user
     *   - that have completion tracking enabled (cm.completion > 0)
     *   - whose module type is not in EXCLUDED_MODULES
     *
     * Returns 0 when the course has no progress-trackable activities.
     */
    /**
     * Trackable activity counts for a course: total, completed and percentage.
     *
     * @return array{total: int, completed: int, percentage: int}
     */
    public static function course_progress(int $courseid, int $userid): array {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal(
            self::EXCLUDED_MODULES,
            SQL_PARAMS_NAMED,
            'cfprog_mod',
            false
        );

        $sql = "SELECT
                    COUNT(cm.id) AS total,
                    SUM(CASE WHEN cmc.completionstate >= 1 THEN 1 ELSE 0 END) AS completed
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
             LEFT JOIN {course_modules_completion} cmc
                       ON cmc.coursemoduleid = cm.id AND cmc.userid = :uid
                 WHERE cm.course = :cid
                   AND cm.deletioninprogress = 0
                   AND cm.visible = 1
                   AND cm.completion > 0
                   AND m.name $insql";

        $params = ['uid' => $userid, 'cid' => $courseid] + $inparams;
        $row = $DB->get_record_sql($sql, $params);

        $total = (int) ($row->total ?? 0);
        $completed = (int) ($row->completed ?? 0);
        $percentage = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return ['total' => $total, 'completed' => $completed, 'percentage' => $percentage];
    }

    /**
     * Shorthand — returns only the percentage (0–100).
     */
    public static function course_progress_percentage(int $courseid, int $userid): int {
        return self::course_progress($courseid, $userid)['percentage'];
    }

    // ──────────────────────────────────────────────────────────────────
    // Unified streak (login-based — most lenient)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Consecutive-day login streak for a user (Duolingo-style grace).
     *
     * Counts consecutive days that have a `SOURCE_LOGIN_DAILY` entry in
     * the XP log. If today has no entry yet but yesterday does, the
     * streak is considered alive (grace period until midnight).
     *
     * This is the single source of truth — dashboard, profile,
     * achievements and missions all call this method.
     *
     * @param int $userid
     * @return int Number of consecutive days (0–365).
     */
    public static function login_streak(int $userid): int {
        global $DB;

        $checkdate = new \DateTime('now', \core_date::get_user_timezone_object());

        $hasentryfor = function (\DateTime $d) use ($DB, $userid): bool {
            $epoch = (int) (clone $d)->setTime(0, 0, 0)->getTimestamp();
            return $DB->record_exists('local_smgp_xp_log', [
                'userid'   => $userid,
                'source'   => xp_service::SOURCE_LOGIN_DAILY,
                'sourceid' => $epoch,
            ]);
        };

        // Grace: if today is empty, check yesterday. Break only if both empty.
        if (!$hasentryfor($checkdate)) {
            $checkdate->modify('-1 day');
            if (!$hasentryfor($checkdate)) {
                return 0;
            }
        }

        $streak = 0;
        for ($i = 0; $i < 365; $i++) {
            if ($hasentryfor($checkdate)) {
                $streak++;
                $checkdate->modify('-1 day');
            } else {
                break;
            }
        }
        return $streak;
    }

    // ──────────────────────────────────────────────────────────────────
    // Unified "is course completed"
    // ──────────────────────────────────────────────────────────────────

    /**
     * Whether a course should be considered completed for a user.
     *
     * A course is completed if ANY of these is true:
     *   1. Moodle standard completion (`course_completions.timecompleted > 0`)
     *   2. IOMAD track completion (`local_iomad_track.timecompleted > 0`)
     *   3. Trackable progress ≥ threshold (from `local_smgp_course_meta`,
     *      defaults to 100%)
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_course_completed(int $courseid, int $userid): bool {
        global $DB;

        // 1. Moodle standard completion.
        if ($DB->record_exists_select('course_completions',
                'course = :cid AND userid = :uid AND timecompleted > 0',
                ['cid' => $courseid, 'uid' => $userid])) {
            return true;
        }

        // 2. IOMAD track completion (table may not exist).
        if ($DB->get_manager()->table_exists('local_iomad_track')) {
            if ($DB->record_exists_select('local_iomad_track',
                    'courseid = :cid AND userid = :uid AND timecompleted > 0',
                    ['cid' => $courseid, 'uid' => $userid])) {
                return true;
            }
        }

        // 3. Trackable progress ≥ custom threshold (default 100%).
        $meta = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
        $threshold = ($meta && isset($meta->completion_percentage))
            ? (int) $meta->completion_percentage : 100;
        if (self::course_progress_percentage($courseid, $userid) >= $threshold) {
            return true;
        }

        return false;
    }

    /**
     * Count how many courses a user has completed (using unified criteria).
     *
     * @param int $userid
     * @return int
     */
    public static function completed_course_count(int $userid): int {
        $courses = enrol_get_users_courses($userid, true);
        $count = 0;
        foreach ($courses as $course) {
            if (self::is_course_completed((int) $course->id, $userid)) {
                $count++;
            }
        }
        return $count;
    }
}

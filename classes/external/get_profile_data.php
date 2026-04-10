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
require_once($CFG->libdir . '/completionlib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * Returns profile page data (avatar, stats, 7-day activity chart, streak).
 *
 * Ported from pages/profile.php (dev branch) into a bulk fetcher for the Vue SPA.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_profile_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID (0 = current user)', VALUE_DEFAULT, 0),
            'lang'   => new external_value(PARAM_ALPHANUMEXT, 'SPA language: es | en | pt_br', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $userid = 0, string $lang = ''): array {
        global $CFG, $DB, $USER, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'lang'   => $lang,
        ]);
        self::validate_context(\context_system::instance());

        // Resolve the language: prefer the validated args param, fall back
        // to the URL query string (for SPA builds that pass lang outside
        // the args object). Final fallback is 'es'. This is normalized to
        // the supported set inside the services themselves.
        $lang = $params['lang'] !== ''
            ? $params['lang']
            : optional_param('lang', '', PARAM_ALPHANUMEXT);

        $userid = $params['userid'] > 0 ? $params['userid'] : $USER->id;
        $user = \core_user::get_user($userid, '*', MUST_EXIST);

        // Avatar.
        $userpicture = new \user_picture($user);
        $userpicture->size = 150;
        $avatarurl = $userpicture->get_url($PAGE)->out(false);

        // Department.
        $department = '';
        if (!empty($user->department)) {
            $department = $user->department;
        } else if (!empty($user->institution)) {
            $department = $user->institution;
        }

        // Join date.
        $joindate = userdate($user->timecreated, '%B %Y');

        // Enrolled courses count.
        $enrolledcourses = enrol_get_users_courses($userid, true);
        $coursecount = count($enrolledcourses);

        // Completed courses count (unified criteria).
        $completedcount = \local_sm_graphics_plugin\gamification\completion_filter::completed_course_count($userid);

        // ── Stats based on course-module COMPLETIONS ──
        // We treat an "activity done" as a course_modules_completion row whose
        // completionstate is >= 1 (complete / complete_pass / complete_fail).
        // The timemodified column is updated by Moodle when the state changes,
        // so it doubles as the "transitioned to complete" timestamp.
        // Just visiting an activity, or revisiting an already-complete one,
        // does NOT update timemodified, so it does not count here.
        //
        // We also filter out activity types the SmartMind player doesn't show
        // (forum, label) via completion_filter so the gamification numbers
        // match the player's flatActivities list.
        $cf = \local_sm_graphics_plugin\gamification\completion_filter::build('c');

        // Total hours spent: sum the per-activity duration (AI-estimated when
        // available in local_smgp_activity_duration, otherwise default 5 min).
        $sql = "SELECT COALESCE(SUM(COALESCE(d.duration_minutes, 5)), 0) AS minutes
                  FROM {course_modules_completion} c
             LEFT JOIN {local_smgp_activity_duration} d ON d.cmid = c.coursemoduleid
                  {$cf['join']}
                 WHERE c.userid = :uid AND c.completionstate >= 1
                   AND {$cf['where']}";
        $totalminutes = (int) $DB->get_field_sql($sql, ['uid' => $userid] + $cf['params']);
        $totalhours = (int) round($totalminutes / 60);

        // Weekly activity: count completions whose timemodified falls in each
        // day of the current week (Mon..Sun).
        $weekdays = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
        $weekactivity = [];
        $today = new \DateTime('now', \core_date::get_user_timezone_object());
        $dayofweek = (int) $today->format('N');

        for ($i = 1; $i <= 7; $i++) {
            $date = clone $today;
            $offset = $i - $dayofweek;
            $date->modify("{$offset} days");
            $daystart = (clone $date)->setTime(0, 0, 0)->getTimestamp();
            $dayend = (clone $date)->setTime(23, 59, 59)->getTimestamp();

            $countsql = "SELECT COUNT(c.id)
                           FROM {course_modules_completion} c
                           {$cf['join']}
                          WHERE c.userid = :uid AND c.completionstate >= 1
                            AND c.timemodified BETWEEN :start AND :end
                            AND {$cf['where']}";
            $count = (int) $DB->count_records_sql(
                $countsql,
                ['uid' => $userid, 'start' => $daystart, 'end' => $dayend] + $cf['params']
            );

            $weekactivity[] = [
                'day'     => $weekdays[$i - 1],
                'count'   => $count,
                'istoday' => ($i == $dayofweek),
                'ispast'  => ($i <= $dayofweek),
                'height'  => 0, // set below
            ];
        }

        // Scale bar heights relative to max.
        $maxactivity = max(array_column($weekactivity, 'count'));
        if ($maxactivity < 1) {
            $maxactivity = 1;
        }
        foreach ($weekactivity as &$day) {
            $day['height'] = (int) round(($day['count'] / $maxactivity) * 100);
        }
        unset($day);

        // Streak: unified login-based streak (single source of truth).
        $streak = \local_sm_graphics_plugin\gamification\completion_filter::login_streak($userid);

        // ── Gamification: XP, level, achievements, recent XP feed ──
        // Award the daily-visit XP bonus first (idempotent per calendar day)
        // so opening the profile counts as today's "login" even if Moodle's
        // user_loggedin event never fired (long-lived sessions, SSO, etc.).
        $todayepoch = (clone $today)->setTime(0, 0, 0)->getTimestamp();
        \local_sm_graphics_plugin\gamification\xp_service::award_xp(
            $userid,
            \local_sm_graphics_plugin\gamification\xp_service::SOURCE_LOGIN_DAILY,
            (int) $todayepoch,
            \local_sm_graphics_plugin\gamification\xp_service::XP_PER_LOGIN_DAILY,
            'Daily visit bonus'
        );

        // Lazy-evaluate so a fresh user gets a row created on first profile load
        // and any pending achievements are unlocked before we read them.
        \local_sm_graphics_plugin\gamification\achievement_service::check_and_unlock($userid);
        $xprow = \local_sm_graphics_plugin\gamification\xp_service::get_user_xp($userid);
        $progress = \local_sm_graphics_plugin\gamification\xp_service::progress((int) $xprow->xp_total);

        $achievements = \local_sm_graphics_plugin\gamification\achievement_service::user_achievements($userid, $lang);

        $unlockedcount = 0;
        foreach ($achievements as $a) {
            if (!empty($a['unlocked'])) {
                $unlockedcount++;
            }
        }

        $recentxp = \local_sm_graphics_plugin\gamification\xp_service::recent_xp_log($userid, 8);
        $recentxpout = [];
        foreach ($recentxp as $r) {
            $recentxpout[] = [
                'source'      => $r->source,
                'sourceid'    => (int) $r->sourceid,
                'xp_amount'   => (int) $r->xp_amount,
                'description' => (string) ($r->description ?? ''),
                'label'       => \local_sm_graphics_plugin\gamification\xp_service::localize_source($r->source, $lang),
                'timecreated' => (int) $r->timecreated,
            ];
        }

        // Daily / weekly missions (static catalog).
        $missions = \local_sm_graphics_plugin\gamification\mission_service::user_missions($userid, $lang);

        // Company leaderboard: every student in the same IOMAD company,
        // sorted by XP desc then alphabetically. Empty list when the user
        // has no company (e.g. site admin without IOMAD enrolment).
        $leaderboard = self::compute_leaderboard($userid);

        return [
            'userid'          => (int) $user->id,
            'fullname'        => fullname($user),
            'email'           => $user->email,
            'avatarurl'       => $avatarurl,
            'department'      => $department,
            'has_department'  => !empty($department),
            'joindate'        => $joindate,
            'course_count'    => $coursecount,
            'completed_count' => $completedcount,
            'total_hours'     => $totalhours,
            'streak'          => $streak,
            'week_activity'   => $weekactivity,

            // Gamification.
            'xp_total'           => (int) $progress['xp_total'],
            'level'              => (int) $progress['level'],
            'xp_into_level'      => (int) $progress['xp_into_level'],
            'xp_for_next'        => (int) $progress['xp_for_next'],
            'xp_to_next'         => (int) $progress['xp_to_next'],
            'level_progress_pct' => (int) $progress['progress_pct'],
            'achievements'       => $achievements,
            'achievements_unlocked' => $unlockedcount,
            'achievements_total'    => count($achievements),
            'recent_xp'          => $recentxpout,
            'daily_missions'     => $missions['daily'],
            'weekly_missions'    => $missions['weekly'],
            'leaderboard'        => $leaderboard,
        ];
    }

    /**
     * Build the company-wide leaderboard for a user.
     *
     * Lists at most 6 student users of the user's IOMAD company, left-joined
     * with local_smgp_xp so users that haven't earned any XP yet still appear
     * at the bottom. Sort: XP descending, then last name, then first name
     * (case-insensitive). The current user's row is flagged with `isself=true`
     * so the SPA can highlight it.
     *
     * Excluded from the count:
     *   - IOMAD managers (managertype != 0)
     *   - Site admins ($CFG->siteadmins)
     *   - Webservice / token accounts (auth = 'webservice') — covers the
     *     SmartLearning integration service user.
     *   - Deleted / suspended users.
     *
     * Returns an empty array when the user has no IOMAD company row.
     */
    private static function compute_leaderboard(int $userid): array {
        global $CFG, $DB, $PAGE;

        $companyid = (int) $DB->get_field('company_users', 'companyid', ['userid' => $userid]);
        if ($companyid <= 0) {
            return [];
        }

        // LEFT JOIN local_smgp_xp so students with no XP yet still appear.
        // COALESCE makes the sort stable (NULL → 0). Webservice accounts
        // (used by the SmartLearning integration) are excluded at the SQL
        // level so they never reach the result set.
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       COALESCE(x.xp_total, 0) AS xp_total,
                       COALESCE(x.level, 1)    AS level
                  FROM {company_users} cu
                  JOIN {user} u ON u.id = cu.userid
             LEFT JOIN {local_smgp_xp} x ON x.userid = u.id
                 WHERE cu.companyid = :companyid
                   AND cu.managertype = 0
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND u.auth <> 'webservice'
              ORDER BY xp_total DESC,
                       LOWER(u.lastname) ASC,
                       LOWER(u.firstname) ASC";

        $records = $DB->get_records_sql($sql, ['companyid' => $companyid]);

        // Site admins live in $CFG->siteadmins (comma-separated user ids).
        $adminids = [];
        if (!empty($CFG->siteadmins)) {
            $adminids = array_map('intval', explode(',', $CFG->siteadmins));
        }

        $maxrows = 6;
        $rows = [];
        $position = 0;
        foreach ($records as $r) {
            // Drop site admins from the student leaderboard.
            if (in_array((int) $r->id, $adminids, true)) {
                continue;
            }

            $position++;
            $picture = new \user_picture($r);
            $picture->size = 50;
            $rows[] = [
                'userid'    => (int) $r->id,
                'fullname'  => fullname($r),
                'avatarurl' => $picture->get_url($PAGE)->out(false),
                'xp_total'  => (int) $r->xp_total,
                'level'     => (int) $r->level,
                'position'  => $position,
                'isself'    => ((int) $r->id === $userid),
            ];

            if ($position >= $maxrows) {
                break;
            }
        }

        return $rows;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid'          => new external_value(PARAM_INT, 'User ID'),
            'fullname'        => new external_value(PARAM_TEXT, 'Full name'),
            'email'           => new external_value(PARAM_RAW, 'Email'),
            'avatarurl'       => new external_value(PARAM_RAW, 'Avatar URL'),
            'department'      => new external_value(PARAM_TEXT, 'Department or institution'),
            'has_department'  => new external_value(PARAM_BOOL, 'Has department set'),
            'joindate'        => new external_value(PARAM_TEXT, 'Join date (localized)'),
            'course_count'    => new external_value(PARAM_INT, 'Enrolled courses count'),
            'completed_count' => new external_value(PARAM_INT, 'Completed courses count'),
            'total_hours'     => new external_value(PARAM_INT, 'Total hours (last 90 days)'),
            'streak'          => new external_value(PARAM_INT, 'Consecutive days with activity'),
            'week_activity'   => new external_multiple_structure(
                new external_single_structure([
                    'day'     => new external_value(PARAM_TEXT, 'Day letter'),
                    'count'   => new external_value(PARAM_INT, 'Log count'),
                    'istoday' => new external_value(PARAM_BOOL, 'Is today'),
                    'ispast'  => new external_value(PARAM_BOOL, 'Is past day of this week'),
                    'height'  => new external_value(PARAM_INT, 'Bar height 0-100 (for chart)'),
                ])
            ),

            // Gamification.
            'xp_total'              => new external_value(PARAM_INT, 'Total XP earned'),
            'level'                 => new external_value(PARAM_INT, 'Current level'),
            'xp_into_level'         => new external_value(PARAM_INT, 'XP earned within the current level'),
            'xp_for_next'           => new external_value(PARAM_INT, 'XP needed to span this level'),
            'xp_to_next'            => new external_value(PARAM_INT, 'XP remaining to next level'),
            'level_progress_pct'    => new external_value(PARAM_INT, 'Progress percentage to next level (0-100)'),
            'achievements_unlocked' => new external_value(PARAM_INT, 'Number of achievements unlocked'),
            'achievements_total'    => new external_value(PARAM_INT, 'Total achievements available'),
            'achievements'          => new external_multiple_structure(
                new external_single_structure([
                    'code'            => new external_value(PARAM_ALPHANUMEXT, 'Stable identifier'),
                    'name_key'        => new external_value(PARAM_TEXT, 'Lang key (debug)'),
                    'description_key' => new external_value(PARAM_TEXT, 'Lang key (debug)'),
                    'name'            => new external_value(PARAM_TEXT, 'Localized name'),
                    'description'     => new external_value(PARAM_TEXT, 'Localized description'),
                    'icon'            => new external_value(PARAM_TEXT, 'Icon identifier'),
                    'condition_type'  => new external_value(PARAM_TEXT, 'Condition type'),
                    'condition_value' => new external_value(PARAM_INT, 'Threshold value'),
                    'xp_reward'       => new external_value(PARAM_INT, 'XP reward on unlock'),
                    'unlocked'        => new external_value(PARAM_BOOL, 'Whether the user has unlocked it'),
                    'unlocked_at'     => new external_value(PARAM_INT, 'Unlock timestamp (0 if locked)'),
                    'current_value'   => new external_value(PARAM_INT, 'Current progress towards condition'),
                    'progress_pct'    => new external_value(PARAM_INT, 'Progress percentage 0-100'),
                ])
            ),
            'recent_xp' => new external_multiple_structure(
                new external_single_structure([
                    'source'      => new external_value(PARAM_TEXT, 'XP source identifier'),
                    'sourceid'    => new external_value(PARAM_INT, 'Related id'),
                    'xp_amount'   => new external_value(PARAM_INT, 'XP delta'),
                    'description' => new external_value(PARAM_TEXT, 'Optional description'),
                    'label'       => new external_value(PARAM_TEXT, 'Localized human-readable label'),
                    'timecreated' => new external_value(PARAM_INT, 'Timestamp'),
                ])
            ),
            'daily_missions'  => self::missions_struct(),
            'weekly_missions' => self::missions_struct(),
            'leaderboard'     => new external_multiple_structure(
                new external_single_structure([
                    'userid'    => new external_value(PARAM_INT, 'User id'),
                    'fullname'  => new external_value(PARAM_TEXT, 'Display name'),
                    'avatarurl' => new external_value(PARAM_RAW, 'Avatar URL'),
                    'xp_total'  => new external_value(PARAM_INT, 'Lifetime XP'),
                    'level'     => new external_value(PARAM_INT, 'Current level'),
                    'position'  => new external_value(PARAM_INT, '1-based position in the leaderboard'),
                    'isself'    => new external_value(PARAM_BOOL, 'Whether this row belongs to the requesting user'),
                ])
            ),
        ]);
    }

    /**
     * Shared shape for daily / weekly missions in execute_returns().
     */
    private static function missions_struct(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'code'         => new external_value(PARAM_ALPHANUMEXT, 'Mission code'),
                'name'         => new external_value(PARAM_TEXT, 'Localized name'),
                'description'  => new external_value(PARAM_TEXT, 'Localized description'),
                'icon'         => new external_value(PARAM_TEXT, 'Icon identifier'),
                'period'       => new external_value(PARAM_TEXT, 'daily | weekly'),
                'progress'     => new external_value(PARAM_INT, 'Current progress'),
                'target'       => new external_value(PARAM_INT, 'Target value'),
                'progress_pct' => new external_value(PARAM_INT, 'Progress percentage 0-100'),
                'xp_reward'    => new external_value(PARAM_INT, 'XP awarded on claim'),
                'claimable'    => new external_value(PARAM_BOOL, 'Whether the user can claim it now'),
                'claimed'      => new external_value(PARAM_BOOL, 'Whether the user already claimed for this period'),
            ])
        );
    }
}

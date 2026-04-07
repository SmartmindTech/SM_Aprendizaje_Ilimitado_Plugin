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
        ]);
    }

    public static function execute(int $userid = 0): array {
        global $CFG, $DB, $USER, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);
        self::validate_context(\context_system::instance());

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

        // Completed courses count.
        $completedcount = 0;
        foreach ($enrolledcourses as $course) {
            $completion = new \completion_info($course);
            if ($completion->is_course_complete($userid)) {
                $completedcount++;
            }
        }

        // Total time spent (last 90 days, approximate).
        $since = time() - (90 * DAYSECS);
        $logcount = $DB->count_records_select('logstore_standard_log',
            'userid = :uid AND timecreated > :since',
            ['uid' => $userid, 'since' => $since]
        );
        $totalhours = (int) round(($logcount * 30) / 3600);

        // Weekly activity (last 7 days).
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

            $count = $DB->count_records_select('logstore_standard_log',
                'userid = :uid AND timecreated BETWEEN :start AND :end',
                ['uid' => $userid, 'start' => $daystart, 'end' => $dayend]
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

        // Streak (consecutive days with activity, max 365 days backwards).
        $streak = 0;
        $checkdate = clone $today;
        for ($i = 0; $i < 365; $i++) {
            $daystart = (clone $checkdate)->setTime(0, 0, 0)->getTimestamp();
            $dayend = (clone $checkdate)->setTime(23, 59, 59)->getTimestamp();
            $hasactivity = $DB->record_exists_select('logstore_standard_log',
                'userid = :uid AND timecreated BETWEEN :start AND :end',
                ['uid' => $userid, 'start' => $daystart, 'end' => $dayend]
            );
            if ($hasactivity) {
                $streak++;
                $checkdate->modify('-1 day');
            } else {
                break;
            }
        }

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
        ];
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
        ]);
    }
}

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
 * Custom user profile page — Stratoos-style gamified profile.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_login();

global $CFG, $OUTPUT, $PAGE, $USER, $DB;

$userid = optional_param('id', $USER->id, PARAM_INT);
$user = core_user::get_user($userid, '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/profile.php', ['id' => $userid]));
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_title(fullname($user));
$PAGE->set_heading(fullname($user));
$PAGE->set_pagelayout('standard');

// User avatar.
$userpicture = new user_picture($user);
$userpicture->size = 150;
$avatarurl = $userpicture->get_url($PAGE)->out(false);

// Department (from profile or IOMAD).
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
    $completion = new completion_info($course);
    if ($completion->is_course_complete($userid)) {
        $completedcount++;
    }
}

// Total time spent (approximate from logs - last 90 days).
$since = time() - (90 * DAYSECS);
$totaltime = $DB->get_field_sql(
    "SELECT COALESCE(SUM(
        CASE WHEN lead_time IS NOT NULL THEN LEAST(lead_time, 3600) ELSE 300 END
    ), 0)
    FROM (
        SELECT timecreated,
               LEAD(timecreated) OVER (ORDER BY timecreated) - timecreated AS lead_time
        FROM {logstore_standard_log}
        WHERE userid = :uid AND timecreated > :since
    ) sub",
    ['uid' => $userid, 'since' => $since]
);
// Fallback: simple count-based estimate if the above fails.
if (!$totaltime) {
    $logcount = $DB->count_records_select('logstore_standard_log',
        'userid = :uid AND timecreated > :since',
        ['uid' => $userid, 'since' => $since]
    );
    $totaltime = $logcount * 30; // ~30 seconds per log entry.
}
$totalhours = round($totaltime / 3600);

// Weekly activity (last 7 days log counts).
$weekdays = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
$weekactivity = [];
$today = new DateTime('now', core_date::get_user_timezone_object());
$dayofweek = (int)$today->format('N'); // 1=Monday, 7=Sunday.

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

    $ispast = ($i <= $dayofweek);
    $istoday = ($i == $dayofweek);

    $weekactivity[] = [
        'day' => $weekdays[$i - 1],
        'count' => $count,
        'istoday' => $istoday,
        'ispast' => $ispast,
    ];
}

// Find max for bar scaling.
$maxactivity = max(array_column($weekactivity, 'count'));
if ($maxactivity < 1) {
    $maxactivity = 1;
}
$totalweekxp = 0;
foreach ($weekactivity as &$day) {
    $day['height'] = round(($day['count'] / $maxactivity) * 100);
    $day['xp'] = $day['count'] * 5; // Simulated: 5 XP per action.
    $totalweekxp += $day['xp'];
}
unset($day);

// Streak: count consecutive days with activity (going backwards from today).
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

// Simulated gamification data (to be replaced with real backend later).
$xptotal = $streak * 50 + $completedcount * 200 + $coursecount * 25;
$level = max(1, floor($xptotal / 500) + 1);
$xpforlevel = $level * 500;
$xpprogress = $xptotal - (($level - 1) * 500);
$xpremaining = $xpforlevel - $xptotal;
$levelpercent = round(($xpprogress / 500) * 100);
$circumference = 2 * M_PI * 52; // ~326.73
$leveloffset = round($circumference - ($circumference * $levelpercent / 100));

// Streak milestones.
$milestones = [
    ['days' => 3,  'xp' => 25,  'reached' => ($streak >= 3)],
    ['days' => 7,  'xp' => 100, 'reached' => ($streak >= 7)],
    ['days' => 14, 'xp' => 200, 'reached' => ($streak >= 14)],
    ['days' => 30, 'xp' => 500, 'reached' => ($streak >= 30)],
];

// Find next milestone.
$nextmilestone = null;
foreach ($milestones as $m) {
    if (!$m['reached']) {
        $nextmilestone = $m;
        break;
    }
}
$daystonext = $nextmilestone ? ($nextmilestone['days'] - $streak) : 0;
$nextxp = $nextmilestone ? $nextmilestone['xp'] : 0;
$streakprogress = $nextmilestone ? round(($streak / $nextmilestone['days']) * 100) : 100;

$context = [
    'fullname'       => fullname($user),
    'avatarurl'      => $avatarurl,
    'department'     => $department,
    'joindate'       => $joindate,
    'level'          => $level,
    'xptotal'        => number_format($xptotal, 0, ',', '.'),
    'streak'         => $streak,
    'coursecount'     => $coursecount,
    'totalhours'     => $totalhours,
    'weekactivity'   => $weekactivity,
    'totalweekxp'    => $totalweekxp,
    'levelpercent'   => $levelpercent,
    'leveloffset'    => $leveloffset,
    'xpremaining'    => $xpremaining,
    'nextlevel'      => $level + 1,
    'milestones'     => $milestones,
    'daystonext'     => $daystonext,
    'nextxp'         => $nextxp,
    'streakprogress' => $streakprogress,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/profile_page', $context);
echo $OUTPUT->footer();

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
 * Public certificate verification page — no login required.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
// NO require_login() — this is a public page.

global $CFG, $DB, $OUTPUT, $PAGE;

$code = optional_param('code', '', PARAM_ALPHANUMEXT);
$ajax = optional_param('ajax', 0, PARAM_INT);

// AJAX mode: return JSON for inline verification on the login page.
if ($ajax && !empty($code)) {
    header('Content-Type: application/json; charset=utf-8');
    $record = $DB->get_record('local_smgp_cert_codes', ['code' => $code]);
    if (!$record) {
        echo json_encode(['found' => false]);
        exit;
    }
    $user = $DB->get_record('user', ['id' => $record->userid]);
    $course = $DB->get_record('course', ['id' => $record->courseid]);
    $companyname = '';
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('company_users') && $dbman->table_exists('company')) {
        $cu = $DB->get_record('company_users', ['userid' => $record->userid]);
        if ($cu) {
            $company = $DB->get_record('company', ['id' => $cu->companyid]);
            if ($company) {
                $companyname = $company->name;
            }
        }
    }
    if (empty($companyname) && $course) {
        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        if ($category) {
            $companyname = format_string($category->name);
        }
    }
    $completiondate = '';
    $completion = $DB->get_record('course_completions', [
        'userid' => $record->userid, 'course' => $record->courseid,
    ]);
    if ($completion && !empty($completion->timecompleted)) {
        $completiondate = userdate($completion->timecompleted, '%d/%m/%Y');
    }
    if (empty($completiondate)) {
        $lastactivity = $DB->get_record_sql(
            "SELECT MAX(timemodified) AS lasttime FROM {course_modules_completion}
              WHERE userid = :uid AND coursemoduleid IN (
                  SELECT id FROM {course_modules} WHERE course = :cid
              )", ['uid' => $record->userid, 'cid' => $record->courseid]
        );
        if ($lastactivity && !empty($lastactivity->lasttime)) {
            $completiondate = userdate($lastactivity->lasttime, '%d/%m/%Y');
        }
    }
    if (empty($completiondate)) {
        $completiondate = userdate($record->timecreated, '%d/%m/%Y');
    }
    echo json_encode([
        'found' => true,
        'studentname' => $user ? fullname($user) : '',
        'coursename' => $course ? format_string($course->fullname) : '',
        'completiondate' => $completiondate,
        'companyname' => $companyname,
        'code' => $code,
    ]);
    exit;
}

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/verify_certificate.php', ['code' => $code]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('verify_title', 'local_sm_graphics_plugin'));
$PAGE->set_heading(get_string('verify_title', 'local_sm_graphics_plugin'));

$templatecontext = [
    'code'       => $code,
    'hascode'    => !empty($code),
    'found'      => false,
    'error'      => false,
    'wwwroot'    => $CFG->wwwroot,
    'verifyurl'  => (new moodle_url('/local/sm_graphics_plugin/pages/verify_certificate.php'))->out(false),
];

if (!empty($code)) {
    $record = $DB->get_record('local_smgp_cert_codes', ['code' => $code]);

    if ($record) {
        $user = $DB->get_record('user', ['id' => $record->userid]);
        $course = $DB->get_record('course', ['id' => $record->courseid]);

        // Company name — query IOMAD directly (no logged-in user).
        $companyname = '';
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('company_users') && $dbman->table_exists('company')) {
            $cu = $DB->get_record('company_users', ['userid' => $record->userid]);
            if ($cu) {
                $company = $DB->get_record('company', ['id' => $cu->companyid]);
                if ($company) {
                    $companyname = $company->name;
                }
            }
        }
        // Fallback: course category name.
        if (empty($companyname) && $course) {
            $category = $DB->get_record('course_categories', ['id' => $course->category]);
            if ($category) {
                $companyname = format_string($category->name);
            }
        }

        // Completion date.
        $completiondate = '';
        $completion = $DB->get_record('course_completions', [
            'userid' => $record->userid,
            'course' => $record->courseid,
        ]);
        if ($completion && !empty($completion->timecompleted)) {
            $completiondate = userdate($completion->timecompleted, '%d/%m/%Y');
        }
        if (empty($completiondate) && $dbman->table_exists('local_iomad_track')) {
            $track = $DB->get_record_sql(
                "SELECT timecompleted FROM {local_iomad_track}
                  WHERE userid = :uid AND courseid = :cid AND timecompleted > 0
                  ORDER BY timecompleted DESC LIMIT 1",
                ['uid' => $record->userid, 'cid' => $record->courseid]
            );
            if ($track && !empty($track->timecompleted)) {
                $completiondate = userdate($track->timecompleted, '%d/%m/%Y');
            }
        }
        if (empty($completiondate)) {
            $lastactivity = $DB->get_record_sql(
                "SELECT MAX(timemodified) AS lasttime FROM {course_modules_completion}
                  WHERE userid = :uid AND coursemoduleid IN (
                      SELECT id FROM {course_modules} WHERE course = :cid
                  )",
                ['uid' => $record->userid, 'cid' => $record->courseid]
            );
            if ($lastactivity && !empty($lastactivity->lasttime)) {
                $completiondate = userdate($lastactivity->lasttime, '%d/%m/%Y');
            }
        }
        if (empty($completiondate)) {
            $completiondate = userdate($record->timecreated, '%d/%m/%Y');
        }

        $templatecontext['found'] = true;
        $templatecontext['studentname'] = $user ? fullname($user) : '';
        $templatecontext['coursename'] = $course ? format_string($course->fullname) : '';
        $templatecontext['completiondate'] = $completiondate;
        $templatecontext['companyname'] = $companyname;
        $templatecontext['hascompany'] = !empty($companyname);
    } else {
        $templatecontext['error'] = true;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/verify_certificate', $templatecontext);
echo $OUTPUT->footer();

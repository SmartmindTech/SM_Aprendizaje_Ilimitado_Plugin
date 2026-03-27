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
 * Certificate download handler with language selection.
 *
 * Generates certificates using the SmartMind template directly via TCPDF.
 * Works both with and without an iomadcertificate activity in the course.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();

global $CFG, $DB, $USER;

require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');
require_once($CFG->libdir . '/pdflib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$all      = optional_param('all', 0, PARAM_INT);
$certlang = optional_param('certlang', 'es', PARAM_ALPHANUMEXT);

// Validate language.
$validlangs = ['es', 'en', 'pt_br'];
if (!in_array($certlang, $validlangs)) {
    $certlang = 'es';
}

// Load iomadcertificate helpers if available.
$hasiomadcert = file_exists($CFG->dirroot . '/mod/iomadcertificate/locallib.php');
if ($hasiomadcert) {
    require_once($CFG->dirroot . '/mod/iomadcertificate/locallib.php');
}

if ($all) {
    download_all_certificates($USER, $certlang);
} else if ($courseid) {
    download_single_certificate($USER, $courseid, $certlang);
} else {
    throw new \moodle_exception('missingparam', 'error', '', 'courseid');
}

/**
 * Check if a user has completed a course (by any available metric).
 */
function smgp_is_course_completed($userid, $courseid) {
    global $DB;

    // Moodle standard completion.
    if ($DB->get_record_sql(
        "SELECT id FROM {course_completions}
          WHERE userid = :userid AND course = :courseid AND timecompleted > 0
          LIMIT 1",
        ['userid' => $userid, 'courseid' => $courseid]
    )) {
        return true;
    }

    // IOMAD track completion.
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_iomad_track')) {
        if ($DB->get_record_sql(
            "SELECT id FROM {local_iomad_track}
              WHERE userid = :userid AND courseid = :courseid AND timecompleted > 0
              LIMIT 1",
            ['userid' => $userid, 'courseid' => $courseid]
        )) {
            return true;
        }
    }

    // Fallback: check if progress meets custom completion threshold.
    $courseobj = get_course($courseid);
    $progress = \core_completion\progress::get_course_progress_percentage($courseobj, $userid);
    if ($progress !== null) {
        $pricing = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
        $threshold = ($pricing && isset($pricing->completion_percentage)) ? (int)$pricing->completion_percentage : 100;
        if (round($progress) >= $threshold) {
            return true;
        }
    }

    return false;
}

/**
 * Download a single certificate PDF for a course.
 */
function download_single_certificate($user, int $courseid, string $certlang) {
    global $DB, $CFG;

    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course);

    // Verify the user has completed the course.
    if (!smgp_is_course_completed($user->id, $courseid)) {
        throw new \moodle_exception('nocertificateissue', 'local_sm_graphics_plugin');
    }

    // Try to get iomadcertificate data if the activity exists.
    $iomadcertificate = null;
    $issue = null;
    $certrecord = $DB->get_record_sql(
        "SELECT c.*
           FROM {iomadcertificate} c
           JOIN {course_modules} cm ON cm.instance = c.id
           JOIN {modules} m ON m.id = cm.module AND m.name = 'iomadcertificate'
          WHERE c.course = :courseid
          LIMIT 1",
        ['courseid' => $courseid]
    );

    if ($certrecord) {
        $iomadcertificate = $certrecord;
        $issue = $DB->get_record('iomadcertificate_issues', [
            'iomadcertificateid' => $certrecord->id,
            'userid' => $user->id,
        ]);
        if (!$issue && function_exists('iomadcertificate_get_issue')) {
            $cm = get_coursemodule_from_instance('iomadcertificate', $certrecord->id, $courseid, false, IGNORE_MISSING);
            if ($cm) {
                $issue = iomadcertificate_get_issue($course, $user, $certrecord, $cm);
            }
        }
    }

    // Build a minimal issue object if none exists (for standalone SmartMind cert generation).
    if (!$issue) {
        $issue = new \stdClass();
        $issue->id = 0;
        $issue->userid = $user->id;
        $issue->code = local_sm_graphics_plugin_get_cert_code($user->id, $courseid);
        $issue->timecreated = time();
    }

    $pdf = generate_certificate_pdf($user, $course, $iomadcertificate, $issue, $certlang);

    $filename = clean_filename($course->shortname . '_certificate_' . $certlang . '.pdf');
    $pdf->Output($filename, 'D');
    exit;
}

/**
 * Download all certificates as a ZIP file.
 */
function download_all_certificates($user, string $certlang) {
    global $DB;

    $courses = enrol_get_my_courses('*', 'fullname ASC');
    $tempdir = make_temp_directory('smgp_certs_' . $user->id);
    $files = [];

    foreach ($courses as $course) {
        if (!smgp_is_course_completed($user->id, $course->id)) {
            continue;
        }

        // Try to get iomadcertificate data.
        $certrecord = $DB->get_record_sql(
            "SELECT c.*
               FROM {iomadcertificate} c
               JOIN {course_modules} cm ON cm.instance = c.id
               JOIN {modules} m ON m.id = cm.module AND m.name = 'iomadcertificate'
              WHERE c.course = :courseid
              LIMIT 1",
            ['courseid' => $course->id]
        );

        $issue = null;
        if ($certrecord) {
            $issue = $DB->get_record('iomadcertificate_issues', [
                'iomadcertificateid' => $certrecord->id,
                'userid' => $user->id,
            ]);
        }

        if (!$issue) {
            $issue = new \stdClass();
            $issue->id = 0;
            $issue->userid = $user->id;
            $issue->code = local_sm_graphics_plugin_get_cert_code($user->id, $course->id);
            $issue->timecreated = time();
        }

        $pdf = generate_certificate_pdf($user, $course, $certrecord, $issue, $certlang);
        $filename = clean_filename($course->shortname . '_certificate_' . $certlang . '.pdf');
        $filepath = $tempdir . '/' . $filename;
        $pdf->Output($filepath, 'F');
        $files[$filename] = $filepath;
    }

    if (empty($files)) {
        throw new \moodle_exception('nocertificates', 'local_sm_graphics_plugin');
    }

    $zipfilename = 'certificates_' . $certlang . '_' . date('Ymd') . '.zip';
    $zippath = $tempdir . '/' . $zipfilename;

    $zip = new \ZipArchive();
    $zip->open($zippath, \ZipArchive::CREATE);
    foreach ($files as $name => $path) {
        $zip->addFile($path, $name);
    }
    $zip->close();

    send_file($zippath, $zipfilename, 0, 0, false, true, 'application/zip');

    // Cleanup.
    foreach ($files as $path) {
        @unlink($path);
    }
    @unlink($zippath);
    @rmdir($tempdir);
}

/**
 * Generate a certificate PDF using the SmartMind template.
 *
 * @param object $certuser  User object
 * @param object $course    Course object
 * @param object|null $iomadcertificate  iomadcertificate record (null if no activity)
 * @param object $issue     Issue record (real or synthetic)
 * @param string $certlang  Language code
 * @return PDF
 */
function generate_certificate_pdf($certuser, $course, $iomadcertificate, $issue, string $certlang) {
    global $CFG;

    // Make certlang available to the template.
    $GLOBALS['smgp_certlang'] = $certlang;

    $templatedir = $CFG->dirroot . '/mod/iomadcertificate/type/smartmind';

    if (!file_exists($templatedir . '/certificate.php')) {
        // Try local plugin copy if not yet deployed to iomadcertificate.
        $templatedir = $CFG->dirroot . '/local/sm_graphics_plugin/certificate_type/smartmind';
    }

    if (!file_exists($templatedir . '/certificate.php')) {
        throw new \moodle_exception('certificatetypenotfound', 'local_sm_graphics_plugin');
    }

    // Create PDF object — landscape A4.
    $pdf = new \PDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetTitle(format_string($course->fullname));
    $pdf->SetProtection(['modify']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();

    // Include the certificate template — it draws on the $pdf object.
    require($templatedir . '/certificate.php');

    return $pdf;
}

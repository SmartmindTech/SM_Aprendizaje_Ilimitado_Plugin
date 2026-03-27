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
 * SmartMind certificate template for mod_iomadcertificate.
 *
 * Landscape A4 with multi-language support (es/en/pt_br).
 * Deployed to /mod/iomadcertificate/type/smartmind/ by the plugin installer.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Required variables from the calling context: $pdf, $iomadcertificate, $course, $certuser, $issue.
// Optional: $GLOBALS['smgp_certlang'] for language selection.

global $CFG, $DB;

// Load iomadcertificate helpers if available.
$hasiomadcertlib = file_exists($CFG->dirroot . '/mod/iomadcertificate/locallib.php');
if ($hasiomadcertlib) {
    require_once($CFG->dirroot . '/mod/iomadcertificate/locallib.php');
}

// Helper: print centered text (works with or without iomadcertificate).
if (!function_exists('smgp_cert_print_text')) {
    function smgp_cert_print_text($pdf, $x, $y, $align, $font, $style, $size, $text) {
        if (function_exists('iomadcertificate_print_text')) {
            iomadcertificate_print_text($pdf, $x, $y, $align, $font, $style, $size, $text);
        } else {
            $pdf->SetFont($font ?: 'freeserif', $style, $size);
            $w = $pdf->GetStringWidth($text);
            if ($align === 'C') {
                $x = $x - ($w / 2);
            } else if ($align === 'R') {
                $x = $x - $w;
            }
            $pdf->SetXY($x, $y);
            $pdf->Cell($w + 2, $size * 0.5, $text, 0, 0, 'L');
        }
    }
}

// --- Language setup ---
$certlang = isset($GLOBALS['smgp_certlang']) ? $GLOBALS['smgp_certlang'] : 'es';

$strings = [
    'es' => [
        'title'       => 'CERTIFICADO DE FORMACIÓN',
        'certifies'   => 'Se certifica que',
        'document'    => 'DNI/NIE/Pasaporte',
        'birthdate'   => 'Fecha de nacimiento',
        'completed'   => 'ha completado el curso',
        'duration'    => 'con una duración de',
        'hours'       => 'horas',
        'company'     => 'Empresa',
        'startdate'   => 'Fecha de inicio',
        'enddate'     => 'Fecha de finalización',
        'code'        => 'Código de verificación',
    ],
    'en' => [
        'title'       => 'TRAINING CERTIFICATE',
        'certifies'   => 'This certifies that',
        'document'    => 'ID Document',
        'birthdate'   => 'Date of birth',
        'completed'   => 'has completed the course',
        'duration'    => 'with a duration of',
        'hours'       => 'hours',
        'company'     => 'Company',
        'startdate'   => 'Start date',
        'enddate'     => 'Completion date',
        'code'        => 'Verification code',
    ],
    'pt_br' => [
        'title'       => 'CERTIFICADO DE FORMAÇÃO',
        'certifies'   => 'Certifica-se que',
        'document'    => 'Documento de identidade',
        'birthdate'   => 'Data de nascimento',
        'completed'   => 'concluiu o curso',
        'duration'    => 'com uma duração de',
        'hours'       => 'horas',
        'company'     => 'Empresa',
        'startdate'   => 'Data de início',
        'enddate'     => 'Data de conclusão',
        'code'        => 'Código de verificação',
    ],
];

$lang = isset($strings[$certlang]) ? $strings[$certlang] : $strings['es'];

// --- Page dimensions (landscape A4: 297 x 210 mm) ---
$pw = 297;
$ph = 210;

// --- 3-tier image loading: company > site > activity ---
// Company logo.
$companylogo = '';
$companyname = '';
// Locate template assets directory (deployed or local).
$smgptypedir = $CFG->dirroot . '/mod/iomadcertificate/type/smartmind';
if (!is_dir($smgptypedir . '/pix')) {
    $smgptypedir = $CFG->dirroot . '/local/sm_graphics_plugin/certificate_type/smartmind';
}

// Try to get company info via IOMAD.
if (file_exists($CFG->dirroot . '/local/iomad/lib/iomad.php')) {
    require_once($CFG->dirroot . '/local/iomad/lib/iomad.php');
    $companyid = \iomad::is_company_user();
    if ($companyid) {
        $company = new \company($companyid);
        $companyname = $company->get_name();
    }
}

// Fallback company name from category.
if (empty($companyname)) {
    $category = $DB->get_record('course_categories', ['id' => $course->category]);
    if ($category) {
        $companyname = format_string($category->name);
    }
}

// --- Load user custom profile fields ---
if (function_exists('profile_load_custom_fields')) {
    profile_load_custom_fields($certuser);
}

$userdni = '';
$userbirthdate = '';
if (!empty($certuser->profile)) {
    if (!empty($certuser->profile['dni'])) {
        $userdni = $certuser->profile['dni'];
    } else if (!empty($certuser->profile['documento'])) {
        $userdni = $certuser->profile['documento'];
    }
    if (!empty($certuser->profile['birthdate'])) {
        $userbirthdate = $certuser->profile['birthdate'];
    } else if (!empty($certuser->profile['fecha_nacimiento'])) {
        $userbirthdate = $certuser->profile['fecha_nacimiento'];
    }
}

// --- Duration hours ---
$durationhours = 0;
$pricing = $DB->get_record('local_smgp_course_meta', ['courseid' => $course->id]);
if ($pricing && !empty($pricing->duration_hours)) {
    $durationhours = $pricing->duration_hours;
}

// --- Dates and codes ---

// Start date: when the student first accessed the course.
$startdate = '';
// 1. Check logstore for first course view (most accurate).
$firstlog = $DB->get_record_sql(
    "SELECT MIN(timecreated) AS firsttime FROM {logstore_standard_log}
      WHERE userid = :userid AND courseid = :courseid AND action = 'viewed'",
    ['userid' => $certuser->id, 'courseid' => $course->id]
);
if ($firstlog && !empty($firstlog->firsttime)) {
    $startdate = userdate($firstlog->firsttime, '%d/%m/%Y');
}
if (empty($startdate)) {
    // 2. Fallback: enrolment creation time.
    $enroltime = $DB->get_record_sql(
        "SELECT MIN(ue.timecreated) AS enrolcreated FROM {user_enrolments} ue
          JOIN {enrol} e ON e.id = ue.enrolid
          WHERE ue.userid = :userid AND e.courseid = :courseid AND ue.timecreated > 0",
        ['userid' => $certuser->id, 'courseid' => $course->id]
    );
    if ($enroltime && !empty($enroltime->enrolcreated)) {
        $startdate = userdate($enroltime->enrolcreated, '%d/%m/%Y');
    }
}
if (empty($startdate) && !empty($course->startdate)) {
    // 3. Last fallback: course start date.
    $startdate = userdate($course->startdate, '%d/%m/%Y');
}

// End date: when the student completed the course.
$enddate = '';
if ($iomadcertificate && function_exists('iomadcertificate_get_date')) {
    $enddate = iomadcertificate_get_date($iomadcertificate, $certuser, $course);
}
if (empty($enddate)) {
    $completion = $DB->get_record('course_completions', [
        'userid' => $certuser->id,
        'course' => $course->id,
    ]);
    if ($completion && !empty($completion->timecompleted)) {
        $enddate = userdate($completion->timecompleted, '%d/%m/%Y');
    }
}
if (empty($enddate)) {
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_iomad_track')) {
        $track = $DB->get_record_sql(
            "SELECT timecompleted FROM {local_iomad_track}
              WHERE userid = :userid AND courseid = :courseid AND timecompleted > 0
              ORDER BY timecompleted DESC LIMIT 1",
            ['userid' => $certuser->id, 'courseid' => $course->id]
        );
        if ($track && !empty($track->timecompleted)) {
            $enddate = userdate($track->timecompleted, '%d/%m/%Y');
        }
    }
}
if (empty($enddate)) {
    $lastactivity = $DB->get_record_sql(
        "SELECT MAX(timemodified) AS lasttime FROM {course_modules_completion}
          WHERE userid = :userid AND coursemoduleid IN (
              SELECT id FROM {course_modules} WHERE course = :courseid
          )",
        ['userid' => $certuser->id, 'courseid' => $course->id]
    );
    if ($lastactivity && !empty($lastactivity->lasttime)) {
        $enddate = userdate($lastactivity->lasttime, '%d/%m/%Y');
    }
}
if (empty($enddate)) {
    $enddate = userdate(time(), '%d/%m/%Y');
}

$code = $issue->code ?? '';

// --- Draw the certificate ---
// Font: Helvetica (modern, classic sans-serif).
$font = 'helvetica';

// Background / border — try IOMAD branding, fall back to clean black border.
$borderfile = '';
if ($iomadcertificate && function_exists('iomadcertificate_get_border')) {
    $borderfile = iomadcertificate_get_border($iomadcertificate, $course);
}
if (!empty($borderfile) && file_exists($borderfile)) {
    $pdf->Image($borderfile, 0, 0, $pw, $ph);
} else {
    // Black double border (matching reference image).
    $pdf->SetLineStyle(['width' => 0.7, 'color' => [0, 0, 0]]);
    $pdf->Rect(8, 8, $pw - 16, $ph - 16);
    $pdf->SetLineStyle(['width' => 0.3, 'color' => [0, 0, 0]]);
    $pdf->Rect(11, 11, $pw - 22, $ph - 22);
}

// Watermark.
if ($iomadcertificate && function_exists('iomadcertificate_get_watermark')) {
    $watermark = iomadcertificate_get_watermark($iomadcertificate, $course);
    if (!empty($watermark) && file_exists($watermark)) {
        $pdf->SetAlpha(0.1);
        $pdf->Image($watermark, 50, 40, 200, 130, '', '', '', true, 150);
        $pdf->SetAlpha(1);
    }
}

$cx = $pw / 2; // horizontal center (148.5 mm)

// --- SmartMind stripe+logo (top-left, PNG drawn after borders to overlay them) ---
$stripePng = $smgptypedir . '/pix/certificate_stripe.png';
$stripeX = 20;    // left position (border lines cross behind the ribbon)
$stripeY = -2;    // start above the page (ribbon extends to top edge)
$stripeW = 40;    // width in mm
$stripeH = 75;    // height in mm (tall ribbon with logo circle)
if (file_exists($stripePng) && filesize($stripePng) > 0) {
    $pdf->Image($stripePng, $stripeX, $stripeY, $stripeW, $stripeH, 'PNG', '', '', true, 300);
}

// The logo circle center is approximately at Y = 34mm (visual center of the circle in the stripe).
$logoCenterY = 32;

// Company logo (top-right, if available via IOMAD).
if ($iomadcertificate && function_exists('iomadcertificate_get_logo')) {
    $logofile = iomadcertificate_get_logo($iomadcertificate, $course);
    if (!empty($logofile) && file_exists($logofile)) {
        $pdf->Image($logofile, $pw - 60, 15, 40, 0, '', '', '', true, 150);
    }
}

// --- Text content ---
// Full-page centered content area (20mm margins each side).
$fullContentX = 20;
$fullContentW = $pw - 40;

// Title — horizontally centered on the page, vertically aligned with the logo circle center.
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont($font, 'B', 22);
$titleY = $logoCenterY - 5;
$pdf->SetXY($fullContentX, $titleY);
$pdf->MultiCell($fullContentW, 10, $lang['title'], 0, 'C', false, 1);
$titleBottomY = $pdf->GetY();

// Decorative line under title (centered on page, same width as title text).
$lineY = $titleBottomY + 1;
$pdf->SetLineStyle(['width' => 0.6, 'color' => [0, 0, 0]]);
$pdf->SetFont($font, 'B', 22);
$titleTextW = $pdf->GetStringWidth($lang['title']);
$lineHalfW = $titleTextW / 2;
$pdf->Line($cx - $lineHalfW, $lineY, $cx + $lineHalfW, $lineY);

// "Se certifica que" / "This certifies that".
$ypos = $lineY + 10;
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont($font, '', 12);
$pdf->SetXY($fullContentX, $ypos);
$pdf->MultiCell($fullContentW, 6, $lang['certifies'], 0, 'C', false, 1);

// User full name.
$ypos = $pdf->GetY() + 4;
$pdf->SetTextColor(0, 0, 0);
$fullname = fullname($certuser);
$pdf->SetFont($font, 'B', 22);
$pdf->SetXY($fullContentX, $ypos);
$pdf->MultiCell($fullContentW, 10, $fullname, 0, 'C', false, 1);

// Document ID (if available).
$ypos = $pdf->GetY() + 1;
if (!empty($userdni)) {
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont($font, '', 10);
    $pdf->SetXY($fullContentX, $ypos);
    $pdf->MultiCell($fullContentW, 5, $lang['document'] . ': ' . $userdni, 0, 'C', false, 1);
    $ypos = $pdf->GetY();
}

// Birth date (if available).
if (!empty($userbirthdate)) {
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont($font, '', 10);
    $pdf->SetXY($fullContentX, $ypos);
    $pdf->MultiCell($fullContentW, 5, $lang['birthdate'] . ': ' . $userbirthdate, 0, 'C', false, 1);
    $ypos = $pdf->GetY();
}

// "ha completado el curso" / "has completed the course".
$ypos = max($ypos + 4, $pdf->GetY() + 4);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont($font, '', 12);
$pdf->SetXY($fullContentX, $ypos);
$pdf->MultiCell($fullContentW, 6, $lang['completed'], 0, 'C', false, 1);

// Course name.
$ypos = $pdf->GetY() + 3;
$pdf->SetTextColor(0, 0, 0);
$coursename = format_string($course->fullname);
$pdf->SetFont($font, 'B', 18);
$pdf->SetXY($fullContentX, $ypos);
$pdf->MultiCell($fullContentW, 8, $coursename, 0, 'C', false, 1);

// Duration hours.
$ypos = $pdf->GetY() + 2;
if ($durationhours > 0) {
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont($font, '', 11);
    $durationtext = $lang['duration'] . ' ' . $durationhours . ' ' . $lang['hours'];
    $pdf->SetXY($fullContentX, $ypos);
    $pdf->MultiCell($fullContentW, 5, $durationtext, 0, 'C', false, 1);
    $ypos = $pdf->GetY() + 1;
}

// Company name.
if (!empty($companyname)) {
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont($font, '', 11);
    $pdf->SetXY($fullContentX, $ypos);
    $pdf->MultiCell($fullContentW, 5, $lang['company'] . ': ' . $companyname, 0, 'C', false, 1);
}

// --- Bottom section: 3 columns evenly spaced ---
$bottomY = $ph - 40;
$colW = ($pw - 40) / 4; // 4 equal columns within 20mm margins
$col1x = 20 + $colW / 2;
$col2x = 20 + $colW + $colW / 2;
$col3x = 20 + 2 * $colW + $colW / 2;
$col4x = 20 + 3 * $colW + $colW / 2;

$pdf->SetTextColor(80, 80, 80);

// Start date (column 1).
$pdf->SetFont($font, '', 8);
$pdf->SetXY($col1x - $colW / 2, $bottomY);
$pdf->Cell($colW, 4, $lang['startdate'] . ':', 0, 1, 'C');
$pdf->SetXY($col1x - $colW / 2, $bottomY + 4);
$pdf->SetFont($font, 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($colW, 5, $startdate, 0, 1, 'C');

// End date (column 2).
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont($font, '', 8);
$pdf->SetXY($col2x - $colW / 2, $bottomY);
$pdf->Cell($colW, 4, $lang['enddate'] . ':', 0, 1, 'C');
$pdf->SetXY($col2x - $colW / 2, $bottomY + 4);
$pdf->SetFont($font, 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($colW, 5, $enddate, 0, 1, 'C');

// Verification code (column 3).
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont($font, '', 8);
$pdf->SetXY($col3x - $colW / 2, $bottomY);
$pdf->Cell($colW, 4, $lang['code'] . ':', 0, 1, 'C');
$pdf->SetXY($col3x - $colW / 2, $bottomY + 4);
$pdf->SetFont($font, 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($colW, 5, $code, 0, 1, 'C');

// CEO Signature (column 4).
$ceosig = $smgptypedir . '/pix/ceo_signature.png';
if (file_exists($ceosig) && filesize($ceosig) > 0) {
    $pdf->Image($ceosig, $col4x - 20, $bottomY - 14, 40, 0, '', '', '', true, 150);
}
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont($font, 'B', 8);
$pdf->SetXY($col4x - $colW / 2, $bottomY + 4);
$pdf->Cell($colW, 4, 'Pablo Lobato Muriente', 0, 1, 'C');
$pdf->SetFont($font, '', 7);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetXY($col4x - $colW / 2, $bottomY + 8);
$pdf->Cell($colW, 4, 'CEO SmartMind', 0, 1, 'C');

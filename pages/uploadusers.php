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
 * Simplified CSV user upload page for company managers.
 *
 * Handles step 1 (file selection) with a clean UI, then hands off
 * to IOMAD's uploaduser.php for step 2 (preview + processing).
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_login();

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

// IOMAD defines these in uploaduser.php — replicate here for the form.
if (!defined('UU_ADDNEW'))     { define('UU_ADDNEW', 0); }
if (!defined('UU_ADDINC'))     { define('UU_ADDINC', 1); }
if (!defined('UU_ADD_UPDATE')) { define('UU_ADD_UPDATE', 2); }
if (!defined('UU_UPDATE'))     { define('UU_UPDATE', 3); }

$managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
if (!$managerrec) {
    throw new moodle_exception('accessdenied', 'admin');
}

$companyname = $DB->get_field('company', 'name', ['id' => $managerrec->companyid]);
$component   = 'local_sm_graphics_plugin';

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/uploadusers.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title($companyname);
$PAGE->set_heading($companyname);
$PAGE->set_pagelayout('standard');

// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $userfile = $_FILES['userfile'] ?? null;

    if (empty($userfile) || $userfile['error'] !== UPLOAD_ERR_OK) {
        redirect($PAGE->url, get_string('uploadusers_nofile', $component), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $content = file_get_contents($userfile['tmp_name']);
    $iid = csv_import_reader::get_new_iid('uploaduser');
    $cir = new csv_import_reader($iid, 'uploaduser');
    $readcount = $cir->load_csv_content($content, 'UTF-8', 'comma');

    if ($readcount === false || $readcount <= 1) {
        redirect($PAGE->url, get_string('uploadusers_empty', $component), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // load_csv_content returns total rows including the header row.
    $usercount = $readcount - 1;

    // Check company limit before handing off to IOMAD.
    $companyid = $managerrec->companyid;
    $limit = local_sm_graphics_plugin_get_company_limit($companyid);
    if ($limit > 0) {
        $currentcount = local_sm_graphics_plugin_get_company_student_count($companyid);
        $remaining = $limit - $currentcount;
        if ($usercount > $remaining) {
            redirect(
                new moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php'),
                get_string('usermgmt_upload_exceeds', $component, (object) [
                    'csvcount'  => $usercount,
                    'remaining' => $remaining,
                    'limit'     => $limit,
                ]),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
    }

    // Success — redirect to IOMAD step 2 with the parsed CSV.
    $uutype = optional_param('uutype', UU_ADDNEW, PARAM_INT);
    redirect(new moodle_url('/blocks/iomad_company_admin/uploaduser.php', [
        'iid'         => $iid,
        'previewrows' => 10,
        'readcount'   => $readcount,
        'uutype'      => $uutype,
    ]));
}

// GET — render the upload form.
$uutypes = [
    ['value' => UU_ADDNEW,     'label' => get_string('uuoptype_addnew', 'tool_uploaduser'),     'selected' => true],
    ['value' => UU_ADDINC,     'label' => get_string('uuoptype_addinc', 'tool_uploaduser'),     'selected' => false],
    ['value' => UU_ADD_UPDATE, 'label' => get_string('uuoptype_addupdate', 'tool_uploaduser'), 'selected' => false],
    ['value' => UU_UPDATE,     'label' => get_string('uuoptype_update', 'tool_uploaduser'),     'selected' => false],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/uploadusers_page', [
    'actionurl'      => $PAGE->url->out(false),
    'sesskey'        => sesskey(),
    'title'          => get_string('uploadusers_title', $component),
    'subtitle'       => get_string('uploadusers_subtitle', $component),
    'file_label'     => get_string('uploadusers_file', $component),
    'file_help'      => get_string('uploadusers_file_help', $component),
    'type_label'     => get_string('uploadusers_type', $component),
    'submit_label'   => get_string('uploadusers_submit', $component),
    'cancel_label'   => get_string('uploadusers_cancel', $component),
    'cancelurl'      => (new moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php'))->out(),
    'uutypes'        => $uutypes,
]);
echo $OUTPUT->footer();

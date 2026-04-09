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
 * Staging endpoint for files the restore-wizard's "Add activity" modal
 * uploads (SCORMs, PDFs, folder archives, H5P, etc.). The file is moved
 * into the current user's personal draft file area so it can later be
 * consumed by Moodle's `add_moduleinfo()` / `create_module()` from
 * `restore_execute.php::create_new_structure_items()`.
 *
 * Accepts a multipart/form-data POST:
 *   - file        (required) — the uploaded binary
 *   - modname     (optional) — the Moodle modname the file is intended for
 *                               (used only for capability / MIME validation
 *                               hints; the draft area itself is generic)
 *
 * Returns JSON:
 *   {
 *     success:     bool,
 *     draftitemid: int,   // 0 on failure
 *     filename:    string,
 *     error:       string
 *   }
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

$fail = static function (string $msg, int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success'     => false,
        'draftitemid' => 0,
        'filename'    => '',
        'error'       => $msg,
    ]);
    exit;
};

if (!has_capability('moodle/restore:restorecourse', context_system::instance())) {
    $fail('Access denied', 403);
}

if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
    $fail('No file uploaded');
}

$upload = $_FILES['file'];
if ($upload['error'] !== UPLOAD_ERR_OK) {
    $fail('Upload error code: ' . (int) $upload['error']);
}

$origname = $upload['name'] ?? 'upload.bin';
$filename = clean_filename($origname);
if ($filename === '') {
    $filename = 'upload.bin';
}

// Create a fresh draft file area owned by the current user. `file_get_unused_draft_itemid`
// gives us a unique id that `add_moduleinfo()` / `create_module()` will then
// consume during `restore_execute::create_new_structure_items`.
global $USER;
$draftitemid = file_get_unused_draft_itemid();
$usercontext = context_user::instance($USER->id);

$fs = get_file_storage();
$filerecord = (object) [
    'contextid' => $usercontext->id,
    'component' => 'user',
    'filearea'  => 'draft',
    'itemid'    => $draftitemid,
    'filepath'  => '/',
    'filename'  => $filename,
    'userid'    => $USER->id,
];

try {
    $fs->create_file_from_pathname($filerecord, $upload['tmp_name']);
} catch (\Throwable $e) {
    $fail('Failed to stash file in draft area: ' . $e->getMessage());
}

echo json_encode([
    'success'     => true,
    'draftitemid' => (int) $draftitemid,
    'filename'    => $filename,
    'error'       => '',
]);

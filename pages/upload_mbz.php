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
 * MBZ upload endpoint for the Vue restore wizard's landing page.
 *
 * Accepts a multipart/form-data POST with a single `mbz` file field,
 * sanitizes the filename, moves the upload into Moodle's backup temp
 * directory, and returns JSON with the staged filename so the wizard
 * can call restore_prepare with it.
 *
 * Returns:
 *   { success: bool, filename: string, error: string }
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

if (!has_capability('moodle/restore:restorecourse', context_system::instance())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'filename' => '', 'error' => 'Access denied']);
    exit;
}

if (empty($_FILES['mbz']) || !isset($_FILES['mbz']['tmp_name'])) {
    echo json_encode(['success' => false, 'filename' => '', 'error' => 'No file uploaded']);
    exit;
}

$upload = $_FILES['mbz'];
if ($upload['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success'  => false,
        'filename' => '',
        'error'    => 'Upload error code: ' . (int) $upload['error'],
    ]);
    exit;
}

// Validate extension — only accept .mbz to prevent arbitrary file uploads
// landing in the backup temp directory.
$origname = $upload['name'] ?? 'unknown.mbz';
$ext = strtolower(pathinfo($origname, PATHINFO_EXTENSION));
if ($ext !== 'mbz') {
    echo json_encode([
        'success'  => false,
        'filename' => '',
        'error'    => 'Only .mbz files are accepted',
    ]);
    exit;
}

// Sanitize + uniqueify the filename so concurrent uploads don't collide
// and the resulting path stays inside the backup temp directory.
$sanitized = clean_filename($origname);
if ($sanitized === '') {
    $sanitized = 'restore.mbz';
}
$staged = 'smgp_upload_' . time() . '_' . $sanitized;

$backupdir = make_backup_temp_directory('');
$destpath  = $backupdir . '/' . $staged;

if (!@move_uploaded_file($upload['tmp_name'], $destpath)) {
    echo json_encode([
        'success'  => false,
        'filename' => '',
        'error'    => 'Failed to move uploaded file into backup temp directory',
    ]);
    exit;
}

@chmod($destpath, 0644);

echo json_encode([
    'success'  => true,
    'filename' => $staged,
    'error'    => '',
]);

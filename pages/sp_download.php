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
 * SharePoint file download proxy.
 *
 * Serves files from SharePoint via the Graph API. Authenticates via
 * HMAC token so that Moodle's SCORM cron fetcher can download without
 * a browser session, or via normal Moodle login for logged-in users.
 *
 * Usage: sp_download.php?item=ITEM_ID&token=HMAC_TOKEN
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Do NOT call require_login() here — the SCORM cron fetcher uses cURL
// without a session. Instead we verify a HMAC token.
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../../config.php');

$itemid = required_param('item', PARAM_RAW);
$token  = required_param('token', PARAM_RAW);

// Verify the HMAC token.
$secret = get_config('local_sm_graphics_plugin', 'sp_client_secret');
$expected = hash_hmac('sha256', $itemid, $secret);

if (!hash_equals($expected, $token)) {
    http_response_code(403);
    die('Invalid token.');
}

// Site and drive IDs stored during import.
$siteid  = get_config('local_sm_graphics_plugin', 'sp_last_site_id');
$driveid = get_config('local_sm_graphics_plugin', 'sp_last_drive_id');

if (empty($siteid) || empty($driveid)) {
    http_response_code(500);
    die('SharePoint site/drive not configured.');
}

// Get file metadata to know the filename and get the download URL.
$data = \local_sm_graphics_plugin\sharepoint\client::api_request_public(
    'GET',
    "/sites/{$siteid}/drives/{$driveid}/items/{$itemid}"
);

if ($data === null || empty($data['name'])) {
    http_response_code(404);
    die('File not found or SharePoint connection error.');
}

$filename = $data['name'];
$mimetype = $data['file']['mimeType'] ?? 'application/octet-stream';
$size = $data['size'] ?? 0;
$etag = $data['eTag'] ?? '';
$lastmodified = $data['lastModifiedDateTime'] ?? '';

// Send ETag and Last-Modified headers so Moodle can detect changes for auto-update.
if ($etag) {
    header('ETag: "' . md5($etag) . '"');
}
if ($lastmodified) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime($lastmodified)) . ' GMT');
}
if ($size) {
    header('Content-Length: ' . $size);
}

// Redirect to the pre-authenticated download URL (short-lived but enough for the cURL fetch).
if (!empty($data['@microsoft.graph.downloadUrl'])) {
    header('Location: ' . $data['@microsoft.graph.downloadUrl']);
    exit;
}

// Fallback: stream through this proxy.
$content = \local_sm_graphics_plugin\sharepoint\client::api_request_public(
    'GET',
    "/sites/{$siteid}/drives/{$driveid}/items/{$itemid}/content",
    null,
    true
);

if ($content === null) {
    http_response_code(502);
    die('Could not download file from SharePoint.');
}

header('Content-Type: ' . $mimetype);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
echo $content;

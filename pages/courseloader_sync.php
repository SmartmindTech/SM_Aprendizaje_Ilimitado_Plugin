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
 * SharePoint course sync — streaming endpoint.
 *
 * Runs the sync task and streams progress lines as text/event-stream (SSE)
 * so the courseloader page can show live log output.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');
require_login();
require_sesskey();

if (!is_siteadmin()) {
    http_response_code(403);
    die('Access denied');
}

@set_time_limit(600);
raise_memory_limit(MEMORY_EXTRA);

// SSE headers — stream text lines as they happen.
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Disable nginx buffering.

// Disable PHP output buffering.
while (ob_get_level()) {
    ob_end_flush();
}

/**
 * Send an SSE data line.
 */
function send_sse(string $msg): void {
    echo "data: " . $msg . "\n\n";
    flush();
}

// Override mtrace to stream via SSE instead of stdout.
// The sync task uses mtrace() for progress — we redirect it here.
global $CFG;
$CFG->mtrace_wrapper = function(string $msg) {
    send_sse($msg);
};

// Monkey-patch: since we can't override mtrace, we'll run the sync
// logic inline with our own progress output.
send_sse('Iniciando sincronización con SharePoint...');

global $DB;

if (!\local_sm_graphics_plugin\sharepoint\client::is_configured()) {
    send_sse('ERROR: SharePoint no está configurado.');
    send_sse('[DONE]');
    exit;
}

$siteurl = get_config('local_sm_graphics_plugin', 'sp_site_url');
if (empty($siteurl)) {
    send_sse('ERROR: URL del sitio SharePoint no configurada.');
    send_sse('[DONE]');
    exit;
}

$docsurl = rtrim($siteurl, '/') . '/Shared Documents';
$parsed = \local_sm_graphics_plugin\sharepoint\client::parse_sharepoint_url($docsurl);
if (!$parsed) {
    send_sse('ERROR: No se pudo parsear la URL de SharePoint.');
    send_sse('[DONE]');
    exit;
}

$siteid  = $parsed['site_id'];
$driveid = $parsed['drive_id'];
$scanfolders = ['PRIV_CATALOGO'];
$now = time();
$foundurls = [];
$scannedfolders = 0;
$foundcourses = 0;

foreach ($scanfolders as $rootname) {
    send_sse("Escaneando /{$rootname}...");

    $subitems = \local_sm_graphics_plugin\sharepoint\client::list_folder($siteid, $driveid, '/' . $rootname);
    if ($subitems === null) {
        send_sse("ERROR: No se pudo listar /{$rootname}: " . \local_sm_graphics_plugin\sharepoint\client::get_last_error());
        continue;
    }

    $totalfolders = count(array_filter($subitems, function($i) { return $i['is_folder']; }));
    send_sse("Encontradas {$totalfolders} carpetas en /{$rootname}. Verificando .mbz...");

    foreach ($subitems as $sub) {
        if (!$sub['is_folder']) {
            continue;
        }
        $scannedfolders++;
        $coursepath = '/' . $rootname . '/' . $sub['name'];

        $coursefiles = \local_sm_graphics_plugin\sharepoint\client::list_folder($siteid, $driveid, $coursepath);
        if ($coursefiles === null) {
            continue;
        }

        // Check for .mbz in the main folder, or inside subfolders named "MBZ" / containing "MBZ".
        $hasmbz = false;
        foreach ($coursefiles as $cf) {
            if (!$cf['is_folder'] && preg_match('/\.mbz$/i', $cf['name'])) {
                $hasmbz = true;
                break;
            }
        }
        if (!$hasmbz) {
            // Check subfolders whose name contains "MBZ" (case-insensitive).
            foreach ($coursefiles as $cf) {
                if ($cf['is_folder'] && stripos($cf['name'], 'MBZ') !== false) {
                    $mbzpath = $coursepath . '/' . $cf['name'];
                    $mbzfiles = \local_sm_graphics_plugin\sharepoint\client::list_folder($siteid, $driveid, $mbzpath);
                    if ($mbzfiles) {
                        foreach ($mbzfiles as $mf) {
                            if (!$mf['is_folder'] && preg_match('/\.mbz$/i', $mf['name'])) {
                                $hasmbz = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        if ($hasmbz) {
            $foundcourses++;
            $weburl = $sub['web_url'];
            $foundurls[] = $weburl;

            $existing = $DB->get_record_select('local_smgp_sp_courses',
                $DB->sql_compare_text('web_url') . ' = ?', [$weburl]);
            if ($existing) {
                $existing->name = $sub['name'];
                $existing->parent_folder = $rootname;
                $existing->timemodified = $now;
                $DB->update_record('local_smgp_sp_courses', $existing);
            } else {
                $DB->insert_record('local_smgp_sp_courses', (object) [
                    'name'          => $sub['name'],
                    'web_url'       => $weburl,
                    'parent_folder' => $rootname,
                    'timecreated'   => $now,
                    'timemodified'  => $now,
                ]);
            }
            send_sse("  ✓ {$sub['name']}");
        }

        if ($scannedfolders % 25 === 0) {
            send_sse("  Progreso: {$scannedfolders}/{$totalfolders} carpetas, {$foundcourses} cursos encontrados...");
        }
    }
}

// Remove stale entries.
$allcached = $DB->get_records('local_smgp_sp_courses', null, '', 'id, web_url');
$deleted = 0;
foreach ($allcached as $cached) {
    if (!in_array($cached->web_url, $foundurls)) {
        $DB->delete_records('local_smgp_sp_courses', ['id' => $cached->id]);
        $deleted++;
    }
}

send_sse("Sincronización completada: {$foundcourses} cursos, {$deleted} obsoletos eliminados.");
send_sse('[DONE]');

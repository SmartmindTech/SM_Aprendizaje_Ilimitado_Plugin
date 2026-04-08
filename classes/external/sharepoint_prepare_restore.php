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

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * AJAX service: download MBZ from SharePoint and prepare for native restore wizard.
 *
 * Downloads the MBZ file to Moodle's backup temp directory and stores the full
 * SharePoint manifest in $SESSION so the restore wizard can show SharePoint
 * extras (SCORM, PDFs, evaluations) and apply them after restore.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sharepoint_prepare_restore extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'folder_url'  => new external_value(PARAM_URL, 'SharePoint folder URL'),
            'categoryid'  => new external_value(PARAM_INT, 'Target course category ID (from company)'),
            'companyids'  => new external_value(PARAM_RAW, 'JSON array of selected company IDs', VALUE_DEFAULT, '[]'),
            'mbz_item_id' => new external_value(PARAM_RAW, 'Pre-scanned MBZ item id (skips analyzer when set)', VALUE_DEFAULT, ''),
            'mbz_name'    => new external_value(PARAM_RAW, 'Pre-scanned MBZ filename', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $folder_url, int $categoryid, string $companyids = '[]', string $mbz_item_id = '', string $mbz_name = ''): array {
        global $CFG, $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'folder_url'  => $folder_url,
            'categoryid'  => $categoryid,
            'companyids'  => $companyids,
            'mbz_item_id' => $mbz_item_id,
            'mbz_name'    => $mbz_name,
        ]);

        $context = \context_system::instance();
        require_capability('local/sm_graphics_plugin:import_courses', $context);

        @set_time_limit(300);
        raise_memory_limit(MEMORY_EXTRA);

        // 1. Get the MBZ item id + name. Fast path: caller passed them
        // from a previous scan_results, so we skip the analyzer call (no
        // extra Graph API round-trip). Fallback: re-scan the folder.
        $manifest = null;
        if ($params['mbz_item_id'] !== '' && $params['mbz_name'] !== '') {
            $mbz = [
                'item_id' => $params['mbz_item_id'],
                'name'    => $params['mbz_name'],
            ];
        } else {
            $manifest = \local_sm_graphics_plugin\sharepoint\course_analyzer::analyze($params['folder_url']);
            if ($manifest === null || empty($manifest['mbz'])) {
                return [
                    'success'   => false,
                    'contextid' => 0,
                    'filename'  => '',
                    'error'     => 'No MBZ file found in the SharePoint folder.',
                ];
            }
            $mbz = $manifest['mbz'][0];
        }

        // 2. Download the MBZ from SharePoint.
        $parsed = \local_sm_graphics_plugin\sharepoint\client::parse_sharepoint_url($params['folder_url']);
        if (!$parsed) {
            return [
                'success'   => false,
                'contextid' => 0,
                'filename'  => '',
                'error'     => 'Could not parse SharePoint URL.',
            ];
        }

        $temppath = \local_sm_graphics_plugin\sharepoint\client::download_file(
            $parsed['site_id'], $parsed['drive_id'], $mbz['item_id'], $mbz['name']
        );
        if (!$temppath || !file_exists($temppath)) {
            return [
                'success'   => false,
                'contextid' => 0,
                'filename'  => '',
                'error'     => 'Failed to download MBZ from SharePoint.',
            ];
        }

        // 3. Move to Moodle's backup temp directory so restore.php can find it.
        $filename = 'smsp_' . time() . '_' . clean_filename($mbz['name']);
        $backupdir = make_backup_temp_directory('');
        $destpath = $backupdir . '/' . $filename;
        rename($temppath, $destpath);

        // 4. Get the category context for the restore wizard.
        $catcontext = \context_coursecat::instance($params['categoryid']);

        // 5. Store manifest (if we have it) + folder URL + company IDs in
        // SESSION for the post-restore observer. Manifest is null on the
        // fast path (no analyzer call) — that's fine, the observer only
        // needs it when SCORM/PDF/Documentos sidecar files have to be
        // attached after restore.
        if ($manifest !== null) {
            $SESSION->smgp_sp_manifest = $manifest;
        }
        $SESSION->smgp_sp_folder_url  = $params['folder_url'];
        $SESSION->smgp_sp_categoryid  = $params['categoryid'];
        $SESSION->smgp_sp_companyids  = json_decode($params['companyids'], true) ?: [];

        return [
            'success'   => true,
            'contextid' => (int) $catcontext->id,
            'filename'  => $filename,
            'error'     => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL, 'Whether preparation succeeded'),
            'contextid' => new external_value(PARAM_INT, 'Category context ID for restore.php'),
            'filename'  => new external_value(PARAM_RAW, 'MBZ filename in backup temp dir'),
            'error'     => new external_value(PARAM_RAW, 'Error message if failed'),
        ]);
    }
}

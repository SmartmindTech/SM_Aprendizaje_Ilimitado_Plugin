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
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * Phase 6 — Vue restore wizard: prepare step.
 *
 * Accepts a backup archive path (either a pathnamehash of a file already in the
 * user's draft area, or a SharePoint-downloaded MBZ in the backup temp dir) and
 * extracts it into a backupid that the rest of the wizard can reference.
 * Returns the parsed backup metadata (course name, sections, activities) for the
 * Confirm step of the Vue wizard.
 *
 * "Thin" wizard version: no settings step, just enough to drive structure editor
 * + SmartMind metadata + execution.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_prepare extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'filename'     => new external_value(PARAM_FILE, 'MBZ filename in backup temp dir (SharePoint flow)', VALUE_DEFAULT, ''),
            'draftitemid'  => new external_value(PARAM_INT, 'Draft file area item ID (manual upload)', VALUE_DEFAULT, 0),
            'pathnamehash' => new external_value(PARAM_RAW, 'Pathname hash of a stored file (course/user backup zones)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $filename = '', int $draftitemid = 0, string $pathnamehash = ''): array {
        global $CFG, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'filename'     => $filename,
            'draftitemid'  => $draftitemid,
            'pathnamehash' => $pathnamehash,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/restore:restorecourse', $context);

        // Resolve the archive path. Three sources:
        //   1. filename — already in backup temp dir (SharePoint or
        //      direct upload via pages/upload_mbz.php)
        //   2. draftitemid — sitting in the user's draft area (legacy)
        //   3. pathnamehash — stored file in any context's file area
        //      (course backup zone, user private backup zone, etc.)
        $archivepath = null;
        if (!empty($params['filename'])) {
            $backuptempdir = make_backup_temp_directory('');
            $archivepath = $backuptempdir . '/' . clean_filename($params['filename']);
            if (!file_exists($archivepath)) {
                return ['success' => false, 'error' => 'MBZ file not found: ' . $params['filename']];
            }
        } else if ($params['pathnamehash'] !== '') {
            // Stored file → copy to backup temp dir so the restore plan
            // can read it. We re-check the capability against the file's
            // owning context so we don't let a user pull files they're
            // not allowed to see.
            $fs = get_file_storage();
            $file = $fs->get_file_by_hash($params['pathnamehash']);
            if (!$file) {
                return ['success' => false, 'error' => 'Stored backup file not found'];
            }
            // Capability check on the file's own context (course / user).
            $filecontext = \context::instance_by_id($file->get_contextid(), IGNORE_MISSING);
            if ($filecontext) {
                require_capability('moodle/restore:restorecourse', $filecontext);
            }
            $archivepath = make_backup_temp_directory('') . '/smgp_restore_' . time() . '_' . $file->get_filename();
            $file->copy_content_to($archivepath);
        } else if ($params['draftitemid'] > 0) {
            // Pull the latest file from the user's draft area.
            $fs = get_file_storage();
            $usercontext = \context_user::instance($USER->id);
            $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $params['draftitemid'],
                'id DESC', false);
            if (empty($files)) {
                return ['success' => false, 'error' => 'No file found in draft area'];
            }
            $file = reset($files);
            $archivepath = make_backup_temp_directory('') . '/smgp_restore_' . time() . '_' . $file->get_filename();
            $file->copy_content_to($archivepath);
        } else {
            return ['success' => false, 'error' => 'No file source provided'];
        }

        // Extract archive into a backup id directory.
        $backupid = 'smgp_' . time() . '_' . random_string(4);
        $extractdir = $CFG->tempdir . '/backup/' . $backupid;
        $fp = get_file_packer('application/vnd.moodle.backup');
        if (!$fp->extract_to_pathname($archivepath, $extractdir)) {
            return ['success' => false, 'error' => 'Failed to extract backup'];
        }

        // Parse backup metadata so Confirm step can show info.
        $details = \backup_general_helper::get_backup_information($backupid);

        // Course-level metadata from course/course.xml.
        $coursexml = $extractdir . '/course/course.xml';
        $originalname = '';
        $originalshortname = '';
        $originalcourseid = 0;
        if (file_exists($coursexml)) {
            $xml = simplexml_load_file($coursexml);
            if ($xml !== false) {
                $originalname = (string) ($xml->fullname ?? '');
                $originalshortname = (string) ($xml->shortname ?? '');
                $originalcourseid = (int) ($xml['id'] ?? 0);
                if (!$originalcourseid && isset($xml->id)) {
                    $originalcourseid = (int) $xml->id;
                }
            }
        }

        // Sections preview (just names) for the Confirm step's "Detalles
        // del curso" card. The full editable structure is loaded later
        // by restore_get_schema in step 4.
        $sectionsmeta = self::parse_section_names($extractdir);

        // Backup-time settings checklist + the original-site/release/mode
        // metadata Moodle's native Confirm page shows in its first card.
        $backupsettings = self::parse_backup_settings($extractdir);
        $backupmeta = self::parse_backup_meta($extractdir);

        return [
            'success'             => true,
            'backupid'            => $backupid,
            'original_fullname'   => $originalname,
            'original_shortname'  => $originalshortname,
            'original_courseid'   => $originalcourseid,
            'backup_format'       => $details->format ?? 'moodle2',
            'backup_type'         => $details->type ?? 'course',
            'backup_mode'         => $backupmeta['mode'] ?? 'general',
            'backup_date'         => (int) ($details->backup_date ?? 0),
            'backup_release'      => $backupmeta['backup_release'] ?? '',
            'backup_version'      => $backupmeta['backup_version'] ?? '',
            'moodle_release'      => $details->moodle_release ?? '',
            'moodle_version'      => $backupmeta['moodle_version'] ?? '',
            'original_wwwroot'    => $backupmeta['original_wwwroot'] ?? '',
            'original_site_hash'  => $backupmeta['original_site_hash'] ?? '',
            'sections'            => $sectionsmeta,
            'backup_settings'     => $backupsettings,
            'error'               => '',
        ];
    }

    /**
     * Parse {section.xml}'s name + index from each section directory under
     * the extracted backup. Returns a flat list keyed by section number so
     * the Confirm step can show "Sección 0", "Sección 1 — Liderazgo", etc.
     */
    private static function parse_section_names(string $extractdir): array {
        $base = $extractdir . '/sections';
        if (!is_dir($base)) {
            return [];
        }
        $out = [];
        $iter = new \DirectoryIterator($base);
        foreach ($iter as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }
            $secxml = $item->getPathname() . '/section.xml';
            if (!file_exists($secxml)) {
                continue;
            }
            $xml = @simplexml_load_file($secxml);
            if ($xml === false) {
                continue;
            }
            $out[] = [
                'number' => isset($xml->number) ? (int) $xml->number : 0,
                'name'   => isset($xml->name) ? trim((string) $xml->name) : '',
            ];
        }
        // Sort by section number so the Confirm card displays them in
        // the same order Moodle would.
        usort($out, fn($a, $b) => $a['number'] <=> $b['number']);
        return $out;
    }

    /**
     * Parse the top-level <information> block from moodle_backup.xml so the
     * Confirm step can render the "Detalles de la copia" card with the
     * same fields Moodle's native restorefile UI shows.
     */
    private static function parse_backup_meta(string $extractdir): array {
        $path = $extractdir . '/moodle_backup.xml';
        if (!file_exists($path)) {
            return [];
        }
        $xml = @simplexml_load_file($path);
        if ($xml === false || !isset($xml->information)) {
            return [];
        }
        $info = $xml->information;
        return [
            'mode'                => (string) ($info->mode ?? 'general'),
            'backup_release'      => (string) ($info->backup_release ?? ''),
            'backup_version'      => (string) ($info->backup_version ?? ''),
            'moodle_version'      => (string) ($info->moodle_version ?? ''),
            'original_wwwroot'    => (string) ($info->original_wwwroot ?? ''),
            'original_site_hash'  => (string) ($info->original_site_identifier_hash ?? ''),
        ];
    }

    /**
     * Parse the root-level <setting> entries from moodle_backup.xml. Each
     * one looks like:
     *   <setting>
     *     <level>root</level>
     *     <name>users</name>
     *     <value>1</value>
     *   </setting>
     *
     * Returns the list as a flat array of {name, value(bool)} so the Vue
     * Confirm step can render badges.
     */
    private static function parse_backup_settings(string $extractdir): array {
        $path = $extractdir . '/moodle_backup.xml';
        if (!file_exists($path)) {
            return [];
        }
        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return [];
        }

        $out = [];
        if (isset($xml->information->settings->setting)) {
            foreach ($xml->information->settings->setting as $s) {
                $level = (string) $s->level;
                if ($level !== 'root') {
                    continue;
                }
                $name  = (string) $s->name;
                $value = (string) $s->value;
                $out[] = [
                    'name'  => $name,
                    'value' => $value,
                    // Boolean projection for the badges.
                    'enabled' => ($value === '1' || $value === 'true'),
                ];
            }
        }
        return $out;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'            => new external_value(PARAM_BOOL, 'Preparation succeeded'),
            'backupid'           => new external_value(PARAM_ALPHANUMEXT, 'Backup ID for subsequent calls'),
            'original_fullname'  => new external_value(PARAM_TEXT, 'Original course fullname from backup'),
            'original_shortname' => new external_value(PARAM_TEXT, 'Original course shortname from backup'),
            'original_courseid'  => new external_value(PARAM_INT, 'Original course id from backup'),
            'backup_format'      => new external_value(PARAM_TEXT, 'Backup format (moodle2, ims_cc1, ...)'),
            'backup_type'        => new external_value(PARAM_TEXT, 'Backup type (course, section, activity)'),
            'backup_mode'        => new external_value(PARAM_TEXT, 'Backup mode (general, import, hub, automated, ...)'),
            'backup_date'        => new external_value(PARAM_INT, 'Backup timestamp'),
            'backup_release'     => new external_value(PARAM_TEXT, 'Backup release version (e.g. "5.0")'),
            'backup_version'     => new external_value(PARAM_TEXT, 'Backup format version'),
            'moodle_release'     => new external_value(PARAM_TEXT, 'Moodle release that created the backup'),
            'moodle_version'     => new external_value(PARAM_TEXT, 'Moodle version number'),
            'original_wwwroot'   => new external_value(PARAM_RAW, 'wwwroot of the site that created the backup'),
            'original_site_hash' => new external_value(PARAM_TEXT, 'Original site identifier hash'),
            'sections'           => new external_multiple_structure(
                new external_single_structure([
                    'number' => new external_value(PARAM_INT, 'Section index'),
                    'name'   => new external_value(PARAM_TEXT, 'Section name'),
                ])
            ),
            'backup_settings'    => new external_multiple_structure(
                new external_single_structure([
                    'name'    => new external_value(PARAM_TEXT, 'Setting name (e.g. users, role_assignments, anonymized, ...)'),
                    'value'   => new external_value(PARAM_TEXT, 'Raw value as stored in the backup'),
                    'enabled' => new external_value(PARAM_BOOL, 'Convenience boolean projection of value'),
                ])
            ),
            'error'              => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

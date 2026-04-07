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
            'filename'    => new external_value(PARAM_FILE, 'MBZ filename in backup temp dir (SharePoint flow)', VALUE_DEFAULT, ''),
            'draftitemid' => new external_value(PARAM_INT, 'Draft file area item ID (manual upload)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(string $filename = '', int $draftitemid = 0): array {
        global $CFG, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'filename'    => $filename,
            'draftitemid' => $draftitemid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/restore:restorecourse', $context);

        // Resolve the archive path.
        $archivepath = null;
        if (!empty($params['filename'])) {
            $backuptempdir = make_backup_temp_directory('');
            $archivepath = $backuptempdir . '/' . clean_filename($params['filename']);
            if (!file_exists($archivepath)) {
                return ['success' => false, 'error' => 'MBZ file not found: ' . $params['filename']];
            }
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

        // Build a lightweight sections/activities preview from extracted course.xml.
        // (The real restore controller will rebuild this, but we want the Vue wizard
        //  to show something on Confirm without spinning up the controller yet.)
        $sections = [];
        $coursexml = $extractdir . '/course/course.xml';
        $originalname = '';
        $originalshortname = '';
        if (file_exists($coursexml)) {
            $xml = simplexml_load_file($coursexml);
            if ($xml !== false) {
                $originalname = (string) ($xml->fullname ?? '');
                $originalshortname = (string) ($xml->shortname ?? '');
            }
        }

        return [
            'success'             => true,
            'backupid'            => $backupid,
            'original_fullname'   => $originalname,
            'original_shortname'  => $originalshortname,
            'backup_format'       => $details->format ?? 'moodle2',
            'backup_type'         => $details->type ?? 'course',
            'backup_date'         => (int) ($details->backup_date ?? 0),
            'moodle_release'      => $details->moodle_release ?? '',
            'sections'            => $sections, // populated by restore_schema step
            'error'               => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'            => new external_value(PARAM_BOOL, 'Preparation succeeded'),
            'backupid'           => new external_value(PARAM_ALPHANUMEXT, 'Backup ID for subsequent calls'),
            'original_fullname'  => new external_value(PARAM_TEXT, 'Original course fullname from backup'),
            'original_shortname' => new external_value(PARAM_TEXT, 'Original course shortname from backup'),
            'backup_format'      => new external_value(PARAM_TEXT, 'Backup format'),
            'backup_type'        => new external_value(PARAM_TEXT, 'Backup type'),
            'backup_date'        => new external_value(PARAM_INT, 'Backup timestamp'),
            'moodle_release'     => new external_value(PARAM_TEXT, 'Moodle release that created the backup'),
            'sections'           => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Section name'),
                ])
            ),
            'error'              => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

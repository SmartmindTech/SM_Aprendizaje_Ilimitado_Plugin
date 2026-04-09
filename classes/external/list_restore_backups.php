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
use external_multiple_structure;
use external_value;

/**
 * AJAX service: list backup files visible to the current user, and any
 * restores currently in progress. Powers the three lower tables on the
 * Vue restore wizard's landing page (Step 0):
 *
 *   1. Course backup zone — backups stored under each restorable course's
 *      backup file area, grouped per course.
 *   2. User private backup zone — backups in the user's own private
 *      backup area (component=user, filearea=backup).
 *   3. Restorations in progress — entries from {backup_controllers} for
 *      this user that are still running or in error.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 */
class list_restore_backups extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $DB, $USER;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/restore:restorecourse', $context);

        return [
            'course_backups'   => self::list_course_backups($USER->id),
            'user_backups'     => self::list_user_backups($USER->id),
            'in_progress'      => self::list_in_progress($USER->id),
        ];
    }

    /**
     * Walk every course the user can restore into and collect any files
     * stored in its 'backup/course' file area. Returned flat so the Vue
     * table can render them as one list.
     */
    private static function list_course_backups(int $userid): array {
        global $DB;

        $out = [];
        $fs = get_file_storage();

        // Look at every visible course the user has restore capability on.
        $courses = $DB->get_records('course', null, '', 'id, fullname, shortname', 0, 500);
        foreach ($courses as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $coursecontext = \context_course::instance($course->id);
            if (!has_capability('moodle/restore:restorecourse', $coursecontext, $userid)) {
                continue;
            }
            $files = $fs->get_area_files($coursecontext->id, 'backup', 'course', false, 'timecreated DESC', false);
            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }
                $out[] = [
                    'filename'    => $file->get_filename(),
                    'time'        => (int) $file->get_timecreated(),
                    'size'        => (int) $file->get_filesize(),
                    'contextid'   => (int) $coursecontext->id,
                    'courseid'    => (int) $course->id,
                    'coursename'  => format_string($course->fullname),
                    'downloadurl' => \moodle_url::make_pluginfile_url(
                        $coursecontext->id, 'backup', 'course', null,
                        $file->get_filepath(), $file->get_filename(), true
                    )->out(false),
                    'pathnamehash' => $file->get_pathnamehash(),
                    'status'      => 'none',
                ];
            }
        }
        // Newest first.
        usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /**
     * Backups in the user's private "user/backup" file area. These are
     * the same files Moodle's native backup → restore page lists under
     * "User private backup area".
     */
    private static function list_user_backups(int $userid): array {
        $out = [];
        $fs = get_file_storage();
        $usercontext = \context_user::instance($userid);
        $files = $fs->get_area_files($usercontext->id, 'user', 'backup', false, 'timecreated DESC', false);
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $out[] = [
                'filename'    => $file->get_filename(),
                'time'        => (int) $file->get_timecreated(),
                'size'        => (int) $file->get_filesize(),
                'contextid'   => (int) $usercontext->id,
                'downloadurl' => \moodle_url::make_pluginfile_url(
                    $usercontext->id, 'user', 'backup', null,
                    $file->get_filepath(), $file->get_filename(), true
                )->out(false),
                'pathnamehash' => $file->get_pathnamehash(),
                'status'      => 'none',
            ];
        }
        return $out;
    }

    /**
     * Restore controllers still alive for this user. backup_controllers
     * stores both backup AND restore operations; filter to operation=restore
     * and any non-final status (executing, awaiting, error). The result
     * column has the resolved Moodle course name when available.
     */
    private static function list_in_progress(int $userid): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('backup_controllers')) {
            return [];
        }

        $records = $DB->get_records_select(
            'backup_controllers',
            'userid = :uid AND operation = :op',
            ['uid' => $userid, 'op' => 'restore'],
            'timecreated DESC',
            'id, itemid, status, timecreated',
            0,
            50
        );

        $out = [];
        foreach ($records as $r) {
            // Resolve course name (best-effort) — controllers store the
            // destination course in itemid, but it's not guaranteed.
            $coursename = '—';
            if ($r->itemid) {
                $course = $DB->get_record('course', ['id' => (int) $r->itemid], 'id, fullname');
                if ($course) {
                    $coursename = format_string($course->fullname);
                }
            }
            $out[] = [
                'coursename' => $coursename,
                'time'       => (int) $r->timecreated,
                'status'     => self::status_label((int) $r->status),
            ];
        }
        return $out;
    }

    /**
     * Translate the integer status from backup_controllers into a stable
     * key the frontend i18n layer can resolve. Mirrors the constants in
     * backup::STATUS_*.
     */
    private static function status_label(int $status): string {
        switch ($status) {
            case 0:    return 'created';
            case 100:  return 'requires_conversion';
            case 200:  return 'planned';
            case 300:  return 'configured';
            case 400:  return 'awaiting';
            case 500:  return 'needs_precheck';
            case 700:  return 'executing';
            case 800:  return 'finished_error';
            case 1000: return 'finished';
            default:   return 'unknown';
        }
    }

    public static function execute_returns(): external_single_structure {
        $filestructure = new external_multiple_structure(
            new external_single_structure([
                'filename'    => new external_value(PARAM_TEXT, 'Backup file name'),
                'time'        => new external_value(PARAM_INT, 'Created timestamp'),
                'size'        => new external_value(PARAM_INT, 'Size in bytes'),
                'contextid'   => new external_value(PARAM_INT, 'Owning context id'),
                'courseid'    => new external_value(PARAM_INT, 'Course id (0 for user backups)', VALUE_DEFAULT, 0),
                'coursename'  => new external_value(PARAM_TEXT, 'Course full name (empty for user backups)', VALUE_DEFAULT, ''),
                'downloadurl' => new external_value(PARAM_RAW, 'Download URL'),
                'pathnamehash' => new external_value(PARAM_RAW, 'Pathname hash for the restore endpoint'),
                'status'      => new external_value(PARAM_TEXT, 'Status label'),
            ])
        );

        return new external_single_structure([
            'course_backups' => $filestructure,
            'user_backups'   => $filestructure,
            'in_progress'    => new external_multiple_structure(
                new external_single_structure([
                    'coursename' => new external_value(PARAM_TEXT, 'Destination course name (or "—")'),
                    'time'       => new external_value(PARAM_INT, 'Created timestamp'),
                    'status'     => new external_value(PARAM_TEXT, 'Status label'),
                ])
            ),
        ]);
    }
}

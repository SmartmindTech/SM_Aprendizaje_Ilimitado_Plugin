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
 * Phase 6 step 4 — Schema. Walks the restore plan's section/activity tasks and
 * returns them as a structured list for the Vue CourseStructureEditor.
 *
 * Also returns the source course's SmartMind metadata if we're restoring over
 * an existing course so the Vue form can pre-populate.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_get_schema extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'backupid'   => new external_value(PARAM_ALPHANUMEXT, 'Backup ID from restore_prepare'),
            'categoryid' => new external_value(PARAM_INT, 'Destination category ID'),
        ]);
    }

    public static function execute(string $backupid, int $categoryid): array {
        global $CFG, $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'backupid'   => $backupid,
            'categoryid' => $categoryid,
        ]);

        $catcontext = \context_coursecat::instance($params['categoryid']);
        self::validate_context($catcontext);
        require_capability('moodle/restore:restorecourse', $catcontext);

        // Parse backup directly from extraction dir.
        $extractdir = $CFG->tempdir . '/backup/' . $params['backupid'];
        if (!is_dir($extractdir)) {
            return ['success' => false, 'error' => 'Backup not found', 'sections' => []];
        }

        $sections = [];
        $moodlebackupxml = $extractdir . '/moodle_backup.xml';
        if (!file_exists($moodlebackupxml)) {
            return ['success' => false, 'error' => 'moodle_backup.xml not found', 'sections' => []];
        }

        $xml = simplexml_load_file($moodlebackupxml);
        if ($xml === false) {
            return ['success' => false, 'error' => 'Failed to parse backup XML', 'sections' => []];
        }

        // Sections.
        // section_number comes from parsing the section.xml inside each
        // section directory — Moodle stores the visible position there.
        // The Vue wizard needs both section_id (the immutable backup id)
        // and section_number (the user-facing index) so the
        // setting_section_section_<n>_included key can be assembled.
        $sectionsindex = [];
        if (isset($xml->information->contents->sections->section)) {
            foreach ($xml->information->contents->sections->section as $sec) {
                $sectionid    = (string) $sec->sectionid;
                $directory    = (string) $sec->directory;
                $title        = (string) $sec->title;
                $sectionnum   = self::parse_section_number($extractdir, $directory);
                $userinfoflag = self::section_has_userinfo($extractdir, $directory);

                $sectionsindex[$sectionid] = [
                    'section_id'     => (int) $sec->sectionid,
                    'section_number' => $sectionnum,
                    'title'          => $title,
                    'original_name'  => $title,
                    'directory'      => $directory,
                    'included'       => true,
                    'userinfo'       => $userinfoflag,
                    'section_key'    => 'setting_section_section_' . $sectionnum . '_included',
                    'activities'     => [],
                ];
            }
        }

        // Activities (matched to sections).
        if (isset($xml->information->contents->activities->activity)) {
            foreach ($xml->information->contents->activities->activity as $act) {
                $sectionid = (string) $act->sectionid;
                $cmid      = (int) $act->moduleid;
                $modname   = (string) $act->modulename;
                $title     = (string) $act->title;
                $directory = (string) $act->directory;

                $activity = [
                    'cmid'          => $cmid,
                    'name'          => $title,
                    'original_name' => $title,
                    'modname'       => $modname,
                    'directory'     => $directory,
                    'included'      => true,
                    'userinfo'      => self::activity_has_userinfo($extractdir, $directory),
                    'activity_key'  => 'setting_activity_' . $modname . '_' . $cmid . '_included',
                ];
                if (isset($sectionsindex[$sectionid])) {
                    $sectionsindex[$sectionid]['activities'][] = $activity;
                }
            }
        }

        $sections = array_values($sectionsindex);

        return [
            'success'  => true,
            'error'    => '',
            'sections' => $sections,
        ];
    }

    /**
     * Parse the visible section number from the backup's section.xml so the
     * Vue wizard can assemble the matching setting_section_section_<n>_*
     * keys. Falls back to 0 if the file is missing or unparseable.
     */
    private static function parse_section_number(string $extractdir, string $directory): int {
        $path = $extractdir . '/' . $directory . '/section.xml';
        if (!file_exists($path)) {
            return 0;
        }
        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return 0;
        }
        // Moodle's section.xml puts the visible index under <number>.
        return isset($xml->number) ? (int) $xml->number : 0;
    }

    /**
     * Best-effort detection of whether the section's backup actually
     * contains user data (so the Vue wizard can grey-out the "with users"
     * toggle when there's nothing to include). The check is intentionally
     * cheap: just look for a non-empty users.xml inside the section dir.
     */
    private static function section_has_userinfo(string $extractdir, string $directory): bool {
        $path = $extractdir . '/' . $directory . '/users.xml';
        return file_exists($path) && filesize($path) > 200;
    }

    /**
     * Same as section_has_userinfo() but for an activity directory.
     */
    private static function activity_has_userinfo(string $extractdir, string $directory): bool {
        $path = $extractdir . '/' . $directory . '/users.xml';
        if (file_exists($path) && filesize($path) > 200) {
            return true;
        }
        // Some activity types put user data in grades.xml / completion.xml.
        foreach (['grades.xml', 'completion.xml', 'logs.xml'] as $f) {
            $p = $extractdir . '/' . $directory . '/' . $f;
            if (file_exists($p) && filesize($p) > 200) {
                return true;
            }
        }
        return false;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Query succeeded'),
            'error'   => new external_value(PARAM_TEXT, 'Error message if failed'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'section_id'     => new external_value(PARAM_INT, 'Section ID from backup'),
                    'section_number' => new external_value(PARAM_INT, 'Visible section index (0, 1, 2…)'),
                    'title'          => new external_value(PARAM_TEXT, 'Section title (editable)'),
                    'original_name'  => new external_value(PARAM_TEXT, 'Original section title (immutable, for rename matching)'),
                    'directory'      => new external_value(PARAM_TEXT, 'Extraction directory'),
                    'included'       => new external_value(PARAM_BOOL, 'Whether the section is included by default'),
                    'userinfo'       => new external_value(PARAM_BOOL, 'Whether the section has user data in the backup'),
                    'section_key'    => new external_value(PARAM_TEXT, 'Restore plan setting key for include flag'),
                    'activities' => new external_multiple_structure(
                        new external_single_structure([
                            'cmid'          => new external_value(PARAM_INT, 'CM ID from backup'),
                            'name'          => new external_value(PARAM_TEXT, 'Activity name (editable)'),
                            'original_name' => new external_value(PARAM_TEXT, 'Original activity name (immutable, for rename matching)'),
                            'modname'       => new external_value(PARAM_TEXT, 'Module name (quiz, forum, ...)'),
                            'directory'     => new external_value(PARAM_TEXT, 'Extraction directory'),
                            'included'      => new external_value(PARAM_BOOL, 'Whether the activity is included by default'),
                            'userinfo'      => new external_value(PARAM_BOOL, 'Whether the activity has user data in the backup'),
                            'activity_key'  => new external_value(PARAM_TEXT, 'Restore plan setting key for include flag'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}

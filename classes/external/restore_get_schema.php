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
        $sectionsindex = [];
        if (isset($xml->information->contents->sections->section)) {
            foreach ($xml->information->contents->sections->section as $sec) {
                $sectionid = (string) $sec->sectionid;
                $sectionsindex[$sectionid] = [
                    'section_id'  => (int) $sec->sectionid,
                    'title'       => (string) $sec->title,
                    'directory'   => (string) $sec->directory,
                    'activities'  => [],
                ];
            }
        }

        // Activities (matched to sections).
        if (isset($xml->information->contents->activities->activity)) {
            foreach ($xml->information->contents->activities->activity as $act) {
                $sectionid = (string) $act->sectionid;
                $activity = [
                    'cmid'         => (int) $act->moduleid,
                    'name'         => (string) $act->title,
                    'modname'      => (string) $act->modulename,
                    'directory'    => (string) $act->directory,
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

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Query succeeded'),
            'error'   => new external_value(PARAM_TEXT, 'Error message if failed'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'section_id' => new external_value(PARAM_INT, 'Section ID from backup'),
                    'title'      => new external_value(PARAM_TEXT, 'Section title'),
                    'directory'  => new external_value(PARAM_TEXT, 'Extraction directory'),
                    'activities' => new external_multiple_structure(
                        new external_single_structure([
                            'cmid'      => new external_value(PARAM_INT, 'CM ID from backup'),
                            'name'      => new external_value(PARAM_TEXT, 'Activity name'),
                            'modname'   => new external_value(PARAM_TEXT, 'Module name (quiz, forum, ...)'),
                            'directory' => new external_value(PARAM_TEXT, 'Extraction directory'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}

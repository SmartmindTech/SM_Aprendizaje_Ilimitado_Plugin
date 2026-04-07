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
require_once($CFG->dirroot . '/course/lib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * Phase 6 — Vue restore wizard: execute step.
 *
 * Given a backupid previously created by restore_prepare, creates a destination
 * course (new or existing) and runs restore_controller synchronously with
 * default restore settings. Returns the new course ID.
 *
 * Stages SmartMind metadata in $SESSION before executing so the course_restored
 * observer can pick it up on the new course.
 *
 * This is the "thin" synchronous version — suitable for small backups. Large
 * restores should be moved to an ad-hoc task in a future iteration.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_execute extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'backupid'     => new external_value(PARAM_ALPHANUMEXT, 'Backup ID from restore_prepare'),
            'categoryid'   => new external_value(PARAM_INT, 'Destination course category'),
            'fullname'     => new external_value(PARAM_TEXT, 'New course fullname', VALUE_DEFAULT, ''),
            'shortname'    => new external_value(PARAM_TEXT, 'New course shortname', VALUE_DEFAULT, ''),
            // SmartMind metadata (staged into SESSION for the course_restored observer).
            'smgp_fields_json' => new external_value(PARAM_RAW, 'JSON bundle of SmartMind fields', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(
        string $backupid,
        int $categoryid,
        string $fullname = '',
        string $shortname = '',
        string $smgp_fields_json = '{}'
    ): array {
        global $CFG, $USER, $SESSION, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'backupid'         => $backupid,
            'categoryid'       => $categoryid,
            'fullname'         => $fullname,
            'shortname'        => $shortname,
            'smgp_fields_json' => $smgp_fields_json,
        ]);

        $catcontext = \context_coursecat::instance($params['categoryid']);
        self::validate_context($catcontext);
        require_capability('moodle/restore:restorecourse', $catcontext);
        require_capability('moodle/course:create', $catcontext);

        // Stage SmartMind fields for the course_restored observer.
        $fields = json_decode($params['smgp_fields_json'], true);
        if (is_array($fields) && !empty($fields)) {
            $SESSION->smgp_restore_pending = [
                'courseid' => 0, // populated by observer
                'fields'   => $fields,
            ];
        }

        // Extract directory should exist from restore_prepare.
        $extractdir = $CFG->tempdir . '/backup/' . $params['backupid'];
        if (!is_dir($extractdir)) {
            return ['success' => false, 'error' => 'Backup not found', 'courseid' => 0, 'course_url' => ''];
        }

        // Placeholder course for the restore target.
        $placeholder = (object) [
            'category'  => $params['categoryid'],
            'fullname'  => !empty($params['fullname']) ? $params['fullname'] : 'Importing...',
            'shortname' => !empty($params['shortname']) ? $params['shortname'] : 'smgp_tmp_' . time(),
            'summary'   => '',
            'summaryformat' => FORMAT_HTML,
        ];
        $newcourse = create_course($placeholder);

        try {
            $controller = new \restore_controller(
                $params['backupid'],
                $newcourse->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_EXISTING_DELETING
            );

            $controller->execute_precheck();
            $controller->execute_plan();
            $controller->destroy();
        } catch (\Throwable $e) {
            return [
                'success'    => false,
                'error'      => $e->getMessage(),
                'courseid'   => (int) $newcourse->id,
                'course_url' => '',
            ];
        }

        $courseurl = (new \moodle_url('/local/sm_graphics_plugin/pages/spa.php'))->out(false)
                     . '#/courses/' . $newcourse->id . '/landing';

        return [
            'success'    => true,
            'error'      => '',
            'courseid'   => (int) $newcourse->id,
            'course_url' => $courseurl,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'Restore succeeded'),
            'error'      => new external_value(PARAM_TEXT, 'Error message if failed'),
            'courseid'   => new external_value(PARAM_INT, 'Restored course ID'),
            'course_url' => new external_value(PARAM_RAW, 'Redirect URL (SPA landing)'),
        ]);
    }
}

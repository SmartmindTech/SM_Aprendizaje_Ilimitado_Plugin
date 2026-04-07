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
 * Phase 6 step 3 — Settings. Returns the top-level root settings from the
 * restore plan (Include users, enrolments, role assignments, permissions,
 * activities, blocks, filters, comments, badges, groups, competencies, etc.)
 * so the Vue wizard can render them as toggles.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_get_settings extends external_api {

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

        // Build a throwaway controller bound to a placeholder course (we won't execute it).
        $placeholder = (object) [
            'category'  => $params['categoryid'],
            'fullname'  => 'Restore settings scan',
            'shortname' => 'smgp_settings_' . time(),
            'summary'   => '',
            'summaryformat' => FORMAT_HTML,
        ];
        $newcourse = create_course($placeholder);

        $settings = [];
        try {
            $controller = new \restore_controller(
                $params['backupid'],
                $newcourse->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_EXISTING_DELETING
            );

            $plan = $controller->get_plan();
            foreach ($plan->get_tasks() as $task) {
                if (!($task instanceof \restore_root_task)) {
                    continue;
                }
                foreach ($task->get_settings() as $setting) {
                    $settings[] = [
                        'name'     => $setting->get_ui_name(),
                        'label'    => $setting->get_ui()->get_label(),
                        'type'     => (string) $setting->get_ui()->get_type(),
                        'value'    => (string) $setting->get_value(),
                        'visible'  => (bool) $setting->get_visibility(),
                        'locked'   => $setting->get_status() > 0,
                    ];
                }
            }
            $controller->destroy();
        } catch (\Throwable $e) {
            // Cleanup the placeholder course.
            delete_course($newcourse->id, false);
            return ['success' => false, 'error' => $e->getMessage(), 'settings' => []];
        }

        // Cleanup the placeholder course (we only needed its settings).
        delete_course($newcourse->id, false);

        return ['success' => true, 'error' => '', 'settings' => $settings];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Query succeeded'),
            'error'   => new external_value(PARAM_TEXT, 'Error message if failed'),
            'settings' => new external_multiple_structure(
                new external_single_structure([
                    'name'    => new external_value(PARAM_TEXT, 'Setting name'),
                    'label'   => new external_value(PARAM_TEXT, 'Setting label'),
                    'type'    => new external_value(PARAM_TEXT, 'UI type'),
                    'value'   => new external_value(PARAM_RAW, 'Current value'),
                    'visible' => new external_value(PARAM_BOOL, 'Visible flag'),
                    'locked'  => new external_value(PARAM_BOOL, 'Locked flag'),
                ])
            ),
        ]);
    }
}

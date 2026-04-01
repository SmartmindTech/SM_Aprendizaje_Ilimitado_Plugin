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
 * AJAX service: import a course from a scanned SharePoint folder.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sharepoint_import extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'folder_url' => new external_value(PARAM_URL, 'SharePoint folder URL'),
            'categoryid' => new external_value(PARAM_INT, 'Target course category ID'),
        ]);
    }

    public static function execute(string $folder_url, int $categoryid): array {
        global $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'folder_url' => $folder_url,
            'categoryid' => $categoryid,
        ]);

        $context = \context_system::instance();
        require_capability('local/sm_graphics_plugin:import_courses', $context);

        // Re-scan to get fresh manifest.
        $manifest = \local_sm_graphics_plugin\sharepoint\course_analyzer::analyze($params['folder_url']);

        if ($manifest === null) {
            return [
                'success'    => false,
                'courseid'   => 0,
                'course_url' => '',
                'log'        => ['No se pudo conectar con SharePoint o la URL no es valida.'],
            ];
        }

        // Execute the import.
        $result = \local_sm_graphics_plugin\sharepoint\course_importer::import(
            $manifest,
            $params['categoryid']
        );

        return [
            'success'    => $result['success'],
            'courseid'   => $result['courseid'],
            'course_url' => $result['course_url'],
            'log'        => $result['log'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'Whether the import succeeded'),
            'courseid'   => new external_value(PARAM_INT, 'New course ID'),
            'course_url' => new external_value(PARAM_RAW, 'URL to the new course'),
            'log'        => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Log message')
            ),
        ]);
    }
}

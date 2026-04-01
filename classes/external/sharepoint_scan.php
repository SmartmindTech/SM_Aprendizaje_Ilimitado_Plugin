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
 * AJAX service: scan a SharePoint folder and classify its contents.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sharepoint_scan extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'folder_url' => new external_value(PARAM_URL, 'SharePoint folder URL'),
        ]);
    }

    public static function execute(string $folder_url): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'folder_url' => $folder_url,
        ]);

        $context = \context_system::instance();
        require_capability('local/sm_graphics_plugin:import_courses', $context);

        $manifest = \local_sm_graphics_plugin\sharepoint\course_analyzer::analyze($params['folder_url']);

        if ($manifest === null) {
            $error = \local_sm_graphics_plugin\sharepoint\course_analyzer::get_last_error()
                ?? 'No se pudo conectar con SharePoint o la URL no es valida.';
            return [
                'success'     => false,
                'folder_name' => '',
                'mbz'         => [],
                'scorm'       => [],
                'pdf'         => [],
                'documents'   => [],
                'evaluations_aiken' => [],
                'evaluations_gift'  => [],
                'warnings'    => [$error],
            ];
        }

        return [
            'success'     => true,
            'folder_name' => $manifest['folder_name'],
            'mbz'         => self::format_files($manifest['mbz']),
            'scorm'       => self::format_files($manifest['scorm']),
            'pdf'         => self::format_files($manifest['pdf']),
            'documents'   => self::format_files($manifest['documents']),
            'evaluations_aiken' => self::format_files($manifest['evaluations_aiken']),
            'evaluations_gift'  => self::format_files($manifest['evaluations_gift']),
            'warnings'    => $manifest['warnings'],
        ];
    }

    /**
     * Format file arrays for the external return structure.
     */
    private static function format_files(array $files): array {
        return array_map(function($f) {
            return [
                'name'    => $f['name'],
                'item_id' => $f['item_id'],
                'size'    => $f['size'],
            ];
        }, $files);
    }

    public static function execute_returns(): external_single_structure {
        $filestructure = new external_multiple_structure(
            new external_single_structure([
                'name'    => new external_value(PARAM_TEXT, 'File name'),
                'item_id' => new external_value(PARAM_TEXT, 'SharePoint item ID'),
                'size'    => new external_value(PARAM_INT, 'File size in bytes'),
            ])
        );

        return new external_single_structure([
            'success'     => new external_value(PARAM_BOOL, 'Whether the scan succeeded'),
            'folder_name' => new external_value(PARAM_TEXT, 'Root folder name'),
            'mbz'         => $filestructure,
            'scorm'       => $filestructure,
            'pdf'         => $filestructure,
            'documents'   => $filestructure,
            'evaluations_aiken' => $filestructure,
            'evaluations_gift'  => $filestructure,
            'warnings'    => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Warning message')
            ),
        ]);
    }
}

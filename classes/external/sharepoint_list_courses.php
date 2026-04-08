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
 * AJAX service: search cached SharePoint course folders.
 *
 * Reads from the local_smgp_sp_courses cache table (populated by the
 * sync_sharepoint_courses scheduled task). Returns up to 50 matching results.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sharepoint_list_courses extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_RAW, 'Search term to filter by name', VALUE_DEFAULT, ''),
            'sync'   => new external_value(PARAM_INT, 'When 1, force a fresh SharePoint scan before listing', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(string $search = '', int $sync = 0): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'sync'   => $sync,
        ]);
        $context = \context_system::instance();
        require_capability('local/sm_graphics_plugin:import_courses', $context);

        // Force-sync path: run the same crawler the scheduled task uses,
        // synchronously, so the user sees the fresh cache immediately.
        // Wrapped in try/catch so a SharePoint outage returns a clear
        // error to the UI instead of a 500.
        $syncerror = '';
        if (!empty($params['sync'])) {
            try {
                require_once($CFG->dirroot . '/local/sm_graphics_plugin/classes/task/sync_sharepoint_courses.php');
                $task = new \local_sm_graphics_plugin\task\sync_sharepoint_courses();
                $task->execute();
            } catch (\Throwable $e) {
                $syncerror = $e->getMessage();
            }
        }

        $search = trim($params['search']);
        $totalcount = $DB->count_records('local_smgp_sp_courses');

        // Return the entire cache. The frontend filters client-side as the
        // user types, so a server-side LIMIT would just hide cached courses
        // beyond the cut-off (e.g. 163 → only 50 visible). When the cache
        // grows beyond a few thousand entries we can re-add a LIMIT plus a
        // server-side filter, but at that scale we'd want pagination too.
        if ($search !== '') {
            // Use raw LIKE without Moodle's sql_like() — the default utf8mb4_general_ci
            // collation already handles case-insensitive AND accent-insensitive matching.
            $searchparam = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $courses = $DB->get_records_sql(
                "SELECT id, name, web_url FROM {local_smgp_sp_courses}
                  WHERE name LIKE ? ORDER BY name ASC",
                [$searchparam]
            );
        } else {
            $courses = $DB->get_records('local_smgp_sp_courses', null, 'name ASC', 'id, name, web_url');
        }

        $result = [];
        foreach ($courses as $c) {
            $result[] = [
                'name'    => $c->name,
                'web_url' => $c->web_url,
            ];
        }

        return [
            'success'     => $syncerror === '',
            'total_count' => $totalcount,
            'courses'     => $result,
            'sync_error'  => $syncerror,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'     => new external_value(PARAM_BOOL, 'Whether listing succeeded'),
            'total_count' => new external_value(PARAM_INT, 'Total courses in cache'),
            'courses'     => new external_multiple_structure(
                new external_single_structure([
                    'name'    => new external_value(PARAM_RAW, 'Course folder name'),
                    'web_url' => new external_value(PARAM_RAW, 'SharePoint URL to the folder'),
                ])
            ),
            'sync_error'  => new external_value(PARAM_TEXT, 'Error message from on-demand sync, empty on success', VALUE_DEFAULT, ''),
        ]);
    }
}

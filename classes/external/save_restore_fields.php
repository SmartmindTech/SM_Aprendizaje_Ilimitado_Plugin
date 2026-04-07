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
use external_value;

/**
 * Stage SmartMind restore fields in the PHP session so that the
 * course_restored event observer can write them to DB without relying
 * on browser sessionStorage.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_restore_fields extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'    => new external_value(PARAM_INT, 'Destination course ID (0 if new course)'),
            'fields_json' => new external_value(PARAM_RAW, 'JSON object with all SmartMind field values'),
        ]);
    }

    public static function execute(int $courseid, string $fields_json): array {
        global $DB, $SESSION;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'    => $courseid,
            'fields_json' => $fields_json,
        ]);

        // Capability check — only users who can edit courses may stage restore fields.
        // If courseid > 0 check against that course; otherwise check site context.
        if ($params['courseid'] > 0) {
            $context = \context_course::instance($params['courseid']);
        } else {
            $context = \context_system::instance();
        }
        require_capability('moodle/course:update', $context);

        $fields = json_decode($params['fields_json'], true);
        if (!is_array($fields)) {
            $fields = [];
        }

        // Sanitise each value to prevent injection.
        $allowed = [
            'smgp_duration_hours', 'smgp_level', 'smgp_completion_percentage',
            'smgp_catalogue_cat', 'smgp_smartmind_code', 'smgp_sepe_code',
            'smgp_description', 'smgp_objectives_data', 'smgp_course_structure',
        ];
        $clean = [];
        foreach ($allowed as $key) {
            if (isset($fields[$key])) {
                $clean[$key] = clean_param($fields[$key], PARAM_RAW);
            }
        }

        // Store server-side so course_restored observer can pick it up.
        $SESSION->smgp_restore_pending = [
            'courseid' => $params['courseid'],
            'fields'   => $clean,
        ];

        // If course already exists (post-restore fallback), write directly to DB now.
        if ($params['courseid'] > 0 && $DB->record_exists('course', ['id' => $params['courseid']])) {
            \local_sm_graphics_plugin\observer::write_restore_fields($params['courseid'], $clean);
        }

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the staging succeeded'),
        ]);
    }
}

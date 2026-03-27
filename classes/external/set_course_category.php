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

/**
 * AJAX: Set the catalogue category for a course.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * External function to assign a catalogue category to a course.
 */
class set_course_category extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'categoryid' => new external_value(PARAM_INT, 'Catalogue category ID (0 to remove)'),
        ]);
    }

    /**
     * Execute: set or remove the catalogue category for a course.
     *
     * @param int $courseid Course ID.
     * @param int $categoryid Category ID (0 to remove).
     * @return array {success: bool}
     */
    public static function execute(int $courseid, int $categoryid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'   => $courseid,
            'categoryid' => $categoryid,
        ]);
        $courseid   = $params['courseid'];
        $categoryid = $params['categoryid'];

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        // Remove existing assignment.
        $DB->delete_records('local_smgp_course_category', ['courseid' => $courseid]);

        // Insert new assignment if category > 0.
        if ($categoryid > 0) {
            if (!$DB->record_exists('local_smgp_categories', ['id' => $categoryid])) {
                throw new \moodle_exception('invalidrecord');
            }
            $DB->insert_record('local_smgp_course_category', (object) [
                'courseid'   => $courseid,
                'categoryid' => $categoryid,
            ]);
        }

        return ['success' => true];
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
        ]);
    }
}

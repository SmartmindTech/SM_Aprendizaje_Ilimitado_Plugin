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
 * AJAX: Enrol the current user into a course.
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
 * External function to enrol the current user into a course.
 */
class enrol_user extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID to enrol into'),
        ]);
    }

    /**
     * Execute: enrol the current user.
     *
     * @param int $courseid Course ID.
     * @return array {success: bool, courseviewurl: string}
     */
    public static function execute(int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        // Use system context for validation — the user isn't enrolled yet,
        // so course context would throw requireloginerror.
        $syscontext = \context_system::instance();
        self::validate_context($syscontext);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $context = \context_course::instance($courseid);

        // Check if already enrolled.
        if (is_enrolled($context, $USER->id, '', true)) {
            return [
                'success' => true,
                'courseviewurl' => (new \moodle_url('/course/view.php', ['id' => $courseid, 'smgp_enter' => 1]))->out(false),
            ];
        }

        require_once(__DIR__ . '/../../lib.php');
        $result = local_sm_graphics_plugin_enroll_user($USER->id, $courseid);

        if (!$result) {
            throw new \moodle_exception('enaborterr', 'enrol');
        }

        return [
            'success' => true,
            'courseviewurl' => (new \moodle_url('/course/view.php', ['id' => $courseid, 'smgp_enter' => 1]))->out(false),
        ];
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether enrolment succeeded'),
            'courseviewurl' => new external_value(PARAM_URL, 'URL to view the course'),
        ]);
    }
}

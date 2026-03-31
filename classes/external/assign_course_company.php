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
 * Assign a course to an IOMAD company.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_course_company extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'  => new external_value(PARAM_INT, 'Course ID'),
            'companyid' => new external_value(PARAM_INT, 'Company ID'),
        ]);
    }

    public static function execute(int $courseid, int $companyid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'companyid' => $companyid,
        ]);

        $syscontext = \context_system::instance();
        require_capability('moodle/course:update', $syscontext);

        $courseid = $params['courseid'];
        $companyid = $params['companyid'];

        // Check company exists.
        if (!$DB->record_exists('company', ['id' => $companyid])) {
            return ['success' => false];
        }

        // Check course exists.
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return ['success' => false];
        }

        // Check if already assigned.
        if ($DB->record_exists('company_course', ['companyid' => $companyid, 'courseid' => $courseid])) {
            return ['success' => true]; // Already assigned.
        }

        // Assign course to company.
        $DB->insert_record('company_course', (object) [
            'companyid' => $companyid,
            'courseid'  => $courseid,
        ]);

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the assignment was successful'),
        ]);
    }
}

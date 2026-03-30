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
 * Updates the student limit for a specific company.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_company_limit extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'companyid'   => new external_value(PARAM_INT, 'Company ID'),
            'maxstudents' => new external_value(PARAM_INT, 'Maximum number of students allowed'),
        ]);
    }

    public static function execute(int $companyid, int $maxstudents): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'companyid'   => $companyid,
            'maxstudents' => $maxstudents,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        if (!is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        local_sm_graphics_plugin_save_company_limit($params['companyid'], max(0, $params['maxstudents']));

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the limit was saved successfully'),
        ]);
    }
}

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
 * Returns all companies with their student counts and limits.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_company_limits extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');

        $context = \context_system::instance();
        self::validate_context($context);

        if (!is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $companies = local_sm_graphics_plugin_get_all_company_limits();

        $result = [];
        foreach ($companies as $company) {
            $limitreached = !empty($company['limitreached']);
            if ($company['unlimited']) {
                $status = 'unlimited';
            } else if ($limitreached) {
                $status = 'full';
            } else {
                $status = 'ok';
            }

            $result[] = [
                'id'              => $company['companyid'],
                'name'            => $company['companyname'],
                'shortname'       => $company['shortname'],
                'currentstudents' => $company['studentcount'],
                'maxstudents'     => $company['maxstudents'],
                'limitreached'    => $limitreached,
                'status'          => $status,
            ];
        }

        return ['companies' => $result];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'companies' => new external_multiple_structure(
                new external_single_structure([
                    'id'              => new external_value(PARAM_INT, 'Company ID'),
                    'name'            => new external_value(PARAM_TEXT, 'Company name'),
                    'shortname'       => new external_value(PARAM_TEXT, 'Company short name'),
                    'currentstudents' => new external_value(PARAM_INT, 'Current number of students'),
                    'maxstudents'     => new external_value(PARAM_INT, 'Maximum allowed students'),
                    'limitreached'    => new external_value(PARAM_BOOL, 'Whether the limit has been reached'),
                    'status'          => new external_value(PARAM_TEXT, 'Status: ok, full, or unlimited'),
                ])
            ),
        ]);
    }
}

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
 * Get all IOMAD companies with their course and user counts.
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
use external_multiple_structure;
use external_value;

/**
 * External function to get company statistics.
 */
class get_company_stats extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute: get all companies with course and user counts.
     *
     * @return array Array of company stats.
     */
    public static function execute(): array {
        global $DB;

        $companies = $DB->get_records_sql(
            "SELECT c.id, c.name, c.shortname,
                    (SELECT COUNT(*) FROM {company_course} cc WHERE cc.companyid = c.id) AS coursecount,
                    (SELECT COUNT(*) FROM {company_users} cu WHERE cu.companyid = c.id AND cu.managertype = 0) AS usercount,
                    COALESCE(cl.maxstudents, 0) AS maxusers
               FROM {company} c
          LEFT JOIN {local_smgp_company_limits} cl ON cl.companyid = c.id
           ORDER BY c.name ASC"
        );

        return array_values($companies);
    }

    /**
     * Define return type.
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Company ID'),
                'name' => new external_value(PARAM_TEXT, 'Company name'),
                'shortname' => new external_value(PARAM_TEXT, 'Company short name'),
                'coursecount' => new external_value(PARAM_INT, 'Number of assigned courses'),
                'usercount' => new external_value(PARAM_INT, 'Number of users'),
                'maxusers' => new external_value(PARAM_INT, 'Max users allowed (0 = unlimited)'),
            ])
        );
    }
}

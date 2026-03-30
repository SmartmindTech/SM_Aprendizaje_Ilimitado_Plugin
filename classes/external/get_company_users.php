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
 * Returns company users and management options for the user management page.
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
 * External function to get company users and management data.
 */
class get_company_users extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page'    => new external_value(PARAM_INT, 'Page number (0-based)', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Results per page', VALUE_DEFAULT, 20),
        ]);
    }

    /**
     * Execute: get company users and management options.
     *
     * @param int $page    Page number (0-based).
     * @param int $perpage Results per page.
     * @return array
     */
    public static function execute(int $page = 0, int $perpage = 20): array {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'page'    => $page,
            'perpage' => $perpage,
        ]);
        $page    = $params['page'];
        $perpage = $params['perpage'];

        $context = \context_system::instance();
        self::validate_context($context);

        require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');

        // Only company managers and site admins may access.
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if (!$managerrec && !is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $companyid    = $managerrec->companyid;
        $companyname  = $DB->get_field('company', 'name', ['id' => $companyid]);
        $component    = 'local_sm_graphics_plugin';
        $base         = '/blocks/iomad_company_admin';

        // Company student limit.
        $studentcount = local_sm_graphics_plugin_get_company_student_count($companyid);
        $maxstudents  = local_sm_graphics_plugin_get_company_limit($companyid);
        $limitreached = local_sm_graphics_plugin_is_company_limit_reached($companyid);
        $haslimit     = ($maxstudents > 0);

        // Management option cards.
        $options = [
            [
                'url'         => (new \moodle_url("$base/company_user_create_form.php"))->out(),
                'icon'        => 'fa-user-plus',
                'title'       => get_string('usermgmt_createuser', $component),
                'description' => get_string('usermgmt_createuser_desc', $component),
            ],
            [
                'url'         => (new \moodle_url("$base/editusers.php"))->out(),
                'icon'        => 'fa-pen',
                'title'       => get_string('usermgmt_editusers', $component),
                'description' => get_string('usermgmt_editusers_desc', $component),
            ],
            [
                'url'         => (new \moodle_url("$base/company_departments.php"))->out(),
                'icon'        => 'fa-users-line',
                'title'       => get_string('usermgmt_deptusers', $component),
                'description' => get_string('usermgmt_deptusers_desc', $component),
            ],
            [
                'url'         => (new \moodle_url('/local/sm_graphics_plugin/pages/uploadusers.php'))->out(),
                'icon'        => 'fa-upload',
                'title'       => get_string('usermgmt_uploadusers', $component),
                'description' => get_string('usermgmt_uploadusers_desc', $component),
            ],
            [
                'url'         => (new \moodle_url("$base/user_bulk_download.php"))->out(),
                'icon'        => 'fa-download',
                'title'       => get_string('usermgmt_bulkdownload', $component),
                'description' => get_string('usermgmt_bulkdownload_desc', $component),
            ],
        ];

        // Company user listing (paginated) — query directly for individual fields.
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess
                  FROM {user} u
                  JOIN {company_users} cu ON cu.userid = u.id
                 WHERE cu.companyid = :companyid
                   AND cu.managertype = 0
                   AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        $limitfrom = ($perpage > 0) ? $page * $perpage : 0;
        $limitnum  = ($perpage > 0) ? $perpage : 0;
        $records   = $DB->get_records_sql($sql, ['companyid' => $companyid], $limitfrom, $limitnum);

        $editbaseurl = new \moodle_url('/blocks/iomad_company_admin/editadvanced.php');
        $pageurl     = new \moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php');
        $neverstr    = get_string('usermgmt_never', $component);

        $usersout = [];
        foreach ($records as $r) {
            $usersout[] = [
                'id'        => (int) $r->id,
                'firstname' => $r->firstname,
                'lastname'  => $r->lastname,
                'fullname'  => fullname($r),
                'email'     => $r->email,
                'lastlogin' => $r->lastaccess ? userdate($r->lastaccess) : $neverstr,
                'editurl'   => (new \moodle_url($editbaseurl, ['id' => $r->id]))->out(),
                'deleteurl' => (new \moodle_url($pageurl, [
                    'deleteuser' => $r->id,
                    'sesskey'    => sesskey(),
                ]))->out(),
            ];
        }

        return [
            'heading'      => get_string('usermgmt_heading', $component),
            'users'        => $usersout,
            'hasusers'     => !empty($usersout),
            'usercount'    => count($usersout),
            'studentcount' => $studentcount,
            'maxstudents'  => $maxstudents,
            'haslimit'     => $haslimit,
            'limitreached' => $limitreached,
            'options'      => $options,
        ];
    }

    /**
     * Define return type.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'heading'      => new external_value(PARAM_TEXT, 'Page heading'),
            'users'        => new external_multiple_structure(
                new external_single_structure([
                    'id'        => new external_value(PARAM_INT, 'User ID'),
                    'firstname' => new external_value(PARAM_TEXT, 'First name'),
                    'lastname'  => new external_value(PARAM_TEXT, 'Last name'),
                    'fullname'  => new external_value(PARAM_TEXT, 'Full name'),
                    'email'     => new external_value(PARAM_TEXT, 'Email address'),
                    'lastlogin' => new external_value(PARAM_TEXT, 'Last login date string'),
                    'editurl'   => new external_value(PARAM_RAW, 'Edit user URL'),
                    'deleteurl' => new external_value(PARAM_RAW, 'Delete user URL'),
                ])
            ),
            'hasusers'     => new external_value(PARAM_BOOL, 'Whether there are users'),
            'usercount'    => new external_value(PARAM_INT, 'Number of users on this page'),
            'studentcount' => new external_value(PARAM_INT, 'Total student count'),
            'maxstudents'  => new external_value(PARAM_INT, 'Max students allowed (0 = unlimited)'),
            'haslimit'     => new external_value(PARAM_BOOL, 'Whether a limit is configured'),
            'limitreached' => new external_value(PARAM_BOOL, 'Whether the limit has been reached'),
            'options'      => new external_multiple_structure(
                new external_single_structure([
                    'url'         => new external_value(PARAM_RAW, 'Action URL'),
                    'icon'        => new external_value(PARAM_TEXT, 'FontAwesome icon class'),
                    'title'       => new external_value(PARAM_TEXT, 'Card title'),
                    'description' => new external_value(PARAM_TEXT, 'Card description'),
                ])
            ),
        ]);
    }
}

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
 * Delete a company user.
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
 * External function to delete a company user.
 */
class delete_company_user extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID to delete'),
        ]);
    }

    /**
     * Execute: delete a user belonging to the manager's company.
     *
     * @param int $userid User ID to delete.
     * @return array
     */
    public static function execute(int $userid): array {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
        ]);
        $userid = $params['userid'];

        $context = \context_system::instance();
        self::validate_context($context);

        require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');

        // Only company managers and site admins may delete users.
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if (!$managerrec && !is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        // Verify the target user exists.
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // If manager (not admin), verify the user belongs to the same company.
        if ($managerrec && !is_siteadmin()) {
            $belongs = $DB->record_exists('company_users', [
                'companyid' => $managerrec->companyid,
                'userid'    => $userid,
            ]);
            if (!$belongs) {
                throw new \moodle_exception('accessdenied', 'admin');
            }
        }

        // Prevent deleting yourself.
        if ($userid == $USER->id) {
            return [
                'success' => false,
                'message' => get_string('usermgmt_cannotdeleteself', 'local_sm_graphics_plugin'),
            ];
        }

        // Use IOMAD company_user::delete if available, otherwise Moodle core delete_user.
        if (file_exists($CFG->dirroot . '/local/iomad/lib/company_user.php')) {
            require_once($CFG->dirroot . '/local/iomad/lib/company_user.php');
            if (class_exists('company_user') && method_exists('company_user', 'delete')) {
                \company_user::delete($userid);
            } else {
                delete_user($user);
            }
        } else {
            delete_user($user);
        }

        return [
            'success' => true,
            'message' => get_string('usermgmt_deleted', 'local_sm_graphics_plugin'),
        ];
    }

    /**
     * Define return type.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the deletion succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}

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
 * Checks for plugin updates from the remote repository.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_plugin_update extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/sm_graphics_plugin/classes/update_checker.php');

        $context = \context_system::instance();
        self::validate_context($context);

        if (!is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        // Get current version info.
        $current = \local_sm_graphics_plugin\update_checker::get_current_version();
        $currentversion = $current ? (string) $current->version : '';
        $currentrelease = $current ? $current->release : '';

        // Force check for updates.
        $updateinfo = \local_sm_graphics_plugin\update_checker::check(true);

        if ($updateinfo) {
            return [
                'hasupdate'      => true,
                'currentversion' => $currentversion,
                'currentrelease' => $currentrelease,
                'newversion'     => (string) $updateinfo->version,
                'newrelease'     => $updateinfo->release,
                'downloadurl'    => $updateinfo->download ?? '',
            ];
        }

        return [
            'hasupdate'      => false,
            'currentversion' => $currentversion,
            'currentrelease' => $currentrelease,
            'newversion'     => '',
            'newrelease'     => '',
            'downloadurl'    => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'hasupdate'      => new external_value(PARAM_BOOL, 'Whether an update is available'),
            'currentversion' => new external_value(PARAM_TEXT, 'Current installed version number'),
            'currentrelease' => new external_value(PARAM_TEXT, 'Current installed release string'),
            'newversion'     => new external_value(PARAM_TEXT, 'New available version number'),
            'newrelease'     => new external_value(PARAM_TEXT, 'New available release string'),
            'downloadurl'    => new external_value(PARAM_TEXT, 'Download URL for the update'),
        ]);
    }
}

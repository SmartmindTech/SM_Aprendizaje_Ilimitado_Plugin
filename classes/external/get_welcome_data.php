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
 * Returns welcome page data for the current user.
 */
class get_welcome_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $USER, $CFG;

        $context = \context_system::instance();
        self::validate_context($context);

        return [
            'username' => fullname($USER),
            'siteurl'  => $CFG->wwwroot,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'username' => new external_value(PARAM_TEXT, 'User full name'),
            'siteurl'  => new external_value(PARAM_URL, 'Site URL'),
        ]);
    }
}

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
 * Delete a SmartMind catalogue category.
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
 * External function to delete a catalogue category.
 */
class delete_category extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Category ID to delete'),
        ]);
    }

    /**
     * Execute: delete a category and its course associations.
     *
     * @param int $categoryid Category ID to delete.
     * @return array
     */
    public static function execute(int $categoryid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
        ]);
        $categoryid = $params['categoryid'];

        $context = \context_system::instance();
        self::validate_context($context);

        // Only site admins may delete categories.
        if (!is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        // Reuse the existing delete() helper from create_category.
        \local_sm_graphics_plugin\external\create_category::delete($categoryid);

        return [
            'success' => true,
        ];
    }

    /**
     * Define return type.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the deletion succeeded'),
        ]);
    }
}

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
 * Returns the list of SmartMind catalogue categories for the management page.
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
 * External function to get all SmartMind catalogue categories.
 */
class get_categories_list extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute: get all categories from local_smgp_categories.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);

        // Only site admins may access.
        if (!is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        // Reuse the existing get_all() helper from create_category.
        $categories = \local_sm_graphics_plugin\external\create_category::get_all();

        // Map to expected return structure.
        $catout = [];
        foreach ($categories as $cat) {
            $catout[] = [
                'id'       => (int) $cat['id'],
                'name'     => $cat['name'],
                'imageurl' => $cat['image_src'] ?? '',
            ];
        }

        return [
            'categories'    => $catout,
            'hascategories' => !empty($catout),
        ];
    }

    /**
     * Define return type.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'Category ID'),
                    'name'     => new external_value(PARAM_TEXT, 'Category name'),
                    'imageurl' => new external_value(PARAM_RAW, 'Resolved image URL'),
                ])
            ),
            'hascategories' => new external_value(PARAM_BOOL, 'Whether there are categories'),
        ]);
    }
}

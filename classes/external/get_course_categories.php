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
 * AJAX service: flat list of all Moodle course categories the current
 * user can read, ordered by Moodle's natural sortorder, with a `depth`
 * field so the Vue dropdown can render an indented tree like:
 *
 *    Top
 *      · Programming
 *        · Python
 *      · Languages
 *
 * Reused by the restore wizard's destination step and the courseloader
 * page's category picker.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 */
class get_course_categories extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);

        // get_all() respects capabilities — users without
        // moodle/category:viewhiddencategories won't see hidden ones.
        $categories = \core_course_category::get_all([
            'returnhidden' => false,
            'sort'         => ['sortorder' => 1],
        ]);

        $out = [];
        foreach ($categories as $cat) {
            // depth=1 = top-level child of "Top"; map to 0 so the Vue
            // can render the dropdown without an extra leading dot.
            $depth = max(0, (int) $cat->depth - 1);
            $out[] = [
                'id'    => (int) $cat->id,
                'name'  => format_string($cat->name),
                'depth' => $depth,
                // Pre-formatted indented label so the frontend doesn't
                // have to know about indentation chars.
                'label' => str_repeat('· ', $depth) . format_string($cat->name),
            ];
        }
        return $out;
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'    => new external_value(PARAM_INT, 'Category ID'),
                'name'  => new external_value(PARAM_TEXT, 'Category name'),
                'depth' => new external_value(PARAM_INT, 'Depth in the tree (0 = top-level)'),
                'label' => new external_value(PARAM_TEXT, 'Indented display label'),
            ])
        );
    }
}

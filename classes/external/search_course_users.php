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
 * AJAX: Search enrolled users in a course for @mentions.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind
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
 * External function to search course users.
 */
class search_course_users extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'query'    => new external_value(PARAM_TEXT, 'Search query'),
            'limit'    => new external_value(PARAM_INT, 'Maximum results', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $courseid Course ID.
     * @param string $query Search query.
     * @param int $limit Maximum results.
     * @return array
     */
    public static function execute(int $courseid, string $query, int $limit = 10): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'query'    => $query,
            'limit'    => $limit,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/sm_graphics_plugin:post_comments', $context);

        $limit = min($params['limit'], 20); // Cap at 20.
        $query = trim($params['query']);

        if (empty($query)) {
            return ['users' => []];
        }

        // Get enrolled users matching the query.
        $enrolledusers = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');

        $querylow = mb_strtolower($query);
        $results = [];
        foreach ($enrolledusers as $user) {
            $fullname = fullname($user);
            if (mb_strpos(mb_strtolower($fullname), $querylow) !== false ||
                mb_strpos(mb_strtolower($user->email), $querylow) !== false) {
                $results[] = [
                    'id'       => (int) $user->id,
                    'fullname' => $fullname,
                    'initials' => mb_strtoupper(mb_substr($user->firstname, 0, 1) . mb_substr($user->lastname, 0, 1)),
                ];
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return ['users' => $results];
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'User ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'initials' => new external_value(PARAM_TEXT, 'Initials'),
                ])
            ),
        ]);
    }
}

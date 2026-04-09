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
 * AJAX service: list courses the current user can restore INTO. Used by
 * the restore wizard's "merge into existing" / "delete and replace"
 * destination flows. Capability-checked per row so company managers only
 * see courses inside their company.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 */
class get_courses_for_restore extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_RAW, 'Filter by fullname/shortname (case-insensitive substring)', VALUE_DEFAULT, ''),
            'limit'  => new external_value(PARAM_INT, 'Maximum results to return', VALUE_DEFAULT, 100),
        ]);
    }

    public static function execute(string $search = '', int $limit = 100): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'limit'  => $limit,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $term = trim($params['search']);
        $sqlparams = [];
        $where = 'id <> :siteid AND visible = 1';
        $sqlparams['siteid'] = SITEID;
        if ($term !== '') {
            $like1 = $DB->sql_like('fullname', ':term1', false, false);
            $like2 = $DB->sql_like('shortname', ':term2', false, false);
            $where .= " AND ($like1 OR $like2)";
            $sqlparams['term1'] = '%' . $DB->sql_like_escape($term) . '%';
            $sqlparams['term2'] = '%' . $DB->sql_like_escape($term) . '%';
        }

        $courses = $DB->get_records_select(
            'course',
            $where,
            $sqlparams,
            'fullname ASC',
            'id, fullname, shortname, category',
            0,
            max(1, min(500, (int) $params['limit']))
        );

        $out = [];
        foreach ($courses as $c) {
            $coursecontext = \context_course::instance($c->id);
            // Skip courses the current user can't restore into.
            if (!has_capability('moodle/restore:restorecourse', $coursecontext)) {
                continue;
            }
            $out[] = [
                'id'        => (int) $c->id,
                'fullname'  => format_string($c->fullname),
                'shortname' => format_string($c->shortname),
                'category'  => (int) $c->category,
            ];
        }
        return $out;
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'        => new external_value(PARAM_INT, 'Course ID'),
                'fullname'  => new external_value(PARAM_TEXT, 'Course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'category'  => new external_value(PARAM_INT, 'Category ID'),
            ])
        );
    }
}

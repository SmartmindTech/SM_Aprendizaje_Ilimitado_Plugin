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
 * AJAX: Fetch course comments with pagination.
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
 * External function to fetch comments.
 */
class get_comments extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'parentid' => new external_value(PARAM_INT, 'Parent comment ID (0 for top-level)', VALUE_DEFAULT, 0),
            'cmid'     => new external_value(PARAM_INT, 'Course module ID filter (0 for all)', VALUE_DEFAULT, 0),
            'sort'     => new external_value(PARAM_ALPHA, 'Sort order: newest or oldest', VALUE_DEFAULT, 'newest'),
            'page'     => new external_value(PARAM_INT, 'Page number (0-based)', VALUE_DEFAULT, 0),
            'perpage'  => new external_value(PARAM_INT, 'Results per page', VALUE_DEFAULT, 20),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $courseid Course ID.
     * @param int $parentid Parent comment ID.
     * @param int $cmid Course module ID filter.
     * @param string $sort Sort order.
     * @param int $page Page number.
     * @param int $perpage Results per page.
     * @return array
     */
    public static function execute(int $courseid, int $parentid = 0, int $cmid = 0,
            string $sort = 'newest', int $page = 0, int $perpage = 20): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'parentid' => $parentid,
            'cmid'     => $cmid,
            'sort'     => $sort,
            'page'     => $page,
            'perpage'  => $perpage,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/sm_graphics_plugin:view', $context);

        // Build WHERE clause.
        $where = 'courseid = :courseid AND parentid = :parentid';
        $sqlparams = [
            'courseid' => $params['courseid'],
            'parentid' => $params['parentid'],
        ];

        if ($params['cmid'] > 0) {
            $where .= ' AND cmid = :cmid';
            $sqlparams['cmid'] = $params['cmid'];
        }

        // Sort order.
        $orderby = ($params['sort'] === 'oldest') ? 'timecreated ASC' : 'timecreated DESC';

        // Count total.
        $total = $DB->count_records_select('local_smgp_comments', $where, $sqlparams);

        // Fetch comments.
        $offset = $params['page'] * $params['perpage'];
        $records = $DB->get_records_select(
            'local_smgp_comments',
            $where,
            $sqlparams,
            $orderby,
            '*',
            $offset,
            $params['perpage']
        );

        // Gather user IDs for bulk fetch.
        $userids = [];
        foreach ($records as $rec) {
            $userids[$rec->userid] = true;
        }

        $users = [];
        if (!empty($userids)) {
            $userrecords = $DB->get_records_list('user', 'id', array_keys($userids), '', 'id, firstname, lastname, email');
            foreach ($userrecords as $u) {
                $users[$u->id] = [
                    'id'       => $u->id,
                    'fullname' => fullname($u),
                    'initials' => mb_strtoupper(mb_substr($u->firstname, 0, 1) . mb_substr($u->lastname, 0, 1)),
                ];
            }
        }

        // Build comment array.
        $comments = [];
        foreach ($records as $rec) {
            $user = $users[$rec->userid] ?? ['id' => 0, 'fullname' => '?', 'initials' => '??'];
            $comments[] = [
                'id'                => (int) $rec->id,
                'courseid'          => (int) $rec->courseid,
                'userid'            => (int) $rec->userid,
                'parentid'          => (int) $rec->parentid,
                'content'           => $rec->content,
                'cmid'              => (int) $rec->cmid,
                'positionindex'     => $rec->positionindex !== null ? (int) $rec->positionindex : 0,
                'positiontimestamp' => $rec->positiontimestamp !== null ? (int) $rec->positiontimestamp : 0,
                'activityname'      => $rec->activityname ?? '',
                'activitytype'      => $rec->activitytype ?? '',
                'replycount'        => (int) $rec->replycount,
                'timecreated'       => (int) $rec->timecreated,
                'timemodified'      => (int) $rec->timemodified,
                'userfullname'      => $user['fullname'],
                'userinitials'      => $user['initials'],
                'edited'            => ($rec->timemodified > $rec->timecreated) ? true : false,
            ];
        }

        return [
            'comments' => $comments,
            'total'    => $total,
            'page'     => $params['page'],
            'perpage'  => $params['perpage'],
        ];
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'id'                => new external_value(PARAM_INT, 'Comment ID'),
                    'courseid'          => new external_value(PARAM_INT, 'Course ID'),
                    'userid'            => new external_value(PARAM_INT, 'Author user ID'),
                    'parentid'          => new external_value(PARAM_INT, 'Parent comment ID'),
                    'content'           => new external_value(PARAM_RAW, 'Sanitized HTML content'),
                    'cmid'              => new external_value(PARAM_INT, 'Course module ID'),
                    'positionindex'     => new external_value(PARAM_INT, 'Position index'),
                    'positiontimestamp' => new external_value(PARAM_INT, 'Video timestamp'),
                    'activityname'      => new external_value(PARAM_TEXT, 'Activity name'),
                    'activitytype'      => new external_value(PARAM_TEXT, 'Activity type'),
                    'replycount'        => new external_value(PARAM_INT, 'Number of replies'),
                    'timecreated'       => new external_value(PARAM_INT, 'Created timestamp'),
                    'timemodified'      => new external_value(PARAM_INT, 'Modified timestamp'),
                    'userfullname'      => new external_value(PARAM_TEXT, 'Author full name'),
                    'userinitials'      => new external_value(PARAM_TEXT, 'Author initials'),
                    'edited'            => new external_value(PARAM_BOOL, 'Whether comment was edited'),
                ])
            ),
            'total'   => new external_value(PARAM_INT, 'Total comment count'),
            'page'    => new external_value(PARAM_INT, 'Current page'),
            'perpage' => new external_value(PARAM_INT, 'Items per page'),
        ]);
    }
}

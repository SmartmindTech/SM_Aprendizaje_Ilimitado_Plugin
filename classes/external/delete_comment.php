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
 * AJAX: Delete a course comment and its replies.
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
use external_value;

/**
 * External function to delete a comment.
 */
class delete_comment extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'commentid' => new external_value(PARAM_INT, 'Comment ID to delete'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $commentid Comment ID.
     * @return array
     */
    public static function execute(int $commentid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'commentid' => $commentid,
        ]);

        $comment = $DB->get_record('local_smgp_comments', ['id' => $params['commentid']], '*', MUST_EXIST);

        $context = \context_course::instance($comment->courseid);
        self::validate_context($context);
        require_capability('local/sm_graphics_plugin:post_comments', $context);

        // Must be author OR have delete_any_comment capability.
        $isauthor = ((int) $comment->userid === (int) $USER->id);
        $candeleteany = has_capability('local/sm_graphics_plugin:delete_any_comment', $context);

        if (!$isauthor && !$candeleteany) {
            throw new \moodle_exception('nopermission', 'local_sm_graphics_plugin');
        }

        // Delete all replies (cascade).
        $replycount = $DB->count_records('local_smgp_comments', ['parentid' => $comment->id]);
        $DB->delete_records('local_smgp_comments', ['parentid' => $comment->id]);

        // Delete the comment itself.
        $DB->delete_records('local_smgp_comments', ['id' => $comment->id]);

        // Decrement parent's replycount if this was a reply.
        if ($comment->parentid > 0) {
            $DB->execute(
                "UPDATE {local_smgp_comments} SET replycount = GREATEST(replycount - 1, 0) WHERE id = ?",
                [$comment->parentid]
            );
        }

        return ['success' => true, 'deletedcount' => 1 + $replycount];
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether delete succeeded'),
            'deletedcount' => new external_value(PARAM_INT, 'Number of comments deleted including replies'),
        ]);
    }
}

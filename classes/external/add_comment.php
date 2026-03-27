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
 * AJAX: Create a new course comment or reply.
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
 * External function to add a comment.
 */
class add_comment extends external_api {

    /** @var string[] Allowed HTML tags for comment content. */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><s><u><ul><ol><li><blockquote><a><span>';

    /** @var int Maximum content length. */
    private const MAX_LENGTH = 10000;

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'          => new external_value(PARAM_INT, 'Course ID'),
            'content'           => new external_value(PARAM_RAW, 'Comment HTML content'),
            'parentid'          => new external_value(PARAM_INT, 'Parent comment ID (0 for top-level)', VALUE_DEFAULT, 0),
            'cmid'              => new external_value(PARAM_INT, 'Course module ID (0 for course-level)', VALUE_DEFAULT, 0),
            'positionindex'     => new external_value(PARAM_INT, 'Position index (slide/page/question)', VALUE_DEFAULT, 0),
            'positiontimestamp' => new external_value(PARAM_INT, 'Video timestamp in seconds', VALUE_DEFAULT, 0),
            'activityname'      => new external_value(PARAM_TEXT, 'Activity name', VALUE_DEFAULT, ''),
            'activitytype'      => new external_value(PARAM_ALPHANUMEXT, 'Activity type (quiz, book, etc.)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $courseid Course ID.
     * @param string $content HTML content.
     * @param int $parentid Parent comment ID.
     * @param int $cmid Course module ID.
     * @param int $positionindex Position index.
     * @param int $positiontimestamp Video timestamp.
     * @param string $activityname Activity name.
     * @param string $activitytype Activity type.
     * @return array
     */
    public static function execute(int $courseid, string $content, int $parentid = 0, int $cmid = 0,
            int $positionindex = 0, int $positiontimestamp = 0,
            string $activityname = '', string $activitytype = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'          => $courseid,
            'content'           => $content,
            'parentid'          => $parentid,
            'cmid'              => $cmid,
            'positionindex'     => $positionindex,
            'positiontimestamp' => $positiontimestamp,
            'activityname'      => $activityname,
            'activitytype'      => $activitytype,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/sm_graphics_plugin:post_comments', $context);

        // Sanitize content.
        $cleanhtml = self::sanitize_content($params['content']);
        if (empty(trim(strip_tags($cleanhtml)))) {
            throw new \moodle_exception('emptycomment', 'local_sm_graphics_plugin');
        }

        // Validate parent exists if replying.
        if ($params['parentid'] > 0) {
            $parent = $DB->get_record('local_smgp_comments', ['id' => $params['parentid']], '*', MUST_EXIST);
            if ($parent->courseid != $params['courseid']) {
                throw new \moodle_exception('invalidparent', 'local_sm_graphics_plugin');
            }
        }

        $now = time();
        $record = new \stdClass();
        $record->courseid          = $params['courseid'];
        $record->userid            = $USER->id;
        $record->parentid          = $params['parentid'];
        $record->content           = $cleanhtml;
        $record->contentformat     = FORMAT_HTML;
        $record->cmid              = $params['cmid'];
        $record->positionindex     = $params['positionindex'] > 0 ? $params['positionindex'] : null;
        $record->positiontimestamp = $params['positiontimestamp'] > 0 ? $params['positiontimestamp'] : null;
        $record->activityname      = !empty($params['activityname']) ? $params['activityname'] : null;
        $record->activitytype      = !empty($params['activitytype']) ? $params['activitytype'] : null;
        $record->replycount        = 0;
        $record->timecreated       = $now;
        $record->timemodified      = $now;

        $record->id = $DB->insert_record('local_smgp_comments', $record);

        // Increment parent's replycount.
        if ($params['parentid'] > 0) {
            $DB->execute(
                "UPDATE {local_smgp_comments} SET replycount = replycount + 1 WHERE id = ?",
                [$params['parentid']]
            );
        }

        return [
            'id'            => (int) $record->id,
            'timecreated'   => $now,
            'userfullname'  => fullname($USER),
            'userinitials'  => mb_strtoupper(mb_substr($USER->firstname, 0, 1) . mb_substr($USER->lastname, 0, 1)),
            'content'       => $cleanhtml,
        ];
    }

    /**
     * Sanitize HTML content: strip disallowed tags, enforce max length.
     * Preserves activity tag data attributes through HTMLPurifier.
     *
     * @param string $html Raw HTML.
     * @return string Sanitized HTML.
     */
    private static function sanitize_content(string $html): string {
        // Enforce max length.
        if (mb_strlen($html) > self::MAX_LENGTH) {
            $html = mb_substr($html, 0, self::MAX_LENGTH);
        }

        // Protect activity tag spans from HTMLPurifier stripping data-* attributes.
        // Replace with placeholders before sanitization, restore after.
        $tags = [];
        $html = preg_replace_callback(
            '/<span\s+class="smgp-activity-tag"\s+data-cmid="(\d+)"\s+data-position="([^"]*?)"\s+data-type="([^"]*?)"[^>]*>(.*?)<\/span>/s',
            function ($m) use (&$tags) {
                $idx = count($tags);
                $tags[] = [
                    'cmid'     => (int) $m[1],
                    'position' => preg_replace('/[^0-9]/', '', $m[2]),
                    'type'     => preg_replace('/[^a-zA-Z0-9_]/', '', $m[3]),
                    'label'    => strip_tags($m[4]),
                ];
                return '[[SMGPTAG:' . $idx . ']]';
            },
            $html
        );

        // Strip disallowed tags.
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        // Use Moodle's clean_text for XSS protection.
        $clean = clean_text($clean, FORMAT_HTML);

        // Restore activity tag spans with their data attributes.
        foreach ($tags as $idx => $tag) {
            $span = '<span class="smgp-activity-tag"'
                . ' data-cmid="' . $tag['cmid'] . '"'
                . ' data-position="' . s($tag['position']) . '"'
                . ' data-type="' . s($tag['type']) . '"'
                . ' contenteditable="false">'
                . s($tag['label'])
                . '</span>';
            $clean = str_replace('[[SMGPTAG:' . $idx . ']]', $span, $clean);
        }

        return $clean;
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'           => new external_value(PARAM_INT, 'New comment ID'),
            'timecreated'  => new external_value(PARAM_INT, 'Created timestamp'),
            'userfullname' => new external_value(PARAM_TEXT, 'Author full name'),
            'userinitials' => new external_value(PARAM_TEXT, 'Author initials'),
            'content'      => new external_value(PARAM_RAW, 'Sanitized HTML content'),
        ]);
    }
}

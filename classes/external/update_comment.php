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
 * AJAX: Edit an existing course comment.
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
 * External function to update a comment.
 */
class update_comment extends external_api {

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
            'commentid' => new external_value(PARAM_INT, 'Comment ID to update'),
            'content'   => new external_value(PARAM_RAW, 'New HTML content'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $commentid Comment ID.
     * @param string $content New HTML content.
     * @return array
     */
    public static function execute(int $commentid, string $content): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'commentid' => $commentid,
            'content'   => $content,
        ]);

        $comment = $DB->get_record('local_smgp_comments', ['id' => $params['commentid']], '*', MUST_EXIST);

        $context = \context_course::instance($comment->courseid);
        self::validate_context($context);
        require_capability('local/sm_graphics_plugin:post_comments', $context);

        // Only the author can edit their own comment.
        if ((int) $comment->userid !== (int) $USER->id) {
            throw new \moodle_exception('nopermission', 'local_sm_graphics_plugin');
        }

        // Sanitize content.
        $cleanhtml = self::sanitize_content($params['content']);
        if (empty(trim(strip_tags($cleanhtml)))) {
            throw new \moodle_exception('emptycomment', 'local_sm_graphics_plugin');
        }

        $now = time();
        $comment->content      = $cleanhtml;
        $comment->timemodified = $now;
        $DB->update_record('local_smgp_comments', $comment);

        return [
            'success'      => true,
            'timemodified' => $now,
            'content'      => $cleanhtml,
        ];
    }

    /**
     * Sanitize HTML content. Preserves activity tag data attributes.
     *
     * @param string $html Raw HTML.
     * @return string Sanitized HTML.
     */
    private static function sanitize_content(string $html): string {
        if (mb_strlen($html) > self::MAX_LENGTH) {
            $html = mb_substr($html, 0, self::MAX_LENGTH);
        }

        // Protect activity tag spans from HTMLPurifier stripping data-* attributes.
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

        $clean = strip_tags($html, self::ALLOWED_TAGS);
        $clean = clean_text($clean, FORMAT_HTML);

        // Restore activity tag spans.
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
            'success'      => new external_value(PARAM_BOOL, 'Whether update succeeded'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp'),
            'content'      => new external_value(PARAM_RAW, 'Sanitized HTML content'),
        ]);
    }
}

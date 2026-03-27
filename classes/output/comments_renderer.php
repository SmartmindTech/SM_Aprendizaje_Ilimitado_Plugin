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
 * Comments renderer — builds HTML container for course comments.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders the comments section container with data attributes for JS.
 */
class comments_renderer {

    /**
     * Render the comments container HTML.
     *
     * @return string HTML output.
     */
    public function render(): string {
        global $OUTPUT;

        $context = $this->get_context();
        if (empty($context)) {
            return '';
        }

        try {
            return $OUTPUT->render_from_template('local_sm_graphics_plugin/course_comments', $context);
        } catch (\Exception $e) {
            debugging('Graphic Layer: Failed to render comments template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Build template context from current page state.
     *
     * @return array Template context, or empty if not applicable.
     */
    private function get_context(): array {
        global $PAGE, $USER, $COURSE;

        $course = $PAGE->course ?? $COURSE;
        if (empty($course->id) || $course->id == SITEID) {
            return [];
        }

        $coursecontext = \context_course::instance($course->id);
        $canpost = has_capability('local/sm_graphics_plugin:post_comments', $coursecontext);
        $candeleteany = has_capability('local/sm_graphics_plugin:delete_any_comment', $coursecontext);

        // Detect activity context if on a mod-* page.
        $activitycontext = $this->detect_activity_context();

        return [
            'courseid'     => $course->id,
            'cmid'         => $activitycontext['cmid'],
            'activityname' => $activitycontext['activityname'],
            'activitytype' => $activitycontext['activitytype'],
            'canpost'      => $canpost,
            'candeleteany' => $candeleteany,
            'userid'       => $USER->id,
            'userfullname' => fullname($USER),
        ];
    }

    /**
     * Detect activity context when on a mod-* page.
     *
     * @return array {cmid, activityname, activitytype}
     */
    private function detect_activity_context(): array {
        global $PAGE;

        $result = [
            'cmid'         => 0,
            'activityname' => '',
            'activitytype' => '',
        ];

        $pagetype = $PAGE->pagetype ?? '';
        if (strpos($pagetype, 'mod-') !== 0) {
            return $result;
        }

        $cm = $PAGE->cm ?? null;
        if ($cm) {
            $result['cmid'] = $cm->id;
            $result['activityname'] = $cm->name ?? '';
            $result['activitytype'] = $cm->modname ?? '';
        }

        return $result;
    }
}

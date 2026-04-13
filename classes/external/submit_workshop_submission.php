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
 * AJAX: Submit or update a workshop submission (mod_workshop).
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

class submit_workshop_submission extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'         => new external_value(PARAM_INT, 'Course module ID'),
            'title'        => new external_value(PARAM_TEXT, 'Submission title'),
            'content'      => new external_value(PARAM_RAW, 'Submission content (HTML)', VALUE_DEFAULT, ''),
            'draftitemid'  => new external_value(PARAM_INT, 'Draft file area item ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $cmid, string $title, string $content = '', int $draftitemid = 0): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'title' => $title,
            'content' => $content, 'draftitemid' => $draftitemid,
        ]);

        $cm = get_coursemodule_from_id('workshop', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/workshop:submit', $context);

        require_once($CFG->dirroot . '/mod/workshop/locallib.php');

        $workshop = $DB->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST);

        // Check we're in submission phase.
        if ((int)$workshop->phase !== 20) { // WORKSHOP_PHASE_SUBMISSION = 20.
            return ['success' => false, 'submissionid' => 0, 'message' => 'Not in submission phase'];
        }

        // Get or create submission.
        $submission = $DB->get_record('workshop_submissions', [
            'workshopid' => $workshop->id,
            'authorid'   => $USER->id,
        ]);

        if ($submission) {
            $submission->title = $params['title'];
            $submission->content = $params['content'];
            $submission->contentformat = FORMAT_HTML;
            $submission->timemodified = time();
            $DB->update_record('workshop_submissions', $submission);
        } else {
            $submission = new \stdClass();
            $submission->workshopid = $workshop->id;
            $submission->authorid = $USER->id;
            $submission->title = $params['title'];
            $submission->content = $params['content'];
            $submission->contentformat = FORMAT_HTML;
            $submission->timecreated = time();
            $submission->timemodified = time();
            $submission->example = 0;
            $submission->grade = null;
            $submission->gradeover = null;
            $submission->published = 0;
            $submission->late = 0;
            $submission->id = $DB->insert_record('workshop_submissions', $submission);
        }

        // Save files from draft area.
        if ($params['draftitemid'] > 0) {
            file_save_draft_area_files(
                $params['draftitemid'],
                $context->id,
                'mod_workshop',
                'submission_attachment',
                $submission->id,
                ['maxfiles' => 10]
            );
        }

        // Trigger event.
        $event = \mod_workshop\event\submission_created::create([
            'objectid' => $submission->id,
            'context'  => $context,
            'relateduserid' => $USER->id,
        ]);
        $event->trigger();

        return [
            'success'      => true,
            'submissionid' => (int) $submission->id,
            'message'      => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether submission was saved'),
            'submissionid' => new external_value(PARAM_INT, 'Submission ID'),
            'message'      => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

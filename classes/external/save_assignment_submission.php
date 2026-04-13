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
 * AJAX: Save and optionally submit an assignment submission (mod_assign).
 *
 * Handles online text and file submissions. Uses Moodle's assign API
 * so grades, completion, and notifications flow correctly.
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

class save_assignment_submission extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'onlinetext' => new external_value(PARAM_RAW, 'Online text content (HTML)', VALUE_DEFAULT, ''),
            'draftitemid' => new external_value(PARAM_INT, 'Draft file area item ID (0 = no files)', VALUE_DEFAULT, 0),
            'submitforgrading' => new external_value(PARAM_BOOL, 'Also submit for grading', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(int $cmid, string $onlinetext = '', int $draftitemid = 0, bool $submitforgrading = false): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'onlinetext' => $onlinetext,
            'draftitemid' => $draftitemid, 'submitforgrading' => $submitforgrading,
        ]);

        $cm = get_coursemodule_from_id('assign', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Check cutoff date.
        if ($assign->cutoffdate > 0 && time() > $assign->cutoffdate) {
            return ['success' => false, 'message' => 'Submission cutoff date has passed'];
        }

        // Get or create submission.
        $submission = $DB->get_record_sql(
            "SELECT * FROM {assign_submission}
             WHERE assignment = :assignid AND userid = :userid AND latest = 1",
            ['assignid' => $assign->id, 'userid' => $USER->id]
        );

        if (!$submission) {
            $submission = new \stdClass();
            $submission->assignment = $assign->id;
            $submission->userid = $USER->id;
            $submission->timecreated = time();
            $submission->timemodified = time();
            $submission->status = 'draft';
            $submission->latest = 1;
            $submission->attemptnumber = 0;
            $submission->groupid = 0;
            $submission->id = $DB->insert_record('assign_submission', $submission);
        } else {
            $submission->timemodified = time();
            $submission->status = 'draft';
            $DB->update_record('assign_submission', $submission);
        }

        // Save online text if provided.
        if (!empty($params['onlinetext'])) {
            $textrecord = $DB->get_record('assignsubmission_onlinetext', [
                'assignment' => $assign->id,
                'submission' => $submission->id,
            ]);
            if ($textrecord) {
                $textrecord->onlinetext = $params['onlinetext'];
                $textrecord->onlineformat = FORMAT_HTML;
                $DB->update_record('assignsubmission_onlinetext', $textrecord);
            } else {
                $textrecord = new \stdClass();
                $textrecord->assignment = $assign->id;
                $textrecord->submission = $submission->id;
                $textrecord->onlinetext = $params['onlinetext'];
                $textrecord->onlineformat = FORMAT_HTML;
                $DB->insert_record('assignsubmission_onlinetext', $textrecord);
            }
        }

        // Save file submissions from draft area.
        if ($params['draftitemid'] > 0) {
            file_save_draft_area_files(
                $params['draftitemid'],
                $context->id,
                'assignsubmission_file',
                'submission_files',
                $submission->id,
                ['maxfiles' => $assign->maxfilesubmissions ?? 20]
            );
            // Update file count.
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'assignsubmission_file',
                'submission_files', $submission->id, 'sortorder', false);
            $filerecord = $DB->get_record('assignsubmission_file', [
                'assignment' => $assign->id,
                'submission' => $submission->id,
            ]);
            if ($filerecord) {
                $filerecord->numfiles = count($files);
                $DB->update_record('assignsubmission_file', $filerecord);
            } else {
                $filerecord = new \stdClass();
                $filerecord->assignment = $assign->id;
                $filerecord->submission = $submission->id;
                $filerecord->numfiles = count($files);
                $DB->insert_record('assignsubmission_file', $filerecord);
            }
        }

        // Submit for grading.
        if ($params['submitforgrading']) {
            $submission->status = 'submitted';
            $submission->timemodified = time();
            $DB->update_record('assign_submission', $submission);

            // Trigger event.
            $event = \mod_assign\event\submission_status_updated::create([
                'objectid' => $submission->id,
                'context'  => $context,
                'relateduserid' => $USER->id,
                'other' => ['newstatus' => 'submitted'],
            ]);
            $event->trigger();

            // Completion.
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }

        return ['success' => true, 'message' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether save succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

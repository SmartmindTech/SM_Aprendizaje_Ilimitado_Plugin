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
 * AJAX: Submit survey answers (mod_survey).
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

class submit_survey_answers extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID'),
            'answers' => new external_multiple_structure(
                new external_single_structure([
                    'questionid' => new external_value(PARAM_INT, 'Question ID'),
                    'answer'     => new external_value(PARAM_RAW, 'Answer value'),
                ]),
                'List of answers'
            ),
        ]);
    }

    public static function execute(int $cmid, array $answers): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'answers' => $answers,
        ]);

        $cm = get_coursemodule_from_id('survey', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/survey/lib.php');

        $survey = $DB->get_record('survey', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Check if already completed.
        if ($DB->record_exists('survey_answers', ['survey' => $survey->id, 'userid' => $USER->id])) {
            return ['success' => false, 'message' => 'Survey already completed'];
        }

        // Insert answers.
        $now = time();
        foreach ($params['answers'] as $ans) {
            $record = new \stdClass();
            $record->userid = $USER->id;
            $record->survey = $survey->id;
            $record->question = $ans['questionid'];
            $record->time = $now;
            $record->answer1 = $ans['answer'];
            $record->answer2 = '';
            $DB->insert_record('survey_answers', $record);
        }

        // Trigger events.
        $event = \mod_survey\event\response_submitted::create([
            'context'  => $context,
            'objectid' => $survey->id,
            'relateduserid' => $USER->id,
        ]);
        $event->trigger();

        // Completion.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        return ['success' => true, 'message' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the submission succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

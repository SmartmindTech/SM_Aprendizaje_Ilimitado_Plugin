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
 * AJAX: Save SCORM CMI data from Vue-native SCORM player.
 *
 * Bulk-writes CMI element values using Moodle's scorm_insert_track()
 * so grades, completion status, and score all flow through the normal
 * Moodle SCORM grading pipeline.
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

class save_scorm_cmi_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID'),
            'scoid'   => new external_value(PARAM_INT, 'SCO ID'),
            'attempt' => new external_value(PARAM_INT, 'Attempt number'),
            'data'    => new external_multiple_structure(
                new external_single_structure([
                    'element' => new external_value(PARAM_RAW, 'CMI element name'),
                    'value'   => new external_value(PARAM_RAW, 'CMI element value'),
                ]),
                'CMI key-value pairs to save'
            ),
        ]);
    }

    public static function execute(int $cmid, int $scoid, int $attempt, array $data): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'scoid' => $scoid,
            'attempt' => $attempt, 'data' => $data,
        ]);

        $cm = get_coursemodule_from_id('scorm', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

        // Verify SCO belongs to this SCORM.
        $sco = $DB->get_record('scorm_scoes', ['id' => $params['scoid'], 'scorm' => $scorm->id], '*', MUST_EXIST);

        // Use Moodle's scorm_insert_track to write each value.
        // This is the SAME function Moodle's native SCORM player uses,
        // ensuring grades and completion flow through the normal pipeline.
        $saved = 0;
        foreach ($params['data'] as $item) {
            try {
                scorm_insert_track(
                    $USER->id,
                    $scorm->id,
                    $sco->id,
                    $params['attempt'],
                    $item['element'],
                    $item['value']
                );
                $saved++;
            } catch (\Exception $e) {
                debugging('SCORM CMI save error: ' . $item['element'] . ' = ' . $item['value']
                    . ' — ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Check for completion based on lesson_status.
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $iscomplete = false;
        foreach ($params['data'] as $item) {
            if (in_array($item['element'], ['cmi.core.lesson_status', 'cmi.completion_status'])) {
                if (in_array($item['value'], ['completed', 'passed'])) {
                    $iscomplete = true;
                    break;
                }
            }
        }

        if ($iscomplete) {
            // Trigger completion.
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }

        return [
            'success' => true,
            'saved'   => $saved,
            'message' => '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether save succeeded'),
            'saved'   => new external_value(PARAM_INT, 'Number of elements saved'),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

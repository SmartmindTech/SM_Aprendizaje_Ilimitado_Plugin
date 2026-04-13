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
 * AJAX: Add an entry to a database activity (mod_data).
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

class add_data_entry extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT, 'Course module ID'),
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'fieldid' => new external_value(PARAM_INT, 'Field ID'),
                    'value'   => new external_value(PARAM_RAW, 'Field value'),
                ]),
                'Field values for the new entry'
            ),
        ]);
    }

    public static function execute(int $cmid, array $fields): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'fields' => $fields,
        ]);

        $cm = get_coursemodule_from_id('data', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/data:writeentry', $context);

        require_once($CFG->dirroot . '/mod/data/lib.php');

        $database = $DB->get_record('data', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Check max entries.
        if ($database->maxentries > 0) {
            $count = $DB->count_records('data_records', [
                'dataid' => $database->id,
                'userid' => $USER->id,
            ]);
            if ($count >= $database->maxentries) {
                return ['success' => false, 'entryid' => 0, 'message' => 'Maximum entries reached'];
            }
        }

        // Create the record.
        $record = new \stdClass();
        $record->dataid = $database->id;
        $record->userid = $USER->id;
        $record->groupid = 0;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->approved = ($database->approval) ? 0 : 1;
        $entryid = $DB->insert_record('data_records', $record);

        // Insert field values.
        foreach ($params['fields'] as $f) {
            $content = new \stdClass();
            $content->recordid = $entryid;
            $content->fieldid = $f['fieldid'];
            $content->content = $f['value'];
            $DB->insert_record('data_content', $content);
        }

        // Trigger event.
        $event = \mod_data\event\record_created::create([
            'objectid' => $entryid,
            'context'  => $context,
            'other'    => ['dataid' => $database->id],
        ]);
        $event->trigger();

        // Completion.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        return ['success' => true, 'entryid' => $entryid, 'message' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether entry was created'),
            'entryid' => new external_value(PARAM_INT, 'New entry ID'),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

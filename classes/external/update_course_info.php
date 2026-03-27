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

namespace local_sm_graphics_plugin\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class update_course_info extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'field'    => new external_value(PARAM_TEXT, 'Field name to update'),
            'value'    => new external_value(PARAM_RAW, 'New value'),
        ]);
    }

    public static function execute(int $courseid, string $field, string $value): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'field'    => $field,
            'value'    => $value,
        ]);

        $context = \context_course::instance($params['courseid']);
        require_capability('moodle/course:update', $context);

        $courseid = $params['courseid'];
        $field = $params['field'];
        $value = $params['value'];

        // Course table fields.
        $coursefields = ['fullname', 'summary'];
        if (in_array($field, $coursefields)) {
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $course->$field = $value;
            $course->timemodified = time();
            $DB->update_record('course', $course);
            // Rebuild course cache.
            rebuild_course_cache($courseid, true);
            return ['success' => true, 'value' => $value];
        }

        // Pricing table fields.
        $pricingfields = ['duration_hours', 'sepe_code', 'smartmind_code', 'level',
            'completion_percentage', 'description', 'course_category'];
        if (in_array($field, $pricingfields)) {
            $pricing = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
            if (!$pricing) {
                $pricing = (object) [
                    'courseid' => $courseid,
                    'amount' => 0,
                    'currency' => 'EUR',
                    'duration_hours' => 0,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];
                $pricing->id = $DB->insert_record('local_smgp_course_meta', $pricing);
            }
            $pricing->$field = $value;
            $pricing->timemodified = time();
            $DB->update_record('local_smgp_course_meta', $pricing);
            return ['success' => true, 'value' => $value];
        }

        // Category (link table).
        if ($field === 'categoryid') {
            $catid = (int) $value;
            $DB->delete_records('local_smgp_course_category', ['courseid' => $courseid]);
            if ($catid > 0) {
                $DB->insert_record('local_smgp_course_category', (object) [
                    'courseid'   => $courseid,
                    'categoryid' => $catid,
                ]);
            }
            // Return the category name.
            $catname = '';
            if ($catid > 0) {
                $cat = $DB->get_record('local_smgp_categories', ['id' => $catid]);
                $catname = $cat ? format_string($cat->name) : '';
            }
            return ['success' => true, 'value' => $catname];
        }

        return ['success' => false, 'value' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the update was successful'),
            'value'   => new external_value(PARAM_RAW, 'The updated display value'),
        ]);
    }
}

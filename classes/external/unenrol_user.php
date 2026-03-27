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
require_once($CFG->libdir . '/enrollib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class unenrol_user extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID to unenrol from'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $syscontext = \context_system::instance();
        self::validate_context($syscontext);

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $context = \context_course::instance($courseid);

        // Check if enrolled.
        if (!is_enrolled($context, $USER->id, '', true)) {
            return ['success' => false];
        }

        // Find user's enrolment and unenrol.
        $enrolinstances = enrol_get_instances($courseid, true);
        $unenrolled = false;

        foreach ($enrolinstances as $instance) {
            $plugin = enrol_get_plugin($instance->enrol);
            if (!$plugin) {
                continue;
            }

            // Check if user is enrolled via this instance.
            $ue = $DB->get_record('user_enrolments', [
                'enrolid' => $instance->id,
                'userid'  => $USER->id,
            ]);
            if (!$ue) {
                continue;
            }

            // Try to unenrol.
            try {
                $plugin->unenrol_user($instance, $USER->id);
                $unenrolled = true;
                break;
            } catch (\Exception $e) {
                // Try next instance.
                continue;
            }
        }

        // Reset completion data so progress is zeroed on re-enrol.
        if ($unenrolled) {
            // Delete activity completion states.
            $cmids = $DB->get_fieldset_sql(
                "SELECT id FROM {course_modules} WHERE course = :cid",
                ['cid' => $courseid]
            );
            if (!empty($cmids)) {
                list($insql, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
                $params['uid'] = $USER->id;
                $DB->delete_records_select(
                    'course_modules_completion',
                    "coursemoduleid $insql AND userid = :uid",
                    $params
                );
            }
            // Clear course completion record.
            $DB->delete_records('course_completions', [
                'userid' => $USER->id,
                'course' => $courseid,
            ]);
            // Clear criteria completions.
            $DB->delete_records_select(
                'course_completion_crit_compl',
                'userid = :uid AND course = :cid',
                ['uid' => $USER->id, 'cid' => $courseid]
            );
        }

        return ['success' => $unenrolled];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the unenrolment was successful'),
        ]);
    }
}

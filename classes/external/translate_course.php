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

/**
 * Trigger translation of a course's summary to all supported languages.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translate_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        require_capability('moodle/course:update', $context);

        $courseid = $params['courseid'];
        $course = $DB->get_record('course', ['id' => $courseid], 'summary');
        if (!$course || empty(trim(strip_tags($course->summary ?? '')))) {
            return ['success' => false];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_smgp_course_translations')) {
            return ['success' => false];
        }

        $sourcelang = current_language();
        if (!in_array($sourcelang, ['en', 'es', 'pt_br'])) {
            if (strpos($sourcelang, 'es') === 0) {
                $sourcelang = 'es';
            } else if (strpos($sourcelang, 'pt') === 0) {
                $sourcelang = 'pt_br';
            } else {
                $sourcelang = 'en';
            }
        }

        $alllanguages = ['en', 'es', 'pt_br'];
        $targetlangs = array_diff($alllanguages, [$sourcelang]);
        $plaintext = strip_tags($course->summary);
        $now = time();

        foreach ($targetlangs as $targetlang) {
            $translated = \local_sm_graphics_plugin\gemini::translate($plaintext, $sourcelang, $targetlang);
            if ($translated) {
                $existing = $DB->get_record('local_smgp_course_translations',
                    ['courseid' => $courseid, 'lang' => $targetlang]);
                if ($existing) {
                    $existing->summary = $translated;
                    $existing->timemodified = $now;
                    $DB->update_record('local_smgp_course_translations', $existing);
                } else {
                    $DB->insert_record('local_smgp_course_translations', (object) [
                        'courseid'     => $courseid,
                        'lang'         => $targetlang,
                        'summary'      => $translated,
                        'timecreated'  => $now,
                        'timemodified' => $now,
                    ]);
                }
            }
        }

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether translation was triggered'),
        ]);
    }
}

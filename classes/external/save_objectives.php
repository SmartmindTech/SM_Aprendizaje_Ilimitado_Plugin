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
 * Save learning objectives and optionally trigger translation.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_objectives extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'        => new external_value(PARAM_INT, 'Course ID'),
            'objectives_json' => new external_value(PARAM_RAW, 'JSON array of objective strings'),
            'translate'       => new external_value(PARAM_BOOL, 'Whether to auto-translate', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(int $courseid, string $objectives_json, bool $translate = false): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'objectives_json' => $objectives_json,
            'translate' => $translate,
        ]);

        $context = \context_course::instance($params['courseid']);
        require_capability('moodle/course:update', $context);

        $courseid = $params['courseid'];
        $objectives = json_decode($params['objectives_json'], true);
        if (!is_array($objectives)) {
            return ['success' => false];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_smgp_learning_objectives')) {
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

        // Delete all existing objectives.
        $DB->delete_records('local_smgp_learning_objectives', ['courseid' => $courseid]);

        $now = time();
        $cleantexts = [];
        foreach ($objectives as $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $cleantext = clean_param($text, PARAM_TEXT);
            $cleantexts[] = $cleantext;
            $DB->insert_record('local_smgp_learning_objectives', (object) [
                'courseid'     => $courseid,
                'objective'    => $cleantext,
                'sortorder'    => count($cleantexts) - 1,
                'lang'         => $sourcelang,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        // Auto-translate if requested.
        if ($translate && !empty($cleantexts)) {
            $alllanguages = ['en', 'es', 'pt_br'];
            $targetlangs = array_diff($alllanguages, [$sourcelang]);

            foreach ($targetlangs as $targetlang) {
                $translated = \local_sm_graphics_plugin\gemini::translate_batch(
                    $cleantexts, $sourcelang, $targetlang
                );
                if ($translated) {
                    foreach ($translated as $i => $transtext) {
                        $DB->insert_record('local_smgp_learning_objectives', (object) [
                            'courseid'     => $courseid,
                            'objective'    => clean_param(trim($transtext), PARAM_TEXT),
                            'sortorder'    => $i,
                            'lang'         => $targetlang,
                            'timecreated'  => $now,
                            'timemodified' => $now,
                        ]);
                    }
                }
            }
        }

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether objectives were saved'),
        ]);
    }
}

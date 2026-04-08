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
require_once($CFG->dirroot . '/course/lib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * Atomic write endpoint for the Vue course editor.
 *
 * Writes core course fields (fullname, shortname, summary, category, startdate),
 * SmartMind metadata (duration, level, codes, completion, catalogue category),
 * and learning objectives in one transaction. Supports both create (courseid=0)
 * and update (courseid>0). Scoped to the fields the SmartMind editor exposes —
 * NOT a full replacement for Moodle's native course edit form.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_course_full extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'  => new external_value(PARAM_INT, 'Course ID (0 = create new)'),
            'fullname'  => new external_value(PARAM_TEXT, 'Course fullname'),
            'shortname' => new external_value(PARAM_TEXT, 'Course shortname'),
            'summary'   => new external_value(PARAM_RAW, 'Course summary HTML'),
            'categoryid' => new external_value(PARAM_INT, 'Moodle category ID'),
            'startdate' => new external_value(PARAM_INT, 'Start date timestamp', VALUE_DEFAULT, 0),
            'enddate'   => new external_value(PARAM_INT, 'End date timestamp (0 = no end date)', VALUE_DEFAULT, 0),
            'visible'   => new external_value(PARAM_INT, 'Visible flag', VALUE_DEFAULT, 1),
            'idnumber'         => new external_value(PARAM_TEXT, 'Course ID number (external code)', VALUE_DEFAULT, ''),
            'enablecompletion' => new external_value(PARAM_INT, 'Course completion tracking enabled (0/1)', VALUE_DEFAULT, 1),
            'format'           => new external_value(PARAM_ALPHANUMEXT, 'Course format: topics/weekly/social/singleactivity', VALUE_DEFAULT, 'topics'),
            'numsections'      => new external_value(PARAM_INT, 'Number of sections (only used by topics/weekly)', VALUE_DEFAULT, 1),
            'lang'             => new external_value(PARAM_TEXT, 'Forced course language (empty = site default)', VALUE_DEFAULT, ''),
            // Course image upload — optional. Both fields must be set
            // together to trigger an upload; otherwise the existing
            // overviewfile is left untouched.
            'image_filename'   => new external_value(PARAM_TEXT, 'Filename of the new course image (e.g. cover.jpg)', VALUE_DEFAULT, ''),
            'image_base64'     => new external_value(PARAM_RAW, 'Base64-encoded contents of the new course image', VALUE_DEFAULT, ''),
            // SmartMind meta.
            'duration_hours'        => new external_value(PARAM_FLOAT, 'Duration in hours', VALUE_DEFAULT, 0),
            'level'                 => new external_value(PARAM_ALPHA, 'beginner/medium/advanced', VALUE_DEFAULT, 'beginner'),
            'completion_percentage' => new external_value(PARAM_INT, 'Completion %', VALUE_DEFAULT, 100),
            'is_pill'               => new external_value(PARAM_INT, '1 if this course is a SmartMind pill', VALUE_DEFAULT, 0),
            'smartmind_code'        => new external_value(PARAM_TEXT, 'SmartMind code', VALUE_DEFAULT, ''),
            'sepe_code'             => new external_value(PARAM_TEXT, 'SEPE code', VALUE_DEFAULT, ''),
            'description'           => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'course_category'       => new external_value(PARAM_INT, 'SmartMind category ID', VALUE_DEFAULT, 0),
            // Objectives as a JSON-encoded array of strings.
            'objectives_json'       => new external_value(PARAM_RAW, 'JSON array of objective strings', VALUE_DEFAULT, '[]'),
            'translate'             => new external_value(PARAM_BOOL, 'Translate objectives + summary via Gemini', VALUE_DEFAULT, true),
        ]);
    }

    public static function execute(
        int $courseid,
        string $fullname,
        string $shortname,
        string $summary,
        int $categoryid,
        int $startdate = 0,
        int $enddate = 0,
        int $visible = 1,
        string $idnumber = '',
        int $enablecompletion = 1,
        string $format = 'topics',
        int $numsections = 1,
        string $lang = '',
        string $image_filename = '',
        string $image_base64 = '',
        float $duration_hours = 0,
        string $level = 'beginner',
        int $completion_percentage = 100,
        int $is_pill = 0,
        string $smartmind_code = '',
        string $sepe_code = '',
        string $description = '',
        int $course_category = 0,
        string $objectives_json = '[]',
        bool $translate = true
    ): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'              => $courseid,
            'fullname'              => $fullname,
            'shortname'             => $shortname,
            'summary'               => $summary,
            'categoryid'            => $categoryid,
            'startdate'             => $startdate,
            'enddate'               => $enddate,
            'visible'               => $visible,
            'idnumber'              => $idnumber,
            'enablecompletion'      => $enablecompletion,
            'format'                => $format,
            'numsections'           => $numsections,
            'lang'                  => $lang,
            'image_filename'        => $image_filename,
            'image_base64'          => $image_base64,
            'duration_hours'        => $duration_hours,
            'level'                 => $level,
            'completion_percentage' => $completion_percentage,
            'is_pill'               => $is_pill,
            'smartmind_code'        => $smartmind_code,
            'sepe_code'             => $sepe_code,
            'description'           => $description,
            'course_category'       => $course_category,
            'objectives_json'       => $objectives_json,
            'translate'             => $translate,
        ]);

        // Capability check.
        if ($params['courseid'] > 0) {
            $context = \context_course::instance($params['courseid']);
            require_capability('moodle/course:update', $context);
        } else {
            $catctx = \context_coursecat::instance($params['categoryid']);
            require_capability('moodle/course:create', $catctx);
        }

        // Whitelist allowed course formats — anything else falls back
        // to the safe default 'topics' to avoid storing garbage that
        // would break course rendering.
        $allowedformats = ['topics', 'weekly', 'social', 'singleactivity'];
        $coursefmt = in_array($params['format'], $allowedformats, true)
            ? $params['format']
            : 'topics';

        $transaction = $DB->start_delegated_transaction();

        // 1) Create or update the Moodle course.
        if ($params['courseid'] > 0) {
            $course = get_course($params['courseid']);
            $course->fullname  = $params['fullname'];
            $course->shortname = $params['shortname'];
            $course->summary   = $params['summary'];
            $course->summaryformat = FORMAT_HTML;
            $course->category  = $params['categoryid'];
            $course->startdate = $params['startdate'] > 0 ? $params['startdate'] : $course->startdate;
            $course->enddate   = $params['enddate'];
            $course->visible   = $params['visible'];
            $course->idnumber  = $params['idnumber'];
            $course->enablecompletion = !empty($params['enablecompletion']) ? 1 : 0;
            $course->format    = $coursefmt;
            $course->lang      = $params['lang'];
            update_course($course);
            $newcourseid = (int) $course->id;
        } else {
            $newcourse = (object) [
                'fullname'         => $params['fullname'],
                'shortname'        => $params['shortname'],
                'summary'          => $params['summary'],
                'summaryformat'    => FORMAT_HTML,
                'category'         => $params['categoryid'],
                'startdate'        => $params['startdate'] > 0 ? $params['startdate'] : time(),
                'enddate'          => $params['enddate'],
                'visible'          => $params['visible'],
                'idnumber'         => $params['idnumber'],
                'enablecompletion' => !empty($params['enablecompletion']) ? 1 : 0,
                'format'           => $coursefmt,
                'lang'             => $params['lang'],
            ];
            $created = create_course($newcourse);
            $newcourseid = (int) $created->id;
        }

        // 1b) Persist numsections via the course format options API.
        // Only topics/weekly use this option; for the other formats the
        // call is harmless because update_course_format_options
        // ignores unknown keys for that format.
        if (in_array($coursefmt, ['topics', 'weekly'], true) && $params['numsections'] > 0) {
            require_once($CFG->dirroot . '/course/lib.php');
            $coursefornumsections = get_course($newcourseid);
            course_get_format($coursefornumsections)->update_course_format_options(
                (object) ['numsections' => max(1, (int) $params['numsections'])]
            );
        }

        // 1c) If a new course image was uploaded, replace whatever was
        // in the course's overviewfiles area with it. We rebuild the
        // file area from scratch (delete + create) so the user can
        // also use this flow to "remove" the image by uploading a
        // different one. Bypassed entirely when image_base64 is empty.
        if (!empty($params['image_base64']) && !empty($params['image_filename'])) {
            $coursecontext = \context_course::instance($newcourseid);
            $fs = get_file_storage();
            $fs->delete_area_files($coursecontext->id, 'course', 'overviewfiles');
            $fileinfo = [
                'contextid' => $coursecontext->id,
                'component' => 'course',
                'filearea'  => 'overviewfiles',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => clean_param($params['image_filename'], PARAM_FILE),
            ];
            $binary = base64_decode($params['image_base64'], true);
            if ($binary !== false && strlen($binary) > 0) {
                $fs->create_file_from_string($fileinfo, $binary);
            }
        }

        // 2) Upsert SmartMind meta.
        $now = time();
        $existing = $DB->get_record('local_smgp_course_meta', ['courseid' => $newcourseid]);
        // Defensive: coerce is_pill to a strict 0/1 even if the client
        // sent true/'1'/'yes' — the column is NOT NULL so we can't accept
        // anything else.
        $ispill = !empty($params['is_pill']) ? 1 : 0;
        $metarec = (object) [
            'courseid'              => $newcourseid,
            'amount'                => $existing->amount ?? 0,
            'currency'              => $existing->currency ?? 'EUR',
            'duration_hours'        => $params['duration_hours'],
            'description'           => $params['description'],
            'smartmind_code'        => $params['smartmind_code'],
            'sepe_code'             => $params['sepe_code'],
            'level'                 => in_array($params['level'], ['beginner', 'medium', 'advanced'])
                                           ? $params['level'] : 'beginner',
            'completion_percentage' => max(0, min(100, $params['completion_percentage'])),
            'is_pill'               => $ispill,
            'timemodified'          => $now,
        ];
        if ($existing) {
            $metarec->id = $existing->id;
            $DB->update_record('local_smgp_course_meta', $metarec);
        } else {
            $metarec->timecreated = $now;
            $DB->insert_record('local_smgp_course_meta', $metarec);
        }

        // 3) Upsert SmartMind catalogue category link.
        if ($params['course_category'] > 0) {
            $DB->delete_records('local_smgp_course_category', ['courseid' => $newcourseid]);
            $DB->insert_record('local_smgp_course_category', (object) [
                'courseid'   => $newcourseid,
                'categoryid' => $params['course_category'],
            ]);
        }

        // 4) Save learning objectives (delegates to save_objectives logic).
        $objectivesarr = json_decode($params['objectives_json'], true);
        if (is_array($objectivesarr)) {
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('local_smgp_learning_objectives')) {
                $sourcelang = current_language();
                if (!in_array($sourcelang, ['en', 'es', 'pt_br'])) {
                    $sourcelang = strpos($sourcelang, 'es') === 0 ? 'es' :
                                  (strpos($sourcelang, 'pt') === 0 ? 'pt_br' : 'en');
                }
                $DB->delete_records('local_smgp_learning_objectives', ['courseid' => $newcourseid]);
                $clean = [];
                $sortorder = 0;
                foreach ($objectivesarr as $text) {
                    $text = trim((string) $text);
                    if ($text === '') {
                        continue;
                    }
                    $cleantext = clean_param($text, PARAM_TEXT);
                    $clean[] = $cleantext;
                    $DB->insert_record('local_smgp_learning_objectives', (object) [
                        'courseid'     => $newcourseid,
                        'objective'    => $cleantext,
                        'sortorder'    => $sortorder++,
                        'lang'         => $sourcelang,
                        'timecreated'  => $now,
                        'timemodified' => $now,
                    ]);
                }
                // Optional translation to the other languages.
                if ($params['translate'] && !empty($clean)) {
                    $targets = array_diff(['en', 'es', 'pt_br'], [$sourcelang]);
                    foreach ($targets as $target) {
                        try {
                            $translated = \local_sm_graphics_plugin\gemini::translate_batch(
                                $clean, $sourcelang, $target
                            );
                            if ($translated) {
                                foreach ($translated as $idx => $ttext) {
                                    $DB->insert_record('local_smgp_learning_objectives', (object) [
                                        'courseid'     => $newcourseid,
                                        'objective'    => clean_param(trim($ttext), PARAM_TEXT),
                                        'sortorder'    => $idx,
                                        'lang'         => $target,
                                        'timecreated'  => $now,
                                        'timemodified' => $now,
                                    ]);
                                }
                            }
                        } catch (\Throwable $e) {
                            debugging('Gemini translation failed: ' . $e->getMessage(), DEBUG_NORMAL);
                        }
                    }
                }
            }
        }

        $transaction->allow_commit();
        rebuild_course_cache($newcourseid, true);

        return [
            'success'  => true,
            'courseid' => $newcourseid,
            'redirect' => (new \moodle_url('/local/sm_graphics_plugin/pages/spa.php',
                ['_anchor' => 'courses/' . $newcourseid . '/landing']))->out(false),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'courseid' => new external_value(PARAM_INT, 'Saved course ID'),
            'redirect' => new external_value(PARAM_RAW, 'Redirect URL (SPA landing)'),
        ]);
    }
}

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
use external_multiple_structure;
use external_value;

/**
 * Bulk fetcher for the Vue course editor.
 *
 * Returns core course fields (id, fullname, shortname, summary, category, startdate),
 * SmartMind metadata (duration, level, codes, completion %, catalogue category),
 * the course's learning objectives, and lists of available catalogue categories and
 * (on IOMAD sites) company options. Used by Phase 5 of the migration — the Vue
 * course editor that replaces Moodle's native /course/edit.php form.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_edit_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID (0 = new course)'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $context = $courseid > 0
            ? \context_course::instance($courseid)
            : \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        // Core course fields.
        $core = [
            'id'               => 0,
            'fullname'         => '',
            'shortname'        => '',
            'summary'          => '',
            'summaryformat'    => FORMAT_HTML,
            'categoryid'       => 0,
            'startdate'        => 0,
            'enddate'          => 0,
            'visible'          => 1,
            'idnumber'         => '',
            'enablecompletion' => 1,
            'format'           => 'topics',
            'numsections'      => 1,
            'lang'             => '',
            'courseimage_url'  => '',
        ];
        if ($courseid > 0) {
            $course = get_course($courseid);
            $core['id']               = (int) $course->id;
            $core['fullname']         = format_string($course->fullname);
            $core['shortname']        = $course->shortname;
            $core['summary']          = $course->summary ?? '';
            $core['summaryformat']    = (int) ($course->summaryformat ?? FORMAT_HTML);
            $core['categoryid']       = (int) $course->category;
            $core['startdate']        = (int) $course->startdate;
            $core['enddate']          = (int) ($course->enddate ?? 0);
            $core['visible']          = (int) $course->visible;
            $core['idnumber']         = (string) ($course->idnumber ?? '');
            $core['enablecompletion'] = (int) ($course->enablecompletion ?? 1);
            $core['format']           = (string) ($course->format ?? 'topics');
            $core['lang']             = (string) ($course->lang ?? '');

            // numsections lives in course_format_options for the
            // current format (topics/weekly), not directly on the course
            // record. For formats that don't expose numsections (social,
            // singleactivity) the option is just absent — we keep the
            // default of 1 in that case.
            require_once($CFG->dirroot . '/course/lib.php');
            $formatopts = course_get_format($course)->get_format_options();
            if (isset($formatopts['numsections'])) {
                $core['numsections'] = (int) $formatopts['numsections'];
            }

            // Course overview image — return the first valid image found
            // in the course's overviewfiles area, if any.
            $courseobj = new \core_course_list_element($course);
            foreach ($courseobj->get_course_overviewfiles() as $file) {
                if ($file->is_valid_image()) {
                    $core['courseimage_url'] = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                    break;
                }
            }
        }

        // SmartMind meta.
        $meta = [
            'duration_hours'        => 0.0,
            'level'                 => 'beginner',
            'completion_percentage' => 100,
            'is_pill'               => 0,
            'smartmind_code'        => '',
            'sepe_code'             => '',
            'description'           => '',
            'course_category'       => 0,
        ];
        if ($courseid > 0) {
            $rec = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
            if ($rec) {
                $meta['duration_hours']        = (float) ($rec->duration_hours ?? 0);
                $meta['level']                 = $rec->level ?? 'beginner';
                $meta['completion_percentage'] = (int) ($rec->completion_percentage ?? 100);
                $meta['is_pill']               = (int) ($rec->is_pill ?? 0);
                $meta['smartmind_code']        = $rec->smartmind_code ?? '';
                $meta['sepe_code']             = $rec->sepe_code ?? '';
                $meta['description']           = $rec->description ?? '';
            }
            $catlink = $DB->get_record('local_smgp_course_category', ['courseid' => $courseid]);
            if ($catlink) {
                $meta['course_category'] = (int) $catlink->categoryid;
            }
        }

        // Learning objectives for this course (source language only — editor UI).
        $objectives = [];
        $dbman = $DB->get_manager();
        if ($courseid > 0 && $dbman->table_exists('local_smgp_learning_objectives')) {
            $lang = current_language();
            if (!in_array($lang, ['en', 'es', 'pt_br'])) {
                $lang = strpos($lang, 'es') === 0 ? 'es' : (strpos($lang, 'pt') === 0 ? 'pt_br' : 'en');
            }
            $recs = $DB->get_records('local_smgp_learning_objectives',
                ['courseid' => $courseid, 'lang' => $lang], 'sortorder ASC', 'objective');
            if (empty($recs)) {
                $recs = $DB->get_records('local_smgp_learning_objectives',
                    ['courseid' => $courseid], 'sortorder ASC', 'objective');
            }
            foreach ($recs as $r) {
                $objectives[] = ['text' => $r->objective];
            }
        }

        // Moodle course categories list (for core "category" dropdown).
        $moodlecats = [];
        $catlist = \core_course_category::make_categories_list('moodle/course:create');
        foreach ($catlist as $id => $name) {
            $moodlecats[] = ['id' => (int) $id, 'name' => $name];
        }

        // SmartMind catalogue categories (custom taxonomy).
        $smgpcats = [];
        if ($dbman->table_exists('local_smgp_categories')) {
            $rows = $DB->get_records('local_smgp_categories', null, 'sortorder ASC', 'id, name');
            foreach ($rows as $row) {
                $smgpcats[] = ['id' => (int) $row->id, 'name' => format_string($row->name)];
            }
        }

        // Installed languages (for the "force course language" dropdown).
        $languages = [];
        $translations = get_string_manager()->get_list_of_translations();
        foreach ($translations as $code => $name) {
            $languages[] = ['code' => (string) $code, 'name' => (string) $name];
        }

        return [
            'core'              => $core,
            'meta'              => $meta,
            'objectives'        => $objectives,
            'moodle_categories' => $moodlecats,
            'smgp_categories'   => $smgpcats,
            'languages'         => $languages,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'core' => new external_single_structure([
                'id'               => new external_value(PARAM_INT, 'Course ID'),
                'fullname'         => new external_value(PARAM_TEXT, 'Course fullname'),
                'shortname'        => new external_value(PARAM_TEXT, 'Course shortname'),
                'summary'          => new external_value(PARAM_RAW, 'Course summary HTML'),
                'summaryformat'    => new external_value(PARAM_INT, 'Summary format'),
                'categoryid'       => new external_value(PARAM_INT, 'Moodle category ID'),
                'startdate'        => new external_value(PARAM_INT, 'Start date timestamp'),
                'enddate'          => new external_value(PARAM_INT, 'End date timestamp (0 = no end)', VALUE_DEFAULT, 0),
                'visible'          => new external_value(PARAM_INT, 'Visible flag'),
                'idnumber'         => new external_value(PARAM_TEXT, 'Course ID number (external code)', VALUE_DEFAULT, ''),
                'enablecompletion' => new external_value(PARAM_INT, 'Course completion tracking enabled (0/1)', VALUE_DEFAULT, 1),
                'format'           => new external_value(PARAM_ALPHANUMEXT, 'Course format: topics/weekly/social/singleactivity', VALUE_DEFAULT, 'topics'),
                'numsections'      => new external_value(PARAM_INT, 'Number of sections (only used by topics/weekly formats)', VALUE_DEFAULT, 1),
                'lang'             => new external_value(PARAM_TEXT, 'Forced course language (empty = use site default)', VALUE_DEFAULT, ''),
                'courseimage_url'  => new external_value(PARAM_RAW, 'Current course overview image URL (empty if none)', VALUE_DEFAULT, ''),
            ]),
            'meta' => new external_single_structure([
                'duration_hours'        => new external_value(PARAM_FLOAT, 'Duration in hours'),
                'level'                 => new external_value(PARAM_TEXT, 'beginner/medium/advanced'),
                'completion_percentage' => new external_value(PARAM_INT, 'Completion %'),
                'is_pill'               => new external_value(PARAM_INT, '1 if this course is a SmartMind pill, 0 otherwise', VALUE_DEFAULT, 0),
                'smartmind_code'        => new external_value(PARAM_TEXT, 'SmartMind code'),
                'sepe_code'             => new external_value(PARAM_TEXT, 'SEPE code'),
                'description'           => new external_value(PARAM_RAW, 'Description'),
                'course_category'       => new external_value(PARAM_INT, 'SmartMind catalogue category ID'),
            ]),
            'objectives' => new external_multiple_structure(
                new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'Objective text'),
                ])
            ),
            'moodle_categories' => new external_multiple_structure(
                new external_single_structure([
                    'id'   => new external_value(PARAM_INT, 'Category ID'),
                    'name' => new external_value(PARAM_TEXT, 'Category name'),
                ])
            ),
            'smgp_categories' => new external_multiple_structure(
                new external_single_structure([
                    'id'   => new external_value(PARAM_INT, 'SmartMind category ID'),
                    'name' => new external_value(PARAM_TEXT, 'SmartMind category name'),
                ])
            ),
            'languages' => new external_multiple_structure(
                new external_single_structure([
                    'code' => new external_value(PARAM_TEXT, 'Language code (e.g. es, en, pt_br)'),
                    'name' => new external_value(PARAM_TEXT, 'Localised language display name'),
                ])
            ),
        ]);
    }
}

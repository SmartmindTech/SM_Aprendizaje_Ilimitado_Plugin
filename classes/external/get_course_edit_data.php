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
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $context = $courseid > 0
            ? \context_course::instance($courseid)
            : \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        // Core course fields.
        $core = [
            'id'           => 0,
            'fullname'     => '',
            'shortname'    => '',
            'summary'      => '',
            'summaryformat' => FORMAT_HTML,
            'categoryid'   => 0,
            'startdate'    => 0,
            'visible'      => 1,
        ];
        if ($courseid > 0) {
            $course = get_course($courseid);
            $core['id']            = (int) $course->id;
            $core['fullname']      = format_string($course->fullname);
            $core['shortname']     = $course->shortname;
            $core['summary']       = $course->summary ?? '';
            $core['summaryformat'] = (int) ($course->summaryformat ?? FORMAT_HTML);
            $core['categoryid']    = (int) $course->category;
            $core['startdate']     = (int) $course->startdate;
            $core['visible']       = (int) $course->visible;
        }

        // SmartMind meta.
        $meta = [
            'duration_hours'        => 0.0,
            'level'                 => 'beginner',
            'completion_percentage' => 100,
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

        return [
            'core'              => $core,
            'meta'              => $meta,
            'objectives'        => $objectives,
            'moodle_categories' => $moodlecats,
            'smgp_categories'   => $smgpcats,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'core' => new external_single_structure([
                'id'            => new external_value(PARAM_INT, 'Course ID'),
                'fullname'      => new external_value(PARAM_TEXT, 'Course fullname'),
                'shortname'     => new external_value(PARAM_TEXT, 'Course shortname'),
                'summary'       => new external_value(PARAM_RAW, 'Course summary HTML'),
                'summaryformat' => new external_value(PARAM_INT, 'Summary format'),
                'categoryid'    => new external_value(PARAM_INT, 'Moodle category ID'),
                'startdate'     => new external_value(PARAM_INT, 'Start date timestamp'),
                'visible'       => new external_value(PARAM_INT, 'Visible flag'),
            ]),
            'meta' => new external_single_structure([
                'duration_hours'        => new external_value(PARAM_FLOAT, 'Duration in hours'),
                'level'                 => new external_value(PARAM_TEXT, 'beginner/medium/advanced'),
                'completion_percentage' => new external_value(PARAM_INT, 'Completion %'),
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
        ]);
    }
}

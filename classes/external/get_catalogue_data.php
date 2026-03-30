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
 * Returns course catalogue data (replaces Moodle /?redirect=0).
 * All visible courses with metadata, categories, and enrollment status.
 */
class get_catalogue_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Filter by SmartMind category ID (0 = all)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $categoryid = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['categoryid' => $categoryid]);
        $categoryid = $params['categoryid'];

        $context = \context_system::instance();
        self::validate_context($context);

        // Get SmartMind categories.
        $dbman = $DB->get_manager();
        $categories = [];
        if ($dbman->table_exists('local_smgp_categories')) {
            $cats = $DB->get_records('local_smgp_categories', null, 'sortorder ASC');
            foreach ($cats as $cat) {
                $categories[] = [
                    'id'       => (int) $cat->id,
                    'name'     => format_string($cat->name),
                    'imageurl' => $cat->image_url ?? '',
                ];
            }
        }

        // Get all visible courses.
        $allcourses = get_courses('all', 'c.fullname ASC', 'c.*');
        unset($allcourses[SITEID]); // Remove site course.

        // If filtering by category, get course IDs in that category.
        $filteredids = null;
        if ($categoryid > 0) {
            $links = $DB->get_records('local_smgp_course_category', ['categoryid' => $categoryid]);
            $filteredids = array_map(fn($l) => (int) $l->courseid, $links);
        }

        $courses = [];
        foreach ($allcourses as $course) {
            if ($filteredids !== null && !in_array((int) $course->id, $filteredids)) {
                continue;
            }

            $coursecontext = \context_course::instance($course->id);
            $courseobj = new \core_course_list_element($course);

            // Image.
            $imageurl = '';
            foreach ($courseobj->get_course_overviewfiles() as $file) {
                if ($file->is_valid_image()) {
                    $imageurl = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                        null, $file->get_filepath(), $file->get_filename()
                    )->out(false);
                    break;
                }
            }

            // Metadata.
            $meta = $DB->get_record('local_smgp_course_meta', ['courseid' => $course->id]);
            $isenrolled = is_enrolled($coursecontext, $USER->id, '', true);

            // Category name.
            $catname = '';
            $catlink = $DB->get_record('local_smgp_course_category', ['courseid' => $course->id]);
            if ($catlink) {
                $cat = $DB->get_record('local_smgp_categories', ['id' => $catlink->categoryid]);
                if ($cat) {
                    $catname = format_string($cat->name);
                }
            }

            $courses[] = [
                'id'             => (int) $course->id,
                'fullname'       => format_string($course->fullname),
                'shortname'      => format_string($course->shortname),
                'summary'        => format_text($course->summary ?? '', $course->summaryformat ?? FORMAT_HTML,
                    ['context' => $coursecontext]),
                'image'          => $imageurl,
                'isenrolled'     => $isenrolled,
                'categoryname'   => $catname,
                'level'          => $meta->level ?? 'beginner',
                'duration_hours' => (float) ($meta->duration_hours ?? 0),
            ];
        }

        return [
            'courses'       => $courses,
            'hascourses'    => !empty($courses),
            'totalcount'    => count($courses),
            'categories'    => $categories,
            'hascategories' => !empty($categories),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id'             => new external_value(PARAM_INT, 'Course ID'),
                    'fullname'       => new external_value(PARAM_TEXT, 'Course name'),
                    'shortname'      => new external_value(PARAM_TEXT, 'Short name'),
                    'summary'        => new external_value(PARAM_RAW, 'Summary HTML'),
                    'image'          => new external_value(PARAM_RAW, 'Image URL'),
                    'isenrolled'     => new external_value(PARAM_BOOL, 'User enrolled'),
                    'categoryname'   => new external_value(PARAM_TEXT, 'SmartMind category'),
                    'level'          => new external_value(PARAM_TEXT, 'Difficulty level'),
                    'duration_hours' => new external_value(PARAM_FLOAT, 'Duration hours'),
                ])
            ),
            'hascourses'    => new external_value(PARAM_BOOL, 'Has courses'),
            'totalcount'    => new external_value(PARAM_INT, 'Total courses'),
            'categories'    => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'Category ID'),
                    'name'     => new external_value(PARAM_TEXT, 'Category name'),
                    'imageurl' => new external_value(PARAM_RAW, 'Category image URL'),
                ])
            ),
            'hascategories' => new external_value(PARAM_BOOL, 'Has categories'),
        ]);
    }
}

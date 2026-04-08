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
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * Dashboard bulk fetcher. Returns:
 * - enrolled courses (continue where you left off)
 * - finished courses (with grade + completion date)
 * - category sections (from local_smgp_categories)
 * - recommended courses (based on finished)
 * - training hours + certificate count for the stats row
 */
class get_dashboard_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $USER, $DB, $CFG;

        $context = \context_system::instance();
        self::validate_context($context);

        $courses = enrol_get_my_courses('*', 'fullname ASC');

        $enrolled = [];
        $finished = [];
        $completedids = [];
        $enrolledids = [];
        $totalseconds = 0;

        foreach ($courses as $course) {
            if (!$course->visible) {
                continue;
            }
            $enrolledids[] = (int) $course->id;

            $completion = new \completion_info($course);
            $iscompleted = $completion->is_enabled() && $completion->is_course_complete($USER->id);

            if ($iscompleted) {
                $completedids[] = (int) $course->id;
                $finished[] = self::format_finished_course($course);
            } else {
                $enrolled[] = self::format_enrolled_course($course);
            }
        }

        // Sort enrolled by lastaccess desc.
        usort($enrolled, fn($a, $b) => $b['lastaccess'] <=> $a['lastaccess']);

        // Training hours — sum from log store for last 90 days.
        $since = time() - (90 * DAYSECS);
        $totalseconds = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(timecreated - lag_time), 0) FROM (
                SELECT timecreated,
                       LAG(timecreated) OVER (PARTITION BY userid ORDER BY timecreated) AS lag_time
                FROM {logstore_standard_log}
                WHERE userid = :uid AND timecreated >= :since
             ) t WHERE (timecreated - lag_time) BETWEEN 1 AND 1800",
            ['uid' => $USER->id, 'since' => $since]
        );
        $traininghours = (int) round($totalseconds / 3600);

        // Certificate count.
        $certificates = (int) $DB->count_records('local_smgp_cert_codes', ['userid' => $USER->id]);

        // Category sections.
        $categories = self::get_category_sections(6, 4);

        // Recommended from finished — drives the "Sigue avanzando" section.
        $recommended = self::get_recommended($completedids, $enrolledids, 4);

        // Recommended for you — based on smgp categories of currently
        // enrolled courses, ranked by overall completion popularity.
        // Drives the "Recomendado para ti" section.
        $recommendedforyou = self::get_recommended_for_you($enrolledids, 4);

        // News — 4 most recently created visible courses excluding ones
        // the user is already enrolled in. Drives the "Novedades" section.
        $news = self::get_news($enrolledids, 4);

        // Recently viewed — delegated to the existing get_browsed_courses
        // external so the dashboard hydrates in a single round-trip instead
        // of two parallel calls.
        $recentlyviewed = \local_sm_graphics_plugin\external\get_browsed_courses::execute(4);

        return [
            'courses'              => $enrolled,
            'finished'             => $finished,
            'categories'           => $categories,
            'recommended'          => $recommended,
            'recommended_for_you'  => $recommendedforyou,
            'news'                 => $news,
            'recently_viewed'      => $recentlyviewed,
            'hascourses'           => !empty($enrolled),
            'hasfinished'          => !empty($finished),
            'hascategories'        => !empty($categories),
            'hasrecommended'       => !empty($recommended),
            'hasrecommendedforyou' => !empty($recommendedforyou),
            'hasnews'              => !empty($news),
            'hasrecentlyviewed'    => !empty($recentlyviewed),
            'username'             => fullname($USER),
            'enrolled_count'       => count($enrolledids),
            'completed_count'      => count($completedids),
            'training_hours'       => $traininghours,
            'certificates'         => $certificates,
        ];
    }

    private static function format_enrolled_course(\stdClass $course): array {
        global $USER, $DB;

        $base = self::build_base($course);

        $progress = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
        $base['progress'] = $progress !== null ? (int) round($progress) : 0;

        $base['lastcmid'] = (int) ($DB->get_field_sql(
            "SELECT contextinstanceid FROM {logstore_standard_log}
              WHERE courseid = :cid AND userid = :uid
                AND action = 'viewed' AND target = 'course_module'
              ORDER BY timecreated DESC LIMIT 1",
            ['cid' => $course->id, 'uid' => $USER->id]
        ) ?: 0);

        $base['lastaccess'] = (int) ($DB->get_field('user_lastaccess', 'timeaccess',
            ['userid' => $USER->id, 'courseid' => $course->id]) ?: 0);

        return $base;
    }

    private static function format_finished_course(\stdClass $course): array {
        global $USER, $DB;

        $base = self::build_base($course);
        $base['progress'] = 100;
        $base['lastcmid'] = 0;
        $base['lastaccess'] = 0;

        // Completion date.
        $timecompleted = (int) $DB->get_field('course_completions', 'timecompleted',
            ['course' => $course->id, 'userid' => $USER->id]);
        $base['timecompleted'] = $timecompleted;
        $base['timecompleted_text'] = $timecompleted ? userdate($timecompleted, '%d %b %Y') : '';

        // Grade.
        $base['grade'] = '';
        $base['grademax'] = '';
        $base['hasgrade'] = false;
        $gradeitems = \grade_item::fetch_all(['courseid' => $course->id]);
        if (!empty($gradeitems)) {
            foreach ($gradeitems as $item) {
                if ($item->itemtype === 'course') {
                    $gradeobj = $item->get_grade($USER->id, false);
                    if ($gradeobj && !is_null($gradeobj->finalgrade)) {
                        $base['grade']    = (string) round($gradeobj->finalgrade, 1);
                        $base['grademax'] = (string) round($item->grademax, 1);
                        $base['hasgrade'] = true;
                    }
                    break;
                }
            }
        }

        return $base;
    }

    private static function build_base(\stdClass $course): array {
        $courseobj = new \core_course_list_element($course);

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

        $categoryname = '';
        if (!empty($course->category)) {
            $cat = \core_course_category::get($course->category, IGNORE_MISSING, true);
            $categoryname = $cat ? format_string($cat->name) : '';
        }

        return [
            'id'           => (int) $course->id,
            'fullname'     => format_string($course->fullname),
            'shortname'    => format_string($course->shortname),
            'categoryname' => $categoryname,
            'image'        => $imageurl,
            'progress'     => 0,
            'lastcmid'     => 0,
            'lastaccess'   => 0,
            // Finished-only fields — defaulted for the multiple_structure.
            'timecompleted'      => 0,
            'timecompleted_text' => '',
            'grade'              => '',
            'grademax'           => '',
            'hasgrade'           => false,
        ];
    }

    private static function get_category_sections(int $maxcategories, int $maxcourses): array {
        global $DB, $CFG;

        if (!$DB->get_manager()->table_exists('local_smgp_categories')) {
            return [];
        }

        $cats = $DB->get_records('local_smgp_categories', null, 'sortorder ASC');
        if (empty($cats)) {
            return [];
        }

        $eligible = [];
        foreach ($cats as $cat) {
            $links = $DB->get_records('local_smgp_course_category', ['categoryid' => $cat->id], '', 'courseid');
            if (empty($links)) {
                continue;
            }
            $ids = array_keys($links);
            list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $courses = $DB->get_records_select('course', "id $insql AND visible = 1", $params, 'fullname ASC');
            if (empty($courses)) {
                continue;
            }
            $eligible[] = ['cat' => $cat, 'courses' => $courses];
        }

        if (count($eligible) > $maxcategories) {
            $keys = array_rand($eligible, $maxcategories);
            if (!is_array($keys)) {
                $keys = [$keys];
            }
            $picked = [];
            foreach ($keys as $k) {
                $picked[] = $eligible[$k];
            }
            $eligible = $picked;
        }

        $sections = [];
        foreach ($eligible as $entry) {
            $cat = $entry['cat'];
            $total = count($entry['courses']);
            $formatted = [];
            $count = 0;
            foreach ($entry['courses'] as $course) {
                if ($count >= $maxcourses) {
                    break;
                }
                $formatted[] = self::build_base($course);
                $count++;
            }

            $imagesrc = '';
            if (!empty($cat->image_url)) {
                foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                    $path = $CFG->dirroot . '/theme/smartmind/pix/categories/' . $cat->image_url . '.' . $ext;
                    if (file_exists($path)) {
                        $imagesrc = $CFG->wwwroot . '/theme/smartmind/pix/categories/' . $cat->image_url . '.' . $ext;
                        break;
                    }
                }
            }

            $sections[] = [
                'categoryname' => format_string($cat->name),
                'categoryid'   => (int) $cat->id,
                'image_src'    => $imagesrc,
                'courses'      => $formatted,
                'count'        => $total,
            ];
        }

        return $sections;
    }

    private static function get_recommended(array $completedids, array $enrolledids, int $limit): array {
        global $DB;

        if (empty($completedids) || !$DB->get_manager()->table_exists('local_smgp_course_category')) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($completedids, SQL_PARAMS_NAMED);
        $catlinks = $DB->get_records_select('local_smgp_course_category', "courseid $insql", $params);
        if (empty($catlinks)) {
            return [];
        }

        $catids = array_unique(array_column($catlinks, 'categoryid'));
        list($catinsql, $catparams) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED);
        $alllinks = $DB->get_records_select('local_smgp_course_category', "categoryid $catinsql", $catparams);
        $candidates = array_unique(array_column($alllinks, 'courseid'));
        $candidates = array_diff($candidates, array_merge($completedids, $enrolledids));

        if (empty($candidates)) {
            return [];
        }

        list($cinsql, $cparams) = $DB->get_in_or_equal(array_values($candidates), SQL_PARAMS_NAMED);
        $courses = $DB->get_records_select('course', "id $cinsql AND visible = 1", $cparams);
        if (empty($courses)) {
            return [];
        }

        $courses = array_values($courses);
        shuffle($courses);
        $courses = array_slice($courses, 0, $limit);

        $out = [];
        foreach ($courses as $course) {
            $out[] = self::build_base($course);
        }
        return $out;
    }

    /**
     * Recommend courses based on the user's currently enrolled courses,
     * ranked by how popular each candidate is across the whole platform.
     *
     * Algorithm:
     *   1. Find the smgp categories of the courses the user is enrolled in.
     *   2. Pull all visible courses linked to those categories.
     *   3. Exclude courses the user is already enrolled in.
     *   4. Rank the remaining candidates by completion count (the more
     *      different users that completed it, the higher it scores).
     *   5. Return the top $limit results, freshly formatted.
     *
     * Falls back to an empty array if the user has no enrolled courses,
     * the smgp tables aren't there yet, or no candidate survives the filters.
     *
     * @param array $enrolledids Course IDs the user is currently enrolled in.
     * @param int   $limit       Max courses to return.
     * @return array
     */
    private static function get_recommended_for_you(array $enrolledids, int $limit): array {
        global $DB;

        if (empty($enrolledids) || !$DB->get_manager()->table_exists('local_smgp_course_category')) {
            return [];
        }

        // 1. Smgp categories of the user's enrolled courses.
        list($insql, $params) = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED);
        $catlinks = $DB->get_records_select('local_smgp_course_category', "courseid $insql", $params);
        if (empty($catlinks)) {
            return [];
        }
        $catids = array_unique(array_column($catlinks, 'categoryid'));

        // 2. All courses in those categories.
        list($catinsql, $catparams) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED);
        $alllinks = $DB->get_records_select('local_smgp_course_category', "categoryid $catinsql", $catparams);
        $candidateids = array_unique(array_column($alllinks, 'courseid'));

        // 3. Drop already enrolled.
        $candidateids = array_values(array_diff($candidateids, $enrolledids));
        if (empty($candidateids)) {
            return [];
        }

        // 4. Rank by completion count. LEFT JOIN so courses with zero
        // completions still appear (popularity = 0) and the slice picks
        // them up if there's nothing more popular.
        list($cinsql, $cparams) = $DB->get_in_or_equal($candidateids, SQL_PARAMS_NAMED);
        $sql = "SELECT c.id, COUNT(cc.id) AS completions
                  FROM {course} c
             LEFT JOIN {course_completions} cc
                    ON cc.course = c.id AND cc.timecompleted > 0
                 WHERE c.id $cinsql
                   AND c.visible = 1
              GROUP BY c.id
              ORDER BY completions DESC, c.id ASC";
        $ranked = $DB->get_records_sql($sql, $cparams, 0, $limit);
        if (empty($ranked)) {
            return [];
        }

        // 5. Hydrate full course records in the popularity order.
        $orderedids = array_keys($ranked);
        list($oinsql, $oparams) = $DB->get_in_or_equal($orderedids, SQL_PARAMS_NAMED);
        $courses = $DB->get_records_select('course', "id $oinsql", $oparams);

        $out = [];
        foreach ($orderedids as $cid) {
            if (isset($courses[$cid])) {
                $out[] = self::build_base($courses[$cid]);
            }
        }
        return $out;
    }

    /**
     * Most recently created visible courses, excluding the ones the user
     * is already enrolled in. Drives the "Novedades" section.
     *
     * @param array $enrolledids Course IDs the user is currently enrolled in.
     * @param int   $limit       Max courses to return.
     * @return array
     */
    private static function get_news(array $enrolledids, int $limit): array {
        global $DB;

        $params = [];
        $exclude = '';
        if (!empty($enrolledids)) {
            list($notin, $exparams) = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'ex', false);
            $exclude = "AND id $notin";
            $params = $exparams;
        }

        $sql = "SELECT *
                  FROM {course}
                 WHERE id <> " . SITEID . "
                   AND visible = 1
                   $exclude
              ORDER BY timecreated DESC, id DESC";
        $courses = $DB->get_records_sql($sql, $params, 0, $limit);
        if (empty($courses)) {
            return [];
        }

        $out = [];
        foreach ($courses as $course) {
            $out[] = self::build_base($course);
        }
        return $out;
    }

    public static function execute_returns(): external_single_structure {
        $coursestruct = new external_single_structure([
            'id'                 => new external_value(PARAM_INT, 'Course ID'),
            'fullname'           => new external_value(PARAM_TEXT, 'Course full name'),
            'shortname'          => new external_value(PARAM_TEXT, 'Course short name'),
            'categoryname'       => new external_value(PARAM_TEXT, 'Moodle category name'),
            'image'              => new external_value(PARAM_RAW, 'Course image URL'),
            'progress'           => new external_value(PARAM_INT, 'Progress 0-100'),
            'lastcmid'           => new external_value(PARAM_INT, 'Last viewed activity cmid'),
            'lastaccess'         => new external_value(PARAM_INT, 'Last access timestamp'),
            'timecompleted'      => new external_value(PARAM_INT, 'Completion timestamp (finished only)'),
            'timecompleted_text' => new external_value(PARAM_TEXT, 'Formatted completion date'),
            'grade'              => new external_value(PARAM_TEXT, 'Final grade'),
            'grademax'           => new external_value(PARAM_TEXT, 'Max grade'),
            'hasgrade'           => new external_value(PARAM_BOOL, 'Whether a grade is available'),
        ]);

        return new external_single_structure([
            'courses'              => new external_multiple_structure($coursestruct),
            'finished'             => new external_multiple_structure($coursestruct),
            'categories'           => new external_multiple_structure(
                new external_single_structure([
                    'categoryname' => new external_value(PARAM_TEXT, 'Category name'),
                    'categoryid'   => new external_value(PARAM_INT, 'SmartMind category id'),
                    'image_src'    => new external_value(PARAM_RAW, 'Category image URL'),
                    'courses'      => new external_multiple_structure($coursestruct),
                    'count'        => new external_value(PARAM_INT, 'Total courses in category'),
                ])
            ),
            'recommended'          => new external_multiple_structure($coursestruct),
            'recommended_for_you'  => new external_multiple_structure($coursestruct),
            'news'                 => new external_multiple_structure($coursestruct),
            'recently_viewed'      => new external_multiple_structure($coursestruct),
            'hascourses'           => new external_value(PARAM_BOOL, 'Has enrolled courses'),
            'hasfinished'          => new external_value(PARAM_BOOL, 'Has finished courses'),
            'hascategories'        => new external_value(PARAM_BOOL, 'Has category sections'),
            'hasrecommended'       => new external_value(PARAM_BOOL, 'Has recommended courses'),
            'hasrecommendedforyou' => new external_value(PARAM_BOOL, 'Has personalised recommendations'),
            'hasnews'              => new external_value(PARAM_BOOL, 'Has news (recently created courses)'),
            'hasrecentlyviewed'    => new external_value(PARAM_BOOL, 'Has recently viewed courses'),
            'username'             => new external_value(PARAM_TEXT, 'User full name'),
            'enrolled_count'       => new external_value(PARAM_INT, 'Number of enrolled courses'),
            'completed_count'      => new external_value(PARAM_INT, 'Number of completed courses'),
            'training_hours'       => new external_value(PARAM_INT, 'Approx training hours last 90 days'),
            'certificates'         => new external_value(PARAM_INT, 'Certificate count'),
        ]);
    }
}

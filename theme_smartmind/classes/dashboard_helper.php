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
 * Dashboard helper — formats course data for SmartMind dashboard templates.
 *
 * @package    theme_smartmind
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_smartmind;

defined('MOODLE_INTERNAL') || die();

class dashboard_helper {

    /**
     * Format a list of course records into arrays ready for dashboard templates.
     *
     * @param array $courses   Array of course DB records.
     * @param bool  $enrolled  Whether the current user is enrolled in these courses.
     * @return array  Array of formatted course arrays.
     */
    /**
     * Format the "continue learning" card data for the dashboard template.
     *
     * @param \stdClass     $course  The course DB record.
     * @param \cm_info|null $cminfo  The last accessed activity, or null if none.
     * @return array  Template-ready associative array.
     */
    public static function format_continue_learning(\stdClass $course, ?\cm_info $cminfo = null): array {
        $courseobj = new \core_course_list_element($course);

        $courseimage = '';
        foreach ($courseobj->get_course_overviewfiles() as $file) {
            $courseimage = \moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                null, $file->get_filepath(), $file->get_filename()
            )->out();
            break;
        }

        $activitydesc = '';
        if ($cminfo) {
            global $DB;
            $record = $DB->get_record($cminfo->modname, ['id' => $cminfo->instance], 'intro, introformat');
            if ($record && !empty($record->intro)) {
                $activitydesc = format_text($record->intro, $record->introformat, ['noclean' => true, 'para' => false]);
            }
        }

        return [
            'courseid'        => $course->id,
            'coursename'      => format_string($course->fullname),
            'courseimage'      => $courseimage,
            'hascourseimage'  => !empty($courseimage),
            'courseurl'        => (new \moodle_url('/course/view.php', ['id' => $course->id, 'smgp_enter' => 1]))->out(false),
            'hasactivity'     => ($cminfo !== null),
            'activityname'    => $cminfo ? format_string($cminfo->name) : '',
            'activitydesc'    => $activitydesc,
            'hasactivitydesc' => !empty($activitydesc),
            'activityurl'     => $cminfo
                ? (new \moodle_url('/course/view.php', [
                    'id' => $course->id,
                    'smgp_enter' => 1,
                    'smgp_cmid' => $cminfo->id,
                  ]))->out(false)
                : (new \moodle_url('/course/view.php', ['id' => $course->id, 'smgp_enter' => 1]))->out(false),
            'activityicon'    => $cminfo ? $cminfo->get_icon_url()->out() : '',
        ];
    }

    /**
     * Format enrolled courses that are still in progress (not completed).
     *
     * Filters out completed course IDs so the active list doesn't duplicate them.
     *
     * @param array $courses      Array of course DB records (from enrol_get_my_courses).
     * @param array $completedids Array of course IDs that are already completed.
     * @return array  Array of formatted course arrays.
     */
    public static function format_active_courses(array $courses, array $completedids = []): array {
        global $USER;

        $list = [];
        foreach ($courses as $course) {
            if (!$course->visible) {
                continue;
            }
            if (in_array($course->id, $completedids)) {
                continue;
            }

            $base = self::build_course_base($course, true);

            // Course progress percentage.
            $progress = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
            $base['progress'] = ($progress !== null) ? round($progress) : 0;
            $base['hasprogress'] = true;

            // Resume URL — enters the course player directly.
            $base['resumeurl'] = (new \moodle_url('/course/view.php', [
                'id' => $course->id,
                'smgp_enter' => 1,
            ]))->out(false);

            $list[] = $base;
        }
        return $list;
    }

    /**
     * Format browsed courses (visited but not enrolled).
     *
     * Shows enrol badge, no progress bar.
     *
     * @param array $courses Array of course DB records.
     * @return array Array of formatted course arrays.
     */
    public static function format_browsed_courses(array $courses): array {
        $list = [];
        foreach ($courses as $course) {
            if (!$course->visible) {
                continue;
            }
            $list[] = self::build_course_base($course, false);
        }
        return $list;
    }

    /**
     * Format completed courses with grade and completion date.
     *
     * @param array $courses Array of course DB records (must include ->timecompleted).
     * @return array  Array of formatted course arrays with grade data.
     */
    public static function format_finished_courses(array $courses): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $list = [];
        foreach ($courses as $course) {
            if (!$course->visible) {
                continue;
            }

            $base = self::build_course_base($course, true);

            // Completion date.
            $timecompleted = $course->timecompleted ?? 0;
            $base['timecompleted'] = $timecompleted ? userdate($timecompleted, '%d %b %Y') : '';
            $base['hastimecompleted'] = ($timecompleted > 0);

            // Course grade.
            $base['grade'] = '';
            $base['grademax'] = '';
            $base['gradetext'] = '';
            $base['hasgrade'] = false;

            $gradeitems = \grade_item::fetch_all(['courseid' => $course->id]);
            if (!empty($gradeitems)) {
                foreach ($gradeitems as $item) {
                    if ($item->itemtype === 'course') {
                        global $USER;
                        $gradeobj = $item->get_grade($USER->id, false);
                        if ($gradeobj && !is_null($gradeobj->finalgrade)) {
                            $grade = round($gradeobj->finalgrade, 1);
                            $grademax = round($item->grademax, 1);
                            $base['grade'] = $grade;
                            $base['grademax'] = $grademax;
                            $base['gradetext'] = $grade . ' / ' . $grademax;
                            $base['hasgrade'] = true;
                        }
                        break;
                    }
                }
            }

            $list[] = $base;
        }
        return $list;
    }

    /**
     * Build the base course array shared by active and finished formatters.
     *
     * @param \stdClass $course   A course DB record.
     * @param bool      $enrolled Whether the user is enrolled.
     * @return array
     */
    private static function build_course_base(\stdClass $course, bool $enrolled = true): array {
        $courseobj = new \core_course_list_element($course);

        $courseimage = '';
        foreach ($courseobj->get_course_overviewfiles() as $file) {
            $courseimage = \moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                null, $file->get_filepath(), $file->get_filename()
            )->out();
            break;
        }

        $coursecontext  = \context_course::instance($course->id);
        $studentcount  = count_enrolled_users($coursecontext);
        $modinfo       = get_fast_modinfo($course);
        $activitycount = count($modinfo->get_cms());
        $startdate     = $course->startdate ? userdate($course->startdate, '%d %b %Y') : '';
        $enddate       = $course->enddate   ? userdate($course->enddate, '%d %b %Y')   : '';

        // Course shortname (code) and category name for catalog cards.
        $shortname = format_string($course->shortname);
        $categoryname = '';
        if (!empty($course->category)) {
            $cat = \core_course_category::get($course->category, IGNORE_MISSING, true);
            $categoryname = $cat ? format_string($cat->name) : '';
        }

        return [
            'id'            => $course->id,
            'fullname'      => format_string($course->fullname),
            'shortname'     => $shortname,
            'categoryname'  => $categoryname,
            'hascategory'   => !empty($categoryname),
            'summary'       => format_text($course->summary, $course->summaryformat, ['noclean' => true, 'para' => false]),
            'courseimage'    => $courseimage,
            'viewurl'       => (new \moodle_url('/local/sm_graphics_plugin/pages/course_landing.php', ['id' => $course->id]))->out(),
            'isenrolled'    => $enrolled,
            'isloggedin'    => true,
            'enrollurl'     => (new \moodle_url('/enrol/index.php', ['id' => $course->id]))->out(),
            'teachercount'  => 0,
            'studentcount'  => $studentcount,
            'activitycount' => $activitycount,
            'startdate'     => $startdate,
            'enddate'       => $enddate,
            'showbadge'     => !$enrolled,
        ];
    }

    /**
     * Return fake recommended courses for development/testing.
     *
     * @return array
     */
    public static function get_fake_recommended_courses(): array {
        // Placeholder images from picsum.photos for development/testing.
        return [
            self::fake_course(701, '[FALSO] Curso recomendado 1', 'Datos de prueba.', 42, 10, false, 'REC001', 'Digital', 'https://picsum.photos/seed/rec1/600/400'),
            self::fake_course(702, '[FALSO] Curso recomendado 2', 'Datos de prueba.', 38, 8, false, 'REC002', 'Habilidades', 'https://picsum.photos/seed/rec2/600/400'),
            self::fake_course(703, '[FALSO] Curso recomendado 3', 'Datos de prueba.', 27, 6, false, 'REC003', 'Legal', 'https://picsum.photos/seed/rec3/600/400'),
            self::fake_course(704, '[FALSO] Curso recomendado 4', 'Datos de prueba.', 55, 5, false, 'REC004', 'Seguridad', 'https://picsum.photos/seed/rec4/600/400'),
            self::fake_course(705, '[FALSO] Curso recomendado 5', 'Datos de prueba.', 19, 12, false, 'REC005', 'Digital', 'https://picsum.photos/seed/rec5/600/400'),
            self::fake_course(706, '[FALSO] Curso recomendado 6', 'Datos de prueba.', 31, 7, false, 'REC006', 'Habilidades', 'https://picsum.photos/seed/rec6/600/400'),
            self::fake_course(707, '[FALSO] Curso recomendado 7', 'Datos de prueba.', 23, 15, false, 'REC007', 'Legal', 'https://picsum.photos/seed/rec7/600/400'),
        ];
    }

    /**
     * Build a single fake course array matching the format_courses output.
     *
     * @param int    $id
     * @param string $fullname
     * @param string $summary
     * @param int    $studentcount
     * @param int    $activitycount
     * @param bool   $enrolled
     * @return array
     */
    private static function fake_course(
        int $id,
        string $fullname,
        string $summary,
        int $studentcount,
        int $activitycount,
        bool $enrolled = true,
        string $shortname = '',
        string $categoryname = '',
        string $courseimage = ''
    ): array {
        return [
            'id'            => $id,
            'fullname'      => $fullname,
            'shortname'     => $shortname,
            'categoryname'  => $categoryname,
            'hascategory'   => !empty($categoryname),
            'summary'       => $summary,
            'courseimage'    => $courseimage,
            'viewurl'       => '#',
            'isenrolled'    => $enrolled,
            'isloggedin'    => true,
            'enrollurl'     => '#',
            'showbadge'     => !$enrolled,
            'teachercount'  => 0,
            'studentcount'  => $studentcount,
            'activitycount' => $activitycount,
            'startdate'     => '01 mar 2026',
            'enddate'       => '30 jun 2026',
        ];
    }
}

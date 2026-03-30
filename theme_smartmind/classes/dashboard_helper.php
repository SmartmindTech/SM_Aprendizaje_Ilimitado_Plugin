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

            // Course progress percentage — cast to string so Mustache renders "0".
            $progress = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
            $pct = ($progress !== null) ? (int) round($progress) : 0;
            $base['progress'] = (string) $pct;
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
     * Fetch SmartMind custom catalogue categories (local_smgp_categories)
     * with their linked courses for the dashboard.
     *
     * @param int $maxcategories Maximum number of categories to return.
     * @param int $maxcourses    Maximum courses per category.
     * @return array Array of [{categoryname, categoryid, image_src, courses, count}].
     */
    public static function get_category_sections(int $maxcategories = 6, int $maxcourses = 4): array {
        global $DB, $CFG;

        $categories = $DB->get_records('local_smgp_categories', null, 'sortorder ASC');
        if (empty($categories)) {
            return [];
        }

        // First pass: collect all categories that have visible courses.
        $eligible = [];
        foreach ($categories as $cat) {
            $links = $DB->get_records('local_smgp_course_category', ['categoryid' => $cat->id], '', 'courseid');
            if (empty($links)) {
                continue;
            }

            $courseids = array_keys($links);
            list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $courses = $DB->get_records_select('course', "id $insql AND visible = 1", $params, 'fullname ASC');

            if (empty($courses)) {
                continue;
            }

            $eligible[] = ['cat' => $cat, 'courses' => $courses];
        }

        // Pick random categories (up to $maxcategories).
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

        // Format the selected categories.
        $sections = [];
        foreach ($eligible as $entry) {
            $cat = $entry['cat'];
            $courses = $entry['courses'];

            $totalcount = count($courses);
            $formatted = [];
            $count = 0;
            foreach ($courses as $course) {
                if ($count >= $maxcourses) {
                    break;
                }
                $formatted[] = self::build_course_base($course, false);
                $count++;
            }

            // Resolve category image.
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
                'count'        => $totalcount,
            ];
        }

        return $sections;
    }

    /**
     * Return fake recommended courses for development/testing.
     * @return array
     */
    public static function get_fake_recommended_courses(): array {
        return [
            self::fake_course(701, '[FAKE] Diseño UX/UI: Metodologías Modernas', '', 42, 10, false, 'REC001', 'Diseño', 'https://picsum.photos/seed/rec1/600/400'),
            self::fake_course(702, '[FAKE] Ciberseguridad Empresarial', '', 38, 8, false, 'REC002', 'Seguridad', 'https://picsum.photos/seed/rec2/600/400'),
            self::fake_course(703, '[FAKE] Data Analytics con Power BI', '', 27, 6, false, 'REC003', 'Datos', 'https://picsum.photos/seed/rec3/600/400'),
            self::fake_course(704, '[FAKE] Cloud Architecture con AWS', '', 55, 5, false, 'REC004', 'Cloud', 'https://picsum.photos/seed/rec4/600/400'),
        ];
    }

    /**
     * Fake: recommended based on explored/browsed courses.
     * @return array
     */
    public static function get_fake_rec_explored(): array {
        return [
            self::fake_course(801, '[FAKE] 8 trucos de productividad con IA', '', 64, 4, false, 'EXP01', 'Productividad', 'https://picsum.photos/seed/exp1/600/400'),
            self::fake_course(802, '[FAKE] Introducción a Prompt Engineering', '', 29, 6, false, 'EXP02', 'IA', 'https://picsum.photos/seed/exp2/600/400'),
            self::fake_course(803, '[FAKE] Visualización de datos en 1 minuto', '', 15, 3, false, 'EXP03', 'Datos', 'https://picsum.photos/seed/exp3/600/400'),
            self::fake_course(804, '[FAKE] ChatGPT para equipos de ventas', '', 73, 5, false, 'EXP04', 'IA', 'https://picsum.photos/seed/exp4/600/400'),
        ];
    }

    /**
     * Fake: recommended based on completed courses.
     * @return array
     */
    public static function get_fake_rec_completed(): array {
        return [
            self::fake_course(811, '[FAKE] Liderazgo en entornos híbridos', '', 48, 8, false, 'CMP01', 'Liderazgo', 'https://picsum.photos/seed/cmp1/600/400'),
            self::fake_course(812, '[FAKE] Negociación avanzada', '', 31, 6, false, 'CMP02', 'Habilidades', 'https://picsum.photos/seed/cmp2/600/400'),
            self::fake_course(813, '[FAKE] Gestión del cambio organizacional', '', 22, 7, false, 'CMP03', 'Management', 'https://picsum.photos/seed/cmp3/600/400'),
            self::fake_course(814, '[FAKE] Comunicación ejecutiva', '', 56, 5, false, 'CMP04', 'Comunicación', 'https://picsum.photos/seed/cmp4/600/400'),
        ];
    }

    /**
     * Fake: video content items.
     * @return array
     */
    public static function get_fake_videos(): array {
        return [
            ['id' => 901, 'title' => '[FAKE] Cómo dar feedback efectivo en 2 min', 'duration' => 2, 'author' => 'María López', 'image' => 'https://picsum.photos/seed/vid1/600/340', 'url' => '#', 'categoryname' => 'Liderazgo'],
            ['id' => 902, 'title' => '[FAKE] Las 5 claves del trabajo remoto', 'duration' => 4, 'author' => 'Carlos Ruiz', 'image' => 'https://picsum.photos/seed/vid2/600/340', 'url' => '#', 'categoryname' => 'Productividad'],
            ['id' => 903, 'title' => '[FAKE] Excel: atajos que nadie te enseñó', 'duration' => 3, 'author' => 'Ana García', 'image' => 'https://picsum.photos/seed/vid3/600/340', 'url' => '#', 'categoryname' => 'Digital'],
            ['id' => 904, 'title' => '[FAKE] Mindfulness en la oficina', 'duration' => 5, 'author' => 'Laura Sánchez', 'image' => 'https://picsum.photos/seed/vid4/600/340', 'url' => '#', 'categoryname' => 'Bienestar'],
        ];
    }

    /**
     * Fake: activity/exercise items.
     * @return array
     */
    public static function get_fake_activities(): array {
        return [
            ['id' => 951, 'title' => '[FAKE] Quiz: ¿Conoces las normas RGPD?', 'type' => 'Quiz', 'duration' => 5, 'image' => 'https://picsum.photos/seed/act1/600/340', 'url' => '#', 'categoryname' => 'Legal'],
            ['id' => 952, 'title' => '[FAKE] Caso práctico: Plan de comunicación', 'type' => 'Caso práctico', 'duration' => 15, 'image' => 'https://picsum.photos/seed/act2/600/340', 'url' => '#', 'categoryname' => 'Comunicación'],
            ['id' => 953, 'title' => '[FAKE] Simulación: Negociación con clientes', 'type' => 'Simulación', 'duration' => 10, 'image' => 'https://picsum.photos/seed/act3/600/340', 'url' => '#', 'categoryname' => 'Ventas'],
            ['id' => 954, 'title' => '[FAKE] Reto: Crea tu dashboard en Power BI', 'type' => 'Reto', 'duration' => 20, 'image' => 'https://picsum.photos/seed/act4/600/340', 'url' => '#', 'categoryname' => 'Datos'],
        ];
    }

    /**
     * Fake: learning itineraries (paths grouping multiple courses).
     * @return array
     */
    public static function get_fake_itineraries(): array {
        return [
            ['id' => 1001, 'title' => '[FAKE] De cero a líder digital', 'coursecount' => 6, 'duration' => 45, 'image' => 'https://picsum.photos/seed/itn1/600/340', 'url' => '#', 'description' => 'Domina las herramientas digitales y lidera la transformación.'],
            ['id' => 1002, 'title' => '[FAKE] Especialista en IA generativa', 'coursecount' => 4, 'duration' => 30, 'image' => 'https://picsum.photos/seed/itn2/600/340', 'url' => '#', 'description' => 'Aprende a usar IA en tu día a día profesional.'],
            ['id' => 1003, 'title' => '[FAKE] Manager 360°', 'coursecount' => 8, 'duration' => 60, 'image' => 'https://picsum.photos/seed/itn3/600/340', 'url' => '#', 'description' => 'Liderazgo, comunicación, gestión de equipos y más.'],
            ['id' => 1004, 'title' => '[FAKE] Data-Driven Business', 'coursecount' => 5, 'duration' => 35, 'image' => 'https://picsum.photos/seed/itn4/600/340', 'url' => '#', 'description' => 'Toma decisiones basadas en datos reales.'],
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

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
 * Grades & Certificates page context builder.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\output;

defined('MOODLE_INTERNAL') || die();

class grades_certificates_renderer {

    /**
     * Build template context for the grades & certificates page.
     *
     * @return array
     */
    public function get_context(): array {
        global $USER, $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $userid = $USER->id;
        $courses = enrol_get_my_courses('*', 'fullname ASC');
        $hasiomadcert = file_exists($CFG->dirroot . '/mod/iomadcertificate');

        $coursesdata = [];
        $hascertificates = false;

        foreach ($courses as $course) {
            if (!$course->visible) {
                continue;
            }

            $courseid = $course->id;

            // Grade.
            $grade = '';
            $grademax = '';
            $percentage = 0;
            $hasgrade = false;

            $gradeitems = \grade_item::fetch_all(['courseid' => $courseid]);
            if (!empty($gradeitems)) {
                foreach ($gradeitems as $item) {
                    if ($item->itemtype === 'course') {
                        $gradeobj = $item->get_grade($userid, false);
                        if ($gradeobj && !is_null($gradeobj->finalgrade)) {
                            $grade = round($gradeobj->finalgrade, 1);
                            $grademax = round($item->grademax, 1);
                            $percentage = ($item->grademax > 0)
                                ? round(($gradeobj->finalgrade / $item->grademax) * 100)
                                : 0;
                            $hasgrade = true;
                        }
                        break;
                    }
                }
            }

            // Progress — trackable-only completion (forum/label excluded).
            // Falls back to grade percentage when no activities have completion
            // tracking enabled.
            $progress = \local_sm_graphics_plugin\gamification\completion_filter::course_progress_percentage(
                (int) $courseid, (int) $userid
            );
            if ($progress === 0 && $hasgrade && $grademax > 0) {
                $progress = $percentage;
            }

            // Duration hours.
            $hours = 0;
            $pricing = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
            if ($pricing && !empty($pricing->duration_hours)) {
                $hours = $pricing->duration_hours;
            }

            // Certificate availability — check regardless of iomadcertificate module,
            // since we can generate SmartMind certificates standalone.
            $hascertificate = $this->check_certificate_available($courseid, $userid);
            if ($hascertificate) {
                $hascertificates = true;
            }

            $downloadurl = (new \moodle_url('/local/sm_graphics_plugin/pages/download_certificate.php', [
                'courseid' => $courseid,
                'certlang' => 'es',
            ]))->out(false);

            // Certificate code and issue date.
            $certcode = '';
            $certdate = '';
            if ($hascertificate) {
                $certrec = $DB->get_record('local_smgp_cert_codes', [
                    'userid' => $userid,
                    'courseid' => $courseid,
                ]);
                if ($certrec) {
                    $certcode = $certrec->code;
                    $certdate = userdate($certrec->timecreated, '%d de %B de %Y');
                }
            }

            // Course image.
            $courseimage = '';
            $courselistobj = new \core_course_list_element($course);
            foreach ($courselistobj->get_course_overviewfiles() as $file) {
                if ($file->is_valid_image()) {
                    $courseimage = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                        null, $file->get_filepath(), $file->get_filename()
                    )->out(false);
                    break;
                }
            }

            $coursesdata[] = [
                'courseid'       => $courseid,
                'coursename'     => format_string($course->fullname),
                'courseimage'     => $courseimage,
                'hascourseimage'  => !empty($courseimage),
                'grade'          => $grade,
                'grademax'       => $grademax,
                'percentage'     => $percentage,
                'hasgrade'       => $hasgrade,
                'gradetext'      => $hasgrade ? $grade . ' / ' . $grademax : '',
                'progress'       => $progress,
                'hascertificate' => $hascertificate,
                'hours'          => $hours,
                'hashours'       => ($hours > 0),
                'downloadurl'    => $downloadurl,
                'certcode'       => $certcode,
                'certdate'       => $certdate,
            ];
        }

        $downloadallurl = (new \moodle_url('/local/sm_graphics_plugin/pages/download_certificate.php', [
            'all' => 1,
            'certlang' => 'es',
        ]))->out(false);

        $languages = [
            ['code' => 'es', 'label' => 'Español', 'selected' => true],
            ['code' => 'en', 'label' => 'English', 'selected' => false],
            ['code' => 'pt_br', 'label' => 'Português', 'selected' => false],
        ];

        return [
            'courses'          => $coursesdata,
            'hascourses'       => !empty($coursesdata),
            'hascertificates'  => $hascertificates,
            'hasiomadcert'     => $hasiomadcert,
            'downloadallurl'   => $downloadallurl,
            'languages'        => $languages,
            'username'         => fullname($USER),
        ];
    }

    /**
     * Check if a certificate is available for the user in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    private function check_certificate_available(int $courseid, int $userid): bool {
        global $DB;

        $dbman = $DB->get_manager();

        // 1. Check iomadcertificate_issues (certificate already issued).
        if ($dbman->table_exists('iomadcertificate_issues')) {
            $sql = "SELECT ci.id
                      FROM {iomadcertificate_issues} ci
                      JOIN {iomadcertificate} c ON c.id = ci.iomadcertificateid
                      JOIN {course_modules} cm ON cm.instance = c.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'iomadcertificate'
                     WHERE ci.userid = :userid AND c.course = :courseid
                     LIMIT 1";
            if ($DB->get_record_sql($sql, ['userid' => $userid, 'courseid' => $courseid])) {
                return true;
            }
        }

        // 2. Check local_iomad_track (IOMAD completion tracking).
        if ($dbman->table_exists('local_iomad_track')) {
            if ($DB->get_record_sql(
                "SELECT id FROM {local_iomad_track}
                  WHERE userid = :userid AND courseid = :courseid AND timecompleted > 0
                  LIMIT 1",
                ['userid' => $userid, 'courseid' => $courseid]
            )) {
                return true;
            }
        }

        // 3. Check course_completions (Moodle standard completion).
        if ($DB->get_record_sql(
            "SELECT id FROM {course_completions}
              WHERE userid = :userid AND course = :courseid AND timecompleted > 0
              LIMIT 1",
            ['userid' => $userid, 'courseid' => $courseid]
        )) {
            return true;
        }

        // 4. Fallback: check if course progress meets the custom completion percentage.
        // Uses the trackable-only helper so the threshold is evaluated against
        // the same activities the user navigates in the player.
        $progress = \local_sm_graphics_plugin\gamification\completion_filter::course_progress_percentage(
            (int) $courseid, (int) $userid
        );
        $pricing = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
        $threshold = ($pricing && isset($pricing->completion_percentage)) ? (int) $pricing->completion_percentage : 100;
        if ($progress >= $threshold) {
            return true;
        }

        return false;
    }
}

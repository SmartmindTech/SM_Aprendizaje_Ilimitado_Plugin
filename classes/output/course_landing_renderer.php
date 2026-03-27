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
 * Course landing page renderer — builds context for the landing page template.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds context for the course landing page template.
 */
class course_landing_renderer {

    /** @var array Activity modname to Lucide Icon class mapping. */
    private const ACTIVITY_ICONS = [
        'assign'     => 'icon-file-text',
        'quiz'       => 'icon-circle-help',
        'forum'      => 'icon-message-circle',
        'resource'   => 'icon-file',
        'url'        => 'icon-link',
        'page'       => 'icon-file-text',
        'book'       => 'icon-book-open',
        'folder'     => 'icon-folder',
        'label'      => 'icon-tag',
        'glossary'   => 'icon-notebook-text',
        'wiki'       => 'icon-book-open',
        'workshop'   => 'icon-users',
        'feedback'   => 'icon-message-square-text',
        'choice'     => 'icon-circle-check',
        'data'       => 'icon-database',
        'lesson'     => 'icon-graduation-cap',
        'scorm'      => 'icon-box',
        'survey'     => 'icon-clipboard-check',
        'chat'       => 'icon-message-circle',
        'lti'        => 'icon-external-link',
        'h5pactivity' => 'icon-circle-play',
        'bigbluebuttonbn' => 'icon-video',
    ];

    /**
     * Build the template context for the landing page.
     *
     * @param int $courseid Course ID.
     * @return array Template context.
     */
    public function get_context(int $courseid): array {
        global $DB, $CFG, $USER;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        // Build sections and activities.
        $modinfo = get_fast_modinfo($course);
        $sections = $this->build_sections($modinfo);

        // Count total activities across all sections.
        $totalactivities = 0;
        foreach ($sections as $section) {
            $totalactivities += $section['activity_count'];
        }

        // Duration from pricing table.
        $pricing = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
        $durationhours = $pricing ? (float)$pricing->duration_hours : 0;

        // Language — convert code to clean human-readable name.
        $langcode = !empty($course->lang) ? $course->lang : $CFG->lang;
        $langmap = [
            'es' => 'Español', 'es_mx' => 'Español', 'en' => 'English',
            'pt_br' => 'Português', 'pt' => 'Português', 'fr' => 'Français',
            'de' => 'Deutsch', 'it' => 'Italiano', 'ca' => 'Català',
        ];
        $language = $langmap[$langcode] ?? $langcode;

        // SEPE code from custom field.
        $sepecode = $pricing ? ($pricing->sepe_code ?? '') : '';

        // SmartMind code.
        $smartmindcode = $pricing ? ($pricing->smartmind_code ?? '') : '';

        // Course category from link table.
        $coursecategoryname = '';
        $catlink = $DB->get_record('local_smgp_course_category', ['courseid' => $courseid]);
        if ($catlink) {
            $cat = $DB->get_record('local_smgp_categories', ['id' => $catlink->categoryid]);
            if ($cat) {
                $coursecategoryname = format_string($cat->name);
            }
        }

        // Level and completion percentage.
        $level = $pricing ? ($pricing->level ?? 'beginner') : 'beginner';
        $levellabels = [
            'beginner' => get_string('level_beginner', 'local_sm_graphics_plugin'),
            'medium'   => get_string('level_medium', 'local_sm_graphics_plugin'),
            'advanced' => get_string('level_advanced', 'local_sm_graphics_plugin'),
        ];
        $levellabel = $levellabels[$level] ?? $levellabels['beginner'];
        $completionpct = $pricing ? (int)($pricing->completion_percentage ?? 100) : 100;

        // Enrolment / access check.
        $isenrolled = is_enrolled($coursecontext, $USER->id, '', true)
            || has_capability('moodle/course:update', $coursecontext);

        // Admin editing capability.
        $canedit = has_capability('moodle/course:update', $coursecontext);

        // Course image.
        $courseimageurl = $this->get_course_image_url($course);

        // Course summary.
        $summary = format_text($course->summary ?? '', $course->summaryformat ?? FORMAT_HTML, [
            'context' => $coursecontext,
        ]);

        // Enrolled user progress data.
        $progress = 0;
        $hasstarted = false;
        $nextactivityname = '';
        $nextactivityurl = '';
        $enrolledrealuser = is_enrolled($coursecontext, $USER->id, '', true);

        if ($enrolledrealuser) {
            // Progress percentage.
            $progressval = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
            if ($progressval !== null) {
                $progress = round($progressval);
                if ($progress > 0) {
                    $hasstarted = true;
                }
            }

            // Check if user has viewed any activity SINCE their current enrolment.
            if (!$hasstarted) {
                // Get the most recent enrolment start time.
                $enroltime = $DB->get_field_sql(
                    "SELECT MAX(ue.timecreated) FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE ue.userid = :uid AND e.courseid = :cid",
                    ['uid' => $USER->id, 'cid' => $courseid]
                );
                $enroltime = $enroltime ?: 0;

                $haslog = $DB->record_exists_sql(
                    "SELECT 1 FROM {logstore_standard_log}
                      WHERE userid = :uid AND courseid = :cid
                        AND action = 'viewed' AND target = 'course_module'
                        AND timecreated > :enroltime",
                    ['uid' => $USER->id, 'cid' => $courseid, 'enroltime' => $enroltime]
                );
                $hasstarted = $haslog;
            }

            // Find the last accessed activity (the furthest reached).
            // If it's incomplete, show it. Otherwise show the next one after it.
            $lastviewedcmid = $DB->get_field_sql(
                "SELECT contextinstanceid
                   FROM {logstore_standard_log}
                  WHERE courseid = :cid AND userid = :uid
                    AND action = 'viewed' AND target = 'course_module'
                 ORDER BY timecreated DESC
                 LIMIT 1",
                ['cid' => $courseid, 'uid' => $USER->id]
            );

            $modinfo = get_fast_modinfo($course);
            $completion = new \completion_info($course);

            if ($lastviewedcmid && isset($modinfo->cms[$lastviewedcmid])) {
                $lastcm = $modinfo->cms[$lastviewedcmid];
                $compdata = $completion->get_data($lastcm, true, $USER->id);

                if ($compdata->completionstate == COMPLETION_INCOMPLETE) {
                    // Last reached activity is not finished — show it.
                    $nextactivityname = format_string($lastcm->name);
                    $nextactivityurl = $lastcm->url ? $lastcm->url->out(false) : '';
                } else {
                    // Last reached is complete — find the next incomplete after it.
                    $foundlast = false;
                    foreach ($modinfo->get_cms() as $cm) {
                        if (!$cm->uservisible || $cm->modname === 'label') {
                            continue;
                        }
                        if ($cm->id == $lastviewedcmid) {
                            $foundlast = true;
                            continue;
                        }
                        if ($foundlast) {
                            $nextactivityname = format_string($cm->name);
                            $nextactivityurl = $cm->url ? $cm->url->out(false) : '';
                            break;
                        }
                    }
                }
            }

            // Fallback: first activity if nothing found.
            if (empty($nextactivityname)) {
                foreach ($modinfo->get_cms() as $cm) {
                    if ($cm->uservisible && $cm->modname !== 'label') {
                        $nextactivityname = format_string($cm->name);
                        $nextactivityurl = $cm->url ? $cm->url->out(false) : '';
                        break;
                    }
                }
            }
        }

        // Unenrol: handled via AJAX (our own web service), not a URL redirect.

        return [
            'courseid'           => $course->id,
            'coursename'         => format_string($course->fullname),
            'coursesummary'      => $summary,
            'hassummary'         => !empty(trim(strip_tags($summary))),
            'courseimageurl'      => $courseimageurl,
            'hasimage'           => !empty($courseimageurl),
            'sections'           => $sections,
            'section_count'      => count($sections),
            'total_activities'   => $totalactivities,
            'duration_hours'     => $durationhours,
            'has_duration'       => ($durationhours > 0),
            'language'           => $language,
            'sepe_code'          => $sepecode,
            'has_sepe'           => !empty($sepecode),
            'smartmind_code'     => $smartmindcode,
            'has_smartmind_code' => !empty($smartmindcode),
            'course_category'    => $coursecategoryname,
            'has_course_category'=> !empty($coursecategoryname),
            'is_enrolled'        => $isenrolled,
            'is_enrolled_real'   => $enrolledrealuser,
            'course_view_url'    => $this->build_course_view_url($course)->out(false),
            'level'              => $level,
            'level_label'        => $levellabel,
            'completion_pct'     => $completionpct,
            'canedit'            => $canedit,
            'edit_course_url'    => (new \moodle_url('/course/edit.php', ['id' => $course->id]))->out(false),
            // Enrolled user data.
            'progress'           => $progress,
            'has_started'        => $hasstarted,
            'next_activity_name' => $nextactivityname,
            'has_next_activity'  => !empty($nextactivityname),
        ];
    }

    /**
     * Build sections array with activities for the landing page.
     *
     * @param \course_modinfo $modinfo Course module info.
     * @return array Sections data.
     */
    private function build_sections(\course_modinfo $modinfo): array {
        global $DB;
        $sections = [];

        foreach ($modinfo->get_section_info_all() as $sectionnum => $sectioninfo) {
            if (!$sectioninfo->visible) {
                continue;
            }

            $sectionname = get_section_name($modinfo->get_course(), $sectioninfo);
            $activities = [];

            if (!empty($modinfo->sections[$sectionnum])) {
                foreach ($modinfo->sections[$sectionnum] as $cmid) {
                    $mod = $modinfo->cms[$cmid];
                    if (!$mod->uservisible || $mod->modname === 'label') {
                        continue;
                    }

                    $iconclass = self::ACTIVITY_ICONS[$mod->modname] ?? 'icon-file';

                    try {
                        $modtypelabel = get_string('pluginname', 'mod_' . $mod->modname);
                    } catch (\Exception $e) {
                        $modtypelabel = $mod->modname;
                    }

                    // Genially detection.
                    if ($mod->modname === 'url') {
                        $urlrec = $DB->get_record('url', ['id' => $mod->instance], 'externalurl');
                        if ($urlrec && (strpos($urlrec->externalurl, 'genial.ly') !== false
                            || strpos($urlrec->externalurl, 'genially.com') !== false)) {
                            $iconclass = 'icon-presentation';
                            $modtypelabel = 'Genially';
                        }
                    }

                    $activities[] = [
                        'cmid'         => $mod->id,
                        'name'         => format_string($mod->name),
                        'iconclass'    => $iconclass,
                        'modtypelabel' => $modtypelabel,
                    ];
                }
            }

            if (empty($activities) && $sectionnum > 0) {
                continue;
            }

            $sections[] = [
                'name'           => $sectionname,
                'number'         => $sectionnum,
                'activity_count' => count($activities),
                'activities'     => $activities,
                'hasactivities'  => !empty($activities),
            ];
        }

        return $sections;
    }

    /**
     * Get the course overview image URL.
     *
     * @param object $course Course record.
     * @return string Image URL or empty string.
     */
    private function get_course_image_url($course): string {
        $courseobj = new \core_course_list_element($course);
        foreach ($courseobj->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
        }
        return '';
    }

    /**
     * Build the course player URL, including the last viewed activity cmid if available.
     *
     * @param \stdClass $course
     * @return \moodle_url
     */
    private function build_course_view_url(\stdClass $course): \moodle_url {
        global $DB, $USER;

        $params = ['id' => $course->id, 'smgp_enter' => 1];

        // Find the last viewed activity in this course.
        $lastcm = $DB->get_record_sql(
            'SELECT contextinstanceid AS cmid
               FROM {logstore_standard_log}
              WHERE courseid = :courseid
                AND userid = :userid
                AND action = :action
                AND target = :target
           ORDER BY timecreated DESC
              LIMIT 1',
            [
                'courseid' => $course->id,
                'userid'   => $USER->id,
                'action'   => 'viewed',
                'target'   => 'course_module',
            ]
        );

        if ($lastcm && $lastcm->cmid) {
            $params['smgp_cmid'] = (int) $lastcm->cmid;
        }

        return new \moodle_url('/course/view.php', $params);
    }
}

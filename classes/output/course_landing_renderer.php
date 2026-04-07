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
        'assign'     => 'icon-pencil',
        'quiz'       => 'icon-circle-help',
        'forum'      => 'icon-message-circle',
        'resource'   => 'icon-file-up',
        'url'        => 'icon-link',
        'page'       => 'icon-file-text',
        'book'       => 'icon-book-open',
        'folder'     => 'icon-folder',
        'label'      => 'icon-tag',
        'glossary'   => 'icon-notebook-text',
        'wiki'       => 'icon-globe',
        'workshop'   => 'icon-users',
        'feedback'   => 'icon-message-square-text',
        'choice'     => 'icon-circle-check',
        'data'       => 'icon-database',
        'lesson'     => 'icon-graduation-cap',
        'scorm'      => 'icon-package',
        'survey'     => 'icon-clipboard-check',
        'chat'       => 'icon-send',
        'lti'        => 'icon-external-link',
        'h5pactivity' => 'icon-layers',
        'bigbluebuttonbn' => 'icon-video',
    ];

    /** @var array Activity modname to badge color class mapping. */
    private const TYPE_COLORS = [
        'scorm'      => 'green',
        'h5pactivity' => 'green',
        'choice'     => 'green',
        'quiz'       => 'red',
        'survey'     => 'red',
        'url'        => 'blue',
        'resource'   => 'blue',
        'page'       => 'blue',
        'folder'     => 'blue',
        'assign'     => 'yellow',
        'workshop'   => 'yellow',
        'lesson'     => 'orange',
        'forum'      => 'orange',
        'chat'       => 'orange',
        'feedback'   => 'purple',
        'data'       => 'purple',
        'glossary'   => 'purple',
        'lti'        => 'purple',
        'book'       => 'brown',
        'wiki'       => 'brown',
        'bigbluebuttonbn' => 'pink',
        'iomadcertificate' => 'pink',
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

        // Build sections and activities (need enrolled status + last viewed cmid first).
        // We'll set these after enrollment checks below and rebuild sections.
        $modinfo = get_fast_modinfo($course);

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

        // Course summary — try translated version first.
        $summarysource = $course->summary ?? '';
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_smgp_course_translations')) {
            $userlang = current_language();
            if (!in_array($userlang, ['en', 'es', 'pt_br'])) {
                if (strpos($userlang, 'es') === 0) {
                    $userlang = 'es';
                } else if (strpos($userlang, 'pt') === 0) {
                    $userlang = 'pt_br';
                } else {
                    $userlang = 'en';
                }
            }
            $trans = $DB->get_record('local_smgp_course_translations',
                ['courseid' => $courseid, 'lang' => $userlang], 'summary');
            if ($trans && !empty(trim($trans->summary))) {
                $summarysource = $trans->summary;
            }
        }
        $summary = format_text($summarysource, $course->summaryformat ?? FORMAT_HTML, [
            'context' => $coursecontext,
        ]);

        // Learning objectives from dedicated table (filtered by language with fallback).
        $objectives = [];
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_smgp_learning_objectives')) {
            $userlang = current_language();
            // Normalize language code.
            if (!in_array($userlang, ['en', 'es', 'pt_br'])) {
                if (strpos($userlang, 'es') === 0) {
                    $userlang = 'es';
                } else if (strpos($userlang, 'pt') === 0) {
                    $userlang = 'pt_br';
                } else {
                    $userlang = 'en';
                }
            }
            // Try user's language, then Spanish, then any.
            $objectiverecords = $DB->get_records('local_smgp_learning_objectives',
                ['courseid' => $courseid, 'lang' => $userlang], 'sortorder ASC', 'id, objective');
            if (empty($objectiverecords)) {
                $objectiverecords = $DB->get_records('local_smgp_learning_objectives',
                    ['courseid' => $courseid, 'lang' => 'es'], 'sortorder ASC', 'id, objective');
            }
            if (empty($objectiverecords)) {
                $objectiverecords = $DB->get_records('local_smgp_learning_objectives',
                    ['courseid' => $courseid], 'sortorder ASC', 'id, objective');
            }
            foreach ($objectiverecords as $obj) {
                $objectives[] = ['text' => format_string($obj->objective)];
            }
        }

        // Enrolled user progress data.
        $progress = 0;
        $hasstarted = false;
        $nextactivityname = '';
        $nextactivityurl = '';
        $lastviewedcmid = 0;
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
            $lastviewedcmid = (int)$DB->get_field_sql(
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

        // Build sections with completion + duration data (needs $enrolledrealuser and $lastviewedcmid).
        $sections = $this->build_sections($modinfo, $enrolledrealuser, $lastviewedcmid);

        // Count total activities, completed, durations, and build content type breakdown.
        $totalactivities = 0;
        $totalcompleted = 0;
        $totaldurationmin = 0;
        $remainingdurationmin = 0;
        $typecounts = [];
        foreach ($sections as $section) {
            $totalactivities += $section['activity_count'];
            $totalcompleted += $section['completed_count'];
            foreach ($section['activities'] as $act) {
                $label = $act['modtypelabel'];
                if (!isset($typecounts[$label])) {
                    $typecounts[$label] = ['type_label' => $label, 'type_count' => 0, 'type_color' => $act['type_color'], 'type_icon' => $act['iconclass']];
                }
                $typecounts[$label]['type_count']++;
                $totaldurationmin += $act['duration_minutes'];
                if (!$act['iscomplete']) {
                    $remainingdurationmin += $act['duration_minutes'];
                }
            }
        }
        $contenttypes = array_values($typecounts);

        // Progress ring SVG values (r=54, circumference=2*pi*54).
        $ringcircumference = 339.29;
        $ringoffset = $ringcircumference - ($ringcircumference * $progress / 100);

        return [
            'courseid'           => $course->id,
            'coursename'         => format_string($course->fullname),
            'dashboard_url'      => (new \moodle_url('/my/'))->out(false),
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
            'next_activity_url'  => $nextactivityurl,
            'has_next_activity'  => !empty($nextactivityname),
            // Progress ring SVG.
            'ring_circumference' => $ringcircumference,
            'ring_offset'        => round($ringoffset, 2),
            // Content type breakdown.
            'content_types'      => $contenttypes,
            'has_content_types'  => !empty($contenttypes),
            // Card stats.
            'total_completed'        => $totalcompleted,
            'remaining_duration_min' => $remainingdurationmin,
            'has_remaining_duration' => ($remainingdurationmin > 0),
            // Learning objectives.
            'objectives'         => $objectives,
            'has_objectives'     => !empty($objectives),
        ];
    }

    /**
     * Build sections array with activities for the landing page.
     *
     * @param \course_modinfo $modinfo Course module info.
     * @param bool $enrolledrealuser Whether the current user is truly enrolled.
     * @param int $lastviewedcmid The last viewed course module ID (for current activity indicator).
     * @return array Sections data.
     */
    private function build_sections(\course_modinfo $modinfo, bool $enrolledrealuser = false, int $lastviewedcmid = 0): array {
        global $DB, $USER;
        $sections = [];
        $course = $modinfo->get_course();

        // Load completion info for enrolled users.
        $completion = null;
        if ($enrolledrealuser) {
            $completion = new \completion_info($course);
        }

        // Load activity durations (table may not exist yet on older installs).
        $durations = [];
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_smgp_activity_duration')) {
            $durations = $DB->get_records('local_smgp_activity_duration', ['courseid' => $course->id], '', 'cmid, duration_minutes');
        }

        $sectionnumber = 0;
        foreach ($modinfo->get_section_info_all() as $sectionnum => $sectioninfo) {
            if (!$sectioninfo->visible) {
                continue;
            }

            $sectionname = get_section_name($course, $sectioninfo);
            $activities = [];
            $completedcount = 0;

            if (!empty($modinfo->sections[$sectionnum])) {
                foreach ($modinfo->sections[$sectionnum] as $cmid) {
                    $mod = $modinfo->cms[$cmid];
                    if (!$mod->uservisible || $mod->modname === 'label') {
                        continue;
                    }

                    $iconclass = self::ACTIVITY_ICONS[$mod->modname] ?? 'icon-file';
                    $typecolor = self::TYPE_COLORS[$mod->modname] ?? 'blue';

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
                            $typecolor = 'pink';
                        }
                    }

                    // Completion status.
                    $iscomplete = false;
                    $iscurrent = false;
                    if ($completion && $completion->is_enabled($mod) != COMPLETION_TRACKING_NONE) {
                        $data = $completion->get_data($mod, true, $USER->id);
                        $iscomplete = ($data->completionstate == COMPLETION_COMPLETE
                            || $data->completionstate == COMPLETION_COMPLETE_PASS);
                    }
                    if ($iscomplete) {
                        $completedcount++;
                    }
                    if (!$iscomplete && $lastviewedcmid == $mod->id) {
                        $iscurrent = true;
                    }

                    // Duration.
                    $durationmin = isset($durations[$mod->id]) ? (int)$durations[$mod->id]->duration_minutes : 0;

                    $activities[] = [
                        'cmid'             => $mod->id,
                        'name'             => format_string($mod->name),
                        'iconclass'        => $iconclass,
                        'modtypelabel'     => $modtypelabel,
                        'type_color'       => $typecolor,
                        'iscomplete'       => $iscomplete,
                        'iscurrent'        => $iscurrent,
                        'duration_minutes' => $durationmin,
                        'has_duration'     => ($durationmin > 0),
                        'edit_url'         => (new \moodle_url('/course/modedit.php', ['update' => $mod->id]))->out(false),
                    ];
                }
            }

            if (empty($activities)) {
                continue;
            }

            $sectionnumber++;
            $actcount = count($activities);
            $sectionprogress = ($actcount > 0) ? round(($completedcount / $actcount) * 100) : 0;

            $sections[] = [
                'name'             => $sectionname,
                'number'           => $sectionnum,
                'section_number'   => $sectionnumber,
                'activity_count'   => $actcount,
                'completed_count'  => $completedcount,
                'section_progress' => $sectionprogress,
                'activities'       => $activities,
                'hasactivities'    => !empty($activities),
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
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');

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

        $suffix = ($lastcm && $lastcm->cmid) ? ('?cmid=' . (int) $lastcm->cmid) : '';
        return local_sm_graphics_plugin_spa_url('courses/' . $course->id . '/player' . $suffix);
    }
}

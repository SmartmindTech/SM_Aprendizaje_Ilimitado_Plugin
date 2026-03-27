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
 * Course page renderer — Udemy-style course player layout.
 *
 * Gathers course data, sections, activities, stats, teachers, and progress
 * for the course_page.mustache template.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds context for the Udemy-style course page template.
 */
class course_page_renderer {

    /** @var array Activity modname → Lucide Icon class mapping. */
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
     * Render the course page HTML.
     *
     * @return string Rendered HTML.
     */
    public function render(): string {
        global $OUTPUT;

        $context = $this->get_context();
        if (empty($context)) {
            return '';
        }

        try {
            return $OUTPUT->render_from_template('local_sm_graphics_plugin/course_page', $context);
        } catch (\Exception $e) {
            debugging('Graphic Layer: course page render failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Build full template context.
     *
     * @return array Template context, or empty if not applicable.
     */
    public function get_context(): array {
        global $PAGE, $USER, $COURSE;

        $course = $PAGE->course ?? $COURSE;
        if (empty($course->id) || $course->id == SITEID) {
            return [];
        }

        $coursecontext = \context_course::instance($course->id);

        // Get course completion info.
        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);

        // Build sections and activities.
        $sectionsdata = $this->build_sections($modinfo, $completion, $USER->id);

        // Count totals.
        $totalactivities = 0;
        $completedactivities = 0;
        foreach ($sectionsdata as $section) {
            $totalactivities += $section['totalcount'];
            $completedactivities += $section['completedcount'];
        }

        // Overall progress.
        $progress = \core_completion\progress::get_course_progress_percentage(
            (object)['id' => $course->id],
            $USER->id
        );
        $overallprogress = round($progress ?? 0);

        // Stats.
        $teacherdata = $this->get_teachers($course->id, $coursecontext);
        $studentcount = count_enrolled_users($coursecontext) - count($teacherdata);
        if ($studentcount < 0) {
            $studentcount = 0;
        }
        $sectioncount = count($sectionsdata);

        // Capabilities for comments.
        $canpost = has_capability('local/sm_graphics_plugin:post_comments', $coursecontext);
        $candeleteany = has_capability('local/sm_graphics_plugin:delete_any_comment', $coursecontext);
        $isteacher = has_capability('moodle/course:update', $coursecontext);

        // Course summary.
        $summary = format_text($course->summary ?? '', $course->summaryformat ?? FORMAT_HTML, [
            'context' => $coursecontext,
        ]);

        // User grades.
        $gradesdata = $this->get_user_grades($course->id, $USER->id);

        // Dashboard URL (back link).
        $dashboardurl = (new \moodle_url('/my/'))->out(false);

        // Sections JSON for JS.
        $sectionsjson = json_encode($sectionsdata, JSON_UNESCAPED_UNICODE);

        return [
            'courseid'           => $course->id,
            'coursename'         => format_string($course->fullname),
            'courseshortname'    => format_string($course->shortname),
            'coursesummary'      => $summary,
            'hassummary'         => !empty(trim(strip_tags($summary))),
            'dashboardurl'       => $dashboardurl,
            'mycoursesurl'       => (new \moodle_url('/my/'))->out(false),
            'teachercount'       => count($teacherdata),
            'studentcount'       => $studentcount,
            'activitycount'      => $totalactivities,
            'sectioncount'       => $sectioncount,
            'overallprogress'    => $overallprogress,
            'completedactivities' => $completedactivities,
            'totalactivities'    => $totalactivities,
            'teachers'           => $teacherdata,
            'hasteachers'        => !empty($teacherdata),
            'sections'           => $sectionsdata,
            'sectionsjson'       => $sectionsjson,
            'grades'             => $gradesdata['items'],
            'hasgrades'          => !empty($gradesdata['items']),
            'coursetotal'        => $gradesdata['coursetotal'],
            'hascoursetotal'     => !empty($gradesdata['coursetotal']),
            'canpost'            => $canpost,
            'candeleteany'       => $candeleteany,
            'isteacher'          => $isteacher,
            'userid'             => $USER->id,
            'userfullname'       => fullname($USER),
        ];
    }

    /**
     * Build sections array with activities from get_fast_modinfo().
     *
     * @param \course_modinfo $modinfo Course module info.
     * @param \completion_info $completion Course completion info.
     * @param int $userid Current user ID.
     * @return array Sections data for the template.
     */
    private function build_sections(\course_modinfo $modinfo, \completion_info $completion, int $userid): array {
        global $DB;
        $sections = [];
        $activityindex = 0;

        foreach ($modinfo->get_section_info_all() as $sectionnum => $sectioninfo) {
            if (!$sectioninfo->visible) {
                continue;
            }

            $sectionname = get_section_name($modinfo->get_course(), $sectioninfo);
            $activities = [];
            $completedcount = 0;
            $totalcount = 0;

            if (!empty($modinfo->sections[$sectionnum])) {
                foreach ($modinfo->sections[$sectionnum] as $cmid) {
                    $mod = $modinfo->cms[$cmid];
                    if (!$mod->uservisible) {
                        continue;
                    }
                    // Skip labels and forums from the count but include them in the list.
                    $islabel = ($mod->modname === 'label');
                    $isforum = ($mod->modname === 'forum');

                    $iscomplete = false;
                    if ($completion->is_enabled($mod) != COMPLETION_TRACKING_NONE) {
                        $data = $completion->get_data($mod, true, $userid);
                        $iscomplete = ($data->completionstate == COMPLETION_COMPLETE ||
                                       $data->completionstate == COMPLETION_COMPLETE_PASS);
                    }

                    $url = $mod->url ? $mod->url->out(false) : '';
                    $iconclass = self::ACTIVITY_ICONS[$mod->modname] ?? 'icon-file';

                    // Translated module type name (uses Moodle's built-in module translations).
                    try {
                        $modtypelabel = get_string('pluginname', 'mod_' . $mod->modname);
                    } catch (\Exception $e) {
                        $modtypelabel = $mod->modname;
                    }

                    // Genially detection — override icon and label for Genially URLs.
                    if ($mod->modname === 'url') {
                        $urlrec = $DB->get_record('url', ['id' => $mod->instance], 'externalurl');
                        if ($urlrec && (strpos($urlrec->externalurl, 'genial.ly') !== false
                            || strpos($urlrec->externalurl, 'genially.com') !== false)) {
                            $iconclass = 'icon-presentation';
                            $modtypelabel = 'Genially';
                        }
                    }

                    $resourceindex = count($activities) + 1; // 1-based within section.

                    $activity = [
                        'cmid'              => $mod->id,
                        'name'              => format_string($mod->name),
                        'modname'           => $mod->modname,
                        'modtypelabel'      => $modtypelabel,
                        'url'               => $url,
                        'iconclass'         => $iconclass,
                        'iscomplete'        => $iscomplete,
                        'islabel'           => $islabel,
                        'isforum'           => $isforum,
                        'visible'           => $mod->visible,
                        'index'             => $activityindex,
                        'duration'          => 0,  // Populated async via JS.
                        'sectionname'       => $sectionname,
                        'sectionindex'      => $sectionnum,
                        'resourceindex'     => $resourceindex,
                        'sectiontotalcount' => 0,  // Backfilled below.
                    ];

                    $activities[] = $activity;
                    $activityindex++;

                    if (!$isforum) {
                        $totalcount++;
                        if ($iscomplete) {
                            $completedcount++;
                        }
                    }
                }
            }

            if (empty($activities) && $sectionnum > 0) {
                continue;
            }

            // Backfill sectiontotalcount on each activity.
            $actcount = count($activities);
            for ($i = 0; $i < $actcount; $i++) {
                $activities[$i]['sectiontotalcount'] = $actcount;
            }

            $sectionprogress = ($totalcount > 0) ? round(($completedcount / $totalcount) * 100) : 0;

            $sections[] = [
                'id'             => $sectioninfo->id,
                'number'         => $sectionnum,
                'name'           => $sectionname,
                'summary'        => format_text($sectioninfo->summary ?? '', $sectioninfo->summaryformat ?? FORMAT_HTML),
                'completedcount' => $completedcount,
                'totalcount'     => $totalcount,
                'progress'       => $sectionprogress,
                'activities'     => $activities,
                'hasactivities'  => !empty($activities),
                'isfirst'        => ($sectionnum === 0),
                'hastrackable'   => ($totalcount > 0),
            ];
        }

        return $sections;
    }

    /**
     * Get teachers for the course.
     *
     * @param int $courseid Course ID.
     * @param \context_course $context Course context.
     * @return array Teacher data [{fullname, role, avatarurl}].
     */
    private function get_teachers(int $courseid, \context_course $context): array {
        global $PAGE;

        $teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.*', 'u.firstname, u.lastname', 0, 50);
        $result = [];

        foreach ($teachers as $teacher) {
            $userpicture = new \user_picture($teacher);
            $userpicture->size = 1;
            $avatarurl = $userpicture->get_url($PAGE)->out(false);

            $roles = get_user_roles($context, $teacher->id, true);
            $rolename = '';
            foreach ($roles as $role) {
                if (in_array($role->shortname, ['editingteacher', 'teacher', 'manager'])) {
                    $rolename = role_get_name($role, $context);
                    break;
                }
            }
            if (empty($rolename)) {
                $rolename = get_string('course_page_teachers', 'local_sm_graphics_plugin');
            }

            $result[] = [
                'fullname'  => fullname($teacher),
                'role'      => $rolename,
                'avatarurl' => $avatarurl,
            ];
        }

        return $result;
    }

    /**
     * Get user grade items for the course.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return array {items: [{name, grade, grademax, percentage, activitytype}], coursetotal: string}
     */
    private function get_user_grades(int $courseid, int $userid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $result = ['items' => [], 'coursetotal' => ''];

        $gradeitems = \grade_item::fetch_all(['courseid' => $courseid]);
        if (empty($gradeitems)) {
            return $result;
        }

        foreach ($gradeitems as $item) {
            if ($item->itemtype === 'course') {
                // Course total.
                $grade = $item->get_grade($userid, false);
                if ($grade && !is_null($grade->finalgrade)) {
                    $result['coursetotal'] = round($grade->finalgrade, 1) . ' / ' . round($item->grademax, 1);
                }
                continue;
            }

            if ($item->itemtype === 'category') {
                continue;
            }

            $grade = $item->get_grade($userid, false);
            $gradevalue = '-';
            $percentage = 0;
            $hasgrade = false;

            if ($grade && !is_null($grade->finalgrade)) {
                $gradevalue = round($grade->finalgrade, 1) . ' / ' . round($item->grademax, 1);
                $percentage = ($item->grademax > 0) ? round(($grade->finalgrade / $item->grademax) * 100) : 0;
                $hasgrade = true;
            }

            $result['items'][] = [
                'name'         => format_string($item->itemname ?? $item->get_name()),
                'grade'        => $gradevalue,
                'grademax'     => round($item->grademax, 1),
                'percentage'   => $percentage,
                'hasgrade'     => $hasgrade,
                'activitytype' => $item->itemmodule ?? '',
            ];
        }

        return $result;
    }
}

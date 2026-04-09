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

namespace local_sm_graphics_plugin\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Materialise an in-memory course structure (sections + activities)
 * into real Moodle course_sections and course_modules rows.
 *
 * Shared between:
 *   - restore_execute.php  (restore wizard step 6)
 *   - update_course_full.php  (create-course page)
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_structure_builder {

    /**
     * Parse the wizard's course_structure_json and create sections +
     * activities in the given course.
     *
     * Each section with section_id == 0 is brand-new; existing sections
     * are looked up by section_id. Each activity with cmid == 0 is new;
     * existing activities are skipped. Layout-aware module creation
     * handles url, genially, page, label, resource, folder, imscp,
     * h5pactivity, scorm, and deferred-config types.
     *
     * Best-effort — one failed module never aborts the batch.
     *
     * @param int    $courseid           Target course ID.
     * @param string $structurejson      JSON array of section objects.
     * @param array  $deferredfailures   Passed by reference; receives
     *                                   entries for deferred types that
     *                                   could not be created blank.
     */
    public static function create(int $courseid, string $structurejson, array &$deferredfailures = []): void {
        global $CFG, $DB;

        $structure = json_decode($structurejson, true);
        if (!is_array($structure) || empty($structure)) {
            return;
        }

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        foreach ($structure as $sec) {
            $isnewsection = empty($sec['section_id']) || (int) $sec['section_id'] === 0;
            $sectionnumber = null;

            if ($isnewsection) {
                try {
                    $section = course_create_section($courseid, 0, true);
                    $sectionnumber = (int) $section->section;
                    if (!empty($sec['name'])) {
                        $DB->set_field('course_sections', 'name', (string) $sec['name'], ['id' => $section->id]);
                    }
                    \rebuild_course_cache($courseid, true);
                } catch (\Throwable $ignore) {
                    continue;
                }
            } else {
                $existing = $DB->get_record('course_sections', ['id' => (int) $sec['section_id']], 'id, section', IGNORE_MISSING);
                if ($existing) {
                    $sectionnumber = (int) $existing->section;
                }
            }

            if ($sectionnumber === null) {
                continue;
            }

            foreach ($sec['activities'] ?? [] as $act) {
                $isnewact = empty($act['cmid']) || (int) $act['cmid'] === 0;
                if (!$isnewact) {
                    continue;
                }
                $name = trim((string) ($act['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $modname     = (string) ($act['modname'] ?? 'label');
                $externalurl = trim((string) ($act['url'] ?? ''));
                $draftitemid = (int) ($act['draftitemid'] ?? 0);
                $introhtml   = (string) ($act['intro'] ?? '');
                $isdeferred  = !empty($act['deferred']);

                try {
                    self::create_module_for_activity(
                        $courseid, $sectionnumber, $modname, $name,
                        $externalurl, $draftitemid, $introhtml, $isdeferred
                    );
                } catch (\Throwable $e) {
                    if ($isdeferred) {
                        $deferredfailures[] = [
                            'modname' => $modname,
                            'name'    => $name,
                            'error'   => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        \rebuild_course_cache($courseid, true);
    }

    /**
     * Create a single Moodle module based on the activity's layout type.
     */
    private static function create_module_for_activity(
        int $courseid, int $sectionnumber, string $modname, string $name,
        string $externalurl, int $draftitemid, string $introhtml, bool $isdeferred
    ): void {
        if ($modname === 'genially' || ($modname === 'url' && $externalurl !== '')) {
            $moduleinfo = (object) [
                'modulename'  => 'url',
                'course'      => $courseid,
                'section'     => $sectionnumber,
                'visible'     => 1,
                'name'        => $name,
                'externalurl' => $externalurl,
                'display'     => $modname === 'genially' ? 1 : 0,
                'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else if ($modname === 'page') {
            $moduleinfo = (object) [
                'modulename'  => 'page',
                'course'      => $courseid,
                'section'     => $sectionnumber,
                'visible'     => 1,
                'name'        => $name,
                'page'        => ['text' => $introhtml, 'format' => FORMAT_HTML, 'itemid' => 0],
                'display'     => 0,
                'printheading' => 1,
                'printintro'  => 0,
                'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else if ($modname === 'label') {
            $labeltext = $introhtml !== '' ? $introhtml : $name;
            $moduleinfo = (object) [
                'modulename'  => 'label',
                'course'      => $courseid,
                'section'     => $sectionnumber,
                'visible'     => 1,
                'name'        => $name,
                'introeditor' => ['text' => $labeltext, 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else if ($modname === 'resource' && $draftitemid > 0) {
            $moduleinfo = (object) [
                'modulename'  => 'resource',
                'course'      => $courseid,
                'section'     => $sectionnumber,
                'visible'     => 1,
                'name'        => $name,
                'files'       => $draftitemid,
                'display'     => 0,
                'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else if ($modname === 'folder' && $draftitemid > 0) {
            $moduleinfo = (object) [
                'modulename'   => 'folder',
                'course'       => $courseid,
                'section'      => $sectionnumber,
                'visible'      => 1,
                'name'         => $name,
                'files'        => $draftitemid,
                'display'      => 0,
                'showexpanded' => 1,
                'introeditor'  => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else if ($modname === 'imscp' && $draftitemid > 0) {
            $moduleinfo = (object) [
                'modulename'  => 'imscp',
                'course'      => $courseid,
                'section'     => $sectionnumber,
                'visible'     => 1,
                'name'        => $name,
                'package'     => $draftitemid,
                'keepold'     => 1,
                'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else if ($modname === 'h5pactivity' && $draftitemid > 0) {
            $moduleinfo = (object) [
                'modulename'     => 'h5pactivity',
                'course'         => $courseid,
                'section'        => $sectionnumber,
                'visible'        => 1,
                'name'           => $name,
                'packagefile'    => $draftitemid,
                'grade'          => 100,
                'displayoptions' => 0,
                'enabletracking' => 1,
                'grademethod'    => 1,
                'reviewmode'     => 1,
                'introeditor'    => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else if ($modname === 'scorm' && $draftitemid > 0) {
            $moduleinfo = (object) [
                'modulename'     => 'scorm',
                'course'         => $courseid,
                'section'        => $sectionnumber,
                'visible'        => 1,
                'name'           => $name,
                'scormtype'      => 'local',
                'packagefile'    => $draftitemid,
                'popup'          => 0,
                'width'          => 100,
                'height'         => 600,
                'grademethod'    => 1,
                'maxgrade'       => 100,
                'whatgrade'      => 0,
                'displaycoursestructure' => 0,
                'hidetoc'        => 0,
                'hidenav'        => 0,
                'hidebrowse'     => 0,
                'forcecompleted' => 0,
                'forcenewattempt' => 0,
                'lastattemptlock' => 0,
                'auto'           => 0,
                'updatefreq'     => 0,
                'timeopen'       => 0,
                'timeclose'      => 0,
                'introeditor'    => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else if ($isdeferred) {
            $moduleinfo = (object) [
                'modulename'  => $modname,
                'course'      => $courseid,
                'section'     => $sectionnumber,
                'visible'     => 1,
                'name'        => $name,
                'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        } else {
            // Unknown — fall back to a label placeholder.
            $moduleinfo = (object) [
                'modulename'  => 'label',
                'course'      => $courseid,
                'section'     => $sectionnumber,
                'visible'     => 1,
                'introeditor' => ['text' => $name, 'format' => FORMAT_HTML, 'itemid' => 0],
            ];
            create_module($moduleinfo);
        }
    }
}

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
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * Phase 6 — Vue restore wizard: execute step.
 *
 * Given a backupid previously created by restore_prepare, creates a destination
 * course (new or existing) and runs restore_controller synchronously with
 * default restore settings. Returns the new course ID.
 *
 * Stages SmartMind metadata in $SESSION before executing so the course_restored
 * observer can pick it up on the new course.
 *
 * This is the "thin" synchronous version — suitable for small backups. Large
 * restores should be moved to an ad-hoc task in a future iteration.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_execute extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'backupid'     => new external_value(PARAM_ALPHANUMEXT, 'Backup ID from restore_prepare'),
            'categoryid'   => new external_value(PARAM_INT, 'Destination course category'),
            'fullname'     => new external_value(PARAM_TEXT, 'New course fullname', VALUE_DEFAULT, ''),
            'shortname'    => new external_value(PARAM_TEXT, 'New course shortname', VALUE_DEFAULT, ''),
            'startdate'    => new external_value(PARAM_INT, 'Course start date (unix ts, 0 = default)', VALUE_DEFAULT, 0),
            // Companies (CSV of IOMAD company IDs to assign the restored course to).
            'companyids'   => new external_value(PARAM_RAW, 'CSV of IOMAD company IDs', VALUE_DEFAULT, ''),
            // Step 3: settings the wizard collected (root-task plan settings).
            'settings_json' => new external_value(PARAM_RAW, 'JSON map of {settingname: value} overrides for the restore plan', VALUE_DEFAULT, '{}'),
            // Step 4: per-section / per-activity include + rename map.
            'course_structure_json' => new external_value(PARAM_RAW, 'JSON [{sectionKey, name, origName, included, userinfo, activities:[{actKey, name, origName, included, userinfo}]}]', VALUE_DEFAULT, '[]'),
            // SmartMind metadata (staged into SESSION for the course_restored observer).
            'smgp_fields_json' => new external_value(PARAM_RAW, 'JSON bundle of SmartMind fields', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(
        string $backupid,
        int $categoryid,
        string $fullname = '',
        string $shortname = '',
        int $startdate = 0,
        string $companyids = '',
        string $settings_json = '{}',
        string $course_structure_json = '[]',
        string $smgp_fields_json = '{}'
    ): array {
        global $CFG, $USER, $SESSION, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'backupid'              => $backupid,
            'categoryid'            => $categoryid,
            'fullname'              => $fullname,
            'shortname'             => $shortname,
            'startdate'             => $startdate,
            'companyids'            => $companyids,
            'settings_json'         => $settings_json,
            'course_structure_json' => $course_structure_json,
            'smgp_fields_json'      => $smgp_fields_json,
        ]);

        $catcontext = \context_coursecat::instance($params['categoryid']);
        self::validate_context($catcontext);
        require_capability('moodle/restore:restorecourse', $catcontext);
        require_capability('moodle/course:create', $catcontext);

        // Stage SmartMind fields for the course_restored observer.
        // The observer's apply_structure_changes() expects the wizard's
        // step-4 JSON under smgp_course_structure, so fold it into the
        // same fields bundle to keep a single round-trip into SESSION.
        $fields = json_decode($params['smgp_fields_json'], true);
        if (!is_array($fields)) {
            $fields = [];
        }
        if ($params['course_structure_json'] !== '' && $params['course_structure_json'] !== '[]') {
            $fields['smgp_course_structure'] = $params['course_structure_json'];
        }
        if (!empty($fields)) {
            $SESSION->smgp_restore_pending = [
                'courseid' => 0, // populated by observer
                'fields'   => $fields,
            ];
        }

        // Extract directory should exist from restore_prepare.
        $extractdir = $CFG->tempdir . '/backup/' . $params['backupid'];
        if (!is_dir($extractdir)) {
            return ['success' => false, 'error' => 'Backup not found', 'courseid' => 0, 'course_url' => ''];
        }

        // Placeholder course for the restore target.
        $placeholder = (object) [
            'category'  => $params['categoryid'],
            'fullname'  => !empty($params['fullname']) ? $params['fullname'] : 'Importing...',
            'shortname' => !empty($params['shortname']) ? $params['shortname'] : 'smgp_tmp_' . time(),
            'summary'   => '',
            'summaryformat' => FORMAT_HTML,
        ];
        $newcourse = create_course($placeholder);

        $sectionsrestored = 0;
        $activitiesrestored = 0;

        try {
            $controller = new \restore_controller(
                $params['backupid'],
                $newcourse->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_EXISTING_DELETING
            );

            // --- Step 3 settings overrides ---
            // Walk every root-task setting and apply matching values from
            // settings_json. Locked / non-overridable settings throw on
            // set_value() — swallow those, the rest are best-effort.
            $settingoverrides = json_decode($params['settings_json'], true);
            if (is_array($settingoverrides) && !empty($settingoverrides)) {
                $plan = $controller->get_plan();
                foreach ($plan->get_tasks() as $task) {
                    if (!($task instanceof \restore_root_task)) {
                        continue;
                    }
                    foreach ($task->get_settings() as $setting) {
                        $name = $setting->get_name();
                        if (array_key_exists($name, $settingoverrides)) {
                            try {
                                $setting->set_value($settingoverrides[$name]);
                            } catch (\Throwable $ignore) {
                                // Locked / dependent settings — skip silently.
                            }
                        }
                    }
                }
            }

            // --- Step 4 structure overrides (per-section / per-activity) ---
            // The wizard sends a flat JSON list with sectionKey/actKey strings
            // matching Moodle's setting names; apply them as include + userinfo
            // toggles on the matching restore_section_task / restore_activity_task.
            $structure = json_decode($params['course_structure_json'], true);
            if (is_array($structure) && !empty($structure)) {
                $sectionoverrides = [];
                $activityoverrides = [];
                foreach ($structure as $sec) {
                    if (!empty($sec['sectionKey'])) {
                        $sectionoverrides[$sec['sectionKey']] = [
                            'included' => !empty($sec['included']),
                            'userinfo' => !empty($sec['userinfo']),
                        ];
                    }
                    foreach ($sec['activities'] ?? [] as $act) {
                        if (!empty($act['actKey'])) {
                            $activityoverrides[$act['actKey']] = [
                                'included' => !empty($act['included']),
                                'userinfo' => !empty($act['userinfo']),
                            ];
                        }
                    }
                }

                $plan = $controller->get_plan();
                foreach ($plan->get_tasks() as $task) {
                    foreach ($task->get_settings() as $setting) {
                        $name = $setting->get_name();
                        // Section include / userinfo.
                        if (isset($sectionoverrides[$name])) {
                            try { $setting->set_value($sectionoverrides[$name]['included'] ? 1 : 0); }
                            catch (\Throwable $ignore) {}
                        }
                        // Activity include / userinfo.
                        if (isset($activityoverrides[$name])) {
                            try { $setting->set_value($activityoverrides[$name]['included'] ? 1 : 0); }
                            catch (\Throwable $ignore) {}
                        }
                    }
                }
            }

            $controller->execute_precheck();
            $controller->execute_plan();
            $controller->destroy();
        } catch (\Throwable $e) {
            return [
                'success'            => false,
                'error'              => $e->getMessage(),
                'courseid'           => (int) $newcourse->id,
                'course_url'         => '',
                'companies_assigned' => 0,
                'sections_restored'  => 0,
                'activities_restored' => 0,
                'sp_extras_applied'  => 0,
                'deferred_failures'  => [],
            ];
        }

        // --- IOMAD company assignment ---
        // Each id in companyids gets a row in {company_course} so the IOMAD
        // company managers see the restored course in their dashboards.
        $companiesassigned = 0;
        $companycsv = trim($params['companyids']);
        if ($companycsv !== '' && $DB->get_manager()->table_exists('company_course')) {
            $ids = array_filter(array_map('intval', explode(',', $companycsv)));
            foreach ($ids as $cid) {
                if ($cid <= 0) {
                    continue;
                }
                if (!$DB->record_exists('company_course', ['companyid' => $cid, 'courseid' => $newcourse->id])) {
                    $DB->insert_record('company_course', (object) [
                        'companyid' => $cid,
                        'courseid'  => $newcourse->id,
                        'departmentid' => 0,
                    ]);
                }
                $companiesassigned++;
            }
        }

        // --- Course-level overrides (fullname / shortname / startdate) ---
        // The placeholder course was created with the wizard's name, but the
        // restore plan typically rewrites it to the backup's original name.
        // Reapply the wizard values + startdate after restore so the saved
        // course matches what the admin chose.
        $courseupdates = [];
        if (!empty($params['fullname']))  { $courseupdates['fullname']  = $params['fullname']; }
        if (!empty($params['shortname'])) { $courseupdates['shortname'] = $params['shortname']; }
        if ($params['startdate'] > 0)     { $courseupdates['startdate'] = $params['startdate']; }
        if (!empty($courseupdates)) {
            $courseupdates['id'] = $newcourse->id;
            try { update_course((object) $courseupdates); }
            catch (\Throwable $ignore) { /* best-effort */ }
        }

        // --- Apply new sections / new activities the wizard added in step 4 ---
        // Anything in course_structure_json with section_id == 0 is a brand-new
        // section the admin added; section_id > 0 with cmid == 0 inside the
        // activities[] is a brand-new activity. Layout-aware create paths live
        // in create_new_structure_items(). Deferred-config failures bubble up
        // so the wizard's complete step can surface them.
        $deferredfailures = [];
        self::create_new_structure_items($newcourse->id, $params['course_structure_json'], $deferredfailures);

        // --- Apply SharePoint extras the admin dragged onto sections ---
        // $SESSION->smgp_sp_manifest was stashed by sharepoint_prepare_restore;
        // the wizard forwards the subset the admin actually dropped via
        // course_structure_json[*].spExtras. For each section, build a
        // filtered manifest and run the normal course_importer pipeline.
        $spextrasapplied = 0;
        $spextraslogs = [];
        if (!empty($SESSION->smgp_sp_manifest) && is_array($SESSION->smgp_sp_manifest)) {
            require_once($CFG->dirroot . '/local/sm_graphics_plugin/classes/sharepoint/course_importer.php');
            $manifest = $SESSION->smgp_sp_manifest;
            $structure = json_decode($params['course_structure_json'], true) ?: [];
            foreach ($structure as $sec) {
                $drops = $sec['spExtras'] ?? [];
                if (empty($drops)) {
                    continue;
                }
                // Resolve the section number — same logic as
                // create_new_structure_items for existing sections;
                // brand-new sections weren't materialised above if the
                // admin didn't also add activities, so we re-query by
                // matching the wizard's section_id when present.
                $sectionnumber = null;
                if (!empty($sec['section_id']) && (int) $sec['section_id'] > 0) {
                    $existing = $DB->get_record('course_sections', ['id' => (int) $sec['section_id']], 'id, section', IGNORE_MISSING);
                    if ($existing) {
                        $sectionnumber = (int) $existing->section;
                    }
                }
                $subset = self::filter_manifest_for_drops($manifest, $drops);
                if (empty($subset)) {
                    continue;
                }
                try {
                    $log = \local_sm_graphics_plugin\sharepoint\course_importer::apply_manifest_subset(
                        $newcourse->id,
                        $subset,
                        $sectionnumber,
                    );
                    $spextrasapplied += (int) ($log['applied'] ?? 0);
                    if (!empty($log['messages'])) {
                        $spextraslogs = array_merge($spextraslogs, $log['messages']);
                    }
                } catch (\Throwable $e) {
                    $spextraslogs[] = 'SP extras error: ' . $e->getMessage();
                }
            }
            // Clear the session manifest so a subsequent restore from the
            // same session doesn't re-apply stale files.
            unset($SESSION->smgp_sp_manifest);
        }

        // --- Run Gemini translations synchronously so they are ready
        //     by the time the admin opens the landing page. The observer
        //     path is async (fires on the event) and races the redirect. ---
        self::translate_smgp_content($newcourse->id, $params['smgp_fields_json']);

        // --- Section / activity counts for the wizard's complete step ---
        $sectionsrestored = (int) $DB->count_records_select(
            'course_sections',
            'course = :cid AND section > 0',
            ['cid' => $newcourse->id]
        );
        $activitiesrestored = (int) $DB->count_records('course_modules', ['course' => $newcourse->id]);

        $courseurl = (new \moodle_url('/local/sm_graphics_plugin/pages/spa.php'))->out(false)
                     . '#/courses/' . $newcourse->id . '/landing';

        return [
            'success'             => true,
            'error'               => '',
            'courseid'            => (int) $newcourse->id,
            'course_url'          => $courseurl,
            'companies_assigned'  => $companiesassigned,
            'sections_restored'   => $sectionsrestored,
            'activities_restored' => $activitiesrestored,
            'sp_extras_applied'   => $spextrasapplied,
            'deferred_failures'   => $deferredfailures,
        ];
    }

    /**
     * Build a manifest subset containing only the files the admin dropped
     * into a specific section. Matches by `name` against each manifest
     * bucket (scorm / pdf / documents / evaluations_*). Returns the same
     * shape course_importer expects.
     */
    private static function filter_manifest_for_drops(array $manifest, array $drops): array {
        $wanted = [];
        foreach ($drops as $d) {
            $wanted[(string) ($d['type'] ?? '') . '::' . (string) ($d['name'] ?? '')] = true;
        }
        $out = [];
        foreach (['scorm', 'pdf', 'documents', 'evaluations_aiken', 'evaluations_gift'] as $bucket) {
            if (empty($manifest[$bucket]) || !is_array($manifest[$bucket])) {
                continue;
            }
            $out[$bucket] = [];
            foreach ($manifest[$bucket] as $f) {
                $key = $bucket . '::' . (string) ($f['name'] ?? '');
                if (isset($wanted[$key])) {
                    $out[$bucket][] = $f;
                }
            }
            if (empty($out[$bucket])) {
                unset($out[$bucket]);
            }
        }
        // Preserve site/drive ids the downloader needs.
        foreach (['site_id', 'drive_id', 'folder_item_id'] as $k) {
            if (isset($manifest[$k])) {
                $out[$k] = $manifest[$k];
            }
        }
        return $out;
    }

    /**
     * Walk the wizard's course_structure_json and materialise any items the
     * admin added by hand: brand-new sections (section_id == 0) and
     * brand-new activities (cmid == 0). New activities are created as
     * mod_label instances using the activity name as the label content.
     *
     * Best-effort — failures are swallowed so a partial structure doesn't
     * break the whole restore.
     */
    private static function create_new_structure_items(int $courseid, string $structurejson, array &$deferredfailures = []): void {
        global $CFG, $DB;

        $structure = json_decode($structurejson, true);
        if (!is_array($structure) || empty($structure)) {
            return;
        }

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        $course = get_course($courseid);

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
                // Existing section — find its section number from section_id
                // (the wizard sends section_number too, but we re-resolve from
                // the DB to be safe after restore plan side-effects).
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
                $modname = (string) ($act['modname'] ?? 'label');
                $externalurl = trim((string) ($act['url'] ?? ''));
                $draftitemid = (int) ($act['draftitemid'] ?? 0);
                $introhtml = (string) ($act['intro'] ?? '');
                $isdeferred = !empty($act['deferred']);

                try {
                    if ($modname === 'genially' || ($modname === 'url' && $externalurl !== '')) {
                        // mod_url (Genially = embed display, plain URL = auto).
                        $moduleinfo = (object) [
                            'modulename'   => 'url',
                            'course'       => $courseid,
                            'section'      => $sectionnumber,
                            'visible'      => 1,
                            'name'         => $name,
                            'externalurl'  => $externalurl,
                            'display'      => $modname === 'genially' ? 1 : 0,
                            'introeditor'  => [
                                'text'   => '',
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    } else if ($modname === 'page') {
                        // mod_page — content is the rich-text body.
                        $moduleinfo = (object) [
                            'modulename'   => 'page',
                            'course'       => $courseid,
                            'section'      => $sectionnumber,
                            'visible'      => 1,
                            'name'         => $name,
                            'page'         => [
                                'text'   => $introhtml,
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                            'display'       => 0,
                            'printheading'  => 1,
                            'printintro'    => 0,
                            'introeditor'   => [
                                'text'   => '',
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    } else if ($modname === 'label') {
                        // mod_label — intro carries the rich-text body
                        // (or falls back to the name for legacy flows).
                        $labeltext = $introhtml !== '' ? $introhtml : $name;
                        $moduleinfo = (object) [
                            'modulename'   => 'label',
                            'course'       => $courseid,
                            'section'      => $sectionnumber,
                            'visible'      => 1,
                            'name'         => $name,
                            'introeditor'  => [
                                'text'   => $labeltext,
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    } else if ($modname === 'resource' && $draftitemid > 0) {
                        $moduleinfo = (object) [
                            'modulename'   => 'resource',
                            'course'       => $courseid,
                            'section'      => $sectionnumber,
                            'visible'      => 1,
                            'name'         => $name,
                            'files'        => $draftitemid,
                            'display'      => 0,
                            'introeditor'  => [
                                'text'   => '',
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
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
                            'introeditor'  => [
                                'text'   => '',
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    } else if ($modname === 'imscp' && $draftitemid > 0) {
                        $moduleinfo = (object) [
                            'modulename'   => 'imscp',
                            'course'       => $courseid,
                            'section'      => $sectionnumber,
                            'visible'      => 1,
                            'name'         => $name,
                            'package'      => $draftitemid,
                            'keepold'      => 1,
                            'introeditor'  => [
                                'text'   => '',
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    } else if ($modname === 'h5pactivity' && $draftitemid > 0) {
                        $moduleinfo = (object) [
                            'modulename'        => 'h5pactivity',
                            'course'            => $courseid,
                            'section'           => $sectionnumber,
                            'visible'           => 1,
                            'name'              => $name,
                            'packagefile'       => $draftitemid,
                            'grade'             => 100,
                            'displayoptions'    => 0,
                            'enabletracking'    => 1,
                            'grademethod'       => 1,
                            'reviewmode'        => 1,
                            'introeditor'       => [
                                'text'   => '',
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    } else if ($modname === 'scorm' && $draftitemid > 0) {
                        // Mirrors course_importer::configure_scorm_activities payload.
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
                            'introeditor'    => [
                                'text'   => '',
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    } else if ($isdeferred) {
                        // Deferred-config types: create blank with just
                        // the name so Moodle's native mod form can finish
                        // it. create_module() throws with a clear error
                        // if the mod requires extra fields → caught by
                        // the outer try and recorded in deferred_failures.
                        $moduleinfo = (object) [
                            'modulename'  => $modname,
                            'course'      => $courseid,
                            'section'     => $sectionnumber,
                            'visible'     => 1,
                            'name'        => $name,
                            'introeditor' => [
                                'text'   => '',
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    } else {
                        // Unknown / unhandled: fall back to a label so the
                        // activity still shows up in the right section and
                        // the admin can finish it by hand post-restore.
                        $moduleinfo = (object) [
                            'modulename'  => 'label',
                            'course'      => $courseid,
                            'section'     => $sectionnumber,
                            'visible'     => 1,
                            'introeditor' => [
                                'text'   => $name,
                                'format' => FORMAT_HTML,
                                'itemid' => 0,
                            ],
                        ];
                        create_module($moduleinfo);
                    }
                } catch (\Throwable $e) {
                    // Record failed deferred types so the wizard's step 7
                    // can surface which activities didn't make it; other
                    // failures are silent best-effort.
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
     * Run Gemini translations for the description + objectives inline so
     * the translations are in the DB before we return the response. This
     * replaces the async observer path for the restore wizard.
     */
    private static function translate_smgp_content(int $courseid, string $fieldsjson): void {
        global $DB;

        $fields = json_decode($fieldsjson, true);
        if (!is_array($fields) || empty($fields)) {
            return;
        }

        $now = time();
        $dbman = $DB->get_manager();
        $alllanguages = ['en', 'es', 'pt_br'];

        // --- Description → course.summary + translations ---
        $desc = $fields['smgp_description'] ?? '';
        if (!empty($desc) && trim(strip_tags($desc)) !== '') {
            // Write into course.summary (source language).
            $course = $DB->get_record('course', ['id' => $courseid]);
            if ($course) {
                $course->summary = $desc;
                $course->timemodified = $now;
                $DB->update_record('course', $course);
            }
            // Translate to other languages.
            if ($dbman->table_exists('local_smgp_course_translations')) {
                $sourcelang = 'es';
                $plaintext = strip_tags($desc);
                foreach (array_diff($alllanguages, [$sourcelang]) as $targetlang) {
                    try {
                        $translated = \local_sm_graphics_plugin\gemini::translate(
                            $plaintext, $sourcelang, $targetlang
                        );
                        if (!$translated) {
                            continue;
                        }
                        $existing = $DB->get_record('local_smgp_course_translations',
                            ['courseid' => $courseid, 'lang' => $targetlang]);
                        if ($existing) {
                            $existing->summary = $translated;
                            $existing->timemodified = $now;
                            $DB->update_record('local_smgp_course_translations', $existing);
                        } else {
                            $DB->insert_record('local_smgp_course_translations', (object) [
                                'courseid'    => $courseid,
                                'lang'        => $targetlang,
                                'summary'     => $translated,
                                'timecreated' => $now,
                                'timemodified' => $now,
                            ]);
                        }
                    } catch (\Throwable $ignore) {
                        // Best-effort — skip failed translations.
                    }
                }
            }
        }

        // --- Objectives → local_smgp_learning_objectives + translations ---
        $objectivesjson = $fields['smgp_objectives_data'] ?? '[]';
        if ($objectivesjson && $objectivesjson !== '[]' && $dbman->table_exists('local_smgp_learning_objectives')) {
            $objectives = json_decode($objectivesjson, true);
            if (is_array($objectives) && !empty($objectives)) {
                $sourcelang = 'es';
                $DB->delete_records('local_smgp_learning_objectives', ['courseid' => $courseid]);
                $cleantexts = [];
                foreach ($objectives as $text) {
                    $text = trim($text);
                    if ($text === '') {
                        continue;
                    }
                    $clean = clean_param($text, PARAM_TEXT);
                    $cleantexts[] = $clean;
                    $DB->insert_record('local_smgp_learning_objectives', (object) [
                        'courseid'    => $courseid,
                        'objective'   => $clean,
                        'sortorder'   => count($cleantexts) - 1,
                        'lang'        => $sourcelang,
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                }
                if (!empty($cleantexts)) {
                    foreach (array_diff($alllanguages, [$sourcelang]) as $targetlang) {
                        try {
                            $translated = \local_sm_graphics_plugin\gemini::translate_batch(
                                $cleantexts, $sourcelang, $targetlang
                            );
                            if (!$translated) {
                                continue;
                            }
                            foreach ($translated as $i => $ttext) {
                                $DB->insert_record('local_smgp_learning_objectives', (object) [
                                    'courseid'    => $courseid,
                                    'objective'   => clean_param(trim($ttext), PARAM_TEXT),
                                    'sortorder'   => $i,
                                    'lang'        => $targetlang,
                                    'timecreated' => $now,
                                    'timemodified' => $now,
                                ]);
                            }
                        } catch (\Throwable $ignore) {
                            // Best-effort.
                        }
                    }
                }
            }
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'             => new external_value(PARAM_BOOL, 'Restore succeeded'),
            'error'               => new external_value(PARAM_TEXT, 'Error message if failed'),
            'courseid'            => new external_value(PARAM_INT, 'Restored course ID'),
            'course_url'          => new external_value(PARAM_RAW, 'Redirect URL (SPA landing)'),
            'companies_assigned'  => new external_value(PARAM_INT, 'Number of IOMAD companies the course was assigned to'),
            'sections_restored'   => new external_value(PARAM_INT, 'Number of sections actually present in the restored course'),
            'activities_restored' => new external_value(PARAM_INT, 'Number of activities actually present in the restored course'),
            'sp_extras_applied'   => new external_value(PARAM_INT, 'Number of SharePoint extras (SCORM/PDF/question files) successfully applied', VALUE_DEFAULT, 0),
            'deferred_failures'   => new \external_multiple_structure(
                new external_single_structure([
                    'modname' => new external_value(PARAM_ALPHAEXT, 'Moodle module name'),
                    'name'    => new external_value(PARAM_TEXT, 'Activity name the admin typed'),
                    'error'   => new external_value(PARAM_TEXT, 'Reason the blank module could not be created'),
                ]),
                'Deferred-config activities the wizard could not create blank',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}

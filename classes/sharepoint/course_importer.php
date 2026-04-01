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

namespace local_sm_graphics_plugin\sharepoint;

defined('MOODLE_INTERNAL') || die();

/**
 * Orchestrates importing a course from a SharePoint manifest.
 *
 * Steps:
 *   1. Restore MBZ as a new course.
 *   2. Configure SCORM activities with external package URL.
 *   3. Create URL resources for PDFs and documents.
 *   4. Import AIKEN/GIFT evaluations into the question bank.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_importer {

    /** SCORM auto-update frequency: every day. */
    private const SCORM_UPDATE_EVERYDAY = 3;

    /**
     * Execute a full course import from a SharePoint manifest.
     *
     * @param array $manifest The manifest from course_analyzer::analyze().
     * @param int $categoryid Target Moodle course category ID.
     * @return array {success: bool, courseid: int, course_url: string, log: string[]}
     */
    public static function import(array $manifest, int $categoryid): array {
        global $CFG;

        \core_php_time_limit::raise(300);

        $log = [];
        $courseid = 0;

        try {
            // Save site/drive IDs for the download proxy.
            set_config('sp_last_site_id', $manifest['site_id'], 'local_sm_graphics_plugin');
            set_config('sp_last_drive_id', $manifest['drive_id'], 'local_sm_graphics_plugin');

            // Step 1: Restore MBZ.
            if (!empty($manifest['mbz'])) {
                $log[] = get_string('courseloader_step_mbz', 'local_sm_graphics_plugin');
                $courseid = self::restore_mbz($manifest, $categoryid);
                if ($courseid) {
                    $log[] = "  OK - Curso creado (ID: {$courseid}).";
                } else {
                    $log[] = "  ERROR - No se pudo restaurar el MBZ.";
                    return ['success' => false, 'courseid' => 0, 'course_url' => '', 'log' => $log];
                }
            } else {
                $log[] = "SKIP - No hay MBZ, no se puede crear el curso.";
                return ['success' => false, 'courseid' => 0, 'course_url' => '', 'log' => $log];
            }

            // Step 2: Configure SCORM.
            if (!empty($manifest['scorm'])) {
                $log[] = get_string('courseloader_step_scorm', 'local_sm_graphics_plugin');
                $scormlog = self::configure_scorm_activities($courseid, $manifest);
                $log = array_merge($log, $scormlog);
            }

            // Step 3: Create URL resources for PDFs and documents.
            $resources = array_merge($manifest['pdf'] ?? [], $manifest['documents'] ?? []);
            if (!empty($resources)) {
                $log[] = get_string('courseloader_step_resources', 'local_sm_graphics_plugin');
                $reslog = self::create_url_resources($courseid, $manifest);
                $log = array_merge($log, $reslog);
            }

            // Step 4: Import evaluations.
            $evals = array_merge($manifest['evaluations_aiken'] ?? [], $manifest['evaluations_gift'] ?? []);
            if (!empty($evals)) {
                $log[] = get_string('courseloader_step_eval', 'local_sm_graphics_plugin');
                $evallog = self::import_evaluations($courseid, $manifest);
                $log = array_merge($log, $evallog);
            }

            $courseurl = $CFG->wwwroot . '/course/view.php?id=' . $courseid;

            return [
                'success'    => true,
                'courseid'   => $courseid,
                'course_url' => $courseurl,
                'log'        => $log,
            ];

        } catch (\Exception $e) {
            $log[] = "EXCEPTION: " . $e->getMessage();
            return [
                'success'    => false,
                'courseid'   => $courseid,
                'course_url' => '',
                'log'        => $log,
            ];
        }
    }

    /**
     * Download and restore an MBZ as a new course.
     *
     * @param array $manifest The manifest.
     * @param int $categoryid Target category.
     * @return int|null New course ID or null on failure.
     */
    private static function restore_mbz(array $manifest, int $categoryid): ?int {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $mbz = $manifest['mbz'][0]; // Use the first MBZ.
        $tempfile = null;

        try {
            // Download the MBZ from SharePoint.
            $tempfile = client::download_file(
                $manifest['site_id'],
                $manifest['drive_id'],
                $mbz['item_id'],
                $mbz['name']
            );

            if ($tempfile === null) {
                return null;
            }

            // Extract the MBZ to a temp backup directory.
            $backupid = 'smcl_' . time() . '_' . random_string(4);
            $extractdir = $CFG->tempdir . '/backup/' . $backupid;

            $fp = get_file_packer('application/vnd.moodle.backup');
            $result = $fp->extract_to_pathname($tempfile, $extractdir);

            if (!$result) {
                debugging('SharePoint importer: failed to extract MBZ.', DEBUG_DEVELOPER);
                return null;
            }

            // Create a placeholder course in the target category for the restore.
            $newcourse = new \stdClass();
            $newcourse->category = $categoryid;
            $newcourse->shortname = 'smcl_temp_' . time();
            $newcourse->fullname = 'Importing...';
            $newcourse->idnumber = '';
            $newcourse->summary = '';
            $newcourse = \create_course($newcourse);

            // Restore the MBZ into the placeholder course.
            $controller = new \restore_controller(
                $backupid,
                $newcourse->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_EXISTING_DELETING
            );

            $controller->execute_precheck();
            $precheckresults = $controller->get_precheck_results();
            // Only abort on errors, not warnings.
            if (!empty($precheckresults['errors'])) {
                debugging('SharePoint importer: restore precheck errors: ' .
                    json_encode($precheckresults['errors']), DEBUG_DEVELOPER);
                $controller->destroy();
                return null;
            }

            $controller->execute_plan();
            $newcourseid = $controller->get_courseid();
            $controller->destroy();

            if ($newcourseid) {
                // The restore may not overwrite the placeholder course name.
                // Read the original course info from the MBZ and update if needed.
                $restoredcourse = $DB->get_record('course', ['id' => $newcourseid]);
                if ($restoredcourse && ($restoredcourse->fullname === 'Importing...'
                        || strpos($restoredcourse->shortname, 'smcl_temp_') === 0)) {
                    // Try to get the name from the backup's course.xml.
                    $coursexmlpath = $extractdir . '/course/course.xml';
                    if (file_exists($coursexmlpath)) {
                        $xml = simplexml_load_file($coursexmlpath);
                        if ($xml) {
                            $fullname = (string) ($xml->fullname ?? '');
                            $shortname = (string) ($xml->shortname ?? '');
                            if ($fullname) {
                                $restoredcourse->fullname = $fullname;
                            }
                            if ($shortname) {
                                // Ensure shortname is unique.
                                $base = $shortname;
                                $i = 1;
                                while ($DB->record_exists_select('course',
                                    'shortname = ? AND id != ?', [$shortname, $newcourseid])) {
                                    $shortname = $base . '_' . $i++;
                                }
                                $restoredcourse->shortname = $shortname;
                            }
                            $DB->update_record('course', $restoredcourse);
                        }
                    }
                }
            }

            return $newcourseid ?: null;

        } finally {
            // Clean up the downloaded file.
            if ($tempfile && file_exists($tempfile)) {
                @unlink($tempfile);
            }
        }
    }

    /**
     * Download SCORM packages from SharePoint, upload them locally so Moodle
     * can parse the content, then switch to external URL with auto-update
     * so future changes in SharePoint are picked up automatically.
     *
     * @param int $courseid The course ID.
     * @param array $manifest The manifest.
     * @return string[] Log messages.
     */
    private static function configure_scorm_activities(int $courseid, array $manifest): array {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        $log = [];
        $course = get_course($courseid);

        // Get existing SCORM activities in the course.
        $existingscorms = $DB->get_records('scorm', ['course' => $courseid], 'id ASC');
        $existinglist = array_values($existingscorms);

        foreach ($manifest['scorm'] as $idx => $scormfile) {
            // Download the SCORM ZIP from SharePoint.
            $tempfile = client::download_file(
                $manifest['site_id'],
                $manifest['drive_id'],
                $scormfile['item_id'],
                $scormfile['name']
            );

            if ($tempfile === null) {
                $log[] = "  ERROR - No se pudo descargar {$scormfile['name']}.";
                continue;
            }

            try {
                // Build the signed proxy URL for auto-update.
                $proxyurl = client::build_proxy_url($scormfile['item_id']);

                if (isset($existinglist[$idx])) {
                    // Update existing SCORM: upload file, parse, then set external URL.
                    $scorm = $existinglist[$idx];
                    $cm = get_coursemodule_from_instance('scorm', $scorm->id, $courseid);
                    $context = \context_module::instance($cm->id);

                    $fs = get_file_storage();
                    $fs->delete_area_files($context->id, 'mod_scorm', 'package');

                    $fs->create_file_from_pathname([
                        'contextid' => $context->id,
                        'component' => 'mod_scorm',
                        'filearea'  => 'package',
                        'itemid'    => 0,
                        'filepath'  => '/',
                        'filename'  => $scormfile['name'],
                    ], $tempfile);

                    // Parse the SCORM content locally.
                    $scorm->reference = $scormfile['name'];
                    $scorm->scormtype = SCORM_TYPE_LOCAL;
                    $scorm->timemodified = time();
                    $DB->update_record('scorm', $scorm);
                    scorm_parse($scorm, true);

                    // Store SharePoint item ID for future auto-update via scheduled task.
                    self::save_sp_mapping($scorm->id, $manifest, $scormfile);

                    $log[] = "  OK - SCORM actualizado: {$scorm->name}.";
                } else {
                    // Create new SCORM: upload via draft area so add_moduleinfo parses it.
                    $fs = get_file_storage();
                    $usercontext = \context_user::instance($USER->id);
                    $draftitemid = file_get_unused_draft_itemid();

                    $fs->create_file_from_pathname([
                        'contextid' => $usercontext->id,
                        'component' => 'user',
                        'filearea'  => 'draft',
                        'itemid'    => $draftitemid,
                        'filepath'  => '/',
                        'filename'  => $scormfile['name'],
                    ], $tempfile);

                    $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
                    $lastsection = end($sections);
                    $sectionnum = $lastsection ? $lastsection->section : 0;
                    $scormmodule = $DB->get_record('modules', ['name' => 'scorm'], 'id', MUST_EXIST);

                    $moduleinfo = new \stdClass();
                    $moduleinfo->modulename = 'scorm';
                    $moduleinfo->module = $scormmodule->id;
                    $moduleinfo->name = pathinfo($scormfile['name'], PATHINFO_FILENAME);
                    $moduleinfo->course = $courseid;
                    $moduleinfo->section = $sectionnum;
                    $moduleinfo->visible = 1;
                    $moduleinfo->scormtype = SCORM_TYPE_LOCAL;
                    $moduleinfo->packagefile = $draftitemid;
                    $moduleinfo->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
                    $moduleinfo->cmidnumber = '';
                    $moduleinfo->groupmode = 0;
                    $moduleinfo->groupingid = 0;

                    // This creates the activity AND parses the SCORM package.
                    $moduleinfo = add_moduleinfo($moduleinfo, $course);

                    // Store SharePoint item ID for future auto-update via scheduled task.
                    $scormrecord = $DB->get_record('scorm', [
                        'course' => $courseid,
                        'name'   => $moduleinfo->name,
                    ], '*', IGNORE_MULTIPLE);

                    if ($scormrecord) {
                        self::save_sp_mapping($scormrecord->id, $manifest, $scormfile);
                    }

                    $log[] = "  OK - SCORM creado: {$moduleinfo->name}.";
                }
            } catch (\Exception $e) {
                $log[] = "  ERROR - SCORM {$scormfile['name']}: " . $e->getMessage();
            } finally {
                @unlink($tempfile);
            }
        }

        return $log;
    }

    /**
     * Create URL resources for PDFs and documents, linked to SharePoint.
     *
     * @param int $courseid The course ID.
     * @param array $manifest The manifest.
     * @return string[] Log messages.
     */
    private static function create_url_resources(int $courseid, array $manifest): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $log = [];
        $course = get_course($courseid);

        // Get the last section to add resources.
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        $lastsection = end($sections);
        $sectionnum = $lastsection ? $lastsection->section : 0;

        $urlmodule = $DB->get_record('modules', ['name' => 'url'], 'id', MUST_EXIST);

        $allfiles = array_merge($manifest['pdf'] ?? [], $manifest['documents'] ?? []);

        foreach ($allfiles as $file) {
            // Build a proxy URL for the file.
            $fileurl = $CFG->wwwroot . '/local/sm_graphics_plugin/pages/sp_download.php?item='
                . urlencode($file['item_id']);

            $moduleinfo = new \stdClass();
            $moduleinfo->modulename = 'url';
            $moduleinfo->module = $urlmodule->id;
            $moduleinfo->name = pathinfo($file['name'], PATHINFO_FILENAME);
            $moduleinfo->course = $courseid;
            $moduleinfo->section = $sectionnum;
            $moduleinfo->visible = 1;
            $moduleinfo->externalurl = $fileurl;
            $moduleinfo->display = RESOURCELIB_DISPLAY_POPUP;
            $moduleinfo->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
            $moduleinfo->cmidnumber = '';
            $moduleinfo->groupmode = 0;
            $moduleinfo->groupingid = 0;

            $moduleinfo = add_moduleinfo($moduleinfo, $course);
            $log[] = "  OK - Recurso creado: {$file['name']}.";
        }

        return $log;
    }

    /**
     * Import AIKEN and GIFT evaluation files into the course question bank.
     *
     * Matches evaluation files to quiz activities by name convention:
     * filename "...Por defecto en QUIZNAME.txt" maps to quiz named QUIZNAME.
     *
     * @param int $courseid The course ID.
     * @param array $manifest The manifest.
     * @return string[] Log messages.
     */
    private static function import_evaluations(int $courseid, array $manifest): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/aiken/format.php');
        if (file_exists($CFG->dirroot . '/question/format/gift/format.php')) {
            require_once($CFG->dirroot . '/question/format/gift/format.php');
        }
        require_once($CFG->libdir . '/questionlib.php');

        $log = [];
        $course = get_course($courseid);

        // Get all quiz activities in the course.
        $quizzes = $DB->get_records('quiz', ['course' => $courseid]);

        $allevals = [];
        foreach ($manifest['evaluations_aiken'] ?? [] as $f) {
            $allevals[] = ['file' => $f, 'format' => 'aiken'];
        }
        foreach ($manifest['evaluations_gift'] ?? [] as $f) {
            $allevals[] = ['file' => $f, 'format' => 'gift'];
        }

        foreach ($allevals as $eval) {
            $evalfile = $eval['file'];
            $formatname = $eval['format'];

            // Download the file.
            $tempfile = client::download_file(
                $manifest['site_id'],
                $manifest['drive_id'],
                $evalfile['item_id'],
                $evalfile['name']
            );

            if ($tempfile === null) {
                $log[] = "  ERROR - No se pudo descargar {$evalfile['name']}.";
                continue;
            }

            if (filesize($tempfile) === 0) {
                @unlink($tempfile);
                $log[] = "  SKIP - Archivo vacio: {$evalfile['name']}.";
                continue;
            }

            try {
                // Match the file to a quiz by name.
                // Convention: "preguntas-AIKEN -Por defecto en QUIZNAME.txt"
                $quiz = self::match_eval_to_quiz($evalfile['name'], $quizzes);

                if ($quiz === null) {
                    // Use the first quiz as fallback.
                    $quiz = reset($quizzes);
                    $log[] = "  WARN - No se encontro quiz para {$evalfile['name']}, usando: {$quiz->name}";
                }

                $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
                $modcontext = \context_module::instance($cm->id);

                // Get or create question category in module context.
                $catname = pathinfo($evalfile['name'], PATHINFO_FILENAME);
                $category = self::get_or_create_question_category($catname, $modcontext);

                // Create the format instance.
                $classname = "qformat_{$formatname}";
                $format = new $classname();

                $format->setCategory($category);
                $format->setCourse($course);
                $format->setFilename($tempfile);
                $format->setRealfilename($evalfile['name']);
                $format->setMatchgrades('nearest');
                $format->setStoponerror(false);

                ob_start();
                $format->importprocess();
                ob_get_clean();

                $count = $DB->count_records('question_bank_entries', [
                    'questioncategoryid' => $category->id,
                ]);

                if ($count > 0) {
                    $log[] = "  OK - {$count} preguntas importadas en '{$quiz->name}' desde {$evalfile['name']}.";
                } else {
                    $log[] = "  WARN - 0 preguntas guardadas desde {$evalfile['name']}.";
                }

            } catch (\Exception $e) {
                $log[] = "  ERROR - {$evalfile['name']}: " . $e->getMessage();
            } finally {
                if (file_exists($tempfile)) {
                    @unlink($tempfile);
                }
            }
        }

        return $log;
    }

    /**
     * Save a SharePoint-to-SCORM mapping for future auto-updates.
     *
     * Stores the mapping in plugin config as a JSON array keyed by scorm ID.
     *
     * @param int $scormid The SCORM activity ID.
     * @param array $manifest The manifest with site_id and drive_id.
     * @param array $scormfile The SCORM file data with item_id.
     */
    private static function save_sp_mapping(int $scormid, array $manifest, array $scormfile): void {
        $mappings = json_decode(
            get_config('local_sm_graphics_plugin', 'sp_scorm_mappings') ?: '{}',
            true
        ) ?: [];

        $mappings[$scormid] = [
            'site_id'  => $manifest['site_id'],
            'drive_id' => $manifest['drive_id'],
            'item_id'  => $scormfile['item_id'],
            'name'     => $scormfile['name'],
        ];

        set_config('sp_scorm_mappings', json_encode($mappings), 'local_sm_graphics_plugin');
    }

    /**
     * Match an evaluation filename to a quiz activity.
     *
     * Convention: "preguntas-AIKEN -Por defecto en QUIZNAME.txt"
     *
     * @param string $filename The evaluation filename.
     * @param array $quizzes Array of quiz records.
     * @return \stdClass|null Matching quiz or null.
     */
    private static function match_eval_to_quiz(string $filename, array $quizzes): ?\stdClass {
        // Extract quiz name from filename: "...Por defecto en QUIZNAME.txt"
        if (preg_match('/Por defecto en (.+)\.txt$/i', $filename, $matches)) {
            $target = trim($matches[1]);

            foreach ($quizzes as $quiz) {
                // Normalize both strings for comparison.
                $quizname = self::normalize_string($quiz->name);
                $targetname = self::normalize_string($target);

                if ($quizname === $targetname || strpos($quizname, $targetname) !== false
                    || strpos($targetname, $quizname) !== false) {
                    return $quiz;
                }
            }
        }

        return null;
    }

    /**
     * Normalize a string for fuzzy comparison (remove accents, lowercase).
     */
    private static function normalize_string(string $str): string {
        $str = mb_strtolower($str, 'UTF-8');
        $str = str_replace(
            ['á','é','í','ó','ú','ñ','ü'],
            ['a','e','i','o','u','n','u'],
            $str
        );
        $str = preg_replace('/[^a-z0-9]/', '', $str);
        return $str;
    }

    /**
     * Get or create a question bank category for the course.
     *
     * @param string $name Category name.
     * @param \context $context Module or course context.
     * @return \stdClass The question category record.
     */
    private static function get_or_create_question_category(string $name, \context $context): \stdClass {
        global $DB;

        // Check if category already exists.
        $category = $DB->get_record('question_categories', [
            'name'      => $name,
            'contextid' => $context->id,
        ]);

        if ($category) {
            return $category;
        }

        // Find or create a top-level category for this course context.
        // In Moodle 4+/5.0, question_get_top_category may require module context,
        // so we look for any existing top category or create one manually.
        $top = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'parent'    => 0,
        ]);

        if (!$top) {
            // Create the top-level category for this course context.
            $top = new \stdClass();
            $top->name = 'top';
            $top->info = '';
            $top->infoformat = FORMAT_PLAIN;
            $top->contextid = $context->id;
            $top->parent = 0;
            $top->sortorder = 0;
            $top->stamp = make_unique_id_code();
            $top->id = $DB->insert_record('question_categories', $top);
        }

        $category = new \stdClass();
        $category->name = $name;
        $category->info = "Importado desde SharePoint";
        $category->infoformat = FORMAT_PLAIN;
        $category->contextid = $context->id;
        $category->parent = $top->id;
        $category->sortorder = 999;
        $category->stamp = make_unique_id_code();
        $category->id = $DB->insert_record('question_categories', $category);

        return $category;
    }
}

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

namespace local_sm_graphics_plugin;

/**
 * Event observer that persists pricing data after course create/update.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Save pricing data when a course is created or updated.
     *
     * The pricing fields are injected into the course edit form via hooks.
     * Since create_course()/update_course() ignore unknown fields,
     * we read the submitted values here and persist them to our own table.
     *
     * @param \core\event\base $event
     */
    public static function course_saved(\core\event\base $event): void {
        global $DB;

        $courseid = $event->courseid;

        // Save catalogue category (before pricing check, which may return early).
        $catid = optional_param('smgp_catalogue_cat', -1, PARAM_INT);
        if ($catid >= 0) {
            $DB->delete_records('local_smgp_course_category', ['courseid' => $courseid]);
            if ($catid > 0) {
                $DB->insert_record('local_smgp_course_category', (object) [
                    'courseid'   => $courseid,
                    'categoryid' => $catid,
                ]);
            }
        }

        // Save learning objectives from JSON.
        $objectivesjson = optional_param('smgp_objectives_data', null, PARAM_RAW);
        if ($objectivesjson !== null) {
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('local_smgp_learning_objectives')) {
                $objectives = json_decode($objectivesjson, true);
                if (is_array($objectives)) {
                    $sourcelang = current_language();
                    // Normalize language code (e.g., 'es_mx' → 'es').
                    if (!in_array($sourcelang, ['en', 'es', 'pt_br'])) {
                        if (strpos($sourcelang, 'es') === 0) {
                            $sourcelang = 'es';
                        } else if (strpos($sourcelang, 'pt') === 0) {
                            $sourcelang = 'pt_br';
                        } else {
                            $sourcelang = 'en';
                        }
                    }

                    // Delete all objectives for this course (all languages).
                    $DB->delete_records('local_smgp_learning_objectives', ['courseid' => $courseid]);

                    // Insert source-language objectives.
                    $now = time();
                    $cleantexts = [];
                    foreach ($objectives as $sortorder => $text) {
                        $text = trim($text);
                        if ($text === '') {
                            continue;
                        }
                        $cleantext = clean_param($text, PARAM_TEXT);
                        $cleantexts[] = $cleantext;
                        $DB->insert_record('local_smgp_learning_objectives', (object) [
                            'courseid'     => $courseid,
                            'objective'    => $cleantext,
                            'sortorder'    => count($cleantexts) - 1,
                            'lang'         => $sourcelang,
                            'timecreated'  => $now,
                            'timemodified' => $now,
                        ]);
                    }

                    // Auto-translate to other languages via Gemini.
                    if (!empty($cleantexts)) {
                        $alllanguages = ['en', 'es', 'pt_br'];
                        $targetlangs = array_diff($alllanguages, [$sourcelang]);

                        foreach ($targetlangs as $targetlang) {
                            $translated = \local_sm_graphics_plugin\gemini::translate_batch(
                                $cleantexts, $sourcelang, $targetlang
                            );
                            if ($translated) {
                                foreach ($translated as $i => $transtext) {
                                    $DB->insert_record('local_smgp_learning_objectives', (object) [
                                        'courseid'     => $courseid,
                                        'objective'    => clean_param(trim($transtext), PARAM_TEXT),
                                        'sortorder'    => $i,
                                        'lang'         => $targetlang,
                                        'timecreated'  => $now,
                                        'timemodified' => $now,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // Auto-translate course summary to other languages.
        $course = $DB->get_record('course', ['id' => $courseid], 'summary');
        if ($course && !empty(trim(strip_tags($course->summary ?? '')))) {
            $sourcelang = current_language();
            if (!in_array($sourcelang, ['en', 'es', 'pt_br'])) {
                if (strpos($sourcelang, 'es') === 0) {
                    $sourcelang = 'es';
                } else if (strpos($sourcelang, 'pt') === 0) {
                    $sourcelang = 'pt_br';
                } else {
                    $sourcelang = 'en';
                }
            }

            $dbman = $DB->get_manager();
            if ($dbman->table_exists('local_smgp_course_translations')) {
                $alllanguages = ['en', 'es', 'pt_br'];
                $targetlangs = array_diff($alllanguages, [$sourcelang]);
                $now = time();
                $plaintext = strip_tags($course->summary);

                foreach ($targetlangs as $targetlang) {
                    $translated = \local_sm_graphics_plugin\gemini::translate($plaintext, $sourcelang, $targetlang);
                    if ($translated) {
                        $existing = $DB->get_record('local_smgp_course_translations',
                            ['courseid' => $courseid, 'lang' => $targetlang]);
                        if ($existing) {
                            $existing->summary = $translated;
                            $existing->timemodified = $now;
                            $DB->update_record('local_smgp_course_translations', $existing);
                        } else {
                            $DB->insert_record('local_smgp_course_translations', (object) [
                                'courseid'     => $courseid,
                                'lang'         => $targetlang,
                                'summary'      => $translated,
                                'timecreated'  => $now,
                                'timemodified' => $now,
                            ]);
                        }
                    }
                }
            }
        }

        // Read submitted values from the form POST data.
        $price = optional_param('smgp_price', null, PARAM_RAW);
        $currency = optional_param('smgp_currency', null, PARAM_ALPHA);
        $durationhours = optional_param('smgp_duration_hours', null, PARAM_RAW);
        $description = optional_param('smgp_description', null, PARAM_TEXT);
        $coursecategory = optional_param('smgp_course_category', null, PARAM_TEXT);
        $smartmindcode = optional_param('smgp_smartmind_code', null, PARAM_TEXT);
        $sepecode = optional_param('smgp_sepe_code', null, PARAM_TEXT);
        $level = optional_param('smgp_level', 'beginner', PARAM_ALPHA);
        $completionpct = optional_param('smgp_completion_percentage', 100, PARAM_INT);

        // If the pricing fields were not in the form submission, do nothing.
        // This avoids interfering with programmatic course creation (e.g. restore, CLI).
        if ($price === null) {
            return;
        }

        $amount = unformat_float($price) ?? 0.0;
        $currency = in_array($currency, ['EUR', 'USD', 'GBP']) ? $currency : 'EUR';
        $duration = unformat_float($durationhours) ?? 0.0;
        $now = time();

        $existing = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);

        if ($existing) {
            $existing->amount = $amount;
            $existing->currency = $currency;
            $existing->duration_hours = $duration;
            $existing->description = $description ?? '';
            $existing->course_category = $coursecategory ?? '';
            $existing->smartmind_code = $smartmindcode ?? '';
            $existing->sepe_code = $sepecode ?? '';
            $existing->level = in_array($level, ['beginner', 'medium', 'advanced']) ? $level : 'beginner';
            $existing->completion_percentage = max(0, min(100, $completionpct));
            $existing->timemodified = $now;
            $DB->update_record('local_smgp_course_meta', $existing);
        } else {
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->amount = $amount;
            $record->currency = $currency;
            $record->duration_hours = $duration;
            $record->description = $description ?? '';
            $record->course_category = $coursecategory ?? '';
            $record->smartmind_code = $smartmindcode ?? '';
            $record->sepe_code = $sepecode ?? '';
            $record->level = in_array($level, ['beginner', 'medium', 'advanced']) ? $level : 'beginner';
            $record->completion_percentage = max(0, min(100, $completionpct));
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_smgp_course_meta', $record);
        }

    }

    /**
     * Save the student limit when a company is created or updated.
     *
     * The "Max students" field is injected into the IOMAD company edit
     * form via JavaScript. We read its value from the POST data here.
     *
     * @param \core\event\base $event
     */
    public static function company_saved(\core\event\base $event): void {
        $maxstudents = optional_param('smgp_maxstudents', -1, PARAM_INT);

        // Field not present in this request — do nothing.
        if ($maxstudents < 0) {
            return;
        }

        $other = $event->other;
        $companyid = $other['companyid'] ?? $event->objectid ?? 0;
        if ($companyid > 0) {
            require_once(__DIR__ . '/../lib.php');
            local_sm_graphics_plugin_save_company_limit((int) $companyid, $maxstudents);
        }
    }

    /**
     * Save SmartMind custom fields after a course is restored.
     *
     * Two data sources, applied in order (later overrides earlier):
     * 1. Copy from the ORIGINAL course (always available via $event->other['originalcourseid']).
     * 2. Override with values from $SESSION->smgp_restore_pending (staged by
     *    the save_restore_fields AJAX endpoint on the restore schema form).
     *
     * This guarantees data is preserved even if the AJAX staging didn't complete
     * (e.g. page navigated before the XHR finished).
     *
     * @param \core\event\course_restored $event
     */
    public static function course_restored(\core\event\course_restored $event): void {
        global $DB, $SESSION;

        $courseid = (int) $event->courseid;
        if ($courseid <= 0) {
            return;
        }

        $other          = $event->other ?? [];
        $originalid     = (int) ($other['originalcourseid'] ?? 0);
        $now            = time();

        // DEBUG logging.
        error_log('[SMGP-RESTORE] course_restored fired: courseid=' . $courseid
            . ', originalid=' . $originalid
            . ', SESSION pending=' . (empty($SESSION->smgp_restore_pending) ? 'EMPTY' : json_encode($SESSION->smgp_restore_pending)));

        // --- Step 1: Copy all SmartMind data from the original course ---
        if ($originalid > 0) {
            self::copy_smgp_data($originalid, $courseid, $now);
            error_log('[SMGP-RESTORE] Copied data from course ' . $originalid . ' to ' . $courseid);
        }

        // --- Step 2: Override with session-staged values (if user modified on the schema form) ---
        if (!empty($SESSION->smgp_restore_pending)) {
            $pending = $SESSION->smgp_restore_pending;
            unset($SESSION->smgp_restore_pending);
            $fields = (array) ($pending['fields'] ?? []);
            error_log('[SMGP-RESTORE] Applying overrides: ' . json_encode($fields));
            self::apply_restore_overrides($courseid, $fields, $now);
        }
    }

    /**
     * Copy all SmartMind custom data from one course to another.
     */
    private static function copy_smgp_data(int $sourceid, int $targetid, int $now): void {
        global $DB;

        // --- Copy local_smgp_course_meta ---
        $srcmeta = $DB->get_record('local_smgp_course_meta', ['courseid' => $sourceid]);
        if ($srcmeta) {
            // Remove any existing meta for the target (in case of restore-over-existing).
            $DB->delete_records('local_smgp_course_meta', ['courseid' => $targetid]);
            $newmeta = clone $srcmeta;
            unset($newmeta->id);
            $newmeta->courseid     = $targetid;
            $newmeta->timecreated  = $now;
            $newmeta->timemodified = $now;
            $DB->insert_record('local_smgp_course_meta', $newmeta);
        }

        // --- Copy catalogue category link ---
        $srccat = $DB->get_record('local_smgp_course_category', ['courseid' => $sourceid]);
        if ($srccat) {
            $DB->delete_records('local_smgp_course_category', ['courseid' => $targetid]);
            $DB->insert_record('local_smgp_course_category', (object) [
                'courseid'   => $targetid,
                'categoryid' => (int) $srccat->categoryid,
            ]);
        }

        // --- Copy learning objectives (all languages) ---
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_smgp_learning_objectives')) {
            $srcobjs = $DB->get_records('local_smgp_learning_objectives', ['courseid' => $sourceid], 'lang, sortorder');
            if ($srcobjs) {
                $DB->delete_records('local_smgp_learning_objectives', ['courseid' => $targetid]);
                foreach ($srcobjs as $obj) {
                    $newobj = clone $obj;
                    unset($newobj->id);
                    $newobj->courseid     = $targetid;
                    $newobj->timecreated  = $now;
                    $newobj->timemodified = $now;
                    $DB->insert_record('local_smgp_learning_objectives', $newobj);
                }
            }
        }

        // --- Copy course translations ---
        if ($dbman->table_exists('local_smgp_course_translations')) {
            $srctrans = $DB->get_records('local_smgp_course_translations', ['courseid' => $sourceid]);
            if ($srctrans) {
                $DB->delete_records('local_smgp_course_translations', ['courseid' => $targetid]);
                foreach ($srctrans as $tr) {
                    $newtr = clone $tr;
                    unset($newtr->id);
                    $newtr->courseid     = $targetid;
                    $newtr->timecreated  = $now;
                    $newtr->timemodified = $now;
                    $DB->insert_record('local_smgp_course_translations', $newtr);
                }
            }
        }
    }

    /**
     * Write SmartMind field values to the DB for a given course.
     * Used both by the course_restored observer and the save_restore_fields
     * AJAX endpoint (post-restore fallback). Only modifies values that were
     * explicitly set (non-empty, non-default values).
     */
    public static function write_restore_fields(int $courseid, array $fields, int $now = 0): void {
        if ($now <= 0) {
            $now = time();
        }
        self::apply_restore_overrides($courseid, $fields, $now);

        // Apply course structure changes (renames, reorders) if present.
        if (!empty($fields['smgp_course_structure'])) {
            self::apply_structure_changes($courseid, $fields['smgp_course_structure']);
        }
    }

    /**
     * Apply course structure changes after restore: rename sections/activities,
     * reorder them based on user edits from the schema step editor.
     */
    public static function apply_structure_changes(int $courseid, string $json): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $structure = json_decode($json, true);
        if (!is_array($structure)) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return;
        }

        foreach ($structure as $secdata) {
            $sectionKey = $secdata['sectionKey'] ?? '';
            $newName    = $secdata['name'] ?? '';
            $origName   = $secdata['origName'] ?? '';

            // Extract section number from key: "setting_section_section_N_included" → N
            if (preg_match('/setting_section_section_(\d+)_included/', $sectionKey, $m)) {
                $sectionnum = (int) $m[1];
                // Rename section if changed.
                if ($newName !== $origName && $newName !== '') {
                    $section = $DB->get_record('course_sections',
                        ['course' => $courseid, 'section' => $sectionnum]);
                    if ($section) {
                        $DB->set_field('course_sections', 'name', $newName,
                            ['id' => $section->id]);
                    }
                }

                // Process activity renames within this section.
                $activities = $secdata['activities'] ?? [];
                foreach ($activities as $actdata) {
                    $actKey  = $actdata['actKey'] ?? '';
                    $actName = $actdata['name'] ?? '';
                    $actOrig = $actdata['origName'] ?? '';

                    // Skip new (placeholder) activities and unchanged names.
                    if (strpos($actKey, 'smgp_new_') === 0 || $actName === $actOrig || $actName === '') {
                        continue;
                    }

                    // Extract module name and instance from key: "setting_activity_MODNAME_N_included"
                    if (preg_match('/setting_activity_(\w+)_(\d+)_included/', $actKey, $am)) {
                        $modname = $am[1];
                        $cmid    = (int) $am[2];
                        // The N in the key is the backup ID, not the cmid.
                        // We need to find the actual cm by matching module name and instance.
                        // Since after restore the IDs change, try matching by original name.
                        $modtable = $modname;
                        if ($DB->get_manager()->table_exists($modtable)) {
                            // Find instances in this course section with the original name.
                            $instances = $DB->get_records_sql(
                                "SELECT m.id, m.name, cm.id AS cmid
                                   FROM {{$modtable}} m
                                   JOIN {course_modules} cm ON cm.instance = m.id AND cm.module = (
                                       SELECT id FROM {modules} WHERE name = ?
                                   )
                                  WHERE cm.course = ? AND m.name = ?",
                                [$modname, $courseid, $actOrig]
                            );
                            foreach ($instances as $inst) {
                                $DB->set_field($modtable, 'name', $actName, ['id' => $inst->id]);
                                break; // Rename the first match only.
                            }
                        }
                    }
                }
            }
        }

        rebuild_course_cache($courseid, true);
    }

    /**
     * Apply field overrides on top of existing SmartMind data.
     */
    private static function apply_restore_overrides(int $courseid, array $fields, int $now): void {
        global $DB;

        // Check if user actually filled in any non-default values.
        $hasOverrides = false;
        foreach ($fields as $k => $v) {
            if ($v !== '' && $v !== '0' && $v !== '[]' && $v !== 'beginner' && $v !== '100') {
                $hasOverrides = true;
                break;
            }
        }
        if (!$hasOverrides) {
            return;
        }

        // --- Update local_smgp_course_meta ---
        $meta = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
        if (!$meta) {
            $meta = (object) [
                'courseid'       => $courseid,
                'amount'         => 0,
                'currency'       => 'EUR',
                'duration_hours' => 0,
                'timecreated'    => $now,
                'timemodified'   => $now,
            ];
            $meta->id = $DB->insert_record('local_smgp_course_meta', $meta);
        }

        if (!empty($fields['smgp_duration_hours'])) {
            $meta->duration_hours = (float) str_replace(',', '.', $fields['smgp_duration_hours']);
        }
        if (!empty($fields['smgp_level']) && $fields['smgp_level'] !== 'beginner') {
            $meta->level = in_array($fields['smgp_level'], ['beginner', 'medium', 'advanced'])
                           ? $fields['smgp_level'] : $meta->level;
        }
        if (!empty($fields['smgp_completion_percentage']) && $fields['smgp_completion_percentage'] !== '100') {
            $meta->completion_percentage = max(0, min(100, (int) $fields['smgp_completion_percentage']));
        }
        if (!empty($fields['smgp_smartmind_code'])) {
            $meta->smartmind_code = clean_param($fields['smgp_smartmind_code'], PARAM_TEXT);
        }
        if (!empty($fields['smgp_sepe_code'])) {
            $meta->sepe_code = clean_param($fields['smgp_sepe_code'], PARAM_TEXT);
        }
        if (!empty($fields['smgp_description'])) {
            $meta->description = clean_param($fields['smgp_description'], PARAM_TEXT);
        }
        $meta->timemodified = $now;
        $DB->update_record('local_smgp_course_meta', $meta);

        // --- Override catalogue category ---
        $catid = isset($fields['smgp_catalogue_cat']) ? (int) $fields['smgp_catalogue_cat'] : 0;
        if ($catid > 0) {
            $DB->delete_records('local_smgp_course_category', ['courseid' => $courseid]);
            $DB->insert_record('local_smgp_course_category', (object) [
                'courseid'   => $courseid,
                'categoryid' => $catid,
            ]);
        }

        // --- Override description in course.summary + translate ---
        if (!empty($fields['smgp_description']) && trim(strip_tags($fields['smgp_description'])) !== '') {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if ($course) {
                $course->summary      = $fields['smgp_description'];
                $course->timemodified = $now;
                $DB->update_record('course', $course);
                rebuild_course_cache($courseid, true);
            }
            // Translate summary.
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('local_smgp_course_translations')) {
                $sourcelang  = 'es';
                $targetlangs = array_diff(['en', 'es', 'pt_br'], [$sourcelang]);
                $plaintext   = strip_tags($fields['smgp_description']);
                foreach ($targetlangs as $targetlang) {
                    $translated = \local_sm_graphics_plugin\gemini::translate($plaintext, $sourcelang, $targetlang);
                    if ($translated) {
                        $existing = $DB->get_record('local_smgp_course_translations',
                            ['courseid' => $courseid, 'lang' => $targetlang]);
                        if ($existing) {
                            $existing->summary      = $translated;
                            $existing->timemodified = $now;
                            $DB->update_record('local_smgp_course_translations', $existing);
                        } else {
                            $DB->insert_record('local_smgp_course_translations', (object) [
                                'courseid'     => $courseid,
                                'lang'         => $targetlang,
                                'summary'      => $translated,
                                'timecreated'  => $now,
                                'timemodified' => $now,
                            ]);
                        }
                    }
                }
            }
        }

        // --- Override learning objectives + translate ---
        $objectivesjson = $fields['smgp_objectives_data'] ?? '[]';
        if ($objectivesjson && $objectivesjson !== '[]') {
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('local_smgp_learning_objectives')) {
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
                        $cleantext    = clean_param($text, PARAM_TEXT);
                        $cleantexts[] = $cleantext;
                        $DB->insert_record('local_smgp_learning_objectives', (object) [
                            'courseid'     => $courseid,
                            'objective'    => $cleantext,
                            'sortorder'    => count($cleantexts) - 1,
                            'lang'         => $sourcelang,
                            'timecreated'  => $now,
                            'timemodified' => $now,
                        ]);
                    }
                    if (!empty($cleantexts)) {
                        foreach (array_diff(['en', 'es', 'pt_br'], [$sourcelang]) as $targetlang) {
                            $translated = \local_sm_graphics_plugin\gemini::translate_batch(
                                $cleantexts, $sourcelang, $targetlang
                            );
                            if ($translated) {
                                foreach ($translated as $i => $ttext) {
                                    $DB->insert_record('local_smgp_learning_objectives', (object) [
                                        'courseid'     => $courseid,
                                        'objective'    => clean_param(trim($ttext), PARAM_TEXT),
                                        'sortorder'    => $i,
                                        'lang'         => $targetlang,
                                        'timecreated'  => $now,
                                        'timemodified' => $now,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

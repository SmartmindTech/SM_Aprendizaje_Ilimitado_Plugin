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
}

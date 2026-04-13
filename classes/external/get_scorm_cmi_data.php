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
 * AJAX: Get SCORM CMI data for Vue-native SCORM player.
 *
 * Returns all stored CMI key/value pairs plus the SCO launch URL
 * so the Vue frontend can implement the SCORM RTE API
 * (LMSInitialize/LMSGetValue/LMSSetValue) without Moodle's player.php.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

class get_scorm_cmi_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid = $params['cmid'];

        $cm = get_coursemodule_from_id('scorm', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

        // Find the primary launchable SCO.
        $sco = $DB->get_record_select(
            'scorm_scoes',
            "scorm = :scormid AND scormtype = 'sco' AND launch <> ''",
            ['scormid' => $scorm->id],
            '*',
            IGNORE_MULTIPLE
        );

        if (!$sco) {
            return [
                'success'    => false,
                'message'    => 'No launchable SCO found',
                'launchurl'  => '',
                'baseurl'    => '',
                'scormtype'  => '',
                'scoid'      => 0,
                'attempt'    => 0,
                'cmidata'    => [],
                'slidecount' => 0,
            ];
        }

        // Determine SCORM version (1.2 or 2004).
        $scormtype = 'scorm_12'; // Default.
        $version = $scorm->version ?? '';
        if (strpos($version, '2004') !== false || strpos($version, 'CAM 1.3') !== false) {
            $scormtype = 'scorm_2004';
        }

        // Get current or create new attempt.
        $dbman = $DB->get_manager();
        $usenormalized = $dbman->table_exists('scorm_scoes_value');

        $attempt = 1;
        $cmidata = [];

        if ($usenormalized) {
            // Moodle 5.0+: scorm_attempt + scorm_scoes_value + scorm_element.
            $attemptrecord = $DB->get_record_sql(
                "SELECT id, attempt FROM {scorm_attempt}
                 WHERE scormid = :scormid AND userid = :userid
                 ORDER BY attempt DESC LIMIT 1",
                ['scormid' => $scorm->id, 'userid' => $USER->id]
            );

            if ($attemptrecord) {
                $attempt = (int) $attemptrecord->attempt;

                // Read all CMI values for this attempt + SCO.
                $values = $DB->get_records_sql(
                    "SELECT e.element, v.value
                     FROM {scorm_scoes_value} v
                     JOIN {scorm_element} e ON e.id = v.elementid
                     WHERE v.attemptid = :attemptid AND v.scoid = :scoid
                     ORDER BY v.id ASC",
                    ['attemptid' => $attemptrecord->id, 'scoid' => $sco->id]
                );
                foreach ($values as $v) {
                    $cmidata[] = ['element' => $v->element, 'value' => $v->value];
                }
            } else {
                // No attempt yet — will be created on first LMSCommit.
                $attempt = 1;
            }
        } else {
            // Legacy: scorm_scoes_track.
            $maxattempt = $DB->get_field('scorm_scoes_track', 'MAX(attempt)', [
                'scormid' => $scorm->id,
                'userid'  => $USER->id,
            ]);
            $attempt = $maxattempt ? (int)$maxattempt : 1;

            if ($maxattempt) {
                $tracks = $DB->get_records('scorm_scoes_track', [
                    'scormid' => $scorm->id,
                    'userid'  => $USER->id,
                    'scoid'   => $sco->id,
                    'attempt' => $attempt,
                ], 'id ASC');
                foreach ($tracks as $t) {
                    $cmidata[] = ['element' => $t->element, 'value' => $t->value];
                }
            }
        }

        // Build launch URL — point to the SCO's content directly.
        $launchurl = '';
        if (!empty($sco->launch)) {
            $baseurl = \moodle_url::make_pluginfile_url(
                $context->id, 'mod_scorm', 'content', 0, '/', ''
            )->out(false);
            // Remove trailing slash then append the SCO's launch file.
            $baseurl = rtrim($baseurl, '/');
            $launchurl = $baseurl . '/' . ltrim($sco->launch, '/');
        }

        // Base URL for relative resources inside the SCORM package.
        $packagebaseurl = \moodle_url::make_pluginfile_url(
            $context->id, 'mod_scorm', 'content', 0, '/', ''
        )->out(false);

        // Slide count.
        $slidecount = get_activity_content::detect_scorm_slides($context, $scorm->id);

        return [
            'success'    => true,
            'message'    => '',
            'launchurl'  => $launchurl,
            'baseurl'    => $packagebaseurl,
            'scormtype'  => $scormtype,
            'scoid'      => (int) $sco->id,
            'attempt'    => $attempt,
            'cmidata'    => $cmidata,
            'slidecount' => $slidecount,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'Whether data was retrieved'),
            'message'    => new external_value(PARAM_TEXT, 'Error message if failed'),
            'launchurl'  => new external_value(PARAM_RAW, 'SCO launch URL'),
            'baseurl'    => new external_value(PARAM_RAW, 'Package base URL for relative resources'),
            'scormtype'  => new external_value(PARAM_TEXT, 'scorm_12 or scorm_2004'),
            'scoid'      => new external_value(PARAM_INT, 'SCO ID'),
            'attempt'    => new external_value(PARAM_INT, 'Current attempt number'),
            'cmidata'    => new external_multiple_structure(
                new external_single_structure([
                    'element' => new external_value(PARAM_RAW, 'CMI element name'),
                    'value'   => new external_value(PARAM_RAW, 'CMI element value'),
                ]),
                'All stored CMI key-value pairs'
            ),
            'slidecount' => new external_value(PARAM_INT, 'Detected slide count'),
        ]);
    }
}

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

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * Returns grades & certificates page data (wraps grades_certificates_renderer::get_context).
 */
class get_grades_certificates_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);

        $renderer = new \local_sm_graphics_plugin\output\grades_certificates_renderer();
        return $renderer->get_context();
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid'       => new external_value(PARAM_INT, 'Course ID'),
                    'coursename'     => new external_value(PARAM_TEXT, 'Course name'),
                    'courseimage'     => new external_value(PARAM_RAW, 'Course image URL'),
                    'hascourseimage' => new external_value(PARAM_BOOL, 'Has course image'),
                    'grade'          => new external_value(PARAM_RAW, 'Final grade value'),
                    'grademax'       => new external_value(PARAM_RAW, 'Maximum grade value'),
                    'percentage'     => new external_value(PARAM_INT, 'Grade percentage 0-100'),
                    'hasgrade'       => new external_value(PARAM_BOOL, 'Has a grade'),
                    'gradetext'      => new external_value(PARAM_TEXT, 'Formatted grade text'),
                    'progress'       => new external_value(PARAM_INT, 'Course progress 0-100'),
                    'hascertificate' => new external_value(PARAM_BOOL, 'Certificate is available'),
                    'hours'          => new external_value(PARAM_FLOAT, 'Duration in hours'),
                    'hashours'       => new external_value(PARAM_BOOL, 'Has duration set'),
                    'downloadurl'    => new external_value(PARAM_RAW, 'Certificate download URL'),
                    'certcode'       => new external_value(PARAM_TEXT, 'Certificate code'),
                    'certdate'       => new external_value(PARAM_TEXT, 'Certificate issue date'),
                ])
            ),
            'hascourses'      => new external_value(PARAM_BOOL, 'Has enrolled courses'),
            'hascertificates' => new external_value(PARAM_BOOL, 'Has any certificate available'),
            'hasiomadcert'    => new external_value(PARAM_BOOL, 'IOMAD certificate module exists'),
            'downloadallurl'  => new external_value(PARAM_RAW, 'Download all certificates URL'),
            'languages' => new external_multiple_structure(
                new external_single_structure([
                    'code'     => new external_value(PARAM_TEXT, 'Language code'),
                    'label'    => new external_value(PARAM_TEXT, 'Language label'),
                    'selected' => new external_value(PARAM_BOOL, 'Is selected'),
                ])
            ),
            'username' => new external_value(PARAM_TEXT, 'Full name of current user'),
        ]);
    }
}

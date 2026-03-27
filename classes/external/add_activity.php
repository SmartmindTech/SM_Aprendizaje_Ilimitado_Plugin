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
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class add_activity extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
            'type'       => new external_value(PARAM_TEXT, 'Activity type: genially, url, page, etc.'),
            'name'       => new external_value(PARAM_TEXT, 'Activity name'),
            'url'        => new external_value(PARAM_URL, 'URL (for genially/url types)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $courseid, int $sectionnum, string $type,
            string $name, string $url = ''): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'   => $courseid,
            'sectionnum' => $sectionnum,
            'type'       => $type,
            'name'       => $name,
            'url'        => $url,
        ]);

        $context = \context_course::instance($params['courseid']);
        require_capability('moodle/course:update', $context);

        $courseid = $params['courseid'];
        $sectionnum = $params['sectionnum'];
        $type = $params['type'];
        $name = $params['name'];
        $url = $params['url'];

        // For genially and url types, create a mod_url activity.
        if ($type === 'genially' || $type === 'url') {
            if (empty($url)) {
                return ['success' => false, 'cmid' => 0, 'redirect_url' => ''];
            }

            $course = get_course($courseid);

            // Ensure the section exists.
            course_create_sections_if_missing($course, $sectionnum);

            // Get the module ID for 'url'.
            $urlmodule = $DB->get_record('modules', ['name' => 'url'], 'id', MUST_EXIST);

            // Build moduleinfo object.
            $moduleinfo = new \stdClass();
            $moduleinfo->modulename = 'url';
            $moduleinfo->module = $urlmodule->id;
            $moduleinfo->name = $name;
            $moduleinfo->course = $courseid;
            $moduleinfo->section = $sectionnum;
            $moduleinfo->visible = 1;
            $moduleinfo->externalurl = $url;
            $moduleinfo->display = RESOURCELIB_DISPLAY_EMBED;
            $moduleinfo->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
            $moduleinfo->cmidnumber = '';
            $moduleinfo->groupmode = 0;
            $moduleinfo->groupingid = 0;

            // Create the module.
            $moduleinfo = add_moduleinfo($moduleinfo, $course);

            return [
                'success'      => true,
                'cmid'         => (int) $moduleinfo->coursemodule,
                'redirect_url' => '',
            ];
        }

        // For other types, return a redirect URL to Moodle's standard add form.
        $modname = $type;
        $module = $DB->get_record('modules', ['name' => $modname]);
        if (!$module) {
            return ['success' => false, 'cmid' => 0, 'redirect_url' => ''];
        }

        $redirecturl = new \moodle_url('/course/modedit.php', [
            'add'     => $modname,
            'type'    => '',
            'course'  => $courseid,
            'section' => $sectionnum,
            'return'  => 0,
        ]);

        return [
            'success'      => true,
            'cmid'         => 0,
            'redirect_url' => $redirecturl->out(false),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'cmid'         => new external_value(PARAM_INT, 'Course module ID (0 if redirect)'),
            'redirect_url' => new external_value(PARAM_RAW, 'Redirect URL for standard Moodle form (empty if created directly)'),
        ]);
    }
}

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

namespace theme_smartmind\output\core;

use stdClass;
use core_course_list_element;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

/**
 * Course renderer overrides for theme_smartmind.
 *
 * @package    theme_smartmind
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_renderer extends \core_course_renderer {

    /**
     * Render enrolment options page with course image.
     *
     * @param stdClass $course
     * @param string[] $widgets
     * @param \core\url|null $returnurl
     * @return string
     */
    public function enrolment_options(stdClass $course, array $widgets, ?\core\url $returnurl = null): string {
        if (!$widgets) {
            if (isguestuser()) {
                $message = get_string('noguestaccess', 'enrol');
                $continuebutton = $this->output->continue_button(get_login_url());
            } else if ($returnurl) {
                $message = get_string('notenrollable', 'enrol');
                $continuebutton = $this->output->continue_button($returnurl);
            } else {
                $url = get_local_referer(false);
                if (empty($url)) {
                    $url = new moodle_url('/index.php');
                }
                $message = get_string('notenrollable', 'enrol');
                $continuebutton = $this->output->continue_button($url);
            }
        }

        // Get the course image.
        $courseimage = '';
        $courseobj = new core_course_list_element($course);
        foreach ($courseobj->get_course_overviewfiles() as $file) {
            $courseimage = moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                null, $file->get_filepath(), $file->get_filename()
            )->out();
            break;
        }

        // Category name.
        $categoryname = '';
        if (!empty($course->category)) {
            $category = \core_course_category::get($course->category, IGNORE_MISSING);
            if ($category) {
                $categoryname = $category->get_formatted_name();
            }
        }

        // Course dates.
        $startdate = $course->startdate ? userdate($course->startdate, get_string('strftimedatefullshort', 'langconfig')) : '';
        $enddate = !empty($course->enddate) ? userdate($course->enddate, get_string('strftimedatefullshort', 'langconfig')) : '';

        // Course contacts (teachers).
        $contacts = [];
        foreach ($courseobj->get_course_contacts() as $contact) {
            $contacts[] = ['contactname' => $contact['username']];
        }

        // Custom fields.
        $customfields = [];
        $handler = \core_course\customfield\course_handler::create();
        $customfielddata = $handler->get_instance_data($course->id, true);
        foreach ($customfielddata as $data) {
            $value = $data->export_value();
            if ($value !== null && $value !== '') {
                $customfields[] = [
                    'fieldname' => $data->get_field()->get('name'),
                    'fieldvalue' => $value,
                ];
            }
        }

        // Enrolled students count.
        $context = \context_course::instance($course->id);
        $enrolledcount = count_enrolled_users($context);

        // Course sections (topics).
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) {
                continue; // Skip general section.
            }
            $sectionname = get_section_name($course, $section);
            if (!empty($sectionname)) {
                $sections[] = ['sectionname' => $sectionname];
            }
        }

        // Last updated.
        $timemodified = !empty($course->timemodified) ? $course->timemodified : $course->timecreated;
        $lastupdated = !empty($timemodified) ? userdate($timemodified, get_string('strftimedatefullshort', 'langconfig')) : '';

        $templatecontext = [
            'heading' => get_string('enrolmentoptions', 'enrol'),
            'courseinfobox' => $this->course_info_box($course),
            'widgets' => array_values($widgets),
            'message' => $message ?? '',
            'continuebutton' => $continuebutton ?? '',
            'courseimage' => $courseimage,
            'coursename' => format_string($course->fullname),
            'courseshortname' => format_string($course->shortname),
            'coursesummary' => format_text($course->summary, $course->summaryformat, ['noclean' => true, 'para' => false]),
            'categoryname' => $categoryname,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'hasenddate' => !empty($course->enddate),
            'contacts' => $contacts,
            'hascontacts' => !empty($contacts),
            'customfields' => $customfields,
            'hascustomfields' => !empty($customfields),
            'enrolledcount' => (string) $enrolledcount,
            'sections' => $sections,
            'hassections' => !empty($sections),
            'sectioncount' => count($sections),
            'lastupdated' => $lastupdated,
        ];
        return $this->render_from_template('core_enrol/enrolment_options', $templatecontext);
    }
}

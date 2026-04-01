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
 * Course Loader from SharePoint - admin page.
 *
 * Allows admins to paste a SharePoint folder URL, scan its contents,
 * and automatically import a course into Moodle.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();

global $CFG, $OUTPUT, $PAGE;

if (!is_siteadmin()) {
    throw new moodle_exception('accessdenied', 'admin');
}

$component = 'local_sm_graphics_plugin';

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/courseloader.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('courseloader_title', $component));
$PAGE->set_heading(get_string('courseloader_title', $component));
$PAGE->set_pagelayout('standard');

// Check if SharePoint is configured.
$configured = \local_sm_graphics_plugin\sharepoint\client::is_configured();

// Load course categories for the dropdown.
$categories = [];
if ($configured) {
    $catlist = \core_course_category::make_categories_list();
    foreach ($catlist as $id => $name) {
        $categories[] = ['id' => $id, 'name' => $name];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/courseloader_page', [
    'title'       => get_string('courseloader_title', $component),
    'subtitle'    => get_string('courseloader_subtitle', $component),
    'configured'  => $configured,
    'no_config_msg' => get_string('courseloader_no_config', $component),
    'settings_url'  => (new moodle_url('/admin/settings.php', ['section' => 'local_sm_graphics_plugin']))->out(),
    'categories'  => $categories,
    'str' => [
        'folder_url'        => get_string('courseloader_folder_url', $component),
        'folder_placeholder' => get_string('courseloader_folder_url_placeholder', $component),
        'category'          => get_string('courseloader_category', $component),
        'scan'              => get_string('courseloader_scan', $component),
        'import'            => get_string('courseloader_import', $component),
        'scanning'          => get_string('courseloader_scanning', $component),
        'importing'         => get_string('courseloader_importing', $component),
        'scan_results'      => get_string('courseloader_scan_results', $component),
        'type'              => get_string('courseloader_file_type', $component),
        'count'             => get_string('courseloader_file_count', $component),
        'names'             => get_string('courseloader_file_names', $component),
        'warnings'          => get_string('courseloader_warnings', $component),
        'success'           => get_string('courseloader_success', $component),
        'go_to_course'      => get_string('courseloader_go_to_course', $component),
        'error'             => get_string('courseloader_error', $component),
    ],
]);
$PAGE->requires->js_call_amd('local_sm_graphics_plugin/courseloader', 'init');
echo $OUTPUT->footer();

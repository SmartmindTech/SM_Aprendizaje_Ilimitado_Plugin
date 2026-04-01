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
 * Course management page for site admins and company managers.
 *
 * Shows action cards (create, restore, assign) and a company overview table.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_login();

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

// Site admins and company managers may access this page.
$managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
if (!$managerrec && !is_siteadmin()) {
    throw new moodle_exception('accessdenied', 'admin');
}

$companyname = '';
if ($managerrec) {
    $companyname = $DB->get_field('company', 'name', ['id' => $managerrec->companyid]);
} else if (is_siteadmin()) {
    // Site admins may be assigned to a company in IOMAD.
    $rec = $DB->get_record('company_users', ['userid' => $USER->id], 'companyid', IGNORE_MULTIPLE);
    if ($rec) {
        $companyname = $DB->get_field('company', 'name', ['id' => $rec->companyid]);
    }
}

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/coursemanagement.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('coursemgmt_heading', 'local_sm_graphics_plugin'));
$PAGE->set_heading(!empty($companyname) ? $companyname : get_string('coursemgmt_heading', 'local_sm_graphics_plugin'));
$PAGE->set_pagelayout('standard');

$component = 'local_sm_graphics_plugin';
$base      = '/blocks/iomad_company_admin';

// Action cards.
$options = [
    [
        'url'         => (new moodle_url('/course/edit.php'))->out(),
        'icon'        => 'fa-circle-plus',
        'title'       => get_string('coursemgmt_create', $component),
        'description' => get_string('coursemgmt_create_desc', $component),
    ],
    [
        'url'         => (new moodle_url('/backup/restorefile.php', ['contextid' => context_system::instance()->id]))->out(),
        'icon'        => 'fa-upload',
        'title'       => get_string('coursemgmt_restore', $component),
        'description' => get_string('coursemgmt_restore_desc', $component),
    ],
    [
        'url'         => (new moodle_url($base . '/company_courses_form.php'))->out(),
        'icon'        => 'fa-building',
        'title'       => get_string('coursemgmt_assign', $component),
        'description' => get_string('coursemgmt_assign_desc', $component),
    ],
    [
        'url'         => (new moodle_url('/local/sm_graphics_plugin/pages/createcategory.php'))->out(),
        'icon'        => 'fa-folder-plus',
        'title'       => get_string('coursemgmt_createcat', $component),
        'description' => get_string('coursemgmt_createcat_desc', $component),
    ],
    [
        'url'         => (new moodle_url('/local/sm_graphics_plugin/pages/managecategories.php'))->out(),
        'icon'        => 'fa-folder-open',
        'title'       => get_string('coursemgmt_managecat', $component),
        'description' => get_string('coursemgmt_managecat_desc', $component),
    ],
    [
        'url'         => (new moodle_url('/local/sm_graphics_plugin/pages/courseloader.php'))->out(),
        'icon'        => 'fa-download',
        'title'       => get_string('coursemgmt_sharepoint', $component),
        'description' => get_string('coursemgmt_sharepoint_desc', $component),
    ],
];

// Company stats table.
$companies = \local_sm_graphics_plugin\external\get_company_stats::execute();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/coursemanagement_page', [
    'heading'      => get_string('coursemgmt_heading', $component),
    'companyname'  => $companyname,
    'options'      => $options,
    'companies'    => $companies,
    'hascompanies' => !empty($companies),
]);
echo $OUTPUT->footer();

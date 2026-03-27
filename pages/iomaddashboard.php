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
 * IOMAD dashboard page with SmartMind card-based layout.
 *
 * Replaces the native IOMAD icon dashboard with accordion categories
 * and action cards (same pattern as the manager's othermanagement page).
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
$companyid   = 0;
if ($managerrec) {
    $companyid   = $managerrec->companyid;
    $companyname = $DB->get_field('company', 'name', ['id' => $companyid]);
} else if (is_siteadmin()) {
    // Site admins: look up their company_users record (any role).
    $rec = $DB->get_record('company_users', ['userid' => $USER->id], 'companyid', IGNORE_MULTIPLE);
    if ($rec) {
        $companyid   = $rec->companyid;
        $companyname = $DB->get_field('company', 'name', ['id' => $companyid]);
    } else {
        // Fallback: use the first company in the system.
        $first = $DB->get_record_sql(
            'SELECT id, name FROM {company} ORDER BY name ASC',
            [],
            IGNORE_MULTIPLE
        );
        if ($first) {
            $companyid   = $first->id;
            $companyname = $first->name;
        }
    }
}

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/iomaddashboard.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('iomaddashboard_heading', 'local_sm_graphics_plugin'));
$PAGE->set_heading(!empty($companyname) ? $companyname : get_string('iomaddashboard_heading', 'local_sm_graphics_plugin'));
$PAGE->set_pagelayout('standard');

$component  = 'local_sm_graphics_plugin';

// Full IOMAD tab map — shows all categories (admins see everything).
$fulltabmap = [
    0 => ['key' => 'configuration',   'icon' => 'fa-cog',         'title' => get_string('iomad_configuration',   $component)],
    1 => ['key' => 'companies',       'icon' => 'fa-building',    'title' => get_string('othermgmt_companies',   $component)],
    2 => ['key' => 'users',           'icon' => 'fa-users',       'title' => get_string('iomad_users',           $component)],
    3 => ['key' => 'courses',         'icon' => 'fa-file-text',   'title' => get_string('othermgmt_courses',     $component)],
    4 => ['key' => 'licenses',        'icon' => 'fa-legal',       'title' => get_string('othermgmt_licenses',    $component)],
    5 => ['key' => 'competences',     'icon' => 'fa-cubes',       'title' => get_string('othermgmt_competences', $component)],
    6 => ['key' => 'emailtemplates',  'icon' => 'fa-envelope',    'title' => get_string('iomad_emailtemplates',  $component)],
    7 => ['key' => 'shop',            'icon' => 'fa-shopping-cart','title' => get_string('iomad_shop',            $component)],
    8 => ['key' => 'reports',         'icon' => 'fa-bar-chart-o', 'title' => get_string('othermgmt_reports',     $component)],
];

$categories = [];
if ($companyid) {
    $categories = local_sm_graphics_plugin_get_othermgmt_categories($component, $companyid, $fulltabmap);
}

// Mark first category for auto-expand.
if (!empty($categories)) {
    $categories[0]['isfirst'] = true;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/othermanagement_page', [
    'heading'     => get_string('iomaddashboard_heading', $component),
    'companyname' => $companyname,
    'categories'  => $categories,
    'fourcolumns' => true,
]);
echo $OUTPUT->footer();

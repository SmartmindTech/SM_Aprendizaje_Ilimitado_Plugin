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
 * User management options page for company managers.
 *
 * Shows a grid of cards linking to the IOMAD user-management pages
 * and a table listing every user that belongs to the manager's company.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_login();

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

// Only company managers may access this page.
$managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
if (!$managerrec) {
    throw new moodle_exception('accessdenied', 'admin');
}

$companyname = $DB->get_field('company', 'name', ['id' => $managerrec->companyid]);

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title($companyname);
$PAGE->set_heading($companyname);
$PAGE->set_pagelayout('standard');

$component = 'local_sm_graphics_plugin';
$base      = '/blocks/iomad_company_admin';

// Handle user deletion.
$deleteuser = optional_param('deleteuser', 0, PARAM_INT);
if ($deleteuser) {
    require_sesskey();
    delete_user($DB->get_record('user', ['id' => $deleteuser], '*', MUST_EXIST));
    redirect($PAGE->url, get_string('usermgmt_deleted', $component));
}

// --- Management option cards (5). -------------------------------------------

$options = [
    [
        'url'         => (new moodle_url("$base/company_user_create_form.php"))->out(),
        'icon'        => 'fa-user-plus',
        'title'       => get_string('usermgmt_createuser', $component),
        'description' => get_string('usermgmt_createuser_desc', $component),
    ],
    [
        'url'         => (new moodle_url("$base/editusers.php"))->out(),
        'icon'        => 'fa-pen',
        'title'       => get_string('usermgmt_editusers', $component),
        'description' => get_string('usermgmt_editusers_desc', $component),
    ],
    [
        'url'         => (new moodle_url("$base/company_departments.php"))->out(),
        'icon'        => 'fa-users-line',
        'title'       => get_string('usermgmt_deptusers', $component),
        'description' => get_string('usermgmt_deptusers_desc', $component),
    ],
    [
        'url'         => (new moodle_url('/local/sm_graphics_plugin/pages/uploadusers.php'))->out(),
        'icon'        => 'fa-upload',
        'title'       => get_string('usermgmt_uploadusers', $component),
        'description' => get_string('usermgmt_uploadusers_desc', $component),
    ],
    [
        'url'         => (new moodle_url("$base/user_bulk_download.php"))->out(),
        'icon'        => 'fa-download',
        'title'       => get_string('usermgmt_bulkdownload', $component),
        'description' => get_string('usermgmt_bulkdownload_desc', $component),
    ],
];

// --- Company student limit. -------------------------------------------------

$companyid    = $managerrec->companyid;
$studentcount = local_sm_graphics_plugin_get_company_student_count($companyid);
$maxstudents  = local_sm_graphics_plugin_get_company_limit($companyid);
$limitreached = local_sm_graphics_plugin_is_company_limit_reached($companyid);
$haslimit     = ($maxstudents > 0);

// Disable creation cards when limit is reached.
if ($limitreached) {
    $options[0]['disabled'] = true; // Create user.
    $options[3]['disabled'] = true; // Upload users.
}

// --- Company user listing (paginated). ---------------------------------------

$perpage = 15;
$page    = optional_param('page', 0, PARAM_INT);
$users   = local_sm_graphics_plugin_get_company_users($companyid, $page, $perpage);

$pagingbar = $OUTPUT->paging_bar($studentcount, $page, $perpage, $PAGE->url);

// --- Render. ----------------------------------------------------------------

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/usermanagement_page', [
    'heading'          => get_string('usermgmt_heading', $component),
    'options'          => $options,
    'users'            => $users,
    'hasusers'         => !empty($users),
    'usercount'        => count($users),
    'studentcount'     => $studentcount,
    'maxstudents'      => $maxstudents,
    'haslimit'         => $haslimit,
    'limitreached'     => $limitreached,
    'limit_reached_msg'=> get_string('usermgmt_limit_reached', $component),
    'th_name'          => get_string('usermgmt_th_name', $component),
    'th_email'         => get_string('usermgmt_th_email', $component),
    'th_lastaccess'    => get_string('usermgmt_th_lastaccess', $component),
    'th_actions'       => get_string('usermgmt_th_actions', $component),
    'userlist_heading' => get_string('usermgmt_userlist', $component),
    'edit_label'       => get_string('usermgmt_edit', $component),
    'delete_label'     => get_string('usermgmt_delete', $component),
    'delete_confirm'   => get_string('usermgmt_delete_confirm', $component),
    'nousers'          => get_string('usermgmt_nousers', $component),
    'pagingbar'        => $pagingbar,
]);
echo $OUTPUT->footer();

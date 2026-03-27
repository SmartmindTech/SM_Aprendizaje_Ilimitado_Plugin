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
 * Admin page: manage maximum student limits per company.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $OUTPUT, $PAGE;

$component = 'local_sm_graphics_plugin';

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/companylimits.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('companylimits_title', $component));
$PAGE->set_heading(get_string('companylimits_title', $component));
$PAGE->set_pagelayout('admin');

// Handle POST — save limits.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $limits = optional_param_array('limits', [], PARAM_INT);
    foreach ($limits as $companyid => $maxstudents) {
        local_sm_graphics_plugin_save_company_limit((int) $companyid, max(0, (int) $maxstudents));
    }
    redirect($PAGE->url, get_string('companylimits_saved', $component));
}

// GET — fetch data and render.
$companies = local_sm_graphics_plugin_get_all_company_limits();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/companylimits_page', [
    'heading'        => get_string('companylimits_title', $component),
    'help'           => get_string('companylimits_help', $component),
    'companies'      => $companies,
    'hascompanies'   => !empty($companies),
    'sesskey'        => sesskey(),
    'actionurl'      => $PAGE->url->out(false),
    'th_company'     => get_string('companylimits_th_company', $component),
    'th_shortname'   => get_string('companylimits_th_shortname', $component),
    'th_students'    => get_string('companylimits_th_students', $component),
    'th_maxlimit'    => get_string('companylimits_th_maxlimit', $component),
    'th_status'      => get_string('companylimits_th_status', $component),
    'save_label'     => get_string('companylimits_save', $component),
    'unlimited_label'=> get_string('companylimits_unlimited', $component),
    'ok_label'       => get_string('companylimits_ok', $component),
    'full_label'     => get_string('companylimits_full', $component),
]);
echo $OUTPUT->footer();

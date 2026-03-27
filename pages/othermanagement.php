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
 * Other management options page for company managers.
 *
 * Each category (Companies, Courses, Licenses, Competences, Reports)
 * expands to reveal its specific action cards.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_login();

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

$managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
if (!$managerrec) {
    throw new moodle_exception('accessdenied', 'admin');
}

$companyname = $DB->get_field('company', 'name', ['id' => $managerrec->companyid]);

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/othermanagement.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title($companyname);
$PAGE->set_heading($companyname);
$PAGE->set_pagelayout('standard');

$component = 'local_sm_graphics_plugin';

$categories = local_sm_graphics_plugin_get_othermgmt_categories($component, $managerrec->companyid);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/othermanagement_page', [
    'heading'    => get_string('othermgmt_heading', $component),
    'categories' => $categories,
]);
echo $OUTPUT->footer();

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
 * Welcome page.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();

global $CFG, $USER, $OUTPUT, $PAGE;

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/welcome.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('welcome_title', 'local_sm_graphics_plugin'));
$PAGE->set_heading(get_string('welcome_heading', 'local_sm_graphics_plugin'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/welcome_page', [
    'username' => fullname($USER),
    'siteurl'  => $CFG->wwwroot,
]);
echo $OUTPUT->footer();

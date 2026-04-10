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

defined('MOODLE_INTERNAL') || die();

/**
 * A login page layout for the boost theme.
 *
 * @package   theme_smartmind
 * @copyright 2016 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Redirect to the Vue SPA login page if the plugin is installed.
// The SPA handles its own login UI and POSTs credentials back to Moodle.
// On failed login POST, Moodle re-renders this layout → pass error flag.
$spapath = $CFG->dirroot . '/local/sm_graphics_plugin/pages/spa.php';
if (file_exists($spapath)) {
    $params = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $params['loginerror'] = 1;
    }
    redirect(new moodle_url('/local/sm_graphics_plugin/pages/spa.php', $params, 'login'));
}

$bodyattributes = $OUTPUT->body_attributes();

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'wwwroot' => $CFG->wwwroot,
];

echo $OUTPUT->render_from_template('theme_smartmind/login', $templatecontext);


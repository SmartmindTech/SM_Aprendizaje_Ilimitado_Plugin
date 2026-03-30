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
 * Single entry point for the Vue/Nuxt SPA frontend.
 *
 * This page replaces all Mustache-based pages. It:
 * 1. Authenticates the user via Moodle session
 * 2. Injects bootstrap data (user, sesskey, config) as JSON
 * 3. Serves the Nuxt-generated SPA from frontend_dist/
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_login();

global $CFG, $USER, $DB, $OUTPUT, $PAGE;

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/spa.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_sm_graphics_plugin'));
$PAGE->set_pagelayout('spa');

// Build bootstrap data for the Vue app.
$managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
$isadmin = is_siteadmin();

$companyid = 0;
$companyname = '';
if ($managerrec) {
    $companyid = $managerrec->companyid;
    $companyname = $DB->get_field('company', 'name', ['id' => $companyid]) ?: '';
} else if ($isadmin) {
    $rec = $DB->get_record('company_users', ['userid' => $USER->id], 'companyid', IGNORE_MULTIPLE);
    if ($rec) {
        $companyid = $rec->companyid;
        $companyname = $DB->get_field('company', 'name', ['id' => $companyid]) ?: '';
    }
}

$bootstrapdata = [
    'wwwroot'     => $CFG->wwwroot,
    'sesskey'     => sesskey(),
    'userid'      => (int) $USER->id,
    'fullname'    => fullname($USER),
    'email'       => $USER->email,
    'lang'        => current_language(),
    'ismanager'   => !empty($managerrec),
    'isadmin'     => $isadmin,
    'companyid'   => (int) $companyid,
    'companyname' => $companyname,
    'pluginbaseurl' => $CFG->wwwroot . '/local/sm_graphics_plugin',
];

// Path to the Nuxt-generated SPA.
$distpath = __DIR__ . '/../frontend_dist/index.html';

if (file_exists($distpath)) {
    // Serve the SPA directly — bypass Moodle's layout completely.
    // The SPA provides its own HTML, CSS, JS (Bootstrap 5, Vue, etc.).
    $html = file_get_contents($distpath);

    // Inject bootstrap data right before </head> so the Vue app can read it on mount.
    $bootstrapscript = '<script>window.__MOODLE_BOOTSTRAP__ = '
        . json_encode($bootstrapdata, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
    $html = str_replace('</head>', $bootstrapscript . '</head>', $html);

    // Output the full HTML document (not through Moodle's renderer).
    echo $html;
    exit;
} else {
    // Dev mode fallback: show instructions when frontend hasn't been built yet.
    echo $OUTPUT->header();
    echo '<div style="padding:2rem;font-family:system-ui;max-width:600px;margin:0 auto">';
    echo '<h2>SmartMind SPA</h2>';
    echo '<p>The frontend has not been built yet. Run:</p>';
    echo '<pre style="background:#f5f5f5;padding:1rem;border-radius:8px">';
    echo "cd frontend\nnpm install\nnpm run deploy";
    echo '</pre>';
    echo '<p>Or for development with HMR:</p>';
    echo '<pre style="background:#f5f5f5;padding:1rem;border-radius:8px">';
    echo "cd frontend\nnpm run dev";
    echo '</pre>';
    echo '</div>';
    echo $OUTPUT->footer();
}

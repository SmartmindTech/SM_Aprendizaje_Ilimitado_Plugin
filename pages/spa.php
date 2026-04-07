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

// Try to fetch HTML from a running Nuxt dev server first (instantaneous HMR).
// If unreachable, fall back to the pre-built frontend_dist/.
//
// Enable by setting SPA_DEV=1 (and optionally SPA_DEV_PORT=<port>) in .env.
// Default port is 4173. The script tries the host.docker.internal alias first
// (Docker Desktop / WSL2) then plain localhost. The first one that responds
// wins. Browser stays at the Moodle URL — only the asset origin changes.
$devhtml = null;
$devmode = false;
$devport = 4173;
$envfile = __DIR__ . '/../.env';
if (file_exists($envfile)) {
    foreach (file($envfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envline) {
        if (strpos($envline, 'SPA_DEV=1') === 0) {
            $devmode = true;
        } else if (strpos($envline, 'SPA_DEV_PORT=') === 0) {
            $devport = (int) trim(substr($envline, 13));
        }
    }
}

if ($devmode) {
    // Build the list of host candidates to probe. Order matters: the first
    // reachable one wins. host.docker.internal works on Docker Desktop and
    // newer Linux Docker (with extra_hosts: host-gateway). On plain Linux
    // Docker without that flag, the host is reachable via the container's
    // default gateway, which we discover dynamically from /proc/net/route.
    $hosts = ['host.docker.internal', 'localhost'];
    $route = @file('/proc/net/route');
    if ($route !== false) {
        foreach ($route as $line) {
            $cols = preg_split('/\s+/', trim($line));
            if (count($cols) >= 3 && $cols[1] === '00000000' && ctype_xdigit($cols[2])) {
                // Gateway field is little-endian hex.
                $hex = $cols[2];
                $gw  = hexdec(substr($hex, 6, 2)) . '.'
                     . hexdec(substr($hex, 4, 2)) . '.'
                     . hexdec(substr($hex, 2, 2)) . '.'
                     . hexdec(substr($hex, 0, 2));
                if ($gw !== '0.0.0.0') {
                    $hosts[] = $gw;
                }
                break;
            }
        }
    }

    foreach ($hosts as $host) {
        $origin = 'http://' . $host . ':' . $devport;
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 1,
                'header'  => "Accept: text/html\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($origin . '/', false, $ctx);
        if ($body !== false && stripos($body, '<html') !== false) {
            $devhtml = $body;
            break;
        }
    }
}

if ($devmode && $devhtml === null) {
    // SPA_DEV=1 was requested but the Nuxt dev server is not reachable.
    // Don't silently fall back to a possibly stale frontend_dist/ — that
    // leads to "Failed to load module script" errors when index.html
    // references hashed files that no longer exist on disk. Instead show
    // a clear, actionable error.
    echo $OUTPUT->header();
    echo '<div style="padding:2rem;font-family:system-ui;max-width:720px;margin:0 auto">';
    echo '<h2>SmartMind SPA dev mode</h2>';
    echo '<p><code>SPA_DEV=1</code> is set in <code>.env</code> but the Nuxt dev server is not reachable on port <strong>' . (int) $devport . '</strong>.</p>';
    echo '<p>Start it in another terminal:</p>';
    echo '<pre style="background:#f5f5f5;padding:1rem;border-radius:8px">cd frontend
npm install   # only if you have not yet
npm run dev</pre>';
    echo '<p>Then reload this page. The watcher script (<code>watch.sh</code> / <code>watch.ps1</code>) is supposed to start it automatically — check <code>scripts/docker_*/.nuxt-dev.log</code> for errors.</p>';
    echo '<p>Alternatively, set <code>SPA_DEV=0</code> in <code>.env</code> and run <code>npm run deploy</code> once to use the static build.</p>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

if ($devhtml !== null) {
    // The browser loads this page from the Moodle origin (e.g. localhost:8081)
    // but the proxied HTML uses relative URLs that need to resolve against the
    // Nuxt dev server (e.g. localhost:4173). We CANNOT use <base href> for this
    // because base href would also rewrite NuxtLink navigation URLs and kick
    // the user out of Moodle's origin on every click. Instead, rewrite all
    // asset/module URLs in src=, href=, and import statements to absolute
    // localhost:<devport> URLs server-side. NuxtLinks (which render as
    // <a href="#/...">) keep working against the Moodle origin.
    $devorigin = 'http://localhost:' . (int) $devport;
    $devhtml = preg_replace(
        '#(\b(?:src|href)=")(/(?:_nuxt/|@id/|@vite/|@fs/|__nuxt_island/|node_modules/))#',
        '$1' . $devorigin . '$2',
        $devhtml
    );

    $bootstrapscript = '<script>window.__MOODLE_BOOTSTRAP__ = '
        . json_encode($bootstrapdata, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
    // Inject the bootstrap as the FIRST script in <head> so it runs before
    // any module imports.
    $devhtml = preg_replace('/<head([^>]*)>/i', '<head$1>' . $bootstrapscript, $devhtml, 1);
    echo $devhtml;
    exit;
}

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

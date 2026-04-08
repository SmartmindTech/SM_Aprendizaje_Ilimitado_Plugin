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
 * Auto-update page for SmartMind Graphic Layer Plugin + Theme.
 *
 * Downloads latest code from GitHub dev branch for both
 * the plugin and the SmartMind theme, then triggers Moodle upgrade.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

if (!is_siteadmin()) {
    throw new moodle_exception('accessdenied', 'admin');
}

require_once(__DIR__ . '/classes/update_checker.php');

$confirm = optional_param('confirm', 0, PARAM_BOOL);
$force   = optional_param('force',   0, PARAM_BOOL);

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/update.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('update_page_title', 'local_sm_graphics_plugin'));
$PAGE->set_heading(get_string('update_page_title', 'local_sm_graphics_plugin'));
$PAGE->set_pagelayout('admin');

// Get current and available versions.
$plugin = core_plugin_manager::instance()->get_plugin_info('local_sm_graphics_plugin');
$currentversion = $plugin->versiondisk;
$currentrelease = $plugin->release ?? 'unknown';

// Fetch update info.
$updateinfo = \local_sm_graphics_plugin\update_checker::fetch_update_info();

if (!$updateinfo) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('update_fetch_error', 'local_sm_graphics_plugin'), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/admin/settings.php', ['section' => 'local_sm_graphics_plugin']));
    echo $OUTPUT->footer();
    exit;
}

// Check if update is needed. Skip this gate when ?force=1 is passed so admins
// can re-apply the latest release after a partial sync or hot-patch.
if (!$force && $updateinfo['version'] <= $currentversion) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('update_uptodate', 'local_sm_graphics_plugin', $currentrelease), 'info');

    // Offer a "Force reinstall latest" escape hatch.
    $forceurl = new moodle_url('/local/sm_graphics_plugin/update.php', [
        'confirm' => 1,
        'force'   => 1,
        'sesskey' => sesskey(),
    ]);
    echo html_writer::tag('p',
        html_writer::link($forceurl,
            get_string('update_force_reinstall', 'local_sm_graphics_plugin'),
            ['class' => 'btn btn-warning']
        ),
        ['class' => 'mt-3']
    );
    echo html_writer::tag('p',
        get_string('update_force_reinstall_desc', 'local_sm_graphics_plugin'),
        ['class' => 'text-muted small']
    );

    echo $OUTPUT->continue_button(new moodle_url('/admin/settings.php', ['section' => 'local_sm_graphics_plugin']));
    echo $OUTPUT->footer();
    exit;
}

if ($confirm && confirm_sesskey()) {
    // Perform the update.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('update_installing', 'local_sm_graphics_plugin'));

    $success = perform_plugin_and_theme_update($updateinfo);

    if ($success) {
        echo $OUTPUT->notification(get_string('update_success', 'local_sm_graphics_plugin'), 'success');
        $upgradeurl = new moodle_url('/admin/index.php');
        echo $OUTPUT->continue_button($upgradeurl);
    } else {
        echo $OUTPUT->notification(get_string('update_failed', 'local_sm_graphics_plugin'), 'error');
        echo $OUTPUT->continue_button(new moodle_url('/admin/settings.php', ['section' => 'local_sm_graphics_plugin']));
    }

    echo $OUTPUT->footer();
    exit;
}

// Show confirmation page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('update_available', 'local_sm_graphics_plugin'));

// Version info table.
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->data = [
    [get_string('update_current_version', 'local_sm_graphics_plugin'), $currentrelease . ' (' . $currentversion . ')'],
    [get_string('update_new_version', 'local_sm_graphics_plugin'), $updateinfo['release'] . ' (' . $updateinfo['version'] . ')'],
];
echo html_writer::table($table);

echo html_writer::tag('p', get_string('update_confirm', 'local_sm_graphics_plugin'), ['class' => 'alert alert-warning']);

// Confirmation buttons.
$confirmurl = new moodle_url('/local/sm_graphics_plugin/update.php', ['confirm' => 1, 'sesskey' => sesskey()]);
$cancelurl = new moodle_url('/admin/settings.php', ['section' => 'local_sm_graphics_plugin']);

echo $OUTPUT->confirm(
    get_string('update_confirm_question', 'local_sm_graphics_plugin'),
    $confirmurl,
    $cancelurl
);

echo $OUTPUT->footer();

/**
 * Perform the plugin update from the CI-built release zip.
 *
 * The release zip contains the whole plugin at its root (sm_graphics_plugin/),
 * including the bundled theme at theme_smartmind/. The theme is deployed to
 * /theme/smartmind/ by the plugin's upgrade hook (deploy_theme()), so no
 * separate theme download is needed.
 *
 * @param array $updateinfo Update information from update.xml. Must contain
 *                          a 'download' key pointing at the release zip.
 * @return bool True on success.
 */
function perform_plugin_and_theme_update(array $updateinfo): bool {
    global $CFG;

    if (empty($updateinfo['download'])) {
        echo html_writer::tag('p', 'No download URL in update.xml.', ['class' => 'text-danger']);
        return false;
    }
    $pluginzip = $updateinfo['download'];
    $tempdir = make_temp_directory('local_sm_graphics_plugin_update');

    // --- Step 1: Download the release zip ---
    echo html_writer::tag('h4', get_string('update_step_plugin', 'local_sm_graphics_plugin'));
    echo html_writer::tag('p', get_string('update_downloading', 'local_sm_graphics_plugin'));

    $pluginzipfile = $tempdir . '/plugin_update.zip';
    if (!download_zip($pluginzip, $pluginzipfile)) {
        return false;
    }

    echo html_writer::tag('p', '&#10003; ' . get_string('update_downloaded', 'local_sm_graphics_plugin') .
        ' (' . round(filesize($pluginzipfile) / 1024) . ' KB)', ['class' => 'text-success']);

    $pluginsource = extract_zip_to_temp($pluginzipfile, $tempdir . '/plugin_extracted');
    if (!$pluginsource) {
        return false;
    }

    $plugintarget = $CFG->dirroot . '/local/sm_graphics_plugin';
    if (!is_writable($plugintarget)) {
        echo html_writer::tag('p', get_string('update_not_writable', 'local_sm_graphics_plugin') .
            ': ' . $plugintarget, ['class' => 'text-danger']);
        return false;
    }

    echo html_writer::tag('p', get_string('update_copying', 'local_sm_graphics_plugin'));
    $result = recursive_copy_overwrite($pluginsource, $plugintarget);
    if (!$result['success']) {
        echo html_writer::tag('p', get_string('update_copy_failed', 'local_sm_graphics_plugin') .
            ': ' . $result['error'], ['class' => 'text-danger']);
        return false;
    }
    echo html_writer::tag('p', '&#10003; ' . $result['count'] . ' ' .
        get_string('update_files_copied', 'local_sm_graphics_plugin'), ['class' => 'text-success']);

    // Build AMD JS: copy src/*.js → build/*.min.js.
    $amdsrc = $plugintarget . '/amd/src';
    $amdbuild = $plugintarget . '/amd/build';
    if (is_dir($amdsrc)) {
        if (!is_dir($amdbuild)) {
            @mkdir($amdbuild, 0755, true);
        }
        foreach (glob($amdsrc . '/*.js') as $srcfile) {
            $base = basename($srcfile, '.js');
            @copy($srcfile, $amdbuild . '/' . $base . '.min.js');
        }
    }

    // --- Step 2: Deploy the bundled theme to /theme/smartmind/ ---
    // The release zip already carries theme_smartmind/ inside the plugin
    // directory. Call the plugin's own deploy_theme() helper (same one
    // used by install.php / upgrade.php) to copy it into place.
    echo html_writer::tag('h4', get_string('update_step_theme', 'local_sm_graphics_plugin'));
    require_once($plugintarget . '/db/install.php');
    if (function_exists('local_sm_graphics_plugin_deploy_theme')) {
        local_sm_graphics_plugin_deploy_theme();
        echo html_writer::tag('p', '&#10003; Theme deployed from bundled release', ['class' => 'text-success']);
    }

    // --- Step 3: Clean up and purge caches ---
    @unlink($pluginzipfile);
    recursive_delete($tempdir . '/plugin_extracted');

    purge_all_caches();

    echo html_writer::tag('p', '&#10003; ' . get_string('update_caches_purged', 'local_sm_graphics_plugin'),
        ['class' => 'text-success']);

    return true;
}

/**
 * Download a ZIP file from a URL.
 *
 * @param string $url URL to download from.
 * @param string $filepath Local path to save to.
 * @return bool True on success.
 */
function download_zip(string $url, string $filepath): bool {
    $curl = new curl(['cache' => false]);
    $curl->setopt([
        'CURLOPT_TIMEOUT' => 120,
        'CURLOPT_FOLLOWLOCATION' => true,
        'CURLOPT_SSL_VERIFYPEER' => false,
    ]);

    $curl->download_one($url, null, ['filepath' => $filepath]);

    if (!file_exists($filepath) || filesize($filepath) < 1000) {
        echo html_writer::tag('p', get_string('update_download_failed', 'local_sm_graphics_plugin') .
            ' (curl error: ' . $curl->error . ')', ['class' => 'text-danger']);
        return false;
    }

    return true;
}

/**
 * Extract a ZIP file and return the path to the extracted source folder.
 *
 * @param string $zipfile Path to ZIP file.
 * @param string $extractdir Directory to extract into.
 * @return string|false Path to extracted folder, or false on failure.
 */
function extract_zip_to_temp(string $zipfile, string $extractdir) {
    $zip = new ZipArchive();
    if ($zip->open($zipfile) !== true) {
        echo html_writer::tag('p', get_string('update_extract_failed', 'local_sm_graphics_plugin'),
            ['class' => 'text-danger']);
        return false;
    }

    @mkdir($extractdir, 0777, true);
    $zip->extractTo($extractdir);
    $zip->close();

    // GitHub ZIPs have a single root folder named "RepoName-branchname".
    $folders = glob($extractdir . '/*', GLOB_ONLYDIR);
    if (empty($folders)) {
        echo html_writer::tag('p', get_string('update_extract_failed', 'local_sm_graphics_plugin'),
            ['class' => 'text-danger']);
        return false;
    }

    return $folders[0];
}

/**
 * Recursively copy files from source to destination, overwriting existing files.
 *
 * @param string $src Source directory.
 * @param string $dst Destination directory.
 * @return array Result with 'success', 'count', and 'error' keys.
 */
function recursive_copy_overwrite(string $src, string $dst): array {
    $result = ['success' => true, 'count' => 0, 'error' => ''];

    $dir = @opendir($src);
    if (!$dir) {
        $result['success'] = false;
        $result['error'] = "Cannot open source directory: $src";
        return $result;
    }

    if (!is_dir($dst)) {
        if (!@mkdir($dst, 0755, true)) {
            $result['success'] = false;
            $result['error'] = "Cannot create directory: $dst";
            closedir($dir);
            return $result;
        }
    }

    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        // Skip .git directories.
        if ($file === '.git') {
            continue;
        }

        $srcpath = $src . '/' . $file;
        $dstpath = $dst . '/' . $file;

        if (is_dir($srcpath)) {
            $subresult = recursive_copy_overwrite($srcpath, $dstpath);
            if (!$subresult['success']) {
                closedir($dir);
                return $subresult;
            }
            $result['count'] += $subresult['count'];
        } else {
            // If the destination file already exists but isn't writable (e.g.
            // it was created by `docker cp` as root and Apache can't overwrite
            // it), copy() silently fails. Try to unlink it first so the next
            // copy() lands on a fresh inode owned by the web user.
            if (file_exists($dstpath) && !is_writable($dstpath)) {
                @chmod($dstpath, 0666);
            }
            if (file_exists($dstpath)) {
                @unlink($dstpath);
            }
            if (!@copy($srcpath, $dstpath)) {
                $err = error_get_last();
                $detail = $err['message'] ?? 'unknown error';
                $result['success'] = false;
                $result['error'] = "Cannot copy file: $srcpath to $dstpath ($detail)";
                closedir($dir);
                return $result;
            }
            $result['count']++;
        }
    }

    closedir($dir);
    return $result;
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory to delete.
 */
function recursive_delete(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            recursive_delete($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

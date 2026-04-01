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
 * Auto-update page for SmartMind Graphics Plugin.
 *
 * Downloads the release ZIP from GitHub, extracts plugin + theme,
 * copies files and redirects to Moodle upgrade.
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
    echo $OUTPUT->continue_button(new moodle_url('/admin/index.php'));
    echo $OUTPUT->footer();
    exit;
}

// Check if update is needed.
if ($updateinfo['version'] <= $currentversion) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('update_uptodate', 'local_sm_graphics_plugin', $currentrelease), 'info');
    echo $OUTPUT->continue_button(new moodle_url('/admin/index.php'));
    echo $OUTPUT->footer();
    exit;
}

if ($confirm && confirm_sesskey()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('update_installing', 'local_sm_graphics_plugin'));

    $success = perform_plugin_update($updateinfo);

    if ($success) {
        echo $OUTPUT->notification(get_string('update_success', 'local_sm_graphics_plugin'), 'success');
        $upgradeurl = new moodle_url('/admin/index.php');
        echo $OUTPUT->continue_button($upgradeurl);
    } else {
        echo $OUTPUT->notification(get_string('update_failed', 'local_sm_graphics_plugin'), 'error');
        show_manual_update_instructions($updateinfo);
        echo $OUTPUT->continue_button(new moodle_url('/admin/index.php'));
    }

    echo $OUTPUT->footer();
    exit;
}

// Show confirmation page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('update_available', 'local_sm_graphics_plugin'));

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->data = [
    [get_string('update_current_version', 'local_sm_graphics_plugin'), $currentrelease . ' (' . $currentversion . ')'],
    [get_string('update_new_version', 'local_sm_graphics_plugin'), $updateinfo['release'] . ' (' . $updateinfo['version'] . ')'],
];
echo html_writer::table($table);

$confirmurl = new moodle_url('/local/sm_graphics_plugin/update.php', ['confirm' => 1, 'sesskey' => sesskey()]);
$cancelurl = new moodle_url('/admin/index.php');

echo $OUTPUT->confirm(
    get_string('update_confirm', 'local_sm_graphics_plugin'),
    $confirmurl,
    $cancelurl
);

echo $OUTPUT->footer();

/**
 * Perform the plugin update from GitHub release ZIP.
 *
 * The release ZIP contains sm_graphics_plugin/ with the theme bundled inside.
 *
 * @param array $updateinfo Update information from update.xml.
 * @return bool True on success.
 */
function perform_plugin_update(array $updateinfo): bool {
    global $CFG;

    $downloadurl = $updateinfo['download'];
    if (empty($downloadurl)) {
        echo html_writer::tag('p', get_string('update_download_failed', 'local_sm_graphics_plugin'), ['class' => 'text-danger']);
        return false;
    }

    $tempdir = make_temp_directory('local_sm_graphics_plugin_update');
    $zipfile = $tempdir . '/plugin_update.zip';

    // --- Step 1: Download release ZIP ---
    echo html_writer::tag('h4', get_string('update_step_plugin', 'local_sm_graphics_plugin'));
    echo html_writer::tag('p', get_string('update_downloading', 'local_sm_graphics_plugin'));

    $curl = new curl(['cache' => false]);
    $curl->setopt([
        'CURLOPT_TIMEOUT' => 120,
        'CURLOPT_FOLLOWLOCATION' => true,
        'CURLOPT_SSL_VERIFYPEER' => false,
    ]);

    $curl->download_one($downloadurl, null, ['filepath' => $zipfile]);

    if (!file_exists($zipfile) || filesize($zipfile) < 1000) {
        echo html_writer::tag('p', get_string('update_download_failed', 'local_sm_graphics_plugin') .
            ' (' . $curl->error . ')', ['class' => 'text-danger']);
        return false;
    }

    echo html_writer::tag('p', '&#10003; ' . get_string('update_downloaded', 'local_sm_graphics_plugin') .
        ' (' . round(filesize($zipfile) / 1024) . ' KB)', ['class' => 'text-success']);

    // --- Step 2: Extract ZIP ---
    $zip = new ZipArchive();
    if ($zip->open($zipfile) !== true) {
        echo html_writer::tag('p', get_string('update_extract_failed', 'local_sm_graphics_plugin'), ['class' => 'text-danger']);
        return false;
    }

    $extractdir = $tempdir . '/extracted';
    @mkdir($extractdir, 0777, true);
    $zip->extractTo($extractdir);
    $zip->close();

    // Release ZIP contains a single folder: sm_graphics_plugin/
    $folders = glob($extractdir . '/*', GLOB_ONLYDIR);
    if (empty($folders)) {
        echo html_writer::tag('p', get_string('update_extract_failed', 'local_sm_graphics_plugin'), ['class' => 'text-danger']);
        return false;
    }
    $sourcedir = $folders[0];

    // --- Step 3: Copy plugin files ---
    $plugintarget = $CFG->dirroot . '/local/sm_graphics_plugin';
    if (!is_writable($plugintarget)) {
        echo html_writer::tag('p', get_string('update_not_writable', 'local_sm_graphics_plugin') .
            ': ' . $plugintarget, ['class' => 'text-danger']);
        return false;
    }

    echo html_writer::tag('p', get_string('update_copying', 'local_sm_graphics_plugin'));
    $result = recursive_copy_overwrite($sourcedir, $plugintarget);
    if (!$result['success']) {
        echo html_writer::tag('p', get_string('update_copy_failed', 'local_sm_graphics_plugin') .
            ': ' . $result['error'], ['class' => 'text-danger']);
        return false;
    }
    echo html_writer::tag('p', '&#10003; ' . $result['count'] . ' ' .
        get_string('update_files_copied', 'local_sm_graphics_plugin'), ['class' => 'text-success']);

    // --- Step 4: Deploy theme from bundled theme_smartmind/ ---
    echo html_writer::tag('h4', get_string('update_step_theme', 'local_sm_graphics_plugin'));
    echo html_writer::tag('p', get_string('update_copying', 'local_sm_graphics_plugin'));

    $themesource = $plugintarget . '/theme_smartmind';
    $themetarget = $CFG->dirroot . '/theme/smartmind';

    if (is_dir($themesource)) {
        if (!is_dir($themetarget)) {
            @mkdir($themetarget, 0755, true);
        }
        $themeresult = recursive_copy_overwrite($themesource, $themetarget);
        if (!$themeresult['success']) {
            echo html_writer::tag('p', get_string('update_copy_failed', 'local_sm_graphics_plugin') .
                ': ' . $themeresult['error'], ['class' => 'text-danger']);
            return false;
        }
        echo html_writer::tag('p', '&#10003; ' . $themeresult['count'] . ' ' .
            get_string('update_files_copied', 'local_sm_graphics_plugin'), ['class' => 'text-success']);
    }

    // --- Step 5: Clean up and purge caches ---
    @unlink($zipfile);
    if (is_dir($extractdir)) {
        recursive_delete($extractdir);
    }

    purge_all_caches();
    echo html_writer::tag('p', '&#10003; ' . get_string('update_caches_purged', 'local_sm_graphics_plugin'),
        ['class' => 'text-success']);

    return true;
}

/**
 * Show manual update instructions when auto-update fails.
 *
 * @param array $updateinfo Update information.
 */
function show_manual_update_instructions(array $updateinfo): void {
    $downloadurl = $updateinfo['download'] ?? 'https://github.com/SmartmindTech/SM_Aprendizaje_Ilimitado_Plugin/releases/latest';
    $installerurl = new moodle_url('/admin/tool/installaddon/index.php');

    echo html_writer::start_tag('div', ['class' => 'alert alert-info mt-3']);
    echo html_writer::tag('h5', get_string('update_manual_title', 'local_sm_graphics_plugin'));
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', get_string('update_manual_step1', 'local_sm_graphics_plugin') . ' ' .
        html_writer::link($downloadurl, 'Download ZIP', ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']));
    echo html_writer::tag('li', get_string('update_manual_step2', 'local_sm_graphics_plugin') . ' ' .
        html_writer::link($installerurl, 'Plugin installer', ['class' => 'btn btn-sm btn-secondary', 'target' => '_blank']));
    echo html_writer::tag('li', get_string('update_manual_step3', 'local_sm_graphics_plugin'));
    echo html_writer::end_tag('ol');
    echo html_writer::end_tag('div');
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
        if ($file === '.' || $file === '..' || $file === '.git') {
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
            if (!@copy($srcpath, $dstpath)) {
                $result['success'] = false;
                $result['error'] = "Cannot copy file: $srcpath to $dstpath";
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
        is_dir($path) ? recursive_delete($path) : @unlink($path);
    }
    @rmdir($dir);
}

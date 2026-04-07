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
 * Post-installation script — deploys and activates the SmartMind theme.
 *
 * The theme files are bundled inside this plugin under theme_smartmind/.
 * On install/upgrade, they are copied to /theme/smartmind/ and activated.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Runs after the plugin is installed for the first time.
 *
 * @return bool
 */
function xmldb_local_sm_graphics_plugin_install() {
    local_sm_graphics_plugin_deploy_theme();
    local_sm_graphics_plugin_activate_theme();
    local_sm_graphics_plugin_deploy_lang_overrides();
    local_sm_graphics_plugin_deploy_certificate_type();
    local_sm_graphics_plugin_enable_activity_modules();
    return true;
}

/**
 * Enable activity modules that are installed but disabled.
 *
 * Ensures BigBlueButton (Training event) and other modules needed by SmartMind
 * are visible and available for course creation.
 */
function local_sm_graphics_plugin_enable_activity_modules() {
    global $DB, $CFG;

    // Modules to auto-enable if they exist on disk but are disabled.
    $modules = ['bigbluebuttonbn'];

    foreach ($modules as $modname) {
        // Check if the module exists on disk.
        if (!file_exists($CFG->dirroot . '/mod/' . $modname)) {
            continue;
        }

        // Check if it's in the modules table.
        $mod = $DB->get_record('modules', ['name' => $modname]);
        if (!$mod) {
            continue;
        }

        // Enable it if disabled.
        if (!$mod->visible) {
            $DB->set_field('modules', 'visible', 1, ['id' => $mod->id]);
        }
    }

    // Set a default BBB server URL if not configured (demo server for development).
    if (file_exists($CFG->dirroot . '/mod/bigbluebuttonbn')) {
        // BBB's config::get() reads from $CFG->bigbluebuttonbn_server_url (flat property),
        // which requires storing in the core {config} table, not {config_plugins}.
        $bbburl = get_config('', 'bigbluebuttonbn_server_url');
        if (empty($bbburl)) {
            set_config('bigbluebuttonbn_server_url', 'https://test-install.blindsidenetworks.com/bigbluebutton/');
            set_config('bigbluebuttonbn_shared_secret', '8cd8ef52e8e101574e400365b55e11a6');
        }
    }
}

/**
 * Deploy the SmartMind certificate template to mod_iomadcertificate types.
 */
function local_sm_graphics_plugin_deploy_certificate_type() {
    global $CFG;

    $source = $CFG->dirroot . '/local/sm_graphics_plugin/certificate_type/smartmind';
    $dest   = $CFG->dirroot . '/mod/iomadcertificate/type/smartmind';

    if (!is_dir($CFG->dirroot . '/mod/iomadcertificate/type')) {
        return; // iomadcertificate not installed.
    }

    local_sm_graphics_plugin_copy_directory($source, $dest);
}

/**
 * Copy the bundled theme_smartmind/ directory to /theme/smartmind/.
 *
 * Only deploys if the bundled theme version is newer than the one already installed,
 * preventing downgrades when the plugin's theme_smartmind/ subfolder is stale
 * (e.g. after a partial Moodle UI update that did not replace nested directories).
 */
function local_sm_graphics_plugin_deploy_theme() {
    global $CFG;

    $source = $CFG->dirroot . '/local/sm_graphics_plugin/theme_smartmind';
    $dest   = $CFG->dirroot . '/theme/smartmind';

    if (!is_dir($source)) {
        return;
    }

    // Read source theme version.
    $sourceversion = local_sm_graphics_plugin_read_theme_version($source . '/version.php');

    // Read destination theme version (if already deployed).
    $destversion = is_dir($dest)
        ? local_sm_graphics_plugin_read_theme_version($dest . '/version.php')
        : 0;

    // If we can read both versions and source is strictly older → skip to prevent downgrade.
    if ($sourceversion > 0 && $destversion > 0 && $sourceversion < $destversion) {
        debugging(
            "[SmartMind] Skipping theme deploy: bundled theme_smartmind version ({$sourceversion}) "
            . "is older than installed theme/smartmind ({$destversion}). Preventing downgrade.",
            DEBUG_NORMAL
        );
        return;
    }

    // If versions are equal, still copy (source may have file-level changes without version bump),
    // but this is safe because the DB version stays the same.
    local_sm_graphics_plugin_copy_directory($source, $dest);
}

/**
 * Read $plugin->version from a Moodle version.php file.
 *
 * @param string $versionfile Path to version.php.
 * @return int Version number, or 0 if unreadable.
 */
function local_sm_graphics_plugin_read_theme_version(string $versionfile): int {
    if (!file_exists($versionfile)) {
        return 0;
    }
    $content = file_get_contents($versionfile);
    if ($content === false) {
        return 0;
    }
    if (preg_match('/\$plugin->version\s*=\s*(\d+)/', $content, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/**
 * Activate the SmartMind theme.
 */
function local_sm_graphics_plugin_activate_theme() {
    global $CFG;

    $themeversionfile = $CFG->dirroot . '/theme/smartmind/version.php';
    if (!file_exists($themeversionfile)) {
        return;
    }

    // Set site theme.
    if (get_config('core', 'theme') !== 'smartmind') {
        set_config('theme', 'smartmind');
        theme_reset_all_caches();
    }

    // Force SmartMind for all IOMAD companies and clear user overrides.
    local_sm_graphics_plugin_force_theme_for_all();
}

/**
 * Force SmartMind theme for all IOMAD companies and clear user-level overrides.
 *
 * IOMAD can set per-company and per-user themes (e.g. iomadboost) which override
 * the site theme. This ensures SmartMind is applied everywhere.
 */
function local_sm_graphics_plugin_force_theme_for_all() {
    global $DB;

    // Set SmartMind for all IOMAD companies.
    if ($DB->get_manager()->table_exists('company')) {
        $DB->execute("UPDATE {company} SET theme = 'smartmind' WHERE theme <> 'smartmind' OR theme IS NULL");
    }

    // Clear all user-level theme overrides.
    $DB->execute("UPDATE {user} SET theme = '' WHERE theme <> '' AND theme IS NOT NULL");

    // Disable user theme choice.
    set_config('allowuserthemes', 0);
}

/**
 * Copy theme language overrides to Moodle's local lang customisation folder.
 *
 * Theme lang files (theme_smartmind/lang/xx/moodle.php, admin.php, etc.)
 * cannot override core strings from the theme directory alone. Moodle only
 * checks the local customisation folder (moodledata/lang/) for overrides.
 * This function copies them there so they take effect.
 */
function local_sm_graphics_plugin_deploy_lang_overrides() {
    global $CFG;

    $themelangdir = $CFG->dirroot . '/theme/smartmind/lang';
    if (!is_dir($themelangdir)) {
        return;
    }

    $langs = scandir($themelangdir);
    foreach ($langs as $lang) {
        if ($lang === '.' || $lang === '..' || $lang === 'en') {
            continue;
        }

        $langpath = $themelangdir . '/' . $lang;
        if (!is_dir($langpath)) {
            continue;
        }

        $files = scandir($langpath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'theme_smartmind.php') {
                continue; // Theme's own strings are handled by Moodle normally.
            }

            $source = $langpath . '/' . $file;
            $destdir = $CFG->dataroot . '/lang/' . $lang . '_local';
            if (!is_dir($destdir)) {
                mkdir($destdir, 0755, true);
            }

            // Merge: load existing overrides, then apply ours on top.
            $existing = [];
            $destfile = $destdir . '/' . $file;
            if (file_exists($destfile)) {
                $string = [];
                include($destfile);
                $existing = $string;
            }

            $string = [];
            include($source);
            $merged = array_merge($existing, $string);

            // Write merged file.
            $content = "<?php\n// SmartMind theme language overrides (auto-generated).\n";
            foreach ($merged as $key => $value) {
                $escaped = str_replace("'", "\\'", $value);
                $content .= "\$string['$key'] = '$escaped';\n";
            }
            file_put_contents($destfile, $content);
        }
    }

    get_string_manager()->reset_caches();
}

/**
 * Recursively copy a directory.
 *
 * @param string $source Source directory path.
 * @param string $dest   Destination directory path.
 */
function local_sm_graphics_plugin_copy_directory(string $source, string $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $target = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

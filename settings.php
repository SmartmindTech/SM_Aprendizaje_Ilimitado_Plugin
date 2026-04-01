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
 * SM Graphic Layer Plugin - admin settings page.
 *
 * Accessible at: Site Administration → Plugins → Local plugins → SM Graphic Layer
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Local plugins must create their own settings page and register it manually.
// ($settings is not pre-created for local plugins the way it is for blocks/modules.)
$settings = new admin_settingpage(
    'local_sm_graphics_plugin',
    get_string('pluginname', 'local_sm_graphics_plugin')
);
$ADMIN->add('localplugins', $settings);

if ($ADMIN->fulltree) {
    global $CFG;

    // -----------------------------------------------------------------------
    // Master toggle
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_configcheckbox(
        'local_sm_graphics_plugin/enabled',
        get_string('enabled', 'local_sm_graphics_plugin'),
        get_string('enabled_desc', 'local_sm_graphics_plugin'),
        1  // Default: enabled.
    ));

    // -----------------------------------------------------------------------
    // Brand colors
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_sm_graphics_plugin/colors_heading',
        get_string('colors_heading', 'local_sm_graphics_plugin'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sm_graphics_plugin/color_primary',
        get_string('color_primary', 'local_sm_graphics_plugin'),
        get_string('color_primary_desc', 'local_sm_graphics_plugin'),
        '#6366f1',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sm_graphics_plugin/color_header_bg',
        get_string('color_header_bg', 'local_sm_graphics_plugin'),
        get_string('color_header_bg_desc', 'local_sm_graphics_plugin'),
        '#1a1f35',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sm_graphics_plugin/color_sidebar_bg',
        get_string('color_sidebar_bg', 'local_sm_graphics_plugin'),
        get_string('color_sidebar_bg_desc', 'local_sm_graphics_plugin'),
        '#ffffff',
        PARAM_TEXT
    ));

    // -----------------------------------------------------------------------
    // Logo
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_sm_graphics_plugin/logo_heading',
        get_string('logo_heading', 'local_sm_graphics_plugin'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sm_graphics_plugin/logo_url',
        get_string('logo_url', 'local_sm_graphics_plugin'),
        get_string('logo_url_desc', 'local_sm_graphics_plugin'),
        '',
        PARAM_URL
    ));

    // -----------------------------------------------------------------------
    // Company student limits
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_sm_graphics_plugin/companylimits_heading',
        get_string('companylimits_heading', 'local_sm_graphics_plugin'),
        '<a href="' . $CFG->wwwroot . '/local/sm_graphics_plugin/pages/companylimits.php" class="btn btn-primary">'
        . get_string('companylimits_button', 'local_sm_graphics_plugin') . '</a>'
        . '<p class="text-muted mt-2">' . get_string('companylimits_button_desc', 'local_sm_graphics_plugin') . '</p>'
    ));

    // -----------------------------------------------------------------------
    // Plugin updates
    // -----------------------------------------------------------------------
    // Build update section HTML with version info and check/update buttons.
    $updatehtml = '';

    // Show current version.
    $plugin = core_plugin_manager::instance()->get_plugin_info('local_sm_graphics_plugin');
    if ($plugin) {
        $updatehtml .= '<p><strong>' . get_string('update_current_version', 'local_sm_graphics_plugin')
            . ':</strong> ' . ($plugin->release ?? '') . ' (' . $plugin->versiondisk . ')</p>';
    }

    // Check for updates (using cache, not forced).
    require_once(__DIR__ . '/classes/update_checker.php');
    $updateavailable = \local_sm_graphics_plugin\update_checker::check(false);

    if ($updateavailable) {
        $updatehtml .= '<div class="alert alert-warning">'
            . get_string('update_available_msg', 'local_sm_graphics_plugin',
                (object)['current' => $updateavailable->currentrelease, 'new' => $updateavailable->release])
            . '</div>';
        $updatehtml .= '<a href="' . $CFG->wwwroot . '/local/sm_graphics_plugin/update.php" class="btn btn-success mr-2">'
            . get_string('update_plugin_theme', 'local_sm_graphics_plugin') . '</a> ';
    }

    // Always show check button (forces a fresh fetch).
    $checkurl = $CFG->wwwroot . '/admin/settings.php?section=local_sm_graphics_plugin&smgp_checkupdate=1';
    $updatehtml .= '<a href="' . $checkurl . '" class="btn btn-primary">'
        . get_string('update_button', 'local_sm_graphics_plugin') . '</a>';
    $updatehtml .= '<p class="text-muted mt-2">' . get_string('update_button_desc', 'local_sm_graphics_plugin') . '</p>';

    // Handle force check if requested.
    if (optional_param('smgp_checkupdate', 0, PARAM_BOOL)) {
        $forceresult = \local_sm_graphics_plugin\update_checker::check(true);
        if ($forceresult) {
            $updatehtml = '<div class="alert alert-warning">'
                . get_string('update_available_msg', 'local_sm_graphics_plugin',
                    (object)['current' => $forceresult->currentrelease, 'new' => $forceresult->release])
                . '</div>'
                . '<a href="' . $CFG->wwwroot . '/local/sm_graphics_plugin/update.php" class="btn btn-success mr-2">'
                . get_string('update_plugin_theme', 'local_sm_graphics_plugin') . '</a> '
                . '<a href="' . $checkurl . '" class="btn btn-primary">'
                . get_string('update_button', 'local_sm_graphics_plugin') . '</a>'
                . '<p class="text-muted mt-2">' . get_string('update_button_desc', 'local_sm_graphics_plugin') . '</p>';
        } else {
            $currentrel = $plugin ? ($plugin->release ?? $plugin->versiondisk) : '';
            $updatehtml = '<div class="alert alert-success">'
                . get_string('update_uptodate', 'local_sm_graphics_plugin', $currentrel)
                . '</div>'
                . '<a href="' . $checkurl . '" class="btn btn-primary">'
                . get_string('update_button', 'local_sm_graphics_plugin') . '</a>'
                . '<p class="text-muted mt-2">' . get_string('update_button_desc', 'local_sm_graphics_plugin') . '</p>';
        }
    }

    $settings->add(new admin_setting_heading(
        'local_sm_graphics_plugin/update_heading',
        get_string('update_heading', 'local_sm_graphics_plugin'),
        $updatehtml
    ));

    // -----------------------------------------------------------------------
    // SharePoint integration (Course Loader)
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_sm_graphics_plugin/sharepoint_heading',
        get_string('sp_heading', 'local_sm_graphics_plugin'),
        '<a href="' . $CFG->wwwroot . '/local/sm_graphics_plugin/pages/courseloader.php" class="btn btn-primary">'
        . get_string('sp_courseloader_button', 'local_sm_graphics_plugin') . '</a>'
        . '<p class="text-muted mt-2">' . get_string('sp_courseloader_button_desc', 'local_sm_graphics_plugin') . '</p>'
    ));

    $settings->add(new admin_setting_configtext(
        'local_sm_graphics_plugin/sp_tenant_id',
        get_string('sp_tenant_id', 'local_sm_graphics_plugin'),
        get_string('sp_tenant_id_desc', 'local_sm_graphics_plugin'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sm_graphics_plugin/sp_client_id',
        get_string('sp_client_id', 'local_sm_graphics_plugin'),
        get_string('sp_client_id_desc', 'local_sm_graphics_plugin'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sm_graphics_plugin/sp_client_secret',
        get_string('sp_client_secret', 'local_sm_graphics_plugin'),
        get_string('sp_client_secret_desc', 'local_sm_graphics_plugin'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sm_graphics_plugin/sp_site_url',
        get_string('sp_site_url', 'local_sm_graphics_plugin'),
        get_string('sp_site_url_desc', 'local_sm_graphics_plugin'),
        '',
        PARAM_URL
    ));
}

// -----------------------------------------------------------------------
// Add shortcut links directly under the "users" admin category
// so the "Usuarios" row is not empty on the admin settings page.
// -----------------------------------------------------------------------
if ($hassiteconfig) {
    $ADMIN->add('users', new admin_externalpage(
        'smgp_userlist',
        get_string('usermgmt_userlist', 'local_sm_graphics_plugin'),
        new moodle_url('/admin/user.php')
    ));
    $ADMIN->add('users', new admin_externalpage(
        'smgp_adduser',
        get_string('usermgmt_createuser', 'local_sm_graphics_plugin'),
        new moodle_url('/user/editadvanced.php', ['id' => -1])
    ));
    $ADMIN->add('users', new admin_externalpage(
        'smgp_uploadusers',
        get_string('usermgmt_uploadusers', 'local_sm_graphics_plugin'),
        new moodle_url('/admin/tool/uploaduser/index.php')
    ));
}

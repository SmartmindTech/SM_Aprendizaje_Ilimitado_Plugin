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

namespace local_sm_graphics_plugin\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * Returns the current plugin settings.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_plugin_settings extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);

        if (!is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $component = 'local_sm_graphics_plugin';

        $enabled = get_config($component, 'enabled');
        $colorprimary = get_config($component, 'color_primary');
        $colorheaderbg = get_config($component, 'color_header_bg');
        $colorsidebarbg = get_config($component, 'color_sidebar_bg');
        $logourl = get_config($component, 'logo_url');

        return [
            'enabled'          => !empty($enabled),
            'color_primary'    => $colorprimary !== false ? $colorprimary : '#6366f1',
            'color_header_bg'  => $colorheaderbg !== false ? $colorheaderbg : '#1a1f35',
            'color_sidebar_bg' => $colorsidebarbg !== false ? $colorsidebarbg : '#ffffff',
            'logo_url'         => $logourl !== false ? $logourl : '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled'          => new external_value(PARAM_BOOL, 'Whether the plugin is enabled'),
            'color_primary'    => new external_value(PARAM_TEXT, 'Primary brand color'),
            'color_header_bg'  => new external_value(PARAM_TEXT, 'Header background color'),
            'color_sidebar_bg' => new external_value(PARAM_TEXT, 'Sidebar background color'),
            'logo_url'         => new external_value(PARAM_TEXT, 'Logo URL'),
        ]);
    }
}

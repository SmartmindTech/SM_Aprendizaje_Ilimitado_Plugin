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
 * Updates plugin settings.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_plugin_settings extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'enabled'          => new external_value(PARAM_BOOL, 'Whether the plugin is enabled'),
            'color_primary'    => new external_value(PARAM_TEXT, 'Primary brand color'),
            'color_header_bg'  => new external_value(PARAM_TEXT, 'Header background color'),
            'color_sidebar_bg' => new external_value(PARAM_TEXT, 'Sidebar background color'),
            'logo_url'         => new external_value(PARAM_TEXT, 'Logo URL'),
        ]);
    }

    public static function execute(
        bool $enabled,
        string $colorprimary,
        string $colorheaderbg,
        string $colorsidebarbg,
        string $logourl
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'enabled'          => $enabled,
            'color_primary'    => $colorprimary,
            'color_header_bg'  => $colorheaderbg,
            'color_sidebar_bg' => $colorsidebarbg,
            'logo_url'         => $logourl,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        if (!is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $component = 'local_sm_graphics_plugin';

        set_config('enabled', $params['enabled'] ? 1 : 0, $component);
        set_config('color_primary', $params['color_primary'], $component);
        set_config('color_header_bg', $params['color_header_bg'], $component);
        set_config('color_sidebar_bg', $params['color_sidebar_bg'], $component);
        set_config('logo_url', $params['logo_url'], $component);

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the settings were saved successfully'),
        ]);
    }
}

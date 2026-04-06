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
 * Plugin version and other meta-data.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sm_graphics_plugin';
$plugin->version = 2026040603;  // YYYYMMDDXX format.
$plugin->requires = 2022112800; // Moodle 4.1+
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '1.0.8';  // MATURITY_ALPHA, MATURITY_BETA, MATURITY_RC, MATURITY_STABLE

// GitHub update server - allows automatic update notifications.
// Branch is configurable via UPDATE_BRANCH in .env (default: main).
$smgp_update_branch = 'main';
$smgp_env_file = __DIR__ . '/.env';
if (file_exists($smgp_env_file)) {
    $smgp_env_lines = file($smgp_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($smgp_env_lines as $smgp_line) {
        if (strpos($smgp_line, 'UPDATE_BRANCH=') === 0) {
            $smgp_update_branch = trim(substr($smgp_line, 14));
            break;
        }
    }
}
$plugin->updateserver = 'https://raw.githubusercontent.com/SmartmindTech/SM_Aprendizaje_Ilimitado_Plugin/' . $smgp_update_branch . '/update.xml';
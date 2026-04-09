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
 * Claim a mission. Validates that the mission is completed and not already
 * claimed for its current period, then awards the XP via xp_service and
 * returns the new XP/level state so the SPA can animate the bar.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class claim_mission extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Mission code'),
            'lang' => new external_value(PARAM_ALPHANUMEXT, 'SPA language: es | en | pt_br', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $code, string $lang = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'code' => $code,
            'lang' => $lang,
        ]);
        self::validate_context(\context_system::instance());

        $lang = $params['lang'] !== ''
            ? $params['lang']
            : optional_param('lang', '', PARAM_ALPHANUMEXT);

        $userid = (int) $USER->id;

        $result = \local_sm_graphics_plugin\gamification\mission_service::claim($userid, $params['code']);

        // Always read the latest XP state so the SPA gets a fresh snapshot,
        // even if the claim was rejected (e.g. user already claimed in
        // another tab — the panel will refresh and show the new state).
        \local_sm_graphics_plugin\gamification\achievement_service::check_and_unlock($userid);
        $xprow = \local_sm_graphics_plugin\gamification\xp_service::get_user_xp($userid);
        $progress = \local_sm_graphics_plugin\gamification\xp_service::progress((int) $xprow->xp_total);

        return [
            'success'            => (bool) $result['success'],
            'reason'             => (string) $result['reason'],
            'xp_awarded'         => (int) $result['xp_awarded'],
            'mission_code'       => $params['code'],
            'xp_total'           => (int) $progress['xp_total'],
            'level'              => (int) $progress['level'],
            'xp_into_level'      => (int) $progress['xp_into_level'],
            'xp_for_next'        => (int) $progress['xp_for_next'],
            'xp_to_next'         => (int) $progress['xp_to_next'],
            'level_progress_pct' => (int) $progress['progress_pct'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'            => new external_value(PARAM_BOOL, 'Whether the XP was awarded'),
            'reason'             => new external_value(PARAM_TEXT, 'ok | unknown | not_completed | already_claimed'),
            'xp_awarded'         => new external_value(PARAM_INT, 'XP added by this call (0 if rejected)'),
            'mission_code'       => new external_value(PARAM_ALPHANUMEXT, 'Mission code'),
            'xp_total'           => new external_value(PARAM_INT, 'New total XP'),
            'level'              => new external_value(PARAM_INT, 'New level'),
            'xp_into_level'      => new external_value(PARAM_INT, 'XP earned within current level'),
            'xp_for_next'        => new external_value(PARAM_INT, 'XP span of current level'),
            'xp_to_next'         => new external_value(PARAM_INT, 'XP remaining to next level'),
            'level_progress_pct' => new external_value(PARAM_INT, 'Progress percentage 0-100'),
        ]);
    }
}

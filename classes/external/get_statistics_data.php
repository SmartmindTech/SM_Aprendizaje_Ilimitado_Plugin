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
 * Returns statistics data for company managers.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use local_sm_graphics_plugin\statistics;

/**
 * External function to get company statistics data.
 */
class get_statistics_data extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute: get statistics cards and weekly chart data.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);

        require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');

        // Only company managers and site admins may access.
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if (!$managerrec && !is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $companyid = $managerrec->companyid;
        $component = 'local_sm_graphics_plugin';

        // Fetch stats.
        $statsobj           = new statistics($companyid);
        $stats              = $statsobj->get_all();
        $weeklycompletions  = $statsobj->get_weekly_completions(10);
        $weeklyactiveusers  = $statsobj->get_weekly_active_users(10);

        // Build card data.
        $cards = [
            [
                'icon'  => 'fa-clock',
                'value' => (string) $stats['active_last_5_days'],
                'label' => get_string('stats_active_5days', $component),
            ],
            [
                'icon'  => 'fa-book-open',
                'value' => (string) $stats['courses_started'],
                'label' => get_string('stats_courses_started', $component),
            ],
            [
                'icon'  => 'fa-circle-check',
                'value' => (string) $stats['courses_completed'],
                'label' => get_string('stats_courses_completed', $component),
            ],
            [
                'icon'  => 'fa-gauge',
                'value' => $stats['completion_rate'] . '%',
                'label' => get_string('stats_completion_rate', $component),
            ],
            [
                'icon'  => 'fa-graduation-cap',
                'value' => (string) $stats['courses_available'],
                'label' => get_string('stats_courses_available', $component),
            ],
        ];

        // Build weekly completions data.
        $completionsout = [];
        foreach ($weeklycompletions as $wc) {
            $completionsout[] = [
                'label' => $wc['label'],
                'value' => (int) $wc['value'],
            ];
        }

        // Build weekly active users data.
        $activeout = [];
        foreach ($weeklyactiveusers as $wa) {
            $activeout[] = [
                'label' => $wa['label'],
                'value' => (int) $wa['value'],
            ];
        }

        return [
            'cards'               => $cards,
            'weekly_completions'  => $completionsout,
            'weekly_active_users' => $activeout,
        ];
    }

    /**
     * Define return type.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cards' => new external_multiple_structure(
                new external_single_structure([
                    'icon'  => new external_value(PARAM_TEXT, 'FontAwesome icon class'),
                    'value' => new external_value(PARAM_TEXT, 'Stat value (may include % sign)'),
                    'label' => new external_value(PARAM_TEXT, 'Stat label'),
                ])
            ),
            'weekly_completions' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Week label (dd/mm)'),
                    'value' => new external_value(PARAM_INT, 'Completions count'),
                ])
            ),
            'weekly_active_users' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Week label (dd/mm)'),
                    'value' => new external_value(PARAM_INT, 'Active users count'),
                ])
            ),
        ]);
    }
}

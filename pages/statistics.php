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
 * Statistics page for company managers.
 *
 * Shows summary stat cards for the manager's company.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_login();

use local_sm_graphics_plugin\statistics;

global $DB, $USER, $OUTPUT, $PAGE;

// Only company managers may access this page.
$managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
if (!$managerrec) {
    throw new moodle_exception('accessdenied', 'admin');
}

$companyid   = $managerrec->companyid;
$companyname = $DB->get_field('company', 'name', ['id' => $companyid]);
$component   = 'local_sm_graphics_plugin';

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/statistics.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title($companyname . ' — ' . get_string('stats_title', $component));
$PAGE->set_heading($companyname);
$PAGE->set_pagelayout('standard');

// Fetch stats.
$statsobj = new statistics($companyid);
$stats = $statsobj->get_all();
$weeklycompletions = $statsobj->get_weekly_completions(10);
$weeklyactiveusers = $statsobj->get_weekly_active_users(10);

// Build card data.
$cards = [
    [
        'icon'  => 'fa-clock',
        'value' => $stats['active_last_5_days'],
        'label' => get_string('stats_active_5days', $component),
    ],
    [
        'icon'  => 'fa-book-open',
        'value' => $stats['courses_started'],
        'label' => get_string('stats_courses_started', $component),
    ],
    [
        'icon'  => 'fa-circle-check',
        'value' => $stats['courses_completed'],
        'label' => get_string('stats_courses_completed', $component),
    ],
    [
        'icon'  => 'fa-gauge',
        'value' => $stats['completion_rate'] . '%',
        'label' => get_string('stats_completion_rate', $component),
    ],
    [
        'icon'  => 'fa-graduation-cap',
        'value' => $stats['courses_available'],
        'label' => get_string('stats_courses_available', $component),
    ],
];

// Build Moodle chart objects.
$completionslabels = array_column($weeklycompletions, 'label');
$completionsvalues = array_column($weeklycompletions, 'value');
$completionsseries = new \core\chart_series(
    get_string('stats_courses_completed', $component),
    $completionsvalues
);
$completionsseries->set_colors(['#6b7280']);
$completionschart = new \core\chart_bar();
$completionschart->add_series($completionsseries);
$completionschart->set_labels($completionslabels);
$completionschart->set_legend_options(['display' => false]);

$activelabels = array_column($weeklyactiveusers, 'label');
$activevalues = array_column($weeklyactiveusers, 'value');
$activeseries = new \core\chart_series(
    get_string('stats_active_5days', $component),
    $activevalues
);
$activeseries->set_colors(['#d1d5db']);
$activechart = new \core\chart_bar();
$activechart->add_series($activeseries);
$activechart->set_labels($activelabels);
$activechart->set_legend_options(['display' => false]);

$PAGE->requires->js_call_amd('local_sm_graphics_plugin/statistics_charts', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/statistics_page', [
    'heading'              => get_string('stats_heading', $component),
    'cards'                => $cards,
    'completions_title'    => get_string('stats_weekly_completions', $component),
    'completions_chart'    => $OUTPUT->render($completionschart),
    'active_users_title'   => get_string('stats_weekly_active', $component),
    'active_users_chart'   => $OUTPUT->render($activechart),
]);
echo $OUTPUT->footer();

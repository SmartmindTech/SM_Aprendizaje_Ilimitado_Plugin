/**
 * Statistics page — Restyle Moodle-rendered Chart.js charts.
 *
 * Moodle creates charts via core/chart_builder. This module waits for
 * Chart.js instances to exist, then applies minimalist styling:
 * rounded bars, Y-axis grace, subtle grid, clean typography.
 *
 * @module     local_sm_graphics_plugin/statistics_charts
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Apply minimalist style to all Chart.js instances on the page.
     */
    function applyStyle() {
        if (typeof Chart === 'undefined' || !Chart.instances) {
            return;
        }

        var instances = Object.values(Chart.instances);
        if (instances.length === 0) {
            return;
        }

        instances.forEach(function(chart) {
            // Rounded bars.
            chart.data.datasets.forEach(function(ds) {
                ds.borderRadius = 6;
                ds.borderSkipped = false;
            });

            // Y axis — grace, minimal grid, clean ticks.
            var yAxis = chart.options.scales && chart.options.scales.y;
            if (yAxis) {
                yAxis.grace = '15%';
                yAxis.grid = {color: '#d1d5db', drawBorder: false};
                yAxis.border = {display: true, color: '#d1d5db'};
                yAxis.ticks.color = '#374151';
                yAxis.ticks.padding = 8;
                yAxis.ticks.font = {size: 13};
            }

            // X axis — no grid, clean ticks.
            var xAxis = chart.options.scales && chart.options.scales.x;
            if (xAxis) {
                xAxis.grid = {display: false};
                xAxis.border = {display: true, color: '#d1d5db'};
                xAxis.ticks.color = '#374151';
                xAxis.ticks.padding = 8;
                xAxis.ticks.font = {size: 13};
            }

            chart.update();
        });
    }

    return {
        init: function() {
            // Poll until Chart.js instances are created by Moodle's chart_builder.
            var attempts = 0;
            var interval = setInterval(function() {
                attempts++;
                if (typeof Chart !== 'undefined' && Object.keys(Chart.instances).length > 0) {
                    clearInterval(interval);
                    applyStyle();
                }
                if (attempts > 50) {
                    clearInterval(interval);
                }
            }, 100);
        }
    };
});

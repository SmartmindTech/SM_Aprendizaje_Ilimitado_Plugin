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
 * Hook callbacks for SM Graphic Layer Plugin.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => core_course\hook\after_form_definition::class,
        'callback' => local_sm_graphics_plugin\hook\course_form_handler::class . '::add_pricing_fields',
    ],
    [
        'hook' => core_course\hook\after_form_definition_after_data::class,
        'callback' => local_sm_graphics_plugin\hook\course_form_handler::class . '::load_pricing_data',
    ],
    [
        'hook' => core_course\hook\after_form_validation::class,
        'callback' => local_sm_graphics_plugin\hook\course_form_handler::class . '::validate_pricing',
    ],
];

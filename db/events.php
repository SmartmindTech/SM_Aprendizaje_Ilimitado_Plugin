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
 * Event observers for SM Graphic Layer Plugin.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_created',
        'callback'  => 'local_sm_graphics_plugin\observer::course_saved',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback'  => 'local_sm_graphics_plugin\observer::course_saved',
    ],
    [
        'eventname' => '\core\event\course_restored',
        'callback'  => 'local_sm_graphics_plugin\observer::course_restored',
    ],
    [
        'eventname' => '\block_iomad_company_admin\event\company_created',
        'callback'  => 'local_sm_graphics_plugin\observer::company_saved',
    ],
    [
        'eventname' => '\block_iomad_company_admin\event\company_updated',
        'callback'  => 'local_sm_graphics_plugin\observer::company_saved',
    ],
];

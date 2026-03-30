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
 * External service definitions for AJAX calls.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sm_graphics_plugin_get_activity_content' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_activity_content',
        'methodname'  => 'execute',
        'description' => 'Get rendered content for a single activity module',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_course_progress' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_course_progress',
        'methodname'  => 'execute',
        'description' => 'Get completion progress for all activities in a course',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_mark_activity_complete' => [
        'classname'   => 'local_sm_graphics_plugin\external\mark_activity_complete',
        'methodname'  => 'execute',
        'description' => 'Mark an activity as complete (for client-tracked progress)',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_comments' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_comments',
        'methodname'  => 'execute',
        'description' => 'Fetch course comments with pagination',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_add_comment' => [
        'classname'   => 'local_sm_graphics_plugin\external\add_comment',
        'methodname'  => 'execute',
        'description' => 'Create a new course comment or reply',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:post_comments',
    ],
    'local_sm_graphics_plugin_update_comment' => [
        'classname'   => 'local_sm_graphics_plugin\external\update_comment',
        'methodname'  => 'execute',
        'description' => 'Edit an existing course comment',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:post_comments',
    ],
    'local_sm_graphics_plugin_delete_comment' => [
        'classname'   => 'local_sm_graphics_plugin\external\delete_comment',
        'methodname'  => 'execute',
        'description' => 'Delete a course comment and its replies',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:post_comments',
    ],
    'local_sm_graphics_plugin_search_course_users' => [
        'classname'   => 'local_sm_graphics_plugin\external\search_course_users',
        'methodname'  => 'execute',
        'description' => 'Search enrolled users in a course for @mentions',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:post_comments',
    ],
    'local_sm_graphics_plugin_set_course_category' => [
        'classname'   => 'local_sm_graphics_plugin\external\set_course_category',
        'methodname'  => 'execute',
        'description' => 'Set the catalogue category for a course',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/course:update',
    ],
    'local_sm_graphics_plugin_enrol_user' => [
        'classname'   => 'local_sm_graphics_plugin\external\enrol_user',
        'methodname'  => 'execute',
        'description' => 'Enrol the current user into a course via manual enrolment',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_unenrol_user' => [
        'classname'   => 'local_sm_graphics_plugin\external\unenrol_user',
        'methodname'  => 'execute',
        'description' => 'Unenrol the current user from a course',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_update_course_info' => [
        'classname'   => 'local_sm_graphics_plugin\external\update_course_info',
        'methodname'  => 'execute',
        'description' => 'Update a course info field from the landing page',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/course:update',
    ],
    'local_sm_graphics_plugin_add_activity' => [
        'classname'   => 'local_sm_graphics_plugin\external\add_activity',
        'methodname'  => 'execute',
        'description' => 'Add a new activity to a course section',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/course:update',
    ],
    'local_sm_graphics_plugin_delete_activity' => [
        'classname'   => 'local_sm_graphics_plugin\external\delete_activity',
        'methodname'  => 'execute',
        'description' => 'Delete an activity from a course',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/course:update',
    ],
    'local_sm_graphics_plugin_get_activity_durations' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_activity_durations',
        'methodname'  => 'execute',
        'description' => 'Get durations for all activities in a course',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_set_activity_duration' => [
        'classname'   => 'local_sm_graphics_plugin\external\set_activity_duration',
        'methodname'  => 'execute',
        'description' => 'Set the duration for an activity',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/course:update',
    ],
    'local_sm_graphics_plugin_get_completed_courses' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_completed_courses',
        'methodname'  => 'execute',
        'description' => 'Get completed courses for the current user',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_browsed_courses' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_browsed_courses',
        'methodname'  => 'execute',
        'description' => 'Get recently browsed courses for non-enrolled users',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_company_stats' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_company_stats',
        'methodname'  => 'execute',
        'description' => 'Get company enrollment and completion stats',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_get_last_accessed' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_last_accessed',
        'methodname'  => 'execute',
        'description' => 'Get the last accessed course and activity',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_create_category' => [
        'classname'   => 'local_sm_graphics_plugin\external\create_category',
        'methodname'  => 'execute',
        'description' => 'Create a catalogue category',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'moodle/course:update',
    ],

    // ── SPA: Student/Teacher pages ─────────────────────────────────────────
    'local_sm_graphics_plugin_get_welcome_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_welcome_data',
        'methodname'  => 'execute',
        'description' => 'Get welcome page data',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_course_landing_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_course_landing_data',
        'methodname'  => 'execute',
        'description' => 'Get course landing page data (sections, metadata, enrollment)',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_course_page_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_course_page_data',
        'methodname'  => 'execute',
        'description' => 'Get course player page data (activities, grades, progress)',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_grades_certificates_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_grades_certificates_data',
        'methodname'  => 'execute',
        'description' => 'Get grades and certificates data for the current user',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_mycourses_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_mycourses_data',
        'methodname'  => 'execute',
        'description' => 'Get enrolled and completed courses for the current user',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_dashboard_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_dashboard_data',
        'methodname'  => 'execute',
        'description' => 'Get personal dashboard data (replaces /my/)',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],
    'local_sm_graphics_plugin_get_catalogue_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_catalogue_data',
        'methodname'  => 'execute',
        'description' => 'Get course catalogue with categories and filters',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sm_graphics_plugin:view',
    ],

    // ── SPA: Management pages ──────────────────────────────────────────────
    'local_sm_graphics_plugin_get_company_users' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_company_users',
        'methodname'  => 'execute',
        'description' => 'Get paginated company users for management',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_delete_company_user' => [
        'classname'   => 'local_sm_graphics_plugin\external\delete_company_user',
        'methodname'  => 'execute',
        'description' => 'Delete a company user',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_get_statistics_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_statistics_data',
        'methodname'  => 'execute',
        'description' => 'Get company statistics (cards, weekly charts)',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_get_categories_list' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_categories_list',
        'methodname'  => 'execute',
        'description' => 'Get all catalogue categories for admin management',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_delete_category' => [
        'classname'   => 'local_sm_graphics_plugin\external\delete_category',
        'methodname'  => 'execute',
        'description' => 'Delete a catalogue category',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_get_course_management_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_course_management_data',
        'methodname'  => 'execute',
        'description' => 'Get course management data and options',
        'type'        => 'read',
        'ajax'        => true,
    ],

    // ── SPA: Admin pages ───────────────────────────────────────────────────
    'local_sm_graphics_plugin_get_plugin_settings' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_plugin_settings',
        'methodname'  => 'execute',
        'description' => 'Get plugin admin settings (colors, logo, enabled)',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_update_plugin_settings' => [
        'classname'   => 'local_sm_graphics_plugin\external\update_plugin_settings',
        'methodname'  => 'execute',
        'description' => 'Update plugin admin settings',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_get_company_limits' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_company_limits',
        'methodname'  => 'execute',
        'description' => 'Get all company student limits',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_update_company_limit' => [
        'classname'   => 'local_sm_graphics_plugin\external\update_company_limit',
        'methodname'  => 'execute',
        'description' => 'Update a company student limit',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_get_iomad_dashboard_data' => [
        'classname'   => 'local_sm_graphics_plugin\external\get_iomad_dashboard_data',
        'methodname'  => 'execute',
        'description' => 'Get IOMAD dashboard categories and options',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_sm_graphics_plugin_check_plugin_update' => [
        'classname'   => 'local_sm_graphics_plugin\external\check_plugin_update',
        'methodname'  => 'execute',
        'description' => 'Check for plugin updates from GitHub',
        'type'        => 'read',
        'ajax'        => true,
    ],
];

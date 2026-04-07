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
 * Returns course management page data (action cards and company overview).
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

/**
 * External function to get course management data.
 */
class get_course_management_data extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute: get course management options and company stats.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);

        require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');

        // Site admins and company managers may access this page.
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if (!$managerrec && !is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $companyname = '';
        if ($managerrec) {
            $companyname = $DB->get_field('company', 'name', ['id' => $managerrec->companyid]);
        } else if (is_siteadmin()) {
            $rec = $DB->get_record('company_users', ['userid' => $USER->id], 'companyid', IGNORE_MULTIPLE);
            if ($rec) {
                $companyname = $DB->get_field('company', 'name', ['id' => $rec->companyid]);
            }
        }

        $component = 'local_sm_graphics_plugin';
        $base      = '/blocks/iomad_company_admin';

        // Action cards — all point at SPA routes. Icons are bootstrap-icons
        // (bi bi-*), the icon set actually loaded by the SPA's main.scss.
        $options = [
            [
                'url'         => local_sm_graphics_plugin_spa_url('courses/create')->out(false),
                'icon'        => 'bi-plus-circle',
                'icon_color'  => 'green',
                'title'       => get_string('coursemgmt_create', $component),
                'description' => get_string('coursemgmt_create_desc', $component),
            ],
            [
                'url'         => local_sm_graphics_plugin_spa_url('admin/restore')->out(false),
                'icon'        => 'bi-cloud-upload',
                'icon_color'  => 'blue',
                'title'       => get_string('coursemgmt_restore', $component),
                'description' => get_string('coursemgmt_restore_desc', $component),
            ],
            [
                'url'         => local_sm_graphics_plugin_spa_url('management/courses')->out(false),
                'icon'        => 'bi-building',
                'icon_color'  => 'blue',
                'title'       => get_string('coursemgmt_assign', $component),
                'description' => get_string('coursemgmt_assign_desc', $component),
            ],
            [
                'url'         => local_sm_graphics_plugin_spa_url('management/categories')->out(false),
                'icon'        => 'bi-folder-plus',
                'icon_color'  => 'blue',
                'title'       => get_string('coursemgmt_createcat', $component),
                'description' => get_string('coursemgmt_createcat_desc', $component),
            ],
            [
                'url'         => local_sm_graphics_plugin_spa_url('management/categories')->out(false),
                'icon'        => 'bi-folder2-open',
                'icon_color'  => 'orange',
                'title'       => get_string('coursemgmt_managecat', $component),
                'description' => get_string('coursemgmt_managecat_desc', $component),
            ],
            [
                'url'         => local_sm_graphics_plugin_spa_url('admin/courseloader')->out(false),
                'icon'        => 'bi-cloud-download',
                'icon_color'  => 'green',
                'title'       => get_string('coursemgmt_sharepoint', $component),
                'description' => get_string('coursemgmt_sharepoint_desc', $component),
            ],
        ];

        // Company stats table.
        $companies = \local_sm_graphics_plugin\external\get_company_stats::execute();

        // Map company objects to arrays for the return structure.
        $companiesout = [];
        foreach ($companies as $c) {
            $companiesout[] = [
                'id'          => (int) ($c->id ?? $c['id'] ?? 0),
                'name'        => $c->name ?? $c['name'] ?? '',
                'shortname'   => $c->shortname ?? $c['shortname'] ?? '',
                'coursecount'  => (int) ($c->coursecount ?? $c['coursecount'] ?? 0),
                'usercount'   => (int) ($c->usercount ?? $c['usercount'] ?? 0),
                'maxusers'    => (int) ($c->maxusers ?? $c['maxusers'] ?? 0),
            ];
        }

        return [
            'heading'      => get_string('coursemgmt_heading', $component),
            'companyname'  => $companyname,
            'options'      => $options,
            'companies'    => $companiesout,
            'hascompanies' => !empty($companiesout),
        ];
    }

    /**
     * Define return type.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'heading'      => new external_value(PARAM_TEXT, 'Page heading'),
            'companyname'  => new external_value(PARAM_TEXT, 'Current company name'),
            'options'      => new external_multiple_structure(
                new external_single_structure([
                    'url'         => new external_value(PARAM_RAW, 'Action URL'),
                    'icon'        => new external_value(PARAM_TEXT, 'FontAwesome icon class'),
                    'icon_color'  => new external_value(PARAM_TEXT, 'Icon color tag (green/blue/orange)'),
                    'title'       => new external_value(PARAM_TEXT, 'Card title'),
                    'description' => new external_value(PARAM_TEXT, 'Card description'),
                ])
            ),
            'companies'    => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT, 'Company ID'),
                    'name'        => new external_value(PARAM_TEXT, 'Company name'),
                    'shortname'   => new external_value(PARAM_TEXT, 'Company short name'),
                    'coursecount'  => new external_value(PARAM_INT, 'Number of assigned courses'),
                    'usercount'   => new external_value(PARAM_INT, 'Number of users'),
                    'maxusers'    => new external_value(PARAM_INT, 'Max users allowed (0 = unlimited)'),
                ])
            ),
            'hascompanies' => new external_value(PARAM_BOOL, 'Whether there are companies'),
        ]);
    }
}

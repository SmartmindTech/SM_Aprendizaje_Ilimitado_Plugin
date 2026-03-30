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
use external_multiple_structure;
use external_value;

/**
 * Returns IOMAD dashboard data with category/option cards.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_iomad_dashboard_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/local/sm_graphics_plugin/lib.php');

        $context = \context_system::instance();
        self::validate_context($context);

        // Site admins and company managers may access.
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if (!$managerrec && !is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $companyname = '';
        $companyid   = 0;

        if ($managerrec) {
            $companyid   = $managerrec->companyid;
            $companyname = $DB->get_field('company', 'name', ['id' => $companyid]);
        } else if (is_siteadmin()) {
            // Site admins: look up their company_users record (any role).
            $rec = $DB->get_record('company_users', ['userid' => $USER->id], 'companyid', IGNORE_MULTIPLE);
            if ($rec) {
                $companyid   = $rec->companyid;
                $companyname = $DB->get_field('company', 'name', ['id' => $companyid]);
            } else {
                // Fallback: use the first company in the system.
                $first = $DB->get_record_sql(
                    'SELECT id, name FROM {company} ORDER BY name ASC',
                    [],
                    IGNORE_MULTIPLE
                );
                if ($first) {
                    $companyid   = $first->id;
                    $companyname = $first->name;
                }
            }
        }

        $component = 'local_sm_graphics_plugin';

        // Full IOMAD tab map.
        $fulltabmap = [
            0 => ['key' => 'configuration',   'icon' => 'fa-cog',          'title' => get_string('iomad_configuration',   $component)],
            1 => ['key' => 'companies',        'icon' => 'fa-building',     'title' => get_string('othermgmt_companies',   $component)],
            2 => ['key' => 'users',            'icon' => 'fa-users',        'title' => get_string('iomad_users',           $component)],
            3 => ['key' => 'courses',          'icon' => 'fa-file-text',    'title' => get_string('othermgmt_courses',     $component)],
            4 => ['key' => 'licenses',         'icon' => 'fa-legal',        'title' => get_string('othermgmt_licenses',    $component)],
            5 => ['key' => 'competences',      'icon' => 'fa-cubes',        'title' => get_string('othermgmt_competences', $component)],
            6 => ['key' => 'emailtemplates',   'icon' => 'fa-envelope',     'title' => get_string('iomad_emailtemplates',  $component)],
            7 => ['key' => 'shop',             'icon' => 'fa-shopping-cart', 'title' => get_string('iomad_shop',           $component)],
            8 => ['key' => 'reports',          'icon' => 'fa-bar-chart-o',  'title' => get_string('othermgmt_reports',     $component)],
        ];

        $categories = [];
        if ($companyid) {
            $categories = local_sm_graphics_plugin_get_othermgmt_categories($component, $companyid, $fulltabmap);
        }

        // Flatten options to plain arrays for the web service return structure.
        $resultcategories = [];
        foreach ($categories as $cat) {
            $options = [];
            if (!empty($cat['options'])) {
                foreach ($cat['options'] as $opt) {
                    $options[] = [
                        'url'         => $opt['url'] ?? '',
                        'icon'        => $opt['icon'] ?? '',
                        'title'       => $opt['title'] ?? '',
                        'description' => $opt['description'] ?? '',
                    ];
                }
            }
            $resultcategories[] = [
                'key'     => $cat['key'] ?? '',
                'icon'    => $cat['icon'] ?? '',
                'title'   => $cat['title'] ?? '',
                'options' => $options,
            ];
        }

        return [
            'companyname' => $companyname ?: '',
            'categories'  => $resultcategories,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'companyname' => new external_value(PARAM_TEXT, 'Current company name'),
            'categories'  => new external_multiple_structure(
                new external_single_structure([
                    'key'   => new external_value(PARAM_TEXT, 'Category key'),
                    'icon'  => new external_value(PARAM_TEXT, 'Category icon CSS class'),
                    'title' => new external_value(PARAM_TEXT, 'Category title'),
                    'options' => new external_multiple_structure(
                        new external_single_structure([
                            'url'         => new external_value(PARAM_RAW, 'Option URL'),
                            'icon'        => new external_value(PARAM_TEXT, 'Option icon CSS class'),
                            'title'       => new external_value(PARAM_TEXT, 'Option title'),
                            'description' => new external_value(PARAM_TEXT, 'Option description'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}

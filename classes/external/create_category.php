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
 * SmartMind catalogue category operations (create, update, delete, list).
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
use external_value;

/**
 * External function to create a catalogue category.
 */
class create_category extends external_api {

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'name'      => new external_value(PARAM_TEXT, 'Category display name'),
            'image_url' => new external_value(PARAM_TEXT, 'Image slug (filename without extension)', VALUE_DEFAULT, ''),
            'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create a new catalogue category record.
     *
     * @param string $name      Category display name.
     * @param string $image_url Image slug.
     * @param int    $sortorder Sort position.
     * @return array  The created record data.
     */
    public static function execute(string $name, string $image_url = '', int $sortorder = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'name'      => $name,
            'image_url' => $image_url,
            'sortorder' => $sortorder,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $now = time();
        $record = (object) [
            'name'         => $params['name'],
            'image_url'    => $params['image_url'],
            'sortorder'    => $params['sortorder'],
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        $id = $DB->insert_record('local_smgp_categories', $record);

        return [
            'id'        => $id,
            'name'      => $params['name'],
            'image_url' => $params['image_url'],
            'sortorder' => $params['sortorder'],
        ];
    }

    /**
     * Define return type.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'        => new external_value(PARAM_INT, 'New category ID'),
            'name'      => new external_value(PARAM_TEXT, 'Category name'),
            'image_url' => new external_value(PARAM_TEXT, 'Image slug'),
            'sortorder' => new external_value(PARAM_INT, 'Sort order'),
        ]);
    }

    /**
     * Get the next available sortorder value.
     *
     * @return int
     */
    public static function get_next_sortorder(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {local_smgp_categories}');
    }

    // ── Read / Update / Delete (called from page files) ──────────────────

    /**
     * Get all categories with their resolved image URLs.
     *
     * @return array
     */
    public static function get_all(): array {
        global $DB, $CFG;

        $records = $DB->get_records('local_smgp_categories', null, 'sortorder ASC');
        $categories = [];

        foreach ($records as $rec) {
            $imagesrc = '';
            if (!empty($rec->image_url)) {
                foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                    $path = $CFG->dirroot . '/theme/smartmind/pix/categories/' . $rec->image_url . '.' . $ext;
                    if (file_exists($path)) {
                        $imagesrc = $CFG->wwwroot . '/theme/smartmind/pix/categories/' . $rec->image_url . '.' . $ext;
                        break;
                    }
                }
            }

            $categories[] = [
                'id'        => (int) $rec->id,
                'name'      => $rec->name,
                'image_url' => $rec->image_url ?? '',
                'image_src' => $imagesrc,
                'sortorder' => (int) $rec->sortorder,
            ];
        }

        return $categories;
    }

    /**
     * Update name and/or image_url for a category.
     *
     * @param int    $id        Category ID.
     * @param string $name      New name.
     * @param string $image_url New image slug (empty = keep current).
     */
    public static function update(int $id, string $name, string $image_url = ''): void {
        global $DB;

        $record = $DB->get_record('local_smgp_categories', ['id' => $id], '*', MUST_EXIST);
        $record->name         = $name;
        $record->timemodified = time();

        if ($image_url !== '') {
            $record->image_url = $image_url;
        }

        $DB->update_record('local_smgp_categories', $record);
    }

    /**
     * Delete a category and its course associations.
     *
     * @param int $id Category ID.
     */
    public static function delete(int $id): void {
        global $DB;

        $DB->delete_records('local_smgp_course_category', ['categoryid' => $id]);
        $DB->delete_records('local_smgp_categories', ['id' => $id]);
    }
}

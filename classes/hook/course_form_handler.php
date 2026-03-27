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

namespace local_sm_graphics_plugin\hook;

use core_course\hook\after_form_definition;
use core_course\hook\after_form_definition_after_data;
use core_course\hook\after_form_validation;

/**
 * Hook callbacks that inject pricing fields into the course edit form.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_form_handler {

    /**
     * Add the pricing fields to the course edit form.
     *
     * @param after_form_definition $hook
     */
    public static function add_pricing_fields(after_form_definition $hook): void {
        global $DB;
        $mform = $hook->mform;

        // --- SmartMind course fields — injected into the General section ---
        // Find a reliable anchor element to insert before.
        // Try descriptionhdr first, fall back to courseformathdr, then null (append).
        $insertbefore = null;
        if ($mform->elementExists('descriptionhdr')) {
            $insertbefore = 'descriptionhdr';
        } else if ($mform->elementExists('courseformathdr')) {
            $insertbefore = 'courseformathdr';
        }

        // If no anchor found, use addElement instead of insertElementBefore.
        $insertfn = function($element) use ($mform, $insertbefore) {
            if ($insertbefore) {
                $mform->insertElementBefore($element, $insertbefore);
            } else {
                $mform->addElement($element);
            }
        };

        // Duration (hours).
        $el = $mform->createElement('text', 'smgp_duration_hours',
            get_string('course_hours', 'local_sm_graphics_plugin'));
        $insertfn($el);
        $mform->setType('smgp_duration_hours', PARAM_FLOAT);
        $mform->setDefault('smgp_duration_hours', '0');
        $mform->addHelpButton('smgp_duration_hours', 'course_hours', 'local_sm_graphics_plugin');

        // Course category (dropdown from local_smgp_categories table).
        $categories = [0 => get_string('course_category_none', 'local_sm_graphics_plugin')];
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_smgp_categories')) {
            $cats = $DB->get_records('local_smgp_categories', null, 'sortorder ASC', 'id, name');
            foreach ($cats as $cat) {
                $categories[$cat->id] = $cat->name;
            }
        }
        $el = $mform->createElement('select', 'smgp_catalogue_cat',
            get_string('course_category_field', 'local_sm_graphics_plugin'), $categories);
        $insertfn($el);
        $mform->setDefault('smgp_catalogue_cat', 0);
        $mform->addHelpButton('smgp_catalogue_cat', 'course_category_field', 'local_sm_graphics_plugin');

        // SmartMind code (course ID).
        $el = $mform->createElement('text', 'smgp_smartmind_code',
            get_string('smartmind_code', 'local_sm_graphics_plugin'));
        $insertfn($el);
        $mform->setType('smgp_smartmind_code', PARAM_TEXT);
        $mform->setDefault('smgp_smartmind_code', '');
        $mform->addHelpButton('smgp_smartmind_code', 'smartmind_code', 'local_sm_graphics_plugin');

        // SEPE code.
        $el = $mform->createElement('text', 'smgp_sepe_code',
            get_string('sepe_code', 'local_sm_graphics_plugin'));
        $insertfn($el);
        $mform->setType('smgp_sepe_code', PARAM_TEXT);
        $mform->setDefault('smgp_sepe_code', '');
        $mform->addHelpButton('smgp_sepe_code', 'sepe_code', 'local_sm_graphics_plugin');

        // Level (beginner, medium, advanced).
        $levels = [
            'beginner' => get_string('level_beginner', 'local_sm_graphics_plugin'),
            'medium'   => get_string('level_medium', 'local_sm_graphics_plugin'),
            'advanced' => get_string('level_advanced', 'local_sm_graphics_plugin'),
        ];
        $el = $mform->createElement('select', 'smgp_level',
            get_string('course_level', 'local_sm_graphics_plugin'), $levels);
        $insertfn($el);
        $mform->setDefault('smgp_level', 'beginner');
        $mform->addHelpButton('smgp_level', 'course_level', 'local_sm_graphics_plugin');

        // Completion percentage.
        $el = $mform->createElement('text', 'smgp_completion_percentage',
            get_string('completion_percentage', 'local_sm_graphics_plugin'));
        $insertfn($el);
        $mform->setType('smgp_completion_percentage', PARAM_INT);
        $mform->setDefault('smgp_completion_percentage', '100');
        $mform->addHelpButton('smgp_completion_percentage', 'completion_percentage', 'local_sm_graphics_plugin');

        // --- Pricing section (separate header, at end of form) ---
        $mform->addElement('header', 'smgp_pricing_header',
            get_string('pricing_header', 'local_sm_graphics_plugin'));

        $mform->addElement('text', 'smgp_price',
            get_string('pricing_amount', 'local_sm_graphics_plugin'));
        $mform->setType('smgp_price', PARAM_FLOAT);
        $mform->setDefault('smgp_price', '0.00');
        $mform->addHelpButton('smgp_price', 'pricing_amount', 'local_sm_graphics_plugin');

        $currencies = [
            'EUR' => 'EUR (€)',
            'USD' => 'USD ($)',
            'GBP' => 'GBP (£)',
        ];
        $mform->addElement('select', 'smgp_currency',
            get_string('pricing_currency', 'local_sm_graphics_plugin'), $currencies);
        $mform->setDefault('smgp_currency', 'EUR');
    }

    /**
     * Load existing pricing data into the form after course data is set.
     *
     * @param after_form_definition_after_data $hook
     */
    public static function load_pricing_data(after_form_definition_after_data $hook): void {
        global $DB;

        $mform = $hook->mform;
        $courseid = $mform->getElementValue('id');

        if (empty($courseid)) {
            return;
        }

        $pricing = $DB->get_record('local_smgp_course_meta', ['courseid' => $courseid]);
        if ($pricing) {
            $mform->setDefault('smgp_price', format_float($pricing->amount, 2));
            $mform->setDefault('smgp_currency', $pricing->currency);
            $mform->setDefault('smgp_duration_hours', format_float($pricing->duration_hours ?? 0, 1));
            $mform->setDefault('smgp_description', $pricing->description ?? '');
            $mform->setDefault('smgp_smartmind_code', $pricing->smartmind_code ?? '');
            $mform->setDefault('smgp_sepe_code', $pricing->sepe_code ?? '');
            $mform->setDefault('smgp_level', $pricing->level ?? 'beginner');
            $mform->setDefault('smgp_completion_percentage', $pricing->completion_percentage ?? 100);
        }

        // Load catalogue category from the link table.
        $catlink = $DB->get_record('local_smgp_course_category', ['courseid' => $courseid]);
        if ($catlink) {
            $mform->setDefault('smgp_catalogue_cat', $catlink->categoryid);
        }
    }

    /**
     * Validate the pricing fields.
     *
     * @param after_form_validation $hook
     */
    public static function validate_pricing(after_form_validation $hook): void {
        $data = $hook->get_data();

        if (!isset($data['smgp_price'])) {
            return;
        }

        $price = unformat_float($data['smgp_price']);
        if ($price !== null && $price < 0) {
            $hook->add_errors([
                'smgp_price' => get_string('pricing_error_negative', 'local_sm_graphics_plugin'),
            ]);
        }

        if (isset($data['smgp_duration_hours'])) {
            $duration = unformat_float($data['smgp_duration_hours']);
            if ($duration !== null && $duration < 0) {
                $hook->add_errors([
                    'smgp_duration_hours' => get_string('pricing_error_negative', 'local_sm_graphics_plugin'),
                ]);
            }
        }
    }
}

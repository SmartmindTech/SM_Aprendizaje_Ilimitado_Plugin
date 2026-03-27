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
 * Manage SmartMind catalogue categories — list, edit (modal), delete.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_login();

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

if (!is_siteadmin()) {
    throw new moodle_exception('accessdenied', 'admin');
}

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/managecategories.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('managecat_title', 'local_sm_graphics_plugin'));
$PAGE->set_heading(get_string('managecat_title', 'local_sm_graphics_plugin'));
$PAGE->set_pagelayout('standard');

$component = 'local_sm_graphics_plugin';
$pageurl   = new moodle_url('/local/sm_graphics_plugin/pages/managecategories.php');

// ── Handle POST actions ──────────────────────────────────────────────────

$action = optional_param('action', '', PARAM_ALPHA);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    // Delete.
    if ($action === 'delete') {
        $id = required_param('categoryid', PARAM_INT);
        \local_sm_graphics_plugin\external\create_category::delete($id);
        redirect($pageurl, get_string('managecat_deleted', $component), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // Update.
    if ($action === 'update') {
        $id   = required_param('categoryid', PARAM_INT);
        $name = required_param('category_name', PARAM_TEXT);

        // Handle optional image upload.
        $imageurl = '';
        if (!empty($_FILES['category_image']['name']) && !empty($_FILES['category_image']['tmp_name'])) {
            $filename = $_FILES['category_image']['name'];
            $tmppath  = $_FILES['category_image']['tmp_name'];
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            $slug = clean_param(
                preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name))),
                PARAM_ALPHANUMEXT
            );
            $slug = rtrim($slug, '_');

            $destdir  = $CFG->dirroot . '/theme/smartmind/pix/categories';
            $destfile = $destdir . '/' . $slug . '.' . $ext;

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                move_uploaded_file($tmppath, $destfile);
                $imageurl = $slug;
            }
        }

        \local_sm_graphics_plugin\external\create_category::update($id, $name, $imageurl);
        redirect($pageurl, get_string('managecat_updated', $component), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ── Render ────────────────────────────────────────────────────────────────

$categories = \local_sm_graphics_plugin\external\create_category::get_all();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/managecategories_page', [
    'heading'        => get_string('managecat_title', $component),
    'categories'     => $categories,
    'hascategories'  => !empty($categories),
    'sesskey'        => sesskey(),
    'action_url'     => $pageurl->out(),
    'back_url'       => (new moodle_url('/local/sm_graphics_plugin/pages/coursemanagement.php'))->out(),
    'label_name'     => get_string('createcat_name', $component),
    'label_image'    => get_string('createcat_image', $component),
    'label_preview'  => get_string('createcat_preview', $component),
    'image_help'     => get_string('createcat_image_help', $component),
    'nocategories'   => get_string('managecat_empty', $component),
]);
echo $OUTPUT->footer();

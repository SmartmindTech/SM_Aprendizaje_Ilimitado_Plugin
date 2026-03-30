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
 * Minimal layout for the Vue/Nuxt SPA.
 *
 * Outputs a clean HTML shell with NO Moodle CSS, JS, or theme chrome.
 * The SPA provides all its own styles (Bootstrap 5, custom SCSS).
 * Only the session cookie and M.cfg are preserved for AJAX calls.
 *
 * @package   theme_smartmind
 * @copyright 2026 SmartMind Technologies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
?>
<!DOCTYPE html>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <?php
    // Output only the minimal Moodle JS config (M.cfg with sesskey, wwwroot).
    // This is needed for Moodle AJAX calls from the SPA.
    echo $OUTPUT->standard_head_html();
    ?>
    <style>
        /* Reset any Moodle styles that might leak through standard_head_html */
        body { margin: 0; padding: 0; background: #fff; }
    </style>
</head>
<body>
    <?php echo $OUTPUT->standard_top_of_body_html(); ?>
    <div id="spa-root">
        <?php echo $OUTPUT->main_content(); ?>
    </div>
    <?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>

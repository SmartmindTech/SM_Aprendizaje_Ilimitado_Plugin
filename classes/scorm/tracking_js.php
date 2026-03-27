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
 * SCORM real-time position tracking — Coordinator.
 *
 * Assembles the complete JavaScript tracking IIFE from modular PHP files.
 * Each module returns a JavaScript string fragment via its get_js() static method.
 * This coordinator concatenates all fragments inside a single IIFE wrapped in
 * <script> tags, so all functions share the same closure scope.
 *
 * ========================================================================
 * MODULE ARCHITECTURE
 * ========================================================================
 *
 * The JavaScript IIFE is assembled from these modules in order:
 *
 *   #   Module                      Description
 *   --- --------------------------- -----------------------------------------
 *   1.  (this file)                 var cmid, scormid, slidescount (PHP interpolated)
 *   2.  tracking_core               State vars, parseSlideNumber, sendProgressUpdate,
 *                                   navigation state, isOurNavigationStillActive,
 *                                   tool detection logging
 *   3.  vendors\lzstring            LZ-String compression library (for Storyline)
 *   4.  tracking_parsers            parseSlideFromSuspendData, extractSlide*,
 *                                   modifySuspendData*
 *   5.  tracking_api_scorm12        wrapScorm12Api() — SCORM 1.2 API wrapping
 *   6.  tracking_api_scorm2004      wrapScorm2004Api() — SCORM 2004 API wrapping
 *   7.  tracking_api_detection      wrapScormApi() dispatcher, Object.defineProperty
 *                                   traps, polling, position polling, DOM observer
 *   8.  vendors\storyline           Storyline detection, polling, navigation
 *   9.  vendors\ispring             iSpring detection, polling, navigation
 *   10. vendors\captivate           Captivate detection, polling, navigation
 *   11. vendors\rise360             Rise 360 detection, polling, navigation
 *   12. vendors\lectora             Lectora detection, polling, navigation
 *   13. vendors\generic             Generic SCORM detection, polling, navigation
 *   14. tracking_navigation         navigateToSlide() dispatcher, iframe reload,
 *                                   inbound message listener
 *
 * Assembly order matters for immediately-executed code (setTimeout, setInterval,
 * Object.defineProperty traps). Function declarations are hoisted by JavaScript,
 * so they can be called before their textual position in the concatenated output.
 *
 * ========================================================================
 * DATA FLOW OVERVIEW
 * ========================================================================
 *
 * SmartLearning frontend
 *     |  postMessage('scorm-navigate-to-slide', {slide: 13})
 *     v
 * Plugin JS (assembled by this coordinator)
 *     |  Sets pendingSlideNavigation, stores in sessionStorage, reloads iframe
 *     v
 * Moodle creates window.API or window.API_1484_11
 *     |  Object.defineProperty trap fires → wrapScormApi()
 *     v
 * wrapScormApi() → wrapScorm12Api() or wrapScorm2004Api()
 *     |  1. Pre-init: modify cmi.suspend_data backing store
 *     |  2. Wrap Get/SetValue for read/write interception
 *     |  3. Schedule post-intercept timeout (12s)
 *     v
 * Content initializes → reads modified suspend_data → starts at correct slide
 *     v
 * Content writes cmi.* → interceptor tracks → sendProgressUpdate() → postMessage
 *     v
 * SmartLearning frontend → updates position bar, progress bar
 *
 * ========================================================================
 * SCORM 1.2 vs 2004 ELEMENT MAPPING
 * ========================================================================
 *
 * | Concept        | SCORM 1.2                    | SCORM 2004                |
 * |----------------|------------------------------|---------------------------|
 * | API Object     | window.API                   | window.API_1484_11        |
 * | Initialize     | LMSInitialize('')            | Initialize('')            |
 * | Get Value      | LMSGetValue(element)         | GetValue(element)         |
 * | Set Value      | LMSSetValue(element, value)  | SetValue(element, value)  |
 * | Commit         | LMSCommit('')                | Commit('')                |
 * | Location       | cmi.core.lesson_location     | cmi.location              |
 * | Status         | cmi.core.lesson_status       | cmi.completion_status     |
 * | Score          | cmi.core.score.raw/min/max   | cmi.score.raw/min/max/scaled |
 * | Suspend Data   | cmi.suspend_data             | cmi.suspend_data          |
 *
 * IMPORTANT: Every interception block is duplicated for SCORM 1.2 and 2004.
 * Changes must always be applied to BOTH versions (tracking_api_scorm12.php
 * and tracking_api_scorm2004.php).
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm;

defined('MOODLE_INTERNAL') || die();

// Import vendor sub-namespace classes (Moodle autoloader resolves these).
use local_sm_graphics_plugin\scorm\vendors\lzstring;
use local_sm_graphics_plugin\scorm\vendors\storyline;
use local_sm_graphics_plugin\scorm\vendors\ispring;
use local_sm_graphics_plugin\scorm\vendors\captivate;
use local_sm_graphics_plugin\scorm\vendors\rise360;
use local_sm_graphics_plugin\scorm\vendors\lectora;
use local_sm_graphics_plugin\scorm\vendors\generic;

/**
 * Assembles the SCORM real-time position tracking JavaScript from modular files.
 *
 * Called by the thin delegator in lib.php:
 *   local_sm_estratoos_plugin_get_postmessage_tracking_js() -> tracking_js::get_script()
 *
 * Also called from the before_footer hook when a SCORM player page is detected.
 */
class tracking_js {

    /**
     * Get the complete SCORM tracking JavaScript as an inline <script> block.
     *
     * Returns a self-executing JavaScript function (IIFE) wrapped in <script> tags.
     * The script is designed to be injected into Moodle's SCORM player page via
     * the before_footer hook.
     *
     * The three PHP parameters are interpolated into the JavaScript as the opening
     * variable declarations that all modules reference throughout execution.
     *
     * Example usage:
     *   echo tracking_js::get_script(6, 1, 25);
     *   // Outputs: <script>(function(){ var cmid = 6; var scormid = 1; ... })();</script>
     *
     * @param int $cmid Course module ID (identifies which SCORM activity on the page).
     * @param int $scormid SCORM activity instance ID (used in postMessage payloads).
     * @param int $slidescount Total slides detected by slidecount::detect() (0 = unknown).
     * @return string Complete HTML <script> block containing the tracking IIFE.
     */
    public static function get_script($cmid, $scormid, $slidescount) {
        // Assemble all JavaScript fragments in the correct order.
        //
        // ORDER MATTERS for immediately-executed code:
        // - tracking_core: state vars must be declared first (other modules reference them)
        // - lzstring: self-contained library, used by tracking_parsers
        // - tracking_parsers: functions used by tracking_api_* modules
        // - tracking_api_scorm12/2004: function declarations (hoisted, but called by detection)
        // - tracking_api_detection: Object.defineProperty traps fire IMMEDIATELY on API creation
        // - vendor files: each starts setTimeout for polling (IMMEDIATE)
        // - tracking_navigation: addEventListener for messages (IMMEDIATE)
        $fragments = [
            tracking_core::get_js(),            // #2: State vars, utilities, progress reporting
            lzstring::get_js(),                 // #3: LZ-String compression (used by Storyline)
            tracking_parsers::get_js(),         // #4: Suspend data parsing & modification
            tracking_api_scorm12::get_js(),     // #5: SCORM 1.2 API wrapping (wrapScorm12Api)
            tracking_api_scorm2004::get_js(),   // #6: SCORM 2004 API wrapping (wrapScorm2004Api)
            tracking_api_detection::get_js(),   // #7: API detection + polling + observer
            storyline::get_js(),                // #8: Articulate Storyline support
            ispring::get_js(),                  // #9: iSpring support
            captivate::get_js(),                // #10: Adobe Captivate support
            rise360::get_js(),                  // #11: Articulate Rise 360 support
            lectora::get_js(),                  // #12: Trivantis Lectora support
            generic::get_js(),                  // #13: Generic SCORM fallback
            tracking_navigation::get_js(),      // #14: Navigation dispatch + messaging
        ];

        $js = implode("\n\n", $fragments);

        // Wrap in an IIFE with PHP-interpolated module-level variables.
        // These three variables are referenced by all modules throughout the IIFE.
        return "<script>\n(function() {\n"
            . "    var cmid = {$cmid};\n"
            . "    var scormid = {$scormid};\n"
            . "    var slidescount = {$slidescount};\n\n"
            . $js
            . "\n})();\n</script>";
    }
}

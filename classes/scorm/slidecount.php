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
 * SCORM slide count detection for the SmartMind Estratoos plugin.
 *
 * Detects the total number of slides in a SCORM package by examining
 * the package's content files. This is used by the real-time tracking system
 * to calculate progress percentages and determine when the user has completed
 * the content.
 *
 * Detection priority order:
 *   1. Articulate Storyline: Count story_content/slideXXX.xml files
 *   2. Generic slide files: Count slides/slideXXX.html or similar patterns
 *   3. Storyline slides.xml: Parse <sld> elements from the manifest
 *   4. SCO count fallback: Use the number of SCOs in the SCORM package
 *   5. Final fallback: Return 1 (single-slide content)
 *
 * Supported authoring tools:
 *   - Articulate Storyline (story_content/slide*.xml)
 *   - Generic HTML5 (slides/slide*.html, content/slide*.js, etc.)
 *   - Any tool that generates numbered slide files
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm;

defined('MOODLE_INTERNAL') || die();

/**
 * Detects the total slide count for SCORM packages.
 *
 * Called by the thin delegator in lib.php:
 *   local_sm_estratoos_plugin_get_scorm_slidecount() → slidecount::detect()
 */
class slidecount {

    /**
     * Detect the total number of slides in a SCORM package.
     *
     * Examines the SCORM package's content files stored in Moodle's file system
     * to determine the slide count. Tries multiple detection strategies in order
     * of reliability.
     *
     * Example usage:
     *   $count = slidecount::detect($cmid, $scormid);
     *   // Returns e.g. 25 for a 25-slide Storyline package
     *   // Returns 1 if slide count cannot be determined
     *
     * @param int $cmid Course module ID (used to get the module context for file access).
     * @param int $scormid SCORM activity instance ID (used to count SCOs as fallback).
     * @return int The detected slide count (minimum 1).
     */
    public static function detect($cmid, $scormid) {
        global $DB;

        // Strategy 1: Count SCOs (Shareable Content Objects) as a baseline.
        // Most SCORM packages have one SCO per logical unit.
        $scocount = $DB->count_records('scorm_scoes', ['scorm' => $scormid, 'scormtype' => 'sco']);

        // Strategy 2-4: Detect slides from content files in the SCORM package.
        try {
            $context = \context_module::instance($cmid);
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, 'sortorder', false);

            $slidenumbers = [];
            $slidesxmlfile = null;

            foreach ($files as $file) {
                $path = $file->get_filepath() . $file->get_filename();

                // Strategy 2: Articulate Storyline slide files.
                // Storyline stores each slide as story_content/slide1.xml, slide2.xml, etc.
                if (preg_match('#/story_content/slide(\d+)\.xml$#i', $path, $m)) {
                    $slidenumbers[$m[1]] = true;
                }

                // Strategy 3: Generic slide files from various authoring tools.
                // Matches patterns like: slides/slide1.html, content/slide5.js, data/slide3.css
                if (preg_match('#/(?:res/data|slides|content|data)/slide(\d+)\.(js|html|css)$#i', $path, $m)) {
                    $slidenumbers[$m[1]] = true;
                }

                // Remember slides.xml for Strategy 4 (Storyline manifest).
                if ($path === '/story_content/slides.xml') {
                    $slidesxmlfile = $file;
                }
            }

            // If we found numbered slide files, use that count.
            if (!empty($slidenumbers)) {
                return count($slidenumbers);
            }

            // Strategy 4: Parse Storyline's slides.xml manifest.
            // Each <sld> element represents one slide in the presentation.
            if ($slidesxmlfile) {
                $content = $slidesxmlfile->get_content();
                $count = preg_match_all('/<sld\s/i', $content);
                if ($count > 0) {
                    return $count;
                }
            }

            // Strategy 5: Parse inline HTML for slide-like sections.
            // Many single-file SCORMs embed slides as divs/sections.
            foreach ($files as $file) {
                $fname = $file->get_filename();
                if (preg_match('/\.(html?)$/i', $fname)) {
                    $htmlcontent = $file->get_content();
                    // Count common inline slide patterns.
                    $patterns = [
                        '/class\s*=\s*["\'][^"\']*\bslide\b[^"\']*["\']/i',       // class="...slide..."
                        '/<section\b[^>]*>/i',                                       // <section> elements
                        '/data-index\s*=\s*["\']\d+["\']/i',                        // data-index="N"
                    ];
                    foreach ($patterns as $pattern) {
                        $matches = preg_match_all($pattern, $htmlcontent);
                        if ($matches >= 3) { // At least 3 to be meaningful.
                            return $matches;
                        }
                    }

                    // Strategy 6: Estimate by file size for single-HTML SCORMs.
                    // Typical slide content is ~2-4 KB per slide.
                    $size = strlen($htmlcontent);
                    if ($size > 5000) {
                        $estimated = max(2, (int) round($size / 3000));
                        if ($estimated > 1 && $estimated <= 50) {
                            return $estimated;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            debugging('Error detecting SCORM slides: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Strategy 7: Fall back to SCO count, or 1 if no SCOs found.
        return $scocount ?: 1;
    }
}

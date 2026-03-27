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
 * Suspend data parsing, slide extraction, and modification functions for the SCORM tracking IIFE.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm;

defined('MOODLE_INTERNAL') || die();

class tracking_parsers {

    /**
     * Returns the JavaScript for suspend data parsing and modification functions.
     *
     * @return string JavaScript code
     */
    public static function get_js() {
        return <<<'JSEOF'
// === SUSPEND DATA PARSING ===

// Function to parse slide from suspend_data (multiple vendor formats).
function parseSlideFromSuspendData(data) {
    if (!data || data.length < 5) return null;

    // 1. Try to parse as JSON directly (some tools don't compress).
    try {
        var parsed = JSON.parse(data);
        var slideNum = extractSlideFromParsedData(parsed);
        if (slideNum !== null) {
            slideSource = 'suspend_data';
            return slideNum;
        }
    } catch (e) {
        // Not JSON, try other patterns.
    }

    // 2. Articulate Storyline: LZ-compressed Base64.
    if (data.match(/^[A-Za-z0-9+/=]{20,}$/)) {
        try {
            // Try LZ decompression first (most common for Storyline).
            var decompressed = LZString.decompressFromBase64(data);
            if (decompressed && decompressed.length > 0) {
                // Try to parse decompressed JSON.
                try {
                    var parsed = JSON.parse(decompressed);
                    var slideNum = extractSlideFromParsedData(parsed);
                    if (slideNum !== null) {
                        slideSource = 'suspend_data';
                        return slideNum;
                    }
                } catch (e) {
                    // Not JSON, search for patterns in decompressed string.
                }

                // v2.0.94: Only apply text regex patterns for confirmed Storyline data.
                // Other vendors (iSpring) use LZ+Base64 with different JSON structures
                // where regex patterns like "slide":N could match wrong fields.
                var isStorylineSd = /"resume"\s*:\s*"\d+_\d+"/.test(decompressed) ||
                    /"n"\s*:\s*"Resume"/i.test(decompressed);
                if (isStorylineSd) {
                    var slideNum = extractSlideFromText(decompressed);
                    if (slideNum !== null) {
                        slideSource = 'suspend_data';
                        return slideNum;
                    }
                }
            }
        } catch (e) {
            // LZ decompression failed
        }

        // Fallback: try plain Base64 decode.
        try {
            var decoded = atob(data);

            // Try JSON.
            try {
                var parsed = JSON.parse(decoded);
                var slideNum = extractSlideFromParsedData(parsed);
                if (slideNum !== null) {
                    slideSource = 'suspend_data';
                    return slideNum;
                }
            } catch (e) {}

            // Search for patterns.
            var slideNum = extractSlideFromText(decoded);
            if (slideNum !== null) {
                slideSource = 'suspend_data';
                return slideNum;
            }
        } catch (e) {
            // Not valid Base64.
        }
    }

    // 2.5. Captivate-style: comma-separated key=value (cs=1,vs=0:1:2:3,qt=0,qr=,ts=30013)
    // cs = current slide (0-based), vs = visited slides (colon-separated)
    if (data.indexOf(',') !== -1 && /\bcs=\d+/.test(data)) {
        var csMatch = data.match(/\bcs=(\d+)/);
        if (csMatch) {
            var cs = parseInt(csMatch[1], 10);
            if (!isNaN(cs) && cs >= 0) {
                slideSource = 'suspend_data';
                return cs + 1; // cs is 0-based
            }
        }
    }

    // 3. URL-encoded format (Adobe Captivate style).
    if (data.indexOf('=') !== -1 && data.indexOf('&') !== -1) {
        try {
            var params = new URLSearchParams(data);
            if (params.has('slide')) {
                slideSource = 'suspend_data';
                return parseInt(params.get('slide'), 10);
            }
            if (params.has('current')) {
                slideSource = 'suspend_data';
                return parseInt(params.get('current'), 10);
            }
            if (params.has('page')) {
                slideSource = 'suspend_data';
                return parseInt(params.get('page'), 10);
            }
        } catch (e) {}
    }

    // 4. Search for patterns in raw string.
    var slideNum = extractSlideFromText(data);
    if (slideNum !== null) {
        slideSource = 'suspend_data';
        return slideNum;
    }

    // If we can't parse suspend_data, return null.
    return null;
}

// Extract slide number from parsed JSON structure.
function extractSlideFromParsedData(parsed) {
    if (!parsed) return null;

    // NOTE: The "l" field in Storyline suspend_data is the FURTHEST/RESUME position,
    // NOT the current viewing position. Do NOT use it for current slide detection.
    // The Poll mechanism is the source of truth for current position.

    // Direct properties.
    if (parsed.currentSlide !== undefined) return parseInt(parsed.currentSlide, 10);
    if (parsed.slide !== undefined) return parseInt(parsed.slide, 10);
    if (parsed.current !== undefined) return parseInt(parsed.current, 10);
    if (parsed.position !== undefined) return parseInt(parsed.position, 10);

    // v2.0.96: Rise 360 bookmark: "section_0" (0-based via parseSlideNumber) or numeric
    if (parsed.bookmark !== undefined) {
        var bm = String(parsed.bookmark);
        var bmSlide = parseSlideNumber(bm);
        if (bmSlide !== null) return bmSlide;
    }

    // Articulate Storyline "resume" format (e.g., "0_14" = scene 0, slide 14).
    // v2.0.91: Storyline uses 0-based indexing; add +1 for 1-based (consistent with extractSlideFromText).
    // NOTE: This is the FURTHEST progress, not current position. Only use as fallback.
    if (parsed.resume !== undefined) {
        var resume = String(parsed.resume);
        // Format: scene_slide or just slide number (0-based).
        var match = resume.match(/^(\d+)_(\d+)$/);
        if (match) {
            // scene_slide format - return the slide number (0-based to 1-based).
            return parseInt(match[2], 10) + 1;
        }
        match = resume.match(/^(\d+)$/);
        if (match) return parseInt(match[1], 10) + 1;
    }

    // Nested structures.
    if (parsed.v && parsed.v.current !== undefined) return parseInt(parsed.v.current, 10);
    if (parsed.data && parsed.data.slide !== undefined) return parseInt(parsed.data.slide, 10);

    // Storyline "d" array format: [{n: "Resume", v: "0_14"}, ...].
    // v2.0.91: Add +1 for 0-based to 1-based conversion (consistent with extractSlideFromText).
    if (parsed.d && Array.isArray(parsed.d)) {
        for (var i = 0; i < parsed.d.length; i++) {
            var item = parsed.d[i];
            if (item.n === 'Resume' || item.n === 'resume') {
                var resume = String(item.v);
                var match = resume.match(/^(\d+)_(\d+)$/);
                if (match) {
                    return parseInt(match[2], 10) + 1;
                }
                match = resume.match(/^(\d+)$/);
                if (match) return parseInt(match[1], 10) + 1;
            }
        }
    }

    // Check for Player.CurrentSlideIndex in variables.
    if (parsed.variables) {
        if (parsed.variables.CurrentSlideIndex !== undefined) {
            return parseInt(parsed.variables.CurrentSlideIndex, 10) + 1; // 0-based to 1-based.
        }
        if (parsed.variables['Player.CurrentSlideIndex'] !== undefined) {
            return parseInt(parsed.variables['Player.CurrentSlideIndex'], 10) + 1;
        }
    }

    return null;
}

// Extract slide number from text using patterns.
// IMPORTANT: Storyline uses 0-based indexing internally, so we add 1 to get 1-based slide numbers.
// NOTE: The "l" field in Storyline suspend_data is the FURTHEST/RESUME position,
// NOT the current viewing position. Do NOT use it for current slide detection.
// The Poll mechanism is the source of truth for current position.
function extractSlideFromText(text) {
    if (!text || typeof text !== 'string') return null;

    // Look for explicit resume/slide patterns.
    // These patterns capture 0-based indices from Storyline's internal format.
    var patterns = [
        /["']?resume["']?\s*[:=]\s*["']?(\d+)_(\d+)["']?/i,     // "resume": "1_5" (scene_slide)
        /["']?resume["']?\s*[:=]\s*["']?(\d+)["']?/i,           // "resume": "5"
        /["']?currentSlide["']?\s*[:=]\s*["']?(\d+)["']?/i,     // "currentSlide": 5
        /["']?CurrentSlideIndex["']?\s*[:=]\s*["']?(\d+)["']?/i, // "CurrentSlideIndex": 5
        /["']?slide["']?\s*[:=]\s*["']?(\d+)["']?/i,            // "slide": 5
        /n["']?\s*[:=]\s*["']?Resume["']?.*?v["']?\s*[:=]\s*["']?(\d+)_(\d+)["']?/i, // Storyline d-array
    ];

    for (var i = 0; i < patterns.length; i++) {
        var match = text.match(patterns[i]);
        if (match) {
            var slideIndex;
            // If scene_slide format, get the slide number.
            if (match[2] !== undefined) {
                slideIndex = parseInt(match[2], 10);
            } else {
                slideIndex = parseInt(match[1], 10);
            }
            // Convert from 0-based to 1-based.
            return slideIndex + 1;
        }
    }

    // Look for scene_slide pattern only in non-random context.
    // Must be preceded by a keyword to avoid matching random number pairs.
    var match = text.match(/(?:resume|state|position|bookmark)['":\s]*(\d+)_(\d+)/i);
    if (match) {
        // Convert from 0-based to 1-based.
        return parseInt(match[2], 10) + 1;
    }

    return null;
}

// === SUSPEND DATA MODIFICATION ===

/**
 * Modify suspend_data to change the resume position.
 * Modifies BOTH the "l" field AND the "resume" field for Storyline navigation.
 * The "l" field is the last slide index, "resume" is "scene_slide" format (e.g., "0_12").
 */
function modifySuspendDataForSlide(originalData, targetSlide) {
    if (!originalData || originalData.length < 5) return originalData;

    var targetIndex = targetSlide - 1; // 0-based index

    // Try LZ decompression (Articulate Storyline format)
    if (originalData.match(/^[A-Za-z0-9+/=]{20,}$/)) {
        try {
            var decompressed = LZString.decompressFromBase64(originalData);
            if (decompressed && decompressed.length > 0) {
                // v2.0.94: Only apply Storyline-specific regexes if the decompressed JSON
                // is confirmed to be Storyline. Other vendors (iSpring) also use LZ+Base64
                // but have completely different JSON structures. Applying Storyline regexes
                // (e.g. /"l":\d+/) to iSpring's JSON corrupts its internal state.
                // Storyline signature: "resume":"X_Y" format or d-array {"n":"Resume","v":"X_Y"}
                var isStoryline = /"resume"\s*:\s*"\d+_\d+"/.test(decompressed) ||
                    /"n"\s*:\s*"Resume"/i.test(decompressed);

                if (isStoryline) {
                    var modified = decompressed;
                    var anyChange = false;

                    // 1. Modify "l" field - last slide position (0-indexed)
                    modified = modified.replace(
                        /"l"\s*:\s*(\d+)/g,
                        function(match, oldValue) {
                            if (parseInt(oldValue) !== targetIndex) {
                                anyChange = true;
                                return '"l":' + targetIndex;
                            }
                            return match;
                        }
                    );

                    // 2. Modify "resume" field - scene_slide format "0_7"
                    // Keep the scene number, only change the slide number
                    modified = modified.replace(
                        /"resume"\s*:\s*"(\d+)_(\d+)"/g,
                        function(match, scene, slide) {
                            if (parseInt(slide) !== targetIndex) {
                                anyChange = true;
                                return '"resume":"' + scene + '_' + targetIndex + '"';
                            }
                            return match;
                        }
                    );

                    // 3. Modify d-array Resume variable - {"n":"Resume","v":"0_7"}
                    modified = modified.replace(
                        /("n"\s*:\s*"Resume"\s*,\s*"v"\s*:\s*")(\d+)_(\d+)(")/gi,
                        function(match, prefix, scene, slide, suffix) {
                            if (parseInt(slide) !== targetIndex) {
                                anyChange = true;
                                return prefix + scene + '_' + targetIndex + suffix;
                            }
                            return match;
                        }
                    );

                    // 4. Modify reverse d-array - {"v":"0_7","n":"Resume"}
                    modified = modified.replace(
                        /("v"\s*:\s*")(\d+)_(\d+)("\s*,\s*"n"\s*:\s*"Resume")/gi,
                        function(match, prefix, scene, slide, suffix) {
                            if (parseInt(slide) !== targetIndex) {
                                anyChange = true;
                                return prefix + scene + '_' + targetIndex + suffix;
                            }
                            return match;
                        }
                    );

                    if (anyChange) {
                        // Re-compress with LZ-String
                        var recompressed = LZString.compressToBase64(modified);
                        if (recompressed) {
                            return recompressed;
                        }
                    }
                }
                // Non-Storyline LZ+Base64 (e.g. iSpring): skip LZ modification.
                // We cannot safely modify their internal LZ-compressed JSON structure.
            }
        } catch (e) {
            // LZ error
        }

        // v2.0.96: Try plain Base64 decode → JSON → modify → re-encode.
        // Handles simple Base64-encoded JSON (e.g. iSpring Base64 simulator).
        try {
            var b64decoded = atob(originalData);
            var b64parsed = JSON.parse(b64decoded);
            var b64modified = false;
            if (b64parsed.slide !== undefined) { b64parsed.slide = targetIndex; b64modified = true; }
            if (b64parsed.currentSlide !== undefined) { b64parsed.currentSlide = targetIndex; b64modified = true; }
            if (b64parsed.position !== undefined) { b64parsed.position = targetIndex; b64modified = true; }
            if (b64parsed.bookmark !== undefined) {
                b64parsed.bookmark = formatLocationValue(b64parsed.bookmark, targetSlide);
                b64modified = true;
            }
            if (b64modified) return btoa(JSON.stringify(b64parsed));
        } catch (e) {
            // Not valid Base64 JSON
        }
    }

    // Captivate format: cs=N,vs=0:1:2:3,...
    if (/\bcs=\d+/.test(originalData) && originalData.indexOf(',') !== -1) {
        var modified = originalData.replace(
            /\bcs=(\d+)/,
            function(match, oldValue) {
                if (parseInt(oldValue) !== targetIndex) {
                    return 'cs=' + targetIndex;
                }
                return match;
            }
        );
        if (modified !== originalData) return modified;
    }

    // v2.0.96: Plain JSON: try to parse and modify known fields.
    // Handles Rise 360 JSON ({"bookmark":"section_0",...}) and other simple JSON formats.
    try {
        var jsonParsed = JSON.parse(originalData);
        var jsonModified = false;
        if (jsonParsed.bookmark !== undefined) {
            jsonParsed.bookmark = formatLocationValue(jsonParsed.bookmark, targetSlide);
            jsonModified = true;
        }
        if (jsonParsed.slide !== undefined) { jsonParsed.slide = targetIndex; jsonModified = true; }
        if (jsonParsed.currentSlide !== undefined) { jsonParsed.currentSlide = targetIndex; jsonModified = true; }
        if (jsonParsed.position !== undefined) { jsonParsed.position = targetIndex; jsonModified = true; }
        if (jsonModified) return JSON.stringify(jsonParsed);
    } catch (e) {
        // Not valid JSON
    }

    return originalData;
}

/**
 * Modify suspend_data text (non-JSON) with new slide position.
 * This handles various SCORM authoring tool formats via regex replacement.
 * Uses flexible patterns similar to extractSlideFromText for detection.
 */
function modifySuspendDataText(text, targetSlide) {
    var targetIndex = targetSlide - 1;
    var modified = text;
    var changesMade = false;

    // Pattern 1: scene_slide format with flexible quoting - "resume":"0_7", resume:0_7, etc.
    // Captures: $1=prefix (resume + quotes/separator), $2=scene, $3=slide, $4=suffix
    var p1 = modified.replace(
        /(["']?resume["']?\s*[:=]\s*["']?)(\d+)_(\d+)(["']?)/gi,
        function(match, prefix, scene, slide, suffix) {
            changesMade = true;
            return prefix + scene + '_' + targetIndex + suffix;
        }
    );
    if (p1 !== modified) modified = p1;

    // Pattern 2: Simple resume number - "resume":"7" or resume:7
    var p2 = modified.replace(
        /(["']?resume["']?\s*[:=]\s*["']?)(\d+)(["']?)(?![_\d])/gi,
        function(match, prefix, oldSlide, suffix) {
            // Skip if this is part of a scene_slide (already handled)
            if (match.indexOf('_') !== -1) return match;
            changesMade = true;
            return prefix + targetIndex + suffix;
        }
    );
    if (p2 !== modified) modified = p2;

    // Pattern 3: Storyline d-array - "n":"Resume"..."v":"0_7" or reverse
    var p3 = modified.replace(
        /("n"\s*:\s*"Resume"[^}]{0,50}"v"\s*:\s*")(\d+)_(\d+)(")/gi,
        function(match, prefix, scene, slide, suffix) {
            changesMade = true;
            return prefix + scene + '_' + targetIndex + suffix;
        }
    );
    if (p3 !== modified) modified = p3;

    // Pattern 4: Reverse order "v":"0_7"..."n":"Resume"
    var p4 = modified.replace(
        /("v"\s*:\s*")(\d+)_(\d+)("[^}]{0,50}"n"\s*:\s*"Resume")/gi,
        function(match, prefix, scene, slide, suffix) {
            changesMade = true;
            return prefix + scene + '_' + targetIndex + suffix;
        }
    );
    if (p4 !== modified) modified = p4;

    // Pattern 5: currentSlide with flexible quoting
    var p5 = modified.replace(
        /(["']?currentSlide["']?\s*[:=]\s*["']?)(\d+)(["']?)/gi,
        function(match, prefix, oldSlide, suffix) {
            changesMade = true;
            return prefix + targetIndex + suffix;
        }
    );
    if (p5 !== modified) modified = p5;

    // Pattern 6: CurrentSlideIndex
    var p6 = modified.replace(
        /(["']?CurrentSlideIndex["']?\s*[:=]\s*["']?)(\d+)(["']?)/gi,
        function(match, prefix, oldSlide, suffix) {
            changesMade = true;
            return prefix + targetIndex + suffix;
        }
    );
    if (p6 !== modified) modified = p6;

    // Pattern 7: slide key
    var p7 = modified.replace(
        /(["']?slide["']?\s*[:=]\s*["']?)(\d+)(["']?)(?![_\d])/gi,
        function(match, prefix, oldSlide, suffix) {
            changesMade = true;
            return prefix + targetIndex + suffix;
        }
    );
    if (p7 !== modified) modified = p7;

    // Pattern 8: position key
    var p8 = modified.replace(
        /(["']?position["']?\s*[:=]\s*["']?)(\d+)(["']?)(?![_\d])/gi,
        function(match, prefix, oldSlide, suffix) {
            changesMade = true;
            return prefix + targetIndex + suffix;
        }
    );
    if (p8 !== modified) modified = p8;

    // Pattern 9: state keyword followed by scene_slide (common bookmark format)
    var p9 = modified.replace(
        /(["']?(?:state|bookmark)["']?\s*[:=]\s*["']?)(\d+)_(\d+)(["']?)/gi,
        function(match, prefix, scene, slide, suffix) {
            changesMade = true;
            return prefix + scene + '_' + targetIndex + suffix;
        }
    );
    if (p9 !== modified) modified = p9;

    // Pattern 10: pageIndex or slideIndex
    var p10 = modified.replace(
        /(["']?(?:page|slide)Index["']?\s*[:=]\s*["']?)(\d+)(["']?)/gi,
        function(match, prefix, oldSlide, suffix) {
            changesMade = true;
            return prefix + targetIndex + suffix;
        }
    );
    if (p10 !== modified) modified = p10;

    return modified;
}
JSEOF;
    }
}

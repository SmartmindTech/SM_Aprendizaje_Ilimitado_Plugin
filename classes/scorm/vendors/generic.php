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
 * Generic SCORM content detection, total slide counting, position polling, and fallback navigation.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm\vendors;

defined('MOODLE_INTERNAL') || die();

class generic {

    /**
     * Returns the JavaScript for generic SCORM content detection, polling, and navigation.
     *
     * @return string JavaScript code as a nowdoc string.
     */
    public static function get_js() {
        return <<<'JSEOF'
// === GENERIC SCORM — Detection, Polling & Navigation ===

// ==========================================================================
// GENERIC HTML5 SCORM: Universal fallback detection
// ==========================================================================

var genericSlideIndex = null;
var genericCheckInterval = null;

// Function to detect generic HTML5 SCORM content.
function findGenericScormContent() {
    var iframes = document.querySelectorAll('iframe');
    for (var i = 0; i < iframes.length; i++) {
        try {
            var iframeWin = iframes[i].contentWindow;
            var iframeDoc = iframeWin.document;

            // Check if it has generic slide/page patterns
            if (iframeDoc.querySelector('.slide, .page, [data-slide], [data-page], [class*="slide"], [class*="page"]')) {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
        } catch (e) {
            // Cross-origin, skip.
        }
    }
    return null;
}

// Function to get total slide count from generic SCORM content.
function getGenericTotalSlides(playerInfo) {
    if (!playerInfo || !playerInfo.window) return null;

    try {
        var win = playerInfo.window;

        // Method 1: Check for common total-slides JavaScript variables.
        var totalVarNames = ['totalSlides', 'totalPages', 'slideCount', 'pageCount',
            'numSlides', 'numPages', 'numberOfSlides', 'numberOfPages',
            'maxSlides', 'maxPages', 'slideTotal', 'pageTotal', 'total_slides',
            'TOTAL_SLIDES', 'SLIDE_COUNT', 'NUM_SLIDES', 'MAX_SLIDES'];
        for (var i = 0; i < totalVarNames.length; i++) {
            if (typeof win[totalVarNames[i]] !== 'undefined' && !isNaN(win[totalVarNames[i]])) {
                var total = parseInt(win[totalVarNames[i]], 10);
                if (total > 1) {
                    return total;
                }
            }
        }

        // Method 2: Count DOM elements (slide/page containers).
        var doc = playerInfo.document;
        if (doc) {
            // Try specific selectors in order of reliability.
            var countSelectors = ['.slide', '.page', '[data-slide]', '[data-page]'];
            for (var i = 0; i < countSelectors.length; i++) {
                var elements = doc.querySelectorAll(countSelectors[i]);
                if (elements.length > 1) {
                    return elements.length;
                }
            }

            // Try finding max value in data-slide or data-page attributes.
            var dataElements = doc.querySelectorAll('[data-slide], [data-page]');
            var maxVal = 0;
            for (var i = 0; i < dataElements.length; i++) {
                var val = parseInt(dataElements[i].getAttribute('data-slide') || dataElements[i].getAttribute('data-page'), 10);
                if (!isNaN(val) && val > maxVal) maxVal = val;
            }
            if (maxVal > 1) {
                return maxVal;
            }
        }
    } catch (e) {
        // Cross-origin or error, skip.
    }
    return null;
}

// Function to get current position from generic SCORM content.
function getGenericCurrentPosition(playerInfo) {
    if (!playerInfo || !playerInfo.document) return null;

    try {
        var doc = playerInfo.document;
        var win = playerInfo.window;

        // Method 1: Check for common slide/page variables
        // Adobe Captivate: cpInfoCurrentSlide (1-based), cpInfoCurrentSlideIndex (0-based)
        // Generic: CURRENT_SLIDE, current_slide, etc.
        if (typeof win.cpInfoCurrentSlide !== 'undefined' && !isNaN(win.cpInfoCurrentSlide)) {
            var val = parseInt(win.cpInfoCurrentSlide, 10);
            if (val >= 1) {
                return val; // cpInfoCurrentSlide is 1-based
            }
        }
        if (typeof win.cpInfoCurrentSlideIndex !== 'undefined' && !isNaN(win.cpInfoCurrentSlideIndex)) {
            var val = parseInt(win.cpInfoCurrentSlideIndex, 10);
            if (val >= 0) {
                return val + 1; // 0-based to 1-based
            }
        }
        var varNames = ['currentSlide', 'CURRENT_SLIDE', 'current_slide', 'currentPage', 'slideIndex', 'pageIndex', 'slideNum', 'pageNum', 'currentIndex'];
        for (var i = 0; i < varNames.length; i++) {
            if (typeof win[varNames[i]] !== 'undefined' && !isNaN(win[varNames[i]])) {
                var rawVal = parseInt(win[varNames[i]], 10);
                // Convert 0-indexed to 1-indexed for display.
                // Variables ending in "Index" are always 0-indexed.
                // For others (currentSlide, currentPage, slideNum, pageNum),
                // if value is 0, it's likely 0-indexed (slide 0 = first slide).
                if (varNames[i].indexOf('Index') !== -1 || rawVal === 0) {
                    return rawVal + 1;
                }
                return rawVal;
            }
        }

        // Method 2: Look for visible slide/page elements
        var selectors = [
            '.slide:not([style*="display: none"]):not([style*="visibility: hidden"])',
            '.page:not([style*="display: none"]):not([style*="visibility: hidden"])',
            '[data-slide]:not([style*="display: none"])',
            '[data-page]:not([style*="display: none"])',
            '.slide.active',
            '.page.active',
            '.slide.current',
            '.page.current',
            '[class*="slide"][class*="active"]',
            '[class*="page"][class*="active"]'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var elem = doc.querySelector(selectors[i]);
            if (elem) {
                // Try to get index from data attribute
                var dataSlide = elem.getAttribute('data-slide') || elem.getAttribute('data-page') || elem.getAttribute('data-index');
                if (dataSlide) {
                    return parseInt(dataSlide, 10);
                }

                // Try to get index from class name
                var classes = elem.className;
                var match = classes.match(/(?:slide|page)[_\-]?(\d+)/i);
                if (match) {
                    return parseInt(match[1], 10);
                }

                // Try sibling count
                if (elem.parentElement) {
                    var siblings = elem.parentElement.children;
                    for (var j = 0; j < siblings.length; j++) {
                        if (siblings[j] === elem) {
                            return j + 1;
                        }
                    }
                }
            }
        }

        // Method 3: Check URL hash
        var hash = win.location.hash;
        if (hash) {
            var match = hash.match(/(?:slide|page|section|chapter)[_\-\/]?(\d+)/i);
            if (match) {
                return parseInt(match[1], 10);
            }
            // Try just number in hash
            match = hash.match(/#(\d+)/);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

    } catch (e) {
        // Error detecting position
    }

    return null;
}

// Start generic SCORM monitoring (lower priority, longer delay).
// Detects total slide count (TOTAL_SLIDES variable) and current position from DOM.
// v2.0.67: Always detect total slides even when SCORM API position tracking is active.
setTimeout(function() {
    genericCheckInterval = setInterval(function() {
        // Only run generic detection if no other tool detected anything
        if (storylineSlideIndex !== null || iSpringSlideIndex !== null ||
            captivateSlideIndex !== null || rise360SectionIndex !== null ||
            lectoraPageIndex !== null) {
            return; // Another detector is working
        }

        // v2.0.67: Determine if SCORM API tracking has position (but may lack total).
        // When lastSlide > 1, the API is tracking position — skip DOM position detection
        // but still detect TOTAL_SLIDES so progress percentage can be calculated.
        var apiHasPosition = lastSlide !== null && lastSlide > 1;

        var content = findGenericScormContent();
        if (content) {
            // v2.0.61: Also detect total slide count and update slidescount.
            var detectedTotal = getGenericTotalSlides(content);
            var totalJustUpdated = false;
            if (detectedTotal !== null && detectedTotal > slidescount) {
                slidescount = detectedTotal;
                totalJustUpdated = true;
            }

            // v2.0.67: If total just updated but API has position, re-send current position
            // with the new total so SmartLearning receives correct totalSlides and progress.
            if (totalJustUpdated && apiHasPosition) {
                sendProgressUpdate(null, null, null, lastSlide);
                return;
            }

            // v2.0.76: Only skip generic detection if API recently updated (within last 5 seconds).
            // For content like Captivate that only commits every ~30 seconds,
            // the API goes stale between commits. Allow generic detection to fill the gaps
            // by reading the content's internal JavaScript variables (cpInfoCurrentSlide, etc.).
            var apiRecentlyUpdated = apiHasPosition && lastApiChangeTime > 0 &&
                (Date.now() - lastApiChangeTime) < 5000;
            if (apiRecentlyUpdated) {
                return;
            }

            // v2.0.84: Detect Captivate format from suspend_data before running generic position detection.
            // Captivate uses 0-based variables (currentSlide=2 means slide 3) which Generic
            // would misinterpret. The SCORM API tracking (SetValue interceptor) already handles
            // Captivate position correctly via cs field parsing.
            if (lastCaptivateCs === null) {
                try {
                    var checkSD = null;
                    if (window.API && window.API.LMSGetValue) {
                        checkSD = window.API.LMSGetValue('cmi.suspend_data');
                    } else if (window.API_1484_11 && window.API_1484_11.GetValue) {
                        checkSD = window.API_1484_11.GetValue('cmi.suspend_data');
                    }
                    if (checkSD && /\bcs=\d+/.test(checkSD)) {
                        var csM = checkSD.match(/\bcs=(\d+)/);
                        if (csM) {
                            lastCaptivateCs = parseInt(csM[1], 10);
                        }
                    }
                } catch(e) {}
            }

            // Skip generic position detection for Captivate content — the SCORM API
            // write interceptor handles Captivate position correctly via cs field parsing.
            // Total slides detection above still runs.
            if (lastCaptivateCs !== null) {
                return;
            }

            var currentPosition = getGenericCurrentPosition(content);
            // Only report if:
            // 1. We got a valid position (>= 1 after 0-index conversion)
            // 2. It's different from what we had OR total just changed
            // 3. It's within reasonable bounds (not more than slidescount if known)
            if (currentPosition !== null &&
                currentPosition >= 1 &&
                (currentPosition !== genericSlideIndex || totalJustUpdated) &&
                (slidescount === 0 || currentPosition <= slidescount)) {
                genericSlideIndex = currentPosition;
                if (currentPosition !== lastSlide || totalJustUpdated) {
                    sendProgressUpdate(null, null, null, currentPosition);
                }
            }
        }
    }, 800); // v2.0.64: Faster interval for quicker total slides detection
}, 1500); // v2.0.64: Reduced start delay (was 5000ms) for faster initial detection

window.addEventListener('beforeunload', function() {
    if (genericCheckInterval) {
        clearInterval(genericCheckInterval);
    }
});

// Navigate via SCORM API (generic fallback).
// Tries to set lesson_location via the SCORM API to trigger navigation.
// This is a last resort and may not work with all content.
function navigateViaGenericApi(targetSlide) {
    try {
        if (window.API && window.API.LMSSetValue) {
            // Try setting lesson_location to trigger navigation
            window.API.LMSSetValue('cmi.core.lesson_location', String(targetSlide));
            // Note: This alone won't cause navigation, but some content may respond
        }
        if (window.API_1484_11 && window.API_1484_11.SetValue) {
            window.API_1484_11.SetValue('cmi.location', String(targetSlide));
        }
        return typeof originalScormSetValue === 'function';
    } catch (e) {
        // SCORM API navigation error
        return false;
    }
}

// Navigate via generic inner iframe methods (goToSlide, gotoSlide, setSlide, jumpToSlide).
// Returns true if a navigation method was found and called, false otherwise.
function navigateGenericInnerFrame(iframeWin, targetSlide) {
    if (iframeWin.goToSlide) {
        iframeWin.goToSlide(targetSlide - 1);
        return true;
    }
    if (iframeWin.gotoSlide) {
        iframeWin.gotoSlide(targetSlide - 1);
        return true;
    }
    if (iframeWin.setSlide) {
        iframeWin.setSlide(targetSlide - 1);
        return true;
    }
    if (iframeWin.jumpToSlide) {
        iframeWin.jumpToSlide(targetSlide - 1);
        return true;
    }
    return false;
}
JSEOF;
    }
}

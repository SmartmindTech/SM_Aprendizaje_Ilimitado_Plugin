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
 * Core state variables, progress reporting, and utility functions for the SCORM tracking IIFE.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm;

defined('MOODLE_INTERNAL') || die();

class tracking_core {

    /**
     * Returns the JavaScript for core state variables, progress reporting, and utility functions.
     *
     * @return string JavaScript code
     */
    public static function get_js() {
        return <<<'JSEOF'
// === CORE STATE & UTILITIES ===
    var lastLocation = null;       // Last value written to DB (may be boosted by v2.0.74)
    var lastWrittenLocation = null; // v2.0.77: Actual value content wrote (before boost)
    var lastStatus = null;
    var lastSlide = null;
    var lastSuspendData = null;
    var lastSuspendDataOriginal = null;
    var lastApiChangeTime = 0; // v2.0.76: Track when SCORM API last reported a change
    var pageLoadTime = Date.now(); // v2.0.87: For resume protection window (suppress stale vendor poller reads)

    // Function to parse slide number from lesson_location.
    function parseSlideNumber(location) {
        if (!location || location === '') return null;

        // Pure number: "5"
        if (/^\d+$/.test(location)) {
            return parseInt(location, 10);
        }
        // v2.0.92: Rise 360 uses 0-based section numbering (section_0 = first section).
        // Must be handled before the generic trailing-number match to add +1.
        if (/^section_\d+$/i.test(location)) {
            return parseInt(location.replace(/^section_/i, ''), 10) + 1;
        }
        // Trailing number: "slide_5", "scene1_slide5"
        var match = location.match(/(\d+)$/);
        if (match) {
            return parseInt(match[1], 10);
        }
        // Fraction format: "5/10"
        match = location.match(/^(\d+)\//);
        if (match) {
            return parseInt(match[1], 10);
        }
        // Articulate format: "slide5" or "#/slides/xxx"
        match = location.match(/slide(\d+)/i);
        if (match) {
            return parseInt(match[1], 10);
        }
        return null;
    }

    // Track the source of slide position for priority handling.
    var slideSource = null; // 'suspend_data', 'navigation', 'score'

    // v2.0.66: References to original (unwrapped) SCORM API for writing grades.
    // Set from within the defineProperty trap when the API is detected.
    var originalScormSetValue = null;  // function(element, value) - bypasses our interceptors
    var originalScormCommit = null;    // function() - commits changes to Moodle DB
    var contentWritesScore = false;    // true if SCORM content writes its own score.raw
    var scormApiVersion = null;        // '1.2' or '2004' - determines element name format

    // v2.0.82: Track last Captivate cs value to detect periodic commits (timer-based, not navigation).
    // Captivate writes suspend_data every ~30s with format cs=N,vs=...,qt=...,qr=...,ts=...
    // The cs field stays stale (unchanged) during periodic commits, causing wrong position updates.
    var lastCaptivateCs = null;

    // v2.0.92: Track the last vendor-format lesson_location value for format-aware boost.
    // When the content writes e.g. "slide_2" (iSpring) or "section_1" (Rise 360), we store
    // the format so the boost can construct "slide_3" or "section_2" instead of numeric "3"/"2".
    // Persisted to localStorage so it survives page refresh (pre-init needs it before LMSInitialize).
    var lastKnownLocationFormat = null;
    try {
        var storedFormat = localStorage.getItem('scorm_location_format_' + cmid);
        if (storedFormat && storedFormat.length > 0 && !/^\d+$/.test(storedFormat)) {
            lastKnownLocationFormat = storedFormat;
        }
    } catch (e) {}

    // v2.0.92: Unwrapped GetValue reference for retry loop backing store correction.
    // Set by wrapScorm12Api/wrapScorm2004Api. Bypasses read interceptors to get raw DB values.
    var originalUnwrappedGetValue = null;

    // v2.0.92: Construct a location value preserving the vendor's text format.
    // Uses the offset between the raw number in the reference and the parsed 1-based slide
    // to correctly map targetSlide back to the vendor's numbering scheme.
    // E.g., formatLocationValue("slide_2", 3) → "slide_3" (iSpring, offset=0)
    //        formatLocationValue("section_1", 3) → "section_2" (Rise 360, offset=-1)
    function formatLocationValue(referenceLocation, targetSlide) {
        if (!referenceLocation) return String(targetSlide);
        var str = String(referenceLocation);
        if (/^\d+$/.test(str)) return String(targetSlide);

        var match = str.match(/^(.*\D)(\d+)$/);
        if (match) {
            var prefix = match[1];
            var rawNum = parseInt(match[2], 10);
            var parsedSlide = parseSlideNumber(str);
            if (parsedSlide !== null) {
                var offset = rawNum - parsedSlide;
                return prefix + (targetSlide + offset);
            }
        }
        return String(targetSlide);
    }

    // Track the furthest slide reached (from score)
    // IMPORTANT: Initialize from sessionStorage/localStorage to prevent reset on iframe reload!
    // sessionStorage persists within the same tab, localStorage persists across tabs/refreshes.
    // v2.0.73: Added localStorage fallback. sessionStorage is tab-scoped and lost on new tabs
    // or page refreshes. localStorage persists, ensuring furthestSlide is known BEFORE the
    // SCORM API is detected, so resume correction can fire before content reads CMI data.
    var furthestSlide = null;
    try {
        var storedFurthest = sessionStorage.getItem('scorm_furthest_slide_' + cmid);
        if (!storedFurthest) {
            storedFurthest = localStorage.getItem('scorm_furthest_slide_' + cmid);
        }
        if (storedFurthest) {
            furthestSlide = parseInt(storedFurthest, 10);
            if (isNaN(furthestSlide) || furthestSlide < 1) {
                furthestSlide = null;
            }
        }
    } catch (e) {
        // storage not available
    }

// === PROGRESS REPORTING ===
    function sendProgressUpdate(location, status, score, directSlide, isApiWrite) {
        var currentSlide = directSlide || parseSlideNumber(location) || lastSlide;

        // v2.0.87: Suppress stale vendor poller reads during resume initialization.
        // Vendor pollers (iSpring, Storyline, etc.) can read the player's initial/loading
        // state (slide 1) before content has fully resumed to the correct position.
        // During the first 10s, suppress directSlide backward movement below furthestSlide.
        // v2.0.90: Skip suppress for isApiWrite — SCORM API writes (step 6A/6C/6D) are
        // authoritative and must not be blocked. Only vendor pollers should be suppressed.
        // v2.0.92: Also suppress during tag navigation. Vendor pollers (e.g. Storyline
        // GetPlayer().GetVar) can read the temporary init position (slide 1) before
        // content processes the modified suspend_data. During tag navigation, suppress
        // values below the tag target; during resume, suppress values below furthestSlide.
        if (directSlide !== null && !isApiWrite && lastSlide !== null && directSlide < lastSlide &&
            furthestSlide !== null && directSlide < furthestSlide &&
            (Date.now() - pageLoadTime) < INTERCEPT_WINDOW_MS) {
            if (!pendingSlideNavigation || directSlide < pendingSlideNavigation.slide) {
                currentSlide = lastSlide;
                directSlide = null; // Prevent backward movement
            }
        }

        // Update lastSlide if we have a new value.
        if (currentSlide !== null && currentSlide !== lastSlide) {
            // v2.0.59: Only allow lastSlide to decrease from directSlide (suspend_data).
            // Lesson_location (poll-based) can be stale during resume init, causing a brief dip.
            if (directSlide !== null || lastSlide === null || currentSlide > lastSlide) {
                lastSlide = currentSlide;
            } else {
                currentSlide = lastSlide;
            }
        }

        // v2.0.87: Track furthestSlide from forward navigation.
        // For SCORMs without score.raw (like Basic SCORM), this is the only way to track progress.
        // During tag navigation, only advance furthest if user naturally goes beyond tag target.
        if (currentSlide !== null && (furthestSlide === null || currentSlide > furthestSlide)) {
            var allowFurthestAdvance = true;
            if (pendingSlideNavigation) {
                // v2.0.87: Block ALL furthest advances during tag navigation
                // unless user naturally navigated beyond the tag target.
                allowFurthestAdvance = currentSlide > pendingSlideNavigation.slide;
            }
            if (allowFurthestAdvance) {
                furthestSlide = currentSlide;
                try {
                    sessionStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                    localStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                } catch (e) {}
                console.log('[SCORM Tracking] Furthest updated → ' + furthestSlide);

                // v2.0.66: Write grade to Moodle for SCORMs that don't manage their own scores.
                // Uses the same furthest-slide-based percentage strategy as Storyline score correction.
                if (!contentWritesScore && originalScormSetValue && slidescount > 1) {
                    var gradePercent = Math.min(Math.round((furthestSlide / slidescount) * 10000) / 100, 100);
                    if (scormApiVersion === '2004') {
                        originalScormSetValue('cmi.score.raw', String(gradePercent));
                        originalScormSetValue('cmi.score.min', '0');
                        originalScormSetValue('cmi.score.max', '100');
                        originalScormSetValue('cmi.score.scaled', String(gradePercent / 100));
                    } else {
                        originalScormSetValue('cmi.core.score.raw', String(gradePercent));
                        originalScormSetValue('cmi.core.score.min', '0');
                        originalScormSetValue('cmi.core.score.max', '100');
                    }
                    if (originalScormCommit) {
                        originalScormCommit();
                    }
                }
            }
        }

        // Build message object.
        // v2.0.63: Send totalSlides=0 when slidescount<=1 to signal "unknown total"
        // and prevent SmartLearning from calculating a misleading 1/1=100%.
        var message = {
            type: 'scorm-progress',
            cmid: cmid,
            scormid: scormid,
            currentSlide: currentSlide,
            totalSlides: (slidescount > 1) ? slidescount : 0,
            furthestSlide: furthestSlide, // Furthest progress reached (from score)
            lessonLocation: location || lastLocation,
            lessonStatus: status || lastStatus,
            score: score,
            slideSource: slideSource, // Include source for debugging
            timestamp: Date.now()
        };

        // Calculate progress percentage based on FURTHEST progress, not current position.
        // This ensures the progress bar shows the maximum achieved, not the current view.
        // v2.0.62: Cap at 100% to avoid misleading values.
        var progressSlide = furthestSlide || currentSlide;
        if (progressSlide !== null && slidescount > 1) {
            message.progressPercent = Math.min(100, Math.round((progressSlide / slidescount) * 100));
        } else if (slidescount <= 1 && progressSlide !== null) {
            // slidescount is 1 or unknown: don't calculate progress (would always be 100%).
            // Leave progressPercent undefined so SmartLearning doesn't show misleading 100%.
            message.progressPercent = null;
        }

        // Also include current position percentage for the position indicator.
        if (currentSlide !== null && slidescount > 1) {
            message.currentPercent = Math.min(100, Math.round((currentSlide / slidescount) * 100));
        }

        // Send to parent (SmartLearning app).
        console.log('[SCORM Tracking] Position: slide=' + currentSlide + '/' + (slidescount || '?') + ' furthest=' + furthestSlide + ' source=' + (directSlide ? 'direct' : slideSource || 'unknown'));
        if (window.parent && window.parent !== window) {
            window.parent.postMessage(message, '*');
        }
        // Also try top window in case of nested iframes.
        if (window.top && window.top !== window && window.top !== window.parent) {
            window.top.postMessage(message, '*');
        }
    }

// === NAVIGATION STATE SETUP ===
    // Check for pending slide navigation from sessionStorage (set before reload).
    var pendingSlideNavigation = null;
    // Multiple intercept system: allow multiple SCORM API intercepts within a time window
    // Storyline calls LMSGetValue/LMSSetValue many times during initialization
    var suspendDataInterceptCount = 0;
    var MAX_INTERCEPTS = 999; // Effectively unlimited - rely on time window instead
    var interceptStartTime = null; // Set when navigation is detected (NOT on first read)
    var INTERCEPT_WINDOW_MS = 10000; // Intercept for 10 seconds to cover slow SCORM init
    var directNavigationTarget = null; // Store target for direct navigation fallback
    var directNavigationAttempted = false; // Track if we've tried direct navigation
    var locationInterceptDisabled = false; // v2.0.65: Disable lesson_location intercept after user navigates away from tag target
    var ourNavigationId = null; // Unique ID for this navigation session (to detect superseded navigations)
    try {
        var navData = sessionStorage.getItem('scorm_pending_navigation_' + cmid);
        if (navData) {
            pendingSlideNavigation = JSON.parse(navData);
            // Store target for direct navigation fallback (in case intercepts fail)
            directNavigationTarget = pendingSlideNavigation.slide;
            directNavigationAttempted = false;
            // Start the intercept timer immediately when navigation is detected
            // This is critical because Storyline WRITES before it READS
            interceptStartTime = Date.now();

            // Use navId from navigation data (generated by embed_renderer.php v5+)
            // This is CRITICAL: embed_renderer.php sets scorm_current_navigation_ IMMEDIATELY
            // so OLD iframes see it and stop intercepting. We must use the SAME navId.
            // For older versions without navId, generate one (backwards compatibility)
            if (pendingSlideNavigation.navId) {
                ourNavigationId = pendingSlideNavigation.navId;
            } else {
                // Fallback for older embed_renderer versions
                ourNavigationId = interceptStartTime + '_' + Math.random().toString(36).substr(2, 9);
            }

            // CRITICAL: Initialize furthestSlide from the passed value
            // SessionStorage is origin-specific, so the frontend passes furthest via the embed URL
            // This ensures progress is preserved during tag navigation
            if (pendingSlideNavigation.furthest !== null && pendingSlideNavigation.furthest !== undefined) {
                var passedFurthest = parseInt(pendingSlideNavigation.furthest, 10);
                if (!isNaN(passedFurthest) && passedFurthest > 0) {
                    furthestSlide = passedFurthest;
                }
            }

            // Clear the pending navigation to prevent re-use on subsequent reloads
            sessionStorage.removeItem('scorm_pending_navigation_' + cmid);
            // Also clear any previous fallback reload marker - new navigation means fresh start
            sessionStorage.removeItem('scorm_fallback_reload_' + cmid);

            // Note: scorm_current_navigation_ is already set by embed_renderer.php (v5+)
            // We just verify/update it here to ensure consistency
            sessionStorage.setItem('scorm_current_navigation_' + cmid, JSON.stringify({
                slide: pendingSlideNavigation.slide,
                navId: ourNavigationId,
                timestamp: interceptStartTime
            }));
        }
    } catch (e) {
        // Error reading pending navigation
    }

    function isOurNavigationStillActive() {
        if (!ourNavigationId) return true; // No navigation, no check needed
        try {
            // Check 1: Is there a newer current navigation? (set by player.php)
            var currentNav = sessionStorage.getItem('scorm_current_navigation_' + cmid);
            if (currentNav) {
                var parsed = JSON.parse(currentNav);
                if (parsed.navId && parsed.navId !== ourNavigationId) {
                    return false;
                }
            }

            // Check 2: Is there a 'navigation starting' signal with a newer timestamp?
            // This catches the race condition where embed.php has loaded but player.php hasn't yet.
            // The 'navigation starting' signal is set IMMEDIATELY when embed.php loads.
            var startingNav = sessionStorage.getItem('scorm_navigation_starting_' + cmid);
            if (startingNav && interceptStartTime) {
                var startingParsed = JSON.parse(startingNav);
                if (startingParsed.timestamp && startingParsed.timestamp > interceptStartTime) {
                    // A newer navigation has started (embed.php loaded after our intercept started)
                    // Check if it's a different target
                    if (pendingSlideNavigation && startingParsed.targetSlide !== null &&
                        startingParsed.targetSlide !== pendingSlideNavigation.slide) {
                        return false;
                    }
                }
            }
        } catch (e) {
            // Error checking active navigation
        }
        return true;
    }

JSEOF;
    }
}

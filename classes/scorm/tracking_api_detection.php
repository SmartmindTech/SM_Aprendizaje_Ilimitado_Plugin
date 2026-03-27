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
 * SCORM API detection (defineProperty traps + polling), position polling, and DOM observer.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides JavaScript for SCORM API detection, position polling, and DOM mutation observation.
 */
class tracking_api_detection {

    /**
     * Returns the JavaScript code for API detection, polling, and DOM observer.
     *
     * @return string JavaScript code
     */
    public static function get_js() {
        return <<<'JSEOF'
// === API DETECTION & POLLING ===

// Dispatcher: delegates to version-specific wrappers (defined in tracking_api_scorm12.php and tracking_api_scorm2004.php)
function wrapScormApi() {
    if (scormApiVersion !== null) return true; // Already wrapped
    if (wrapScorm12Api()) return true;
    if (wrapScorm2004Api()) return true;
    return false;
}

// CRITICAL v2.0.51: Set up Object.defineProperty traps to detect API creation IMMEDIATELY.
// Previously, we polled every 200ms which left a gap where Storyline could read
// the cmi object before our wrapper was installed. With defineProperty, we wrap
// the API the INSTANT it is created (when Moodle assigns window.API = new SCORMapi(...)).
// v2.0.86: ALWAYS set traps (removed guard: pendingSlideNavigation || furthestSlide !== null).
// When localStorage is empty, furthestSlide is null at load time and traps weren't set.
// Captivate reads suspend_data from the pre-populated data model BEFORE calling LMSInitialize,
// so the LMSInitialize wrapper (v2.0.85) fires too late. The trap computes furthestSlide from
// score.raw/lesson_location the instant the API is created, enabling pre-init backing store
// modification before any content can read it.
var apiWrapped = false;

// v2.0.86: Helper to compute furthestSlide from a freshly-created SCORM API.
// Called inside defineProperty traps before wrapScormApi() to ensure furthestSlide is known
// for the pre-init backing store modification in wrapScorm12Api/wrapScorm2004Api.
function computeFurthestFromApi(apiObj, getValueFn, locElement, scoreElement) {
    if (pendingSlideNavigation) return; // v2.0.88: Always compute from DB, take max with existing
    try {
        var loc = getValueFn.call(apiObj, locElement);
        var scr = getValueFn.call(apiObj, scoreElement);
        // v2.0.93: Capture vendor location format for boost on refresh
        if (loc && loc.length > 0 && !/^\d+$/.test(loc)) {
            lastKnownLocationFormat = loc;
            try { localStorage.setItem('scorm_location_format_' + cmid, loc); } catch(e) {}
        }
        var parsedLoc = loc ? parseSlideNumber(loc) : null;
        var parsedScore = scr ? parseFloat(scr) : null;
        var dbFurthest = furthestSlide || 0; // v2.0.88: Start from existing value (may be from localStorage)
        if (parsedLoc && !isNaN(parsedLoc) && parsedLoc >= 1) {
            dbFurthest = Math.max(dbFurthest, parsedLoc);
        }
        if (parsedScore && !isNaN(parsedScore) && parsedScore > 0 && parsedScore <= 100 && slidescount > 1) {
            var scoreSlide = Math.round((parsedScore / 100) * slidescount);
            dbFurthest = Math.max(dbFurthest, scoreSlide);
        }
        if (dbFurthest >= 1) {
            furthestSlide = dbFurthest;
            console.log('[SCORM Tracking] Init furthest=' + furthestSlide + ' (location=' + parsedLoc + ', score=' + parsedScore + '%)');
            try {
                sessionStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                localStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
            } catch(e) {}
        }
    } catch(e) {}
}

// Trap for SCORM 1.2 API
if (typeof window.API === 'undefined' || !window.API) {
    (function() {
        var _storedAPI = window.API;
        try {
            Object.defineProperty(window, 'API', {
                get: function() { return _storedAPI; },
                set: function(newAPI) {
                    _storedAPI = newAPI;
                    if (newAPI && newAPI.LMSSetValue && !apiWrapped) {
                        // v2.0.86: Compute furthestSlide before wrapping
                        if (newAPI.LMSGetValue) {
                            computeFurthestFromApi(newAPI, newAPI.LMSGetValue,
                                'cmi.core.lesson_location', 'cmi.core.score.raw');
                        }
                        apiWrapped = wrapScormApi();
                    }
                },
                configurable: true,
                enumerable: true
            });
        } catch (e) {
            // Could not set defineProperty trap for API
        }
    })();
}
// Trap for SCORM 2004 API
if (typeof window.API_1484_11 === 'undefined' || !window.API_1484_11) {
    (function() {
        var _storedAPI2004 = window.API_1484_11;
        try {
            Object.defineProperty(window, 'API_1484_11', {
                get: function() { return _storedAPI2004; },
                set: function(newAPI) {
                    _storedAPI2004 = newAPI;
                    if (newAPI && newAPI.SetValue && !apiWrapped) {
                        // v2.0.86: Compute furthestSlide before wrapping
                        if (newAPI.GetValue) {
                            computeFurthestFromApi(newAPI, newAPI.GetValue,
                                'cmi.location', 'cmi.score.raw');
                        }
                        apiWrapped = wrapScormApi();
                    }
                },
                configurable: true,
                enumerable: true
            });
        } catch (e) {
            // Could not set defineProperty trap for API_1484_11
        }
    })();
}

// v2.0.91: Compute furthestSlide before immediate wrap (API may already exist).
// The defineProperty trap calls computeFurthestFromApi, but if the API already exists
// (assigned before our IIFE), the trap was never set. Without this, furthestSlide
// is null when wrapScormApi runs, causing pre-init backing store modification to skip.
if (!apiWrapped) {
    if (window.API && window.API.LMSGetValue) {
        computeFurthestFromApi(window.API, window.API.LMSGetValue,
            'cmi.core.lesson_location', 'cmi.core.score.raw');
    } else if (window.API_1484_11 && window.API_1484_11.GetValue) {
        computeFurthestFromApi(window.API_1484_11, window.API_1484_11.GetValue,
            'cmi.location', 'cmi.score.raw');
    }
}

// Try to wrap immediately (API may already exist), then retry with intervals as fallback.
if (!apiWrapped && !wrapScormApi()) {
    var attempts = 0;
    var interval = setInterval(function() {
        attempts++;
        if (wrapScormApi() || attempts > 50) {
            clearInterval(interval);
        }
    }, 200);
}

// DISABLED: Direct navigation fallback was causing conflicts with suspend_data interception.
// The multiple-intercept approach to LMSGetValue is now the primary navigation mechanism.
// Keeping sessionStorage clear logic only to prevent stale data.
if (pendingSlideNavigation) {
    // Note: sessionStorage is cleared after reading (line ~1103), so no additional cleanup needed
}

// === INITIAL PROGRESS & POSITION POLLING ===

// Send initial progress message when page loads.
// v2.0.57: If furthestSlide is known from sessionStorage, send it as the current
// position so SmartLearning shows the correct progress immediately on refresh.
// v2.0.62: Only default to slide 1 for SCORMs with slidescount <= 1 (simple/unknown).
// For multi-slide SCORMs (slidescount > 1), let the SCORM API provide the correct
// position to avoid a brief wrong display (e.g. 1/139 before correcting to 16/139).
// v2.0.67: When sessionStorage is empty, read lesson_location from Moodle as fallback
// for furthestSlide. SessionStorage is tab-scoped and lost on new tabs/browser restart.
// v2.0.70: Fast retry mechanism for initial progress. Instead of a fixed 1000ms timeout,
// try every 200ms and send as soon as lesson_location is available from LMSInitialize.
// This eliminates ~800ms delay for fast-loading content (e.g. Basic SCORM).
var initialProgressSent = false;
var initialRetryCount = 0;
var initialRetryInterval = setInterval(function() {
    if (initialProgressSent) { clearInterval(initialRetryInterval); return; }
    initialRetryCount++;

    // v2.0.72: If furthestSlide is still null, read from Moodle's CMI data.
    // Read both lesson_location AND score.raw to determine the actual furthest slide.
    // lesson_location stores CURRENT position (wrong after backward navigation),
    // score.raw stores furthest progress percentage (more reliable).
    var retryParsedLocation = null;
    var retryParsedScore = null;
    if (!pendingSlideNavigation) { // v2.0.88: Always read DB, take max with existing
        try {
            if (window.API && window.API.LMSGetValue) {
                var loc = window.API.LMSGetValue.call(window.API, 'cmi.core.lesson_location');
                var scr = window.API.LMSGetValue.call(window.API, 'cmi.core.score.raw');
                if (loc) {
                    retryParsedLocation = parseSlideNumber(loc); if (retryParsedLocation === null || retryParsedLocation < 1) retryParsedLocation = null;
                    // v2.0.93: Capture vendor location format for boost on refresh
                    if (loc.length > 0 && !/^\d+$/.test(loc) && !lastKnownLocationFormat) {
                        lastKnownLocationFormat = loc;
                        try { localStorage.setItem('scorm_location_format_' + cmid, loc); } catch(e) {}
                    }
                }
                if (scr) { retryParsedScore = parseFloat(scr); if (isNaN(retryParsedScore) || retryParsedScore <= 0) retryParsedScore = null; }
            } else if (window.API_1484_11 && window.API_1484_11.GetValue) {
                var loc2 = window.API_1484_11.GetValue.call(window.API_1484_11, 'cmi.location');
                var scr2 = window.API_1484_11.GetValue.call(window.API_1484_11, 'cmi.score.raw');
                if (loc2) {
                    retryParsedLocation = parseSlideNumber(loc2); if (retryParsedLocation === null || retryParsedLocation < 1) retryParsedLocation = null;
                    // v2.0.93: Capture vendor location format for boost on refresh
                    if (loc2.length > 0 && !/^\d+$/.test(loc2) && !lastKnownLocationFormat) {
                        lastKnownLocationFormat = loc2;
                        try { localStorage.setItem('scorm_location_format_' + cmid, loc2); } catch(e) {}
                    }
                }
                if (scr2) { retryParsedScore = parseFloat(scr2); if (isNaN(retryParsedScore) || retryParsedScore <= 0) retryParsedScore = null; }
            }
        } catch (e) {}
    }

    // v2.0.70: Try to detect total slides early so the initial progress message
    // includes correct totalSlides. The SCORM content iframe is often already loaded
    // at this point (LMSInitialize was called before this timeout fires).
    if (slidescount <= 1) {
        try {
            var earlyContent = findGenericScormContent();
            if (earlyContent) {
                var earlyTotal = getGenericTotalSlides(earlyContent);
                if (earlyTotal !== null && earlyTotal > slidescount) {
                    slidescount = earlyTotal;
                }
            }
        } catch (e) {}
    }

    // v2.0.72: Determine furthestSlide using max of location and score-based calculation.
    // lesson_location gives current position (wrong after backward nav).
    // score.raw gives furthest progress (reliable). Use whichever is higher.
    if (!pendingSlideNavigation) { // v2.0.88: Always compute, take max with existing
        var scoreBasedSlide = null;
        if (retryParsedScore !== null && retryParsedScore <= 100 && slidescount > 1) {
            scoreBasedSlide = Math.round((retryParsedScore / 100) * slidescount);
        }
        if (retryParsedLocation !== null || scoreBasedSlide !== null) {
            var dbMax = Math.max(retryParsedLocation || 0, scoreBasedSlide || 0);
            var newFurthest = Math.max(furthestSlide || 0, dbMax); // v2.0.88: Max with existing
            if (newFurthest >= 1 && newFurthest !== furthestSlide) {
                furthestSlide = newFurthest;
                console.log('[SCORM Tracking] Retry furthest=' + furthestSlide + ' (location=' + retryParsedLocation + ', score=' + retryParsedScore + '%)');
                // v2.0.92: Persist to storage (retry loop didn't persist before)
                try {
                    sessionStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                    localStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                } catch(e) {}

                // v2.0.92: Correct backing store. The pre-init modification was skipped because
                // furthestSlide was null at wrap time (LMSGetValue returns empty before LMSInitialize).
                // Now that we know furthestSlide, correct the data for content that hasn't read it yet.
                if (originalScormSetValue && originalUnwrappedGetValue) {
                    try {
                        var locEl = scormApiVersion === '1.2' ? 'cmi.core.lesson_location' : 'cmi.location';
                        var sdEl = 'cmi.suspend_data';
                        // Correct lesson_location
                        var curLoc = originalUnwrappedGetValue(locEl);
                        var curLocSlide = curLoc ? parseSlideNumber(curLoc) : null;
                        if (curLocSlide === null || curLocSlide < furthestSlide) {
                            originalScormSetValue(locEl, formatLocationValue(lastKnownLocationFormat || curLoc, furthestSlide));
                        }
                        // Correct suspend_data
                        var curSD = originalUnwrappedGetValue(sdEl);
                        if (curSD && curSD.length > 5) {
                            var sdSlide = parseSlideFromSuspendData(curSD);
                            if (sdSlide !== null && sdSlide < furthestSlide) {
                                var fixedSD = modifySuspendDataForSlide(curSD, furthestSlide);
                                if (fixedSD !== curSD) {
                                    originalScormSetValue(sdEl, fixedSD);
                                    lastSuspendData = fixedSD;
                                }
                            }
                        }
                        if (originalScormCommit) originalScormCommit();
                    } catch(e) {}
                }
            } else if (furthestSlide === null && newFurthest >= 1) {
                furthestSlide = newFurthest;
                console.log('[SCORM Tracking] Retry furthest=' + furthestSlide + ' (location=' + retryParsedLocation + ', score=' + retryParsedScore + '%)');
                // v2.0.92: Persist to storage
                try {
                    sessionStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                    localStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                } catch(e) {}
            }
            // v2.0.95: Even when furthestSlide didn't change, capture vendor format
            // and correct format mismatch. The pre-init writes failed (API not initialized),
            // so this is our backup to ensure the format is known for read interceptors.
            if (newFurthest >= 1 && newFurthest === furthestSlide && originalUnwrappedGetValue) {
                try {
                    var fmtEl = scormApiVersion === '1.2' ? 'cmi.core.lesson_location' : 'cmi.location';
                    var rawLoc = originalUnwrappedGetValue(fmtEl);
                    // Capture non-numeric format from DB value
                    if (rawLoc && rawLoc.length > 0 && !/^\d+$/.test(rawLoc) && !lastKnownLocationFormat) {
                        lastKnownLocationFormat = rawLoc;
                        try { localStorage.setItem('scorm_location_format_' + cmid, rawLoc); } catch(e) {}
                    }
                    // Correct numeric DB value to vendor format if format is known
                    if (rawLoc && /^\d+$/.test(rawLoc) && lastKnownLocationFormat && originalScormSetValue) {
                        var rawSlide = parseSlideNumber(rawLoc);
                        if (rawSlide !== null) {
                            originalScormSetValue(fmtEl, formatLocationValue(lastKnownLocationFormat, rawSlide));
                            if (originalScormCommit) originalScormCommit();
                        }
                    }
                } catch(e) {}
            }
        }
    }

    // Send as soon as we have data, or fall back after 5 attempts (1000ms total).
    if (furthestSlide !== null) {
        clearInterval(initialRetryInterval);
        initialProgressSent = true;
        // v2.0.75: During tag navigation, send tag target as current position.
        // The user is at the tag target, not the furthest slide.
        var initialSlide = pendingSlideNavigation ? pendingSlideNavigation.slide : furthestSlide;
        sendProgressUpdate(null, null, null, initialSlide);
    } else if (initialRetryCount >= 5) {
        clearInterval(initialRetryInterval);
        initialProgressSent = true;
        if (slidescount <= 1 && !pendingSlideNavigation) {
            // v2.0.65: Skip default if tag navigation pending (tag will set correct position).
            // Small/unknown SCORM: send default 1 as a reset signal.
            sendProgressUpdate(null, null, null, 1);
        } else {
            // Multi-slide SCORM without sessionStorage data: let the SCORM API handle it.
            // Don't send a wrong default that would briefly show 1/139.
        }
    }
}, 200);

// Fallback: Poll the SCORM API for current position every 2 seconds.
// Some SCORM content doesn't call SetValue on navigation.
var pollInterval = setInterval(function() {
    var currentLocation = null;
    var currentSuspendData = null;

    // Try SCORM 1.2
    if (window.API && window.API.LMSGetValue) {
        try {
            currentLocation = window.API.LMSGetValue('cmi.core.lesson_location');
            currentSuspendData = window.API.LMSGetValue('cmi.suspend_data');
        } catch (e) {}
    }
    // Try SCORM 2004
    else if (window.API_1484_11 && window.API_1484_11.GetValue) {
        try {
            currentLocation = window.API_1484_11.GetValue('cmi.location');
            currentSuspendData = window.API_1484_11.GetValue('cmi.suspend_data');
        } catch (e) {}
    }

    // Check if location changed.
    if (currentLocation && currentLocation !== lastLocation) {
        lastLocation = currentLocation;
        lastApiChangeTime = Date.now();
        console.log('[SCORM Tracking] Poll: location=' + currentLocation + ' furthest=' + furthestSlide);
        sendProgressUpdate(currentLocation, lastStatus, null, null);

        // DIRECT NAVIGATION FALLBACK: DISABLED
        // The JavaScript intercepts are working correctly - Storyline shows the correct slide.
        // The fallback was causing unnecessary double-loads because cmi.core.lesson_location
        // updates slower than the visual display.
        // Just log and clear the target when position matches (or after timeout).
        if (directNavigationTarget !== null) {
            var currentSlideNum = parseInt(currentLocation, 10);
            if (!isNaN(currentSlideNum) && currentSlideNum === directNavigationTarget) {
                directNavigationTarget = null;
                try {
                    sessionStorage.removeItem('scorm_fallback_reload_' + cmid);
                } catch (e) {}
            }
        }
    }

    // Check if suspend_data changed.
    if (currentSuspendData && currentSuspendData !== lastSuspendData) {
        lastSuspendData = currentSuspendData;
        lastApiChangeTime = Date.now();
        var slideNum = parseSlideFromSuspendData(currentSuspendData);
        if (slideNum !== null && slideNum !== lastSlide) {
            sendProgressUpdate(lastLocation, lastStatus, null, slideNum);
        }
    }
}, 2000);

// Clean up polling when page unloads.
window.addEventListener('beforeunload', function() {
    clearInterval(pollInterval);
});

// Listen for internal navigation events (Storyline, Captivate, etc.).
window.addEventListener('message', function(event) {
    // Some SCORM content sends internal navigation messages.
    if (event.data && typeof event.data === 'object') {
        if (event.data.slide !== undefined) {
            var slideNum = parseInt(event.data.slide, 10);
            if (!isNaN(slideNum) && slideNum !== lastSlide) {
                sendProgressUpdate(null, null, null, slideNum);
            }
        }
    }
}, false);

// === DOM MUTATION OBSERVER ===

// For Articulate Storyline: watch for slide change via DOM mutation.
if (typeof MutationObserver !== 'undefined') {
    var mutationDebounce = null;
    var observer = new MutationObserver(function(mutations) {
        // Debounce to avoid excessive polling.
        if (mutationDebounce) return;
        mutationDebounce = setTimeout(function() {
            mutationDebounce = null;

            var currentSuspendData = null;
            if (window.API && window.API.LMSGetValue) {
                try {
                    currentSuspendData = window.API.LMSGetValue('cmi.suspend_data');
                } catch (e) {}
            } else if (window.API_1484_11 && window.API_1484_11.GetValue) {
                try {
                    currentSuspendData = window.API_1484_11.GetValue('cmi.suspend_data');
                } catch (e) {}
            }

            if (currentSuspendData && currentSuspendData !== lastSuspendData) {
                lastSuspendData = currentSuspendData;
                var slideNum = parseSlideFromSuspendData(currentSuspendData);
                if (slideNum !== null && slideNum !== lastSlide) {
                    sendProgressUpdate(lastLocation, lastStatus, null, slideNum);
                }
            }
        }, 500);
    });

    // Start observing after a delay to let SCORM content initialize.
    setTimeout(function() {
        if (document.body) {
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: false
            });
        }
    }, 3000);
}
JSEOF;
    }
}

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
 * SCORM 1.2 API wrapping — LMSInitialize, LMSGetValue, and LMSSetValue interceptors.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm;

defined('MOODLE_INTERNAL') || die();

class tracking_api_scorm12 {

    /**
     * Returns the JavaScript function wrapScorm12Api() that wraps SCORM 1.2 API calls.
     *
     * @return string JavaScript code
     */
    public static function get_js() {
        return <<<'JSEOF'
// === SCORM 1.2 API WRAPPING (wrapScorm12Api) ===
function wrapScorm12Api() {
if (typeof window.API === 'undefined' || !window.API || !window.API.LMSSetValue) { return false; }

// Save original references before any wrapping (needed for scheduled writes)
var origLMSGetValue12 = window.API.LMSGetValue;
var origLMSSetValue12 = window.API.LMSSetValue;
// v2.0.92: Expose unwrapped GetValue for retry loop backing store correction (detection.php).
originalUnwrappedGetValue = function(el) { return origLMSGetValue12.call(window.API, el); };

// CRITICAL FIX v2.0.51: Directly modify the backing store BEFORE wrapping.
// Storyline does NOT call LMSGetValue('cmi.suspend_data') during initialization.
// It reads directly from Moodle's pre-populated cmi JavaScript object.
// By calling the original LMSSetValue, we update the cmi object at the source,
// ensuring correct data regardless of how Storyline reads it.
if (pendingSlideNavigation && window.API.LMSGetValue && window.API.LMSSetValue) {
    try {
        // 1. Modify suspend_data in the backing store
        var currentSD = window.API.LMSGetValue.call(window.API, 'cmi.suspend_data');
        if (currentSD && currentSD.length > 5) {
            var modifiedSD = modifySuspendDataForSlide(currentSD, pendingSlideNavigation.slide);
            if (modifiedSD !== currentSD) {
                window.API.LMSSetValue.call(window.API, 'cmi.suspend_data', modifiedSD);
            }
        }
        // 2. Set lesson_location directly in the backing store (v2.0.92: preserve vendor format)
        window.API.LMSSetValue.call(window.API, 'cmi.core.lesson_location',
            formatLocationValue(window.API.LMSGetValue.call(window.API, 'cmi.core.lesson_location'), pendingSlideNavigation.slide));
    } catch (e) {}
}

// v2.0.55: On resume (no tag navigation), if sessionStorage has a higher furthest
// slide than the DB, correct the backing store so Storyline resumes at the furthest.
// This is a best-effort optimization - data may not be populated yet when the
// defineProperty trap fires. The read/write interceptors below handle the case
// where this doesn't work.
var resumeReadInterceptCount = 0;
var resumeInterceptStartTime = Date.now(); // Time-based window for resume read intercepts
if (!pendingSlideNavigation && furthestSlide !== null && window.API.LMSGetValue && window.API.LMSSetValue) {
    try {
        var resumeSD = window.API.LMSGetValue.call(window.API, 'cmi.suspend_data');
        if (resumeSD && resumeSD.length > 5) {
            var dbSlide = parseSlideFromSuspendData(resumeSD);
            if (dbSlide !== null && dbSlide < furthestSlide) {
                var corrected = modifySuspendDataForSlide(resumeSD, furthestSlide);
                if (corrected !== resumeSD) {
                    window.API.LMSSetValue.call(window.API, 'cmi.suspend_data', corrected);
                }
            }
        }
        // v2.0.94: Always correct lesson_location for resume, not gated behind suspend_data.
        // For vendors whose suspend_data can't be modified (iSpring Base64, Rise 360),
        // lesson_location is the primary resume mechanism.
        var resumeLoc = window.API.LMSGetValue.call(window.API, 'cmi.core.lesson_location');
        var resumeLocSlide = resumeLoc ? parseSlideNumber(resumeLoc) : null;
        if (resumeLocSlide === null || resumeLocSlide < furthestSlide) {
            window.API.LMSSetValue.call(window.API, 'cmi.core.lesson_location',
                formatLocationValue(lastKnownLocationFormat || resumeLoc, furthestSlide));
        } else if (lastKnownLocationFormat && /^\d+$/.test(resumeLoc)) {
            // v2.0.94: Correct slide but wrong format (numeric "5" vs vendor "slide_5")
            window.API.LMSSetValue.call(window.API, 'cmi.core.lesson_location',
                formatLocationValue(lastKnownLocationFormat, resumeLocSlide));
        }
    } catch (e) {}
}

// v2.0.72: Wrap LMSInitialize so corrections run after DB data is loaded.
// v2.0.95: Always install (removed `furthestSlide === null` gate). Pre-init writes
// fail because Moodle's API returns empty before LMSInitialize. This wrapper is the
// only mechanism that can correct data AFTER the API is functional.
if (!pendingSlideNavigation) {
    var origLMSInitialize12 = window.API.LMSInitialize;
    window.API.LMSInitialize = function(param) {
        var result = origLMSInitialize12.call(window.API, param);
        // v2.0.84: Changed from early return to conditional block.
        // Previously: if (furthestSlide !== null) return result;
        // Problem: the initial retry loop (detection.php) may set furthestSlide from
        // score.raw/lesson_location BEFORE LMSInitialize fires, but does NOT correct
        // suspend_data. The early return skipped suspend_data correction entirely.
        if (furthestSlide === null) {
        try {
            var scoreStr = origLMSGetValue12.call(window.API, 'cmi.core.score.raw');
            var locationStr = origLMSGetValue12.call(window.API, 'cmi.core.lesson_location');
            var score = parseFloat(scoreStr);
            var location = parseSlideNumber(locationStr); // v2.0.92: parseSlideNumber for vendor formats

            if (!isNaN(score) && score > 0 && score <= 100) {
                // Detect total slides (content iframe should be loaded now)
                var content = findGenericScormContent();
                var total = content ? getGenericTotalSlides(content) : null;

                if (total && total > 1) {
                    var furthestFromScore = Math.round((score / 100) * total);
                    slidescount = total;

                    if (location !== null && furthestFromScore > location) {
                        furthestSlide = furthestFromScore;
                        // v2.0.92: Preserve vendor format for lesson_location
                        origLMSSetValue12.call(window.API, 'cmi.core.lesson_location', formatLocationValue(locationStr, furthestSlide));
                        var sd = origLMSGetValue12.call(window.API, 'cmi.suspend_data');
                        if (sd && sd.length > 5) {
                            var fixed = modifySuspendDataForSlide(sd, furthestSlide);
                            if (fixed !== sd) {
                                origLMSSetValue12.call(window.API, 'cmi.suspend_data', fixed);
                                lastSuspendData = fixed; // v2.0.79: Prevent DOM observer from treating boosted DB value as navigation
                            }
                        }
                        try { sessionStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide)); localStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide)); } catch (e) {}
                    } else if (location !== null && location >= 1) {
                        furthestSlide = Math.max(location, furthestFromScore || 0);
                        try { sessionStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide)); localStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide)); } catch (e) {}
                    }
                }
            }
        } catch (e) {}
        } // end if (furthestSlide === null)

        // v2.0.84: Always correct suspend_data if furthestSlide is set and suspend_data
        // is behind. This handles two scenarios:
        // (a) The initial retry loop set furthestSlide before LMSInitialize (early return case)
        // (b) Score logic above set furthestSlide but didn't correct suspend_data because
        //     furthestFromScore <= lesson_location (e.g., Captivate has lesson_location=4
        //     but suspend_data cs=2 meaning slide 3)
        if (furthestSlide !== null && furthestSlide > 1) {
            try {
                var initSD = origLMSGetValue12.call(window.API, 'cmi.suspend_data');
                if (initSD && initSD.length > 5) {
                    var initSlide = parseSlideFromSuspendData(initSD);
                    if (initSlide !== null && initSlide < furthestSlide) {
                        var initFixed = modifySuspendDataForSlide(initSD, furthestSlide);
                        if (initFixed !== initSD) {
                            origLMSSetValue12.call(window.API, 'cmi.suspend_data', initFixed);
                            lastSuspendData = initFixed;
                        }
                    }
                }
            } catch (e) {}
        }

        // v2.0.95: Post-init lesson_location correction for refresh resume.
        // Pre-init writes (lines 76-101) fail because Moodle's API returns empty before LMSInitialize.
        // Now that LMSInitialize loaded DB data, capture format and correct lesson_location.
        if (furthestSlide !== null && furthestSlide >= 1) {
            try {
                var postInitLoc = origLMSGetValue12.call(window.API, 'cmi.core.lesson_location');
                // Capture vendor format from real DB value (first time seeing populated data)
                if (postInitLoc && postInitLoc.length > 0 && !/^\d+$/.test(postInitLoc) && !lastKnownLocationFormat) {
                    lastKnownLocationFormat = postInitLoc;
                    try { localStorage.setItem('scorm_location_format_' + cmid, postInitLoc); } catch(e) {}
                }
                var postInitLocSlide = postInitLoc ? parseSlideNumber(postInitLoc) : null;
                if (postInitLocSlide === null || postInitLocSlide < furthestSlide) {
                    origLMSSetValue12.call(window.API, 'cmi.core.lesson_location',
                        formatLocationValue(lastKnownLocationFormat || postInitLoc, furthestSlide));
                } else if (lastKnownLocationFormat && /^\d+$/.test(postInitLoc)) {
                    // Correct format mismatch: DB has numeric "2" but vendor expects "section_1"
                    origLMSSetValue12.call(window.API, 'cmi.core.lesson_location',
                        formatLocationValue(lastKnownLocationFormat, postInitLocSlide));
                }
            } catch(e) {}
        }

        return result;
    };
}

// Wrap LMSGetValue to intercept reads.
// For tag navigation: intercept lesson_location and suspend_data reads.
// v2.0.58: For resume correction: ALWAYS install when furthestSlide is known.
// Previously depended on pre-computed resumeCorrectedSD which failed when cmi
// data wasn't populated at defineProperty trap time. Now computes on-the-fly.
if (window.API.LMSGetValue && (pendingSlideNavigation || furthestSlide !== null)) {
    var originalGetValue = window.API.LMSGetValue;
    window.API.LMSGetValue = function(element) {
        var result = originalGetValue.call(window.API, element);

        // Intercept lesson_location reads WITHIN the intercept window.
        // During initialization, Storyline uses lesson_location for its resume position.
        // After the window, stop intercepting so the position bar tracks natural navigation.
        if (element === 'cmi.core.lesson_location' && pendingSlideNavigation) {
            // v2.0.65: Stop intercepting if user naturally navigated away from tag target
            if (locationInterceptDisabled) {
                return result;
            }
            var withinWindow = interceptStartTime !== null &&
                (Date.now() - interceptStartTime) < INTERCEPT_WINDOW_MS;
            if (!withinWindow) {
                return result; // Window expired - let actual value through
            }
            if (!isOurNavigationStillActive()) {
                return result;
            }
            return String(pendingSlideNavigation.slide);
        }

        // v2.0.58: Resume correction read intercept (on-the-fly).
        // Computes correction at read time, not at trap time. This handles
        // the case where cmi data wasn't populated when defineProperty trap fired.
        if (element === 'cmi.suspend_data' && furthestSlide !== null && !pendingSlideNavigation) {
            var withinResumeWindow = (Date.now() - resumeInterceptStartTime) < INTERCEPT_WINDOW_MS;
            if (withinResumeWindow && result && result.length > 5) {
                var origSlide = parseSlideFromSuspendData(result);
                if (origSlide !== null && origSlide < furthestSlide) {
                    var correctedSD = modifySuspendDataForSlide(result, furthestSlide);
                    if (correctedSD !== result) {
                        resumeReadInterceptCount++;
                        return correctedSD;
                    }
                }
            }
        }
        if (element === 'cmi.core.lesson_location' && furthestSlide !== null && !pendingSlideNavigation) {
            var withinResumeWindow = (Date.now() - resumeInterceptStartTime) < INTERCEPT_WINDOW_MS;
            if (withinResumeWindow) {
                var locSlide = parseSlideNumber(result); // v2.0.92: parseSlideNumber for vendor formats
                if (locSlide !== null && locSlide < furthestSlide) {
                    return formatLocationValue(lastKnownLocationFormat || result, furthestSlide);
                }
                // v2.0.94: Correct format mismatch even when slide number matches.
                // DB may have numeric "5" from pre-v2.0.92, but vendor expects "slide_5".
                if (locSlide !== null && lastKnownLocationFormat && /^\d+$/.test(result)) {
                    return formatLocationValue(lastKnownLocationFormat, locSlide);
                }
            }
        }

        // Intercept suspend_data reads within the time/count window
        // Storyline calls LMSGetValue multiple times during initialization
        if (element === 'cmi.suspend_data' && pendingSlideNavigation) {
            // CRITICAL: Check if our navigation is still active
            if (!isOurNavigationStillActive()) {
                return result;
            }

            // Start timer on first intercept
            if (interceptStartTime === null) {
                interceptStartTime = Date.now();
            }

            var withinWindow = (Date.now() - interceptStartTime) < INTERCEPT_WINDOW_MS;
            var underLimit = suspendDataInterceptCount < MAX_INTERCEPTS;

            if (withinWindow && underLimit) {
                suspendDataInterceptCount++;

                var modifiedData = modifySuspendDataForSlide(result, pendingSlideNavigation.slide);
                if (modifiedData !== result) {
                    return modifiedData;
                }
            } else if (suspendDataInterceptCount > 0) {
                // Window closed for suspend_data, but keep pendingSlideNavigation for lesson_location
                // lesson_location intercept must continue for the entire session (polling needs it)
                // DO NOT null pendingSlideNavigation - lesson_location intercept still needs it
            }
        }

        return result;
    };
}

var originalSetValue = window.API.LMSSetValue;
// v2.0.66: Store reference to original SCORM 1.2 API for grade writing.
originalScormSetValue = function(el, val) { return originalSetValue.call(window.API, el, val); };
scormApiVersion = '1.2';
if (window.API.LMSCommit) {
    var origCommit = window.API.LMSCommit;
    originalScormCommit = function() { return origCommit.call(window.API, ''); };
}
window.API.LMSSetValue = function(element, value) {
    var valueToWrite = value;

    // Write interception for suspend_data during tag navigation.
    // During intercept window: force TAG TARGET so content initializes at correct slide.
    // v2.0.80: After intercept window, let natural value through (DB gets cs=current).
    if (element === 'cmi.suspend_data' && pendingSlideNavigation) {
        if (!isOurNavigationStillActive()) {
            // Navigation superseded, not intercepting write
        } else {
            var inInterceptWindow = interceptStartTime !== null &&
                (Date.now() - interceptStartTime) < INTERCEPT_WINDOW_MS;

            if (inInterceptWindow) {
                // During initialization: force tag target
                var modifiedValue = modifySuspendDataForSlide(value, pendingSlideNavigation.slide);
                if (modifiedValue !== value) {
                    valueToWrite = modifiedValue;
                }
            }
            // v2.0.80: After intercept window, let natural value through.
            // DB gets cs=current so SmartLearning API polling shows correct position.
            // Resume is handled by pre-init backing store + read interceptors at page load.
        }
    }

    // v2.0.57: Score write interception - ensure score always reflects furthest progress.
    // Storyline writes score based on current position, which decreases when
    // navigating backwards or jumping via tag. Moodle uses this for gradebook/progress.
    if (element === 'cmi.core.score.raw' && furthestSlide !== null && slidescount > 1) {
        var writtenScore = parseFloat(value);
        if (!isNaN(writtenScore)) {
            var furthestScore = Math.min(Math.round((furthestSlide / slidescount) * 10000) / 100, 100);
            if (pendingSlideNavigation) {
                // v2.0.87: During tag navigation, always write furthestScore to prevent DB inflation.
                // Content writes score reflecting tag position, not actual progress.
                valueToWrite = String(furthestScore);
            } else if (writtenScore < furthestScore) {
                valueToWrite = String(furthestScore);
            }
        }
    }

    // v2.0.73: For lesson_location, write max(value, furthestSlide) to DB so that
    // on page refresh, the content resumes at the furthest slide, not the current.
    // v2.0.87: During tag navigation, cap at furthestSlide to prevent DB inflation.
    // Track the actual value (valueToWrite) for position bar updates.
    var dbWriteValue = valueToWrite;
    if (element === 'cmi.core.lesson_location' && furthestSlide !== null) {
        var locSlide = parseSlideNumber(valueToWrite);
        if (locSlide !== null) {
            if (pendingSlideNavigation && locSlide > furthestSlide) {
                // v2.0.87: Cap at furthestSlide during tag navigation to prevent DB inflation.
                // v2.0.92: Preserve vendor format (e.g. "slide_3" not "3")
                dbWriteValue = formatLocationValue(lastKnownLocationFormat || valueToWrite, furthestSlide);
            } else if (locSlide < furthestSlide) {
                // v2.0.92: Preserve vendor format for lesson_location boost
                dbWriteValue = formatLocationValue(lastKnownLocationFormat || valueToWrite, furthestSlide);
            }
        }
    }

    // v2.0.78: Pre-set lastSuspendData BEFORE the DB write to prevent re-entrant tracking.
    // When originalSetValue writes modified suspend_data, Moodle's runtime may trigger
    // a re-entrant call back into this interceptor with the modified value. By pre-setting
    // lastSuspendData, the re-entrant call's value will match and be skipped.
    if (element === 'cmi.suspend_data') {
        lastSuspendData = valueToWrite;
    }

    var result = originalSetValue.call(window.API, element, dbWriteValue);

    // Track lesson_location changes.
    // v2.0.64: Pass parsed slide as directSlide (not location) so backward navigation is allowed.
    // Only poll-based reads should suppress backward movement, not actual SCORM writes.
    // v2.0.77: Compare against lastWrittenLocation (actual content value) not lastLocation
    // (DB value, may be boosted by v2.0.74). Set lastLocation = dbWriteValue so the poll
    // doesn't re-report the boosted DB value as a position change.
    if (element === 'cmi.core.lesson_location' && valueToWrite !== lastWrittenLocation) {
        lastKnownLocationFormat = valueToWrite; // v2.0.92: Track format for boost
        try { localStorage.setItem('scorm_location_format_' + cmid, valueToWrite); } catch(e) {}
        lastWrittenLocation = valueToWrite;
        lastLocation = dbWriteValue;
        lastApiChangeTime = Date.now();
        console.log('[SCORM 1.2] location: ' + valueToWrite + ' (db=' + dbWriteValue + ', furthest=' + furthestSlide + ')');
        var parsedSlide = parseSlideNumber(valueToWrite);
        sendProgressUpdate(null, lastStatus, null, parsedSlide, true);
        // v2.0.65: If user naturally navigated away from tag target, disable the
        // lesson_location read interceptor. Otherwise the poll picks up the stale
        // intercepted value and pushes position back up.
        if (pendingSlideNavigation && !locationInterceptDisabled &&
            String(valueToWrite) !== String(pendingSlideNavigation.slide)) {
            locationInterceptDisabled = true;
        }
    }
    // Track lesson_status changes.
    // v2.0.82: Pass null instead of lastLocation — lastLocation is boosted to
    // furthestSlide by the lesson_location interceptor, so passing it here would
    // override the position bar with furthest instead of current slide.
    if (element === 'cmi.core.lesson_status') {
        lastStatus = valueToWrite;
        sendProgressUpdate(null, valueToWrite, null, null);
    }
    // Track score changes.
    // IMPORTANT: Score represents FURTHEST PROGRESS, not current position.
    if (element === 'cmi.core.score.raw') {
        contentWritesScore = true; // v2.0.66: Mark that this SCORM manages its own scores
        var score = parseFloat(valueToWrite);
        if (!isNaN(score) && slidescount > 0 && score <= 100) {
            // Calculate slide from score percentage.
            var calculatedSlide = Math.round((score / 100) * slidescount);
            calculatedSlide = Math.max(1, Math.min(calculatedSlide, slidescount));

            // Check if we're in the intercept window (tag navigation in progress)
            var inInterceptWindow = pendingSlideNavigation && interceptStartTime !== null &&
                (Date.now() - interceptStartTime) < INTERCEPT_WINDOW_MS;

            // Check if user has naturally navigated beyond the tag target
            // This allows progress to resume after user surpasses the jumped-to slide
            var naturallyBeyondTarget = pendingSlideNavigation === null ||
                calculatedSlide > pendingSlideNavigation.slide;

            // IMPORTANT: Only update furthest during NATURAL navigation, not during tag jumps!
            // When user jumps via tag, they didn't VIEW the intermediate slides,
            // so furthest should NOT increase until they naturally navigate beyond the target.
            // The score from Storyline reflects current position, not actual progress.
            if (!inInterceptWindow && naturallyBeyondTarget &&
                (furthestSlide === null || calculatedSlide > furthestSlide)) {
                furthestSlide = calculatedSlide;
            }

            // Only use score-based slide for CURRENT position if no suspend_data AND not in intercept window.
            // During intercept window, we have a pending navigation target, so don't override with score.
            if (slideSource !== 'suspend_data' && lastSlide === null && !inInterceptWindow) {
                slideSource = 'score';
                sendProgressUpdate(null, lastStatus, valueToWrite, calculatedSlide, true);
            } else {
                // Don't change currentSlide, but send update with furthestSlide for progress bar.
                // v2.0.82: Pass null instead of lastLocation — it's boosted to furthest
                // and would override position bar with wrong slide number.
                sendProgressUpdate(null, lastStatus, valueToWrite, null);
            }
        } else {
            sendProgressUpdate(null, lastStatus, valueToWrite, null);
        }
    }
    // Track suspend_data changes for position and progress.
    // Use the ORIGINAL value (before write interception) for actual position.
    // The modified valueToWrite preserves the tag target in DB, but the
    // original value reflects Storyline's actual current slide.
    // v2.0.78: Also skip if value === lastSuspendData (re-entrant call from Moodle runtime
    // echoing the modified DB value back through our interceptor).
    if (element === 'cmi.suspend_data' && value !== lastSuspendDataOriginal && value !== lastSuspendData) {
        var inInterceptWindow = pendingSlideNavigation && interceptStartTime !== null &&
            (Date.now() - interceptStartTime) < INTERCEPT_WINDOW_MS;

        lastSuspendDataOriginal = value;
        lastSuspendData = valueToWrite;
        lastApiChangeTime = Date.now();
        if (!inInterceptWindow) {
            // Parse from ORIGINAL value to get actual position
            var slideNum = parseSlideFromSuspendData(value);
            if (slideNum !== null) {
                // v2.0.82: Detect Captivate periodic commits (cs unchanged = timer-based, not navigation).
                // Captivate writes suspend_data every ~30s with format cs=N,vs=...,qt=...,qr=...,ts=...
                // The cs field stays stale during periodic commits, causing wrong position updates.
                var isCaptivatePeriodicCommit = false;
                if (/\bcs=\d+/.test(value)) {
                    var csMatch = value.match(/\bcs=(\d+)/);
                    var currentCs = csMatch ? parseInt(csMatch[1], 10) : -1;
                    if (lastCaptivateCs !== null && currentCs === lastCaptivateCs) {
                        isCaptivatePeriodicCommit = true;
                    }
                    lastCaptivateCs = currentCs;
                }

                // v2.0.82: Extract Captivate vs (visited slides) for accurate furthestSlide.
                // vs=0:1:2:3 means slides 0-3 visited → furthest is 4 (0-based to 1-based).
                // v2.0.87: During tag navigation, only advance furthest if beyond tag target.
                if (/\bvs=/.test(value)) {
                    var vsMatch = value.match(/\bvs=([0-9:]+)/);
                    if (vsMatch) {
                        var visited = vsMatch[1].split(':').map(function(s) { return parseInt(s, 10); }).filter(function(n) { return !isNaN(n); });
                        if (visited.length > 0) {
                            var maxVisited = Math.max.apply(null, visited) + 1; // 0-based to 1-based
                            var allowVsAdvance = !pendingSlideNavigation || maxVisited > pendingSlideNavigation.slide;
                            if (allowVsAdvance && (furthestSlide === null || maxVisited > furthestSlide)) {
                                furthestSlide = maxVisited;
                                try {
                                    sessionStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                                    localStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                                } catch (e) {}
                            }
                        }
                    }
                }

                // Update furthestSlide from parsed slide (only increases)
                // v2.0.87: During tag navigation, only advance furthest if beyond tag target.
                var allowAdvance = !pendingSlideNavigation || slideNum > pendingSlideNavigation.slide;
                if (allowAdvance && (furthestSlide === null || slideNum > furthestSlide)) {
                    furthestSlide = slideNum;
                    try {
                        sessionStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                        localStorage.setItem('scorm_furthest_slide_' + cmid, String(furthestSlide));
                    } catch (e) {}
                }

                // Send position update — skip if Captivate periodic commit (cs is stale)
                if (!isCaptivatePeriodicCommit && slideNum !== lastSlide) {
                    console.log('[SCORM 1.2] suspend_data slide: ' + slideNum + ' (furthest=' + furthestSlide + ')');
                    sendProgressUpdate(lastLocation, lastStatus, null, slideNum, true);
                }
            }
        }
    }

    return result;
};

// v2.0.87: Schedule a proactive write after the intercept window closes.
// ALWAYS write furthestSlide and furthestScore unconditionally during tag navigation.
// Tag navigation may have inflated DB values (lesson_location=tag, score=tag%).
// The post-intercept write corrects DB to actual furthest progress.
if (pendingSlideNavigation) {
    setTimeout(function() {
        if (furthestSlide === null) return;
        try {
            // v2.0.87: Always write furthestSlide (tag navigation may have inflated DB values).
            // v2.0.92: Preserve vendor format for lesson_location
            var currentLoc12Post = origLMSGetValue12.call(window.API, 'cmi.core.lesson_location');
            origLMSSetValue12.call(window.API, 'cmi.core.lesson_location',
                formatLocationValue(lastKnownLocationFormat || currentLoc12Post, furthestSlide));
            var furthestScore = null;
            if (slidescount > 1) {
                furthestScore = Math.min(Math.round((furthestSlide / slidescount) * 10000) / 100, 100);
                origLMSSetValue12.call(window.API, 'cmi.core.score.raw', String(furthestScore));
            }
            console.log('[SCORM 1.2] Post-intercept write: location=' + furthestSlide + ' score=' + furthestScore);
            window.API.LMSCommit('');
        } catch (e) {}
    }, INTERCEPT_WINDOW_MS + 2000);
}

// v2.0.80: Schedule a proactive write to ensure DB has correct resume data.
// Only write lesson_location and score - NOT suspend_data.cs.
if (!pendingSlideNavigation && furthestSlide !== null) {
    setTimeout(function() {
        if (furthestSlide === null) return;
        try {
            var currentLoc = origLMSGetValue12.call(window.API, 'cmi.core.lesson_location');
            var locSlide = parseSlideNumber(currentLoc); // v2.0.92: parseSlideNumber for vendor formats
            if (locSlide === null || locSlide < furthestSlide) {
                // v2.0.92: Preserve vendor format for lesson_location
                origLMSSetValue12.call(window.API, 'cmi.core.lesson_location',
                    formatLocationValue(lastKnownLocationFormat || currentLoc, furthestSlide));
            }
            if (slidescount > 1) {
                var currentScore = origLMSGetValue12.call(window.API, 'cmi.core.score.raw');
                var furthestScore = Math.min(Math.round((furthestSlide / slidescount) * 10000) / 100, 100);
                if (!currentScore || parseFloat(currentScore) < furthestScore) {
                    origLMSSetValue12.call(window.API, 'cmi.core.score.raw', String(furthestScore));
                }
            }
            window.API.LMSCommit('');
        } catch (e) {}
    }, INTERCEPT_WINDOW_MS + 2000);
}

return true;
}
JSEOF;
    }
}

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
 * Navigation dispatch, iframe reload, and inbound message handling for the SCORM tracking IIFE.
 *
 * Contains:
 * - navigateToSlide()           — Clean dispatcher calling vendor-specific navigateViaXxx() functions
 * - navigateViaInnerFrames()    — Shared inner-iframe navigation (posts messages, calls vendor functions)
 * - modifySuspendDataAndReload() — Last-resort navigation via suspend_data + iframe reload
 * - reloadScormContentIframe()  — Finds and reloads the SCORM content iframe
 * - Message listener             — Inbound postMessage handler for scorm-navigate-to-slide
 *
 * Vendor-specific navigation functions (navigateViaStoryline, navigateViaCaptivate, etc.)
 * are defined in their respective vendor files under classes/scorm/vendors/.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm;

defined('MOODLE_INTERNAL') || die();

class tracking_navigation {

    /**
     * Returns the JavaScript for slide navigation dispatch, iframe reload,
     * and inbound message handling.
     *
     * @return string JavaScript code (no script tags)
     */
    public static function get_js() {
        return <<<'JSEOF'

// ===================================================================
// SECTION 14: SLIDE NAVIGATION DISPATCH + IFRAME RELOAD + MESSAGING
// ===================================================================
//
// navigateToSlide() is a clean dispatcher that tries vendor-specific
// navigation APIs in priority order, then falls back to suspend_data
// modification and iframe reload.
//
// The vendor-specific navigateViaXxx() functions are defined in their
// respective vendor files (storyline.php, captivate.php, ispring.php,
// lectora.php, rise360.php, generic.php). They are available in the
// shared IIFE scope because vendor files are loaded before this file.

/**
 * Attempt to navigate to a specific slide.
 * Tries vendor-specific APIs in priority order, then falls back to
 * suspend_data modification and iframe reload.
 * @param {number} targetSlide - The 1-based slide number to navigate to.
 * @param {boolean} skipReload - If true, skip reload-based navigation.
 * @returns {boolean} True if navigation was attempted.
 */
function navigateToSlide(targetSlide, skipReload) {
    // Try vendor-specific navigation APIs in priority order.
    // Each navigateViaXxx() function returns true if navigation succeeded.
    // These functions are defined in classes/scorm/vendors/*.php
    if (navigateViaStoryline(targetSlide)) return true;
    if (navigateViaCaptivate(targetSlide)) return true;
    if (navigateViaISpring(targetSlide)) return true;
    if (navigateViaLectora(targetSlide)) return true;
    if (navigateViaRise360(targetSlide)) return true;

    // Try generic SCORM API navigation (set lesson_location directly)
    if (navigateViaGenericApi(targetSlide)) return true;

    // Try navigation in inner iframes (posts messages + tries vendor functions)
    if (navigateViaInnerFrames(targetSlide)) return true;

    // SUSPEND_DATA MODIFICATION: Last resort — modify resume position and reload
    if (skipReload) {
        // Just modify suspend_data via SCORM API without reloading
        try {
            if (window.API && window.API.LMSGetValue && window.API.LMSSetValue) {
                var currentData = window.API.LMSGetValue('cmi.suspend_data');
                if (currentData) {
                    var modifiedData = modifySuspendDataForSlide(currentData, targetSlide);
                    if (modifiedData !== currentData) {
                        window.API.LMSSetValue('cmi.suspend_data', modifiedData);
                        window.API.LMSCommit('');
                    }
                }
            }
        } catch (e) {
            // API modification error
        }
        return false; // Don't claim success - let the content naturally update
    }

    // Full reload-based navigation
    return modifySuspendDataAndReload(targetSlide);
}

// -------------------------------------------------------------------
// INNER IFRAME NAVIGATION (shared across all vendors)
// -------------------------------------------------------------------

/**
 * Try to find and navigate via inner SCORM content iframes.
 * Posts navigation messages and tries vendor-specific functions.
 * The vendor inner-frame functions (navigateGenericInnerFrame, navigateStorylineInnerFrame,
 * navigateISpringInnerFrame) are defined in their respective vendor files.
 * @param {number} targetSlide - 1-based slide number.
 * @returns {boolean} True if navigation succeeded.
 */
function navigateViaInnerFrames(targetSlide) {
    try {
        var iframes = document.querySelectorAll('iframe');
        for (var i = 0; i < iframes.length; i++) {
            try {
                var innerWin = iframes[i].contentWindow;
                if (!innerWin) continue;

                // Post navigation message to inner iframe
                innerWin.postMessage({
                    type: 'scorm-navigate-to-slide',
                    cmid: cmid,
                    slide: targetSlide
                }, '*');
                try {
                    // Try generic inner iframe methods (defined in vendors/generic.php)
                    if (navigateGenericInnerFrame(innerWin, targetSlide)) return true;

                    // Try vendor-specific inner iframe navigation
                    // (defined in vendors/storyline.php and vendors/ispring.php)
                    if (navigateStorylineInnerFrame(innerWin, targetSlide)) return true;
                    if (navigateISpringInnerFrame(innerWin, targetSlide)) return true;

                    // Video player detection (non-navigable)
                    if (innerWin.player && innerWin.player.seekTo) {
                        // Inner iframe has video player, cannot convert slide to time
                    }
                } catch (e) {
                    // Cross-origin, continue
                }
            } catch (e) {
                // Cross-origin frame access, skip
            }
        }
    } catch (e) {
        // Inner iframe navigation error
    }
    return false;
}

// -------------------------------------------------------------------
// SUSPEND DATA RELOAD & IFRAME MANAGEMENT
// -------------------------------------------------------------------

/**
 * Store pending navigation in sessionStorage and reload SCORM content.
 * The LMSGetValue interceptor will modify suspend_data on the first read after reload.
 * This approach works because we intercept BEFORE the content initializes.
 * @param {number} targetSlide - The 1-based slide number to navigate to.
 * @returns {boolean} True if navigation was initiated.
 */
function modifySuspendDataAndReload(targetSlide) {
    // ANTI-RELOAD-LOOP: Check if we've already attempted a fallback reload for this slide
    // This uses a separate key from 'scorm_pending_navigation_' because that one gets cleared on read
    // This prevents cascading reloads when Poll fallback triggers multiple times
    var fallbackKey = 'scorm_fallback_reload_' + cmid;
    try {
        var existingFallback = sessionStorage.getItem(fallbackKey);
        if (existingFallback) {
            var fallbackData = JSON.parse(existingFallback);
            // If same slide and recent (within 15 seconds), don't trigger another reload
            if (fallbackData.slide === targetSlide && (Date.now() - fallbackData.timestamp) < 15000) {
                return false;
            }
        }
    } catch (e) {
        // Continue with normal flow if parsing fails
    }

    // Mark that we're attempting a fallback reload for this slide
    try {
        sessionStorage.setItem(fallbackKey, JSON.stringify({
            slide: targetSlide,
            timestamp: Date.now()
        }));
    } catch (e) {
        // Could not store fallback status
    }

    // Store navigation target in sessionStorage
    // This will be read by the LMSGetValue interceptor on the next page load
    try {
        var navData = {
            slide: targetSlide,
            cmid: cmid,
            timestamp: Date.now()
        };
        sessionStorage.setItem('scorm_pending_navigation_' + cmid, JSON.stringify(navData));
    } catch (e) {
        // Failed to store pending navigation
        return false;
    }

    // Reload the SCORM content
    // When it reloads, the LMSGetValue interceptor will return modified suspend_data
    reloadScormContentIframe();

    return true;
}

/**
 * Find and reload the SCORM content iframe.
 */
function reloadScormContentIframe() {
    // Look for the SCORM content iframe
    var iframes = document.querySelectorAll('iframe');
    var reloaded = false;

    for (var i = 0; i < iframes.length; i++) {
        var iframe = iframes[i];
        var src = iframe.src || '';

        // Look for SCORM content iframes (typically contain the actual content)
        // Skip the outer Moodle player iframes
        if (src.indexOf('/mod/scorm/') === -1 && src.length > 0) {
            try {
                iframe.contentWindow.location.reload();
                reloaded = true;
            } catch (e) {
                // Cross-origin, try setting src
                try {
                    var currentSrc = iframe.src;
                    iframe.src = '';
                    setTimeout(function() {
                        iframe.src = currentSrc;
                    }, 100);
                    reloaded = true;
                } catch (e2) {
                    // Could not reload iframe
                }
            }
            break;
        }
    }

    // If no iframe found, try to send a message to the parent (SmartLearning Vue app)
    // asking it to reload the embed. This is more elegant than window.location.reload()
    // because it doesn't cause cascading re-initialization of the Vue app.
    if (!reloaded) {
        // Try to send message to parent window (SmartLearning Vue app)
        try {
            var pendingNav = sessionStorage.getItem('scorm_pending_navigation_' + cmid);
            var targetSlide = pendingNav ? JSON.parse(pendingNav).slide : null;

            // Send message to all parent frames up to top
            var currentWindow = window;
            var messageSent = false;

            while (currentWindow !== window.top) {
                try {
                    currentWindow.parent.postMessage({
                        type: 'scorm-reload-embed',
                        cmid: cmid,
                        slide: targetSlide,
                        timestamp: Date.now()
                    }, '*');
                    messageSent = true;
                } catch (e) {
                    // Cross-origin, continue to next parent
                }
                currentWindow = currentWindow.parent;
            }

            // Also try posting to top window directly
            if (window.top !== window) {
                try {
                    window.top.postMessage({
                        type: 'scorm-reload-embed',
                        cmid: cmid,
                        slide: targetSlide,
                        timestamp: Date.now()
                    }, '*');
                    messageSent = true;
                } catch (e) {}
            }

            // If message was sent, don't reload - let the parent handle it
            if (messageSent) {
                return;
            }
        } catch (e) {
            // Could not send message to parent
        }

        // Fallback: reload the whole page if postMessage failed
        window.location.reload();
    }
}

// -------------------------------------------------------------------
// INBOUND MESSAGE LISTENER
// -------------------------------------------------------------------

// Listen for navigation requests from SmartLearning parent window
window.addEventListener('message', function(event) {
    // Check for navigation request message
    if (event.data && event.data.type === 'scorm-navigate-to-slide') {
        var targetCmid = event.data.cmid;
        var targetSlide = event.data.slide;

        // Verify this message is for this SCORM module
        if (targetCmid && targetCmid !== cmid) {
            return;
        }

        if (targetSlide && !isNaN(targetSlide)) {
            var success = navigateToSlide(parseInt(targetSlide, 10));

            // Send response back to parent
            var response = {
                type: 'scorm-navigation-result',
                cmid: cmid,
                targetSlide: targetSlide,
                success: success,
                currentSlide: lastSlide
            };

            if (window.parent && window.parent !== window) {
                window.parent.postMessage(response, '*');
            }
            if (window.top && window.top !== window && window.top !== window.parent) {
                window.top.postMessage(response, '*');
            }
        }
    }
}, false);

JSEOF;
    }
}

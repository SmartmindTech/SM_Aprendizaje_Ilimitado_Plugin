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
 * iSpring SCORM player detection, slide tracking, and navigation.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm\vendors;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides iSpring-specific JavaScript for SCORM player detection, slide tracking,
 * and navigation.
 */
class ispring {

    /**
     * Returns the iSpring detection, polling, and navigation JavaScript.
     *
     * @return string JavaScript code for iSpring support.
     */
    public static function get_js() {
        return <<<'JSEOF'
// === ISPRING — Detection, Polling & Navigation ===

// ==========================================================================
// ISPRING SPECIFIC: Slide detection from iSpring Presentation API
// ==========================================================================

var iSpringSlideIndex = null;
var iSpringCheckInterval = null;

// Function to find the iSpring player in iframes.
function findISpringPlayer() {
    var iframes = document.querySelectorAll('iframe');
    for (var i = 0; i < iframes.length; i++) {
        try {
            var iframeWin = iframes[i].contentWindow;
            var iframeDoc = iframeWin.document;

            // iSpring exposes iSpringPresentationAPI or window.frames.content
            if (iframeWin.iSpringPresentationAPI ||
                iframeWin.ispringPresentationConnector ||
                iframeWin.ISPRING) {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
            // Check for iSpring's player object
            if (iframeWin.player && typeof iframeWin.player.view !== 'undefined') {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
            // Check for iSpring's g_oPresentation global (PowerPoint export)
            if (iframeWin.g_oPresentation || iframeWin.g_oPres || iframeWin.oPresentation) {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
            // Check for iSpring's PresentationAPI or PresentationManager
            if (iframeWin.PresentationAPI || iframeWin.PresentationManager || iframeWin.presentationApi) {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
            // Check for iSpring's slide navigation functions
            if (iframeWin.gotoSlide || iframeWin.goToSlide || iframeWin.navigateToSlide) {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
            // Check for iSpring's player UI elements
            if (iframeDoc.querySelector('#ispring-player, .ispring-player, [class*="ispring"], #presentation-container')) {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
            // Check for iSpring's slide container
            if (iframeDoc.querySelector('#slide-container, .slide-container, #slides-container, .slides-wrapper')) {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
            // v2.0.93: Check for iSpring.LMS (modern LZ-String variant, lowercase 'i')
            if (iframeWin.iSpring && iframeWin.iSpring.LMS) {
                return { iframe: iframes[i], window: iframeWin, document: iframeDoc };
            }
            // Check nested iframes (iSpring often uses nested structure)
            var nestedIframes = iframeDoc.querySelectorAll('iframe');
            for (var j = 0; j < nestedIframes.length; j++) {
                try {
                    var nestedWin = nestedIframes[j].contentWindow;
                    var nestedDoc = nestedWin.document;
                    if (nestedWin.iSpringPresentationAPI || nestedWin.g_oPresentation ||
                        nestedWin.gotoSlide || nestedWin.player ||
                        (nestedWin.iSpring && nestedWin.iSpring.LMS)) {
                        return { iframe: nestedIframes[j], window: nestedWin, document: nestedDoc };
                    }
                } catch (e) {
                    // Cross-origin nested iframe
                }
            }
        } catch (e) {
            // Cross-origin, skip.
        }
    }
    return null;
}

// Function to get current slide from iSpring player.
function getISpringCurrentSlide(playerInfo) {
    if (!playerInfo || !playerInfo.window) return null;

    try {
        var win = playerInfo.window;

        // Method 1: iSpring Presentation API (newer versions)
        if (win.iSpringPresentationAPI) {
            var api = win.iSpringPresentationAPI;
            if (api.slidesCount && api.currentSlideIndex !== undefined) {
                return api.currentSlideIndex + 1; // 0-based to 1-based
            }
            if (api.player && api.player.currentSlide !== undefined) {
                return api.player.currentSlide;
            }
        }

        // Method 2: iSpring presentation connector
        if (win.ispringPresentationConnector) {
            var connector = win.ispringPresentationConnector;
            if (connector.currentSlideIndex !== undefined) {
                return connector.currentSlideIndex + 1;
            }
        }

        // Method 3: Direct player object
        if (win.player) {
            if (win.player.currentSlideIndex !== undefined) {
                return win.player.currentSlideIndex + 1;
            }
            if (win.player.currentSlide !== undefined) {
                return win.player.currentSlide;
            }
        }

        // Method 4: Check for ISPRING global object
        if (win.ISPRING && win.ISPRING.presentation) {
            var pres = win.ISPRING.presentation;
            if (pres.slideIndex !== undefined) {
                return pres.slideIndex + 1;
            }
        }

        // Method 5: iSpring.LMS.instance() — modern iSpring LZ-String variant.
        // The instance exposes a property chain: instance.X.view().playbackController().currentSlideIndex()
        // where X is a minified property name. We walk all properties to find the chain.
        if (win.iSpring && win.iSpring.LMS && typeof win.iSpring.LMS.instance === 'function') {
            try {
                var instance = win.iSpring.LMS.instance();
                if (instance) {
                    for (var key in instance) {
                        try {
                            var prop = instance[key];
                            if (prop && typeof prop === 'object' && typeof prop.view === 'function') {
                                var view = prop.view();
                                if (view && typeof view.playbackController === 'function') {
                                    var ctrl = view.playbackController();
                                    if (ctrl && typeof ctrl.currentSlideIndex === 'function') {
                                        return ctrl.currentSlideIndex() + 1; // 0-based to 1-based
                                    }
                                }
                            }
                        } catch (e) {}
                    }
                }
            } catch (e) {}
        }

        // Method 6: Look for iSpring-specific DOM elements
        var slideElements = win.document.querySelectorAll('.ispring-slide, .slide-wrapper, [data-slide-index]');
        for (var i = 0; i < slideElements.length; i++) {
            var elem = slideElements[i];
            var style = win.getComputedStyle(elem);
            if (style.display !== 'none' && style.visibility !== 'hidden') {
                var idx = elem.getAttribute('data-slide-index');
                if (idx) {
                    return parseInt(idx, 10) + 1;
                }
            }
        }

    } catch (e) {
        // Error accessing player
    }

    return null;
}

// Start iSpring-specific monitoring.
setTimeout(function() {
    iSpringCheckInterval = setInterval(function() {
        var playerInfo = findISpringPlayer();
        if (playerInfo) {
            var currentSlide = getISpringCurrentSlide(playerInfo);
            if (currentSlide !== null && currentSlide !== iSpringSlideIndex) {
                iSpringSlideIndex = currentSlide;
                if (currentSlide !== lastSlide) {
                    sendProgressUpdate(null, null, null, currentSlide);
                }
            }
        }
    }, 1000);
}, 500); // v2.0.95: Reduced from 2000ms for faster initial position detection

// v2.0.95: Post-load resume correction for iSpring.
// iSpring uses its own suspend_data (LZ/Base64) for resume — we can't modify it.
// After content loads, check if it started at the wrong slide and navigate via player API.
if (!pendingSlideNavigation && furthestSlide !== null && furthestSlide > 1) {
    var iSpringResumeCheckCount = 0;
    var iSpringResumeInterval = setInterval(function() {
        iSpringResumeCheckCount++;
        if (iSpringResumeCheckCount > 15) { clearInterval(iSpringResumeInterval); return; }
        var playerInfo = findISpringPlayer();
        if (playerInfo) {
            var currentSlide = getISpringCurrentSlide(playerInfo);
            if (currentSlide !== null) {
                clearInterval(iSpringResumeInterval);
                if (currentSlide < furthestSlide) {
                    console.log('[SCORM Tracking] iSpring resume correction: slide ' + currentSlide + ' → ' + furthestSlide);
                    var navResult = navigateViaISpring(furthestSlide);
                    console.log('[SCORM Tracking] iSpring resume nav result: ' + navResult);
                }
            }
        }
    }, 500);
}

window.addEventListener('beforeunload', function() {
    if (iSpringCheckInterval) {
        clearInterval(iSpringCheckInterval);
    }
});

// Navigate to a specific slide via iSpring APIs.
// Returns true on success, false if iSpring player not found or navigation failed.
function navigateViaISpring(targetSlide) {
    var iSpringPlayer = findISpringPlayer();
    if (!iSpringPlayer || !iSpringPlayer.window) {
        return false;
    }

    try {
        var win = iSpringPlayer.window;
        var doc = iSpringPlayer.document;

        // Method 1: iSpringPresentationAPI.gotoSlide
        if (win.iSpringPresentationAPI && win.iSpringPresentationAPI.gotoSlide) {
            win.iSpringPresentationAPI.gotoSlide(targetSlide - 1);
            return true;
        }

        // Method 2: ispringPresentationConnector.gotoSlide
        if (win.ispringPresentationConnector && win.ispringPresentationConnector.gotoSlide) {
            win.ispringPresentationConnector.gotoSlide(targetSlide - 1);
            return true;
        }

        // Method 3: Direct gotoSlide/goToSlide function
        if (win.gotoSlide) {
            win.gotoSlide(targetSlide - 1);
            return true;
        }
        if (win.goToSlide) {
            win.goToSlide(targetSlide - 1);
            return true;
        }

        // Method 4: g_oPresentation (PowerPoint export)
        if (win.g_oPresentation && win.g_oPresentation.gotoSlide) {
            win.g_oPresentation.gotoSlide(targetSlide - 1);
            return true;
        }
        if (win.g_oPres && win.g_oPres.gotoSlide) {
            win.g_oPres.gotoSlide(targetSlide - 1);
            return true;
        }

        // Method 5: PresentationAPI/Manager
        if (win.PresentationAPI && win.PresentationAPI.gotoSlide) {
            win.PresentationAPI.gotoSlide(targetSlide - 1);
            return true;
        }
        if (win.PresentationManager && win.PresentationManager.gotoSlide) {
            win.PresentationManager.gotoSlide(targetSlide - 1);
            return true;
        }

        // Method 6: player object
        if (win.player) {
            if (win.player.gotoSlide) {
                win.player.gotoSlide(targetSlide - 1);
                return true;
            }
            if (win.player.goToSlide) {
                win.player.goToSlide(targetSlide - 1);
                return true;
            }
            if (win.player.setSlideIndex) {
                win.player.setSlideIndex(targetSlide - 1);
                return true;
            }
        }

        // Method 7: ISPRING global object
        if (win.ISPRING) {
            if (win.ISPRING.gotoSlide) {
                win.ISPRING.gotoSlide(targetSlide - 1);
                return true;
            }
            if (win.ISPRING.presentation && win.ISPRING.presentation.gotoSlide) {
                win.ISPRING.presentation.gotoSlide(targetSlide - 1);
                return true;
            }
        }

        // v2.0.96: Method 8: iSpring.LMS.instance() — modern LZ-String variant.
        // Uses the same discovery chain as getISpringCurrentSlide Method 5.
        // The playbackController that exposes currentSlideIndex() likely has gotoSlide() too.
        if (win.iSpring && win.iSpring.LMS && typeof win.iSpring.LMS.instance === 'function') {
            try {
                var inst = win.iSpring.LMS.instance();
                if (inst) {
                    for (var navKey in inst) {
                        try {
                            var navProp = inst[navKey];
                            if (navProp && typeof navProp === 'object' && typeof navProp.view === 'function') {
                                var navView = navProp.view();
                                if (navView && typeof navView.playbackController === 'function') {
                                    var navCtrl = navView.playbackController();
                                    if (navCtrl) {
                                        var navMethods = ['gotoSlide', 'goToSlide', 'navigateToSlide'];
                                        for (var m = 0; m < navMethods.length; m++) {
                                            if (typeof navCtrl[navMethods[m]] === 'function') {
                                                navCtrl[navMethods[m]](targetSlide - 1);
                                                return true;
                                            }
                                        }
                                    }
                                }
                                // Try navigation directly on view object
                                if (navView && typeof navView.gotoSlide === 'function') { navView.gotoSlide(targetSlide - 1); return true; }
                                if (navView && typeof navView.goToSlide === 'function') { navView.goToSlide(targetSlide - 1); return true; }
                            }
                            // Try navigation on instance property
                            if (navProp && typeof navProp.gotoSlide === 'function') { navProp.gotoSlide(targetSlide - 1); return true; }
                        } catch (e) {}
                    }
                }
            } catch (e) {}
        }

        // Method 9: Try clicking slide thumbnail/navigation
        var slideNavItems = doc.querySelectorAll('.slide-thumbnail, .slide-nav-item, [data-slide-index], .outline-item, .toc-item');
        if (slideNavItems.length >= targetSlide) {
            var targetItem = slideNavItems[targetSlide - 1];
            if (targetItem) {
                var clickTarget = targetItem.querySelector('a, button') || targetItem;
                clickTarget.click();
                return true;
            }
        }

        // Method 10: Try dispatching custom events that iSpring might listen to
        var slideEvent = new CustomEvent('gotoSlide', { detail: { slideIndex: targetSlide - 1 } });
        doc.dispatchEvent(slideEvent);
        win.dispatchEvent(slideEvent);

    } catch (e) {
        // iSpring navigation error
    }

    return false;
}

// Navigate via iSpring APIs found in an inner iframe window.
// Returns true on success, false otherwise.
function navigateISpringInnerFrame(iframeWin, targetSlide) {
    try {
        if (iframeWin.iSpringPresentationAPI && iframeWin.iSpringPresentationAPI.gotoSlide) {
            iframeWin.iSpringPresentationAPI.gotoSlide(targetSlide - 1);
            return true;
        }
        if (iframeWin.g_oPresentation && iframeWin.g_oPresentation.gotoSlide) {
            iframeWin.g_oPresentation.gotoSlide(targetSlide - 1);
            return true;
        }
        if (iframeWin.ispringPresentationConnector && iframeWin.ispringPresentationConnector.gotoSlide) {
            iframeWin.ispringPresentationConnector.gotoSlide(targetSlide - 1);
            return true;
        }
        if (iframeWin.player && iframeWin.player.gotoSlide) {
            iframeWin.player.gotoSlide(targetSlide - 1);
            return true;
        }
        if (iframeWin.ISPRING && iframeWin.ISPRING.gotoSlide) {
            iframeWin.ISPRING.gotoSlide(targetSlide - 1);
            return true;
        }
        // v2.0.96: iSpring.LMS.instance() — modern variant
        if (iframeWin.iSpring && iframeWin.iSpring.LMS && typeof iframeWin.iSpring.LMS.instance === 'function') {
            var inst = iframeWin.iSpring.LMS.instance();
            if (inst) {
                for (var k in inst) {
                    try {
                        var p = inst[k];
                        if (p && typeof p === 'object' && typeof p.view === 'function') {
                            var v = p.view();
                            if (v && typeof v.playbackController === 'function') {
                                var c = v.playbackController();
                                if (c && typeof c.gotoSlide === 'function') { c.gotoSlide(targetSlide - 1); return true; }
                                if (c && typeof c.goToSlide === 'function') { c.goToSlide(targetSlide - 1); return true; }
                            }
                            if (v && typeof v.gotoSlide === 'function') { v.gotoSlide(targetSlide - 1); return true; }
                        }
                        if (p && typeof p.gotoSlide === 'function') { p.gotoSlide(targetSlide - 1); return true; }
                    } catch (e) {}
                }
            }
        }
    } catch (e) {
        // Inner iframe iSpring navigation error
    }

    return false;
}
JSEOF;
    }
}

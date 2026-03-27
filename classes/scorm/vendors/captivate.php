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
 * Adobe Captivate vendor-specific detection, polling, and navigation JavaScript.
 *
 * Extracted from tracking_js.php for modularity. Provides:
 * - findCaptivatePlayer() — Detects Captivate player in iframes
 * - getCaptivateCurrentSlide() — Reads current slide via multiple methods
 * - injectCaptivateListener() — Injects cpCmndSlideEnter/cpSlideEnter event hooks
 * - Polling setup — 1s interval after 4s delay
 * - navigateViaCaptivate() — Direct navigation via Captivate APIs
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm\vendors;

defined('MOODLE_INTERNAL') || die();

/**
 * Generates Adobe Captivate detection, polling, and navigation JavaScript.
 */
class captivate {

    /**
     * Returns the JavaScript code for Adobe Captivate detection, polling, and navigation.
     *
     * @return string JavaScript code as a nowdoc string.
     */
    public static function get_js() {
        return <<<'JSEOF'
// === ADOBE CAPTIVATE — Detection, Polling & Navigation ===

var captivateSlideIndex = null;
var captivateCheckInterval = null;

// Function to find the Captivate player in iframes.
function findCaptivatePlayer() {
    var iframes = document.querySelectorAll('iframe');
    for (var i = 0; i < iframes.length; i++) {
        try {
            var iframeWin = iframes[i].contentWindow;
            // Captivate exposes cpAPIInterface, cpCmndGotoSlide, or cp object
            if (iframeWin.cpAPIInterface ||
                iframeWin.cpCmndGotoSlide ||
                iframeWin.cp ||
                iframeWin.Captivate) {
                return { iframe: iframes[i], window: iframeWin };
            }
            // Also check for cpInfoCurrentSlide variable
            if (typeof iframeWin.cpInfoCurrentSlide !== 'undefined') {
                return { iframe: iframes[i], window: iframeWin };
            }
        } catch (e) {
            // Cross-origin, skip.
        }
    }
    return null;
}

// Function to get current slide from Captivate player.
function getCaptivateCurrentSlide(playerInfo) {
    if (!playerInfo || !playerInfo.window) return null;

    try {
        var win = playerInfo.window;

        // Method 1: cpInfoCurrentSlide variable (most common)
        if (typeof win.cpInfoCurrentSlide !== 'undefined') {
            return win.cpInfoCurrentSlide + 1; // 0-based to 1-based
        }

        // Method 2: cp.movie object
        if (win.cp && win.cp.movie) {
            var movie = win.cp.movie;
            if (movie.cpInfoCurrentSlide !== undefined) {
                return movie.cpInfoCurrentSlide + 1;
            }
            if (movie.currentSlide !== undefined) {
                return movie.currentSlide;
            }
        }

        // Method 3: cpAPIInterface
        if (win.cpAPIInterface) {
            var api = win.cpAPIInterface;
            if (api.getCurrentSlide) {
                var slide = api.getCurrentSlide();
                return slide + 1;
            }
            if (api.currentSlide !== undefined) {
                return api.currentSlide + 1;
            }
        }

        // Method 4: Captivate global object
        if (win.Captivate) {
            if (win.Captivate.currentSlide !== undefined) {
                return win.Captivate.currentSlide;
            }
        }

        // Method 5: cpCmndSlideEnter (event listener based)
        // Store the value if the function was called
        if (win._captivateLastSlide !== undefined) {
            return win._captivateLastSlide;
        }

        // Method 6: Look for Captivate-specific DOM elements
        var cpSlides = win.document.querySelectorAll('.cp-slide, .captivate-slide, [id^="cpSlide"], [class*="cpSlide"]');
        for (var i = 0; i < cpSlides.length; i++) {
            var elem = cpSlides[i];
            var style = win.getComputedStyle(elem);
            if (style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0') {
                // Try to extract slide number from ID or class
                var id = elem.id || elem.className;
                var match = id.match(/slide[_\-]?(\d+)/i);
                if (match) {
                    return parseInt(match[1], 10);
                }
                // Count visible slides
                return i + 1;
            }
        }

    } catch (e) {
        // Error accessing player
    }

    return null;
}

// Inject Captivate event listener to track slide changes.
function injectCaptivateListener(playerInfo) {
    if (!playerInfo || !playerInfo.window) return;

    try {
        var win = playerInfo.window;

        // Inject a slide enter event handler if not already done.
        if (win._captivateListenerInjected) return;
        win._captivateListenerInjected = true;

        // Captivate uses cpCmndSlideEnter callback
        var originalSlideEnter = win.cpCmndSlideEnter;
        win.cpCmndSlideEnter = function(slideIndex) {
            win._captivateLastSlide = slideIndex + 1;
            if (originalSlideEnter) {
                originalSlideEnter.apply(this, arguments);
            }
        };

        // Also listen for cpSlideEnter event
        if (win.addEventListener) {
            win.addEventListener('cpSlideEnter', function(e) {
                if (e.detail && e.detail.slideIndex !== undefined) {
                    win._captivateLastSlide = e.detail.slideIndex + 1;
                }
            });
        }

    } catch (e) {
        // Error injecting listener
    }
}

// Start Captivate-specific monitoring.
setTimeout(function() {
    captivateCheckInterval = setInterval(function() {
        var playerInfo = findCaptivatePlayer();
        if (playerInfo) {
            // Inject event listener on first detection
            injectCaptivateListener(playerInfo);

            var currentSlide = getCaptivateCurrentSlide(playerInfo);
            if (currentSlide !== null && currentSlide !== captivateSlideIndex) {
                captivateSlideIndex = currentSlide;
                if (currentSlide !== lastSlide) {
                    sendProgressUpdate(null, null, null, currentSlide);
                }
            }
        }
    }, 1000);
}, 2000); // v2.0.86: Reduced from 4000ms for faster initial position detection

// v2.0.84: Resume correction via Captivate navigation API.
// If the Captivate content didn't honor the modified suspend_data (cs field) for resume,
// try to navigate to the furthest slide via Captivate's direct navigation API.
// This fires after 5 seconds — enough time for content to fully load — and only when
// there's no active tag navigation and we're behind the furthest slide.
setTimeout(function() {
    if (furthestSlide !== null && !pendingSlideNavigation &&
        lastSlide !== null && lastSlide < furthestSlide) {
        var captPlayer = findCaptivatePlayer();
        if (captPlayer && captPlayer.window) {
                navigateViaCaptivate(furthestSlide);
        }
    }
}, 5000);

window.addEventListener('beforeunload', function() {
    if (captivateCheckInterval) {
        clearInterval(captivateCheckInterval);
    }
});

// Navigate to a specific slide via Captivate APIs.
function navigateViaCaptivate(targetSlide) {
    var captivatePlayer = findCaptivatePlayer();
    if (!captivatePlayer || !captivatePlayer.window) {
        return false;
    }

    try {
        var win = captivatePlayer.window;

        // Method 1: cpCmndGotoSlide function
        if (win.cpCmndGotoSlide) {
            win.cpCmndGotoSlide(targetSlide - 1); // 0-based
            return true;
        }

        // Method 2: cpAPIInterface
        if (win.cpAPIInterface && win.cpAPIInterface.gotoSlide) {
            win.cpAPIInterface.gotoSlide(targetSlide - 1);
            return true;
        }

        // Method 3: cp.movie.gotoSlide
        if (win.cp && win.cp.movie && win.cp.movie.gotoSlide) {
            win.cp.movie.gotoSlide(targetSlide - 1);
            return true;
        }
    } catch (e) {
        // Captivate navigation error
    }

    return false;
}
JSEOF;
    }
}

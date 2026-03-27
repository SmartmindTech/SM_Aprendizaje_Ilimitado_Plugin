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
 * Articulate Storyline SCORM player detection, slide tracking, and navigation.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm\vendors;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides JavaScript for Articulate Storyline SCORM player detection,
 * slide tracking via polling, and navigation helpers.
 */
class storyline {

    /**
     * Returns the JavaScript code for Storyline detection, polling, and navigation.
     *
     * @return string JavaScript code block.
     */
    public static function get_js() {
        return <<<'JSEOF'
// === ARTICULATE STORYLINE — Detection, Polling & Navigation ===

var storylineSlideIndex = null;
var storylineCheckInterval = null;

// Function to find the Storyline iframe and access its player.
function findStorylinePlayer() {
    // Look for the SCORM content iframe.
    var iframes = document.querySelectorAll('iframe');
    for (var i = 0; i < iframes.length; i++) {
        var iframe = iframes[i];
        try {
            var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            var iframeWin = iframe.contentWindow;

            // Check if this is a Storyline player.
            // Storyline has a GetPlayer() function.
            if (iframeWin.GetPlayer) {
                return { iframe: iframe, window: iframeWin, document: iframeDoc };
            }

            // Also check for nested iframes (Storyline often nests content).
            var nestedIframes = iframeDoc.querySelectorAll('iframe');
            for (var j = 0; j < nestedIframes.length; j++) {
                try {
                    var nestedWin = nestedIframes[j].contentWindow;
                    if (nestedWin && nestedWin.GetPlayer) {
                        return { iframe: nestedIframes[j], window: nestedWin, document: nestedWin.document };
                    }
                } catch (e) {
                    // Cross-origin, skip.
                }
            }
        } catch (e) {
            // Cross-origin or other error, skip.
        }
    }
    return null;
}

// Function to get current slide from Storyline player.
function getStorylineCurrentSlide(playerInfo) {
    if (!playerInfo || !playerInfo.window) return null;

    try {
        var win = playerInfo.window;
        var doc = playerInfo.document;

        // Method 1: Use Storyline's GetPlayer() API.
        if (win.GetPlayer) {
            var player = win.GetPlayer();
            if (player) {
                // Try to get current slide index.
                // Storyline stores slide info internally.
                if (player.GetVar) {
                    // Some Storyline versions expose a "Menu.SlideNumber" or similar.
                    var slideNum = player.GetVar('Menu.SlideNumber');
                    if (slideNum) {
                        return parseInt(slideNum, 10);
                    }
                }
            }
        }

        // Method 2: Check the hash/URL for slide reference.
        var hash = win.location.hash;
        if (hash) {
            // Format: #/scenes/xxx/slides/yyy or similar.
            var match = hash.match(/slides?[\/\-_]?(\d+)/i);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

        // Method 3: Look for visible slide container in DOM.
        // Storyline uses elements with data-slide-index or similar.
        var slideContainers = doc.querySelectorAll('[data-slide-index], [data-acc-slide], .slide-container, .slide-layer');
        for (var i = 0; i < slideContainers.length; i++) {
            var container = slideContainers[i];
            // Check if visible.
            var style = win.getComputedStyle(container);
            if (style.display !== 'none' && style.visibility !== 'hidden') {
                var slideIdx = container.getAttribute('data-slide-index') ||
                               container.getAttribute('data-acc-slide');
                if (slideIdx) {
                    return parseInt(slideIdx, 10) + 1; // Convert 0-based to 1-based.
                }
            }
        }

        // Method 4: Check Storyline's internal state object.
        if (win.g_slideObject || win.g_PlayerInfo) {
            var slideObj = win.g_slideObject || win.g_PlayerInfo;
            if (slideObj.slideIndex !== undefined) {
                return slideObj.slideIndex + 1;
            }
        }

        // Method 5: Look for the active slide in the slide container.
        var activeSlide = doc.querySelector('.slide.active, .slide-object.active, .slide-layer.active, [class*="slide"][class*="active"]');
        if (activeSlide) {
            // Try to get index from class name.
            var classes = activeSlide.className;
            var match = classes.match(/slide[_\-]?(\d+)/i);
            if (match) {
                return parseInt(match[1], 10);
            }
            // Try to get index from siblings.
            var siblings = activeSlide.parentElement.children;
            for (var i = 0; i < siblings.length; i++) {
                if (siblings[i] === activeSlide) {
                    return i + 1;
                }
            }
        }

    } catch (e) {
        // Error accessing player
    }

    return null;
}

// Start Storyline-specific monitoring after content loads.
setTimeout(function() {
    storylineCheckInterval = setInterval(function() {
        var playerInfo = findStorylinePlayer();
        if (playerInfo) {
            var currentSlide = getStorylineCurrentSlide(playerInfo);
            if (currentSlide !== null && currentSlide !== storylineSlideIndex) {
                storylineSlideIndex = currentSlide;
                if (currentSlide !== lastSlide) {
                    sendProgressUpdate(null, null, null, currentSlide);
                }
            }
        }
    }, 1000); // Check every second.
}, 2000); // v2.0.86: Reduced from 4000ms for faster initial position detection

// Clean up Storyline interval on unload.
window.addEventListener('beforeunload', function() {
    if (storylineCheckInterval) {
        clearInterval(storylineCheckInterval);
    }
});

/**
 * Navigate to a target slide via Storyline player APIs.
 * @param {number} targetSlide - 1-based slide number to navigate to.
 * @returns {boolean} true if navigation was attempted, false if player not found or all methods failed.
 */
function navigateViaStoryline(targetSlide) {
    var storylinePlayer = findStorylinePlayer();
    if (storylinePlayer && storylinePlayer.window) {
        try {
            var win = storylinePlayer.window;

            // Method 1: Storyline's goToSlide function
            if (win.goToSlide) {
                win.goToSlide(targetSlide - 1); // 0-based
                return true;
            }

            // Method 2: GetPlayer().SetVar for slide navigation
            if (win.GetPlayer) {
                var player = win.GetPlayer();
                if (player && player.SetVar) {
                    // Try common Storyline variables for navigation
                    try {
                        player.SetVar('Jump', targetSlide);
                        return true;
                    } catch (e) {}
                }
            }

            // Method 3: Direct hash navigation
            var hash = win.location.hash;
            if (hash && hash.includes('slide')) {
                // Try to update hash to navigate
                var newHash = hash.replace(/slide[_\-]?(\d+)/i, 'slide' + (targetSlide - 1));
                if (newHash !== hash) {
                    win.location.hash = newHash;
                    return true;
                }
            }
        } catch (e) {
            // Storyline navigation error
        }
    }
    return false;
}

/**
 * Navigate to a target slide via an inner iframe's Storyline GetPlayer API.
 * @param {Window} iframeWin - The inner iframe's window object.
 * @param {number} targetSlide - 1-based slide number to navigate to.
 * @returns {boolean} true if navigation was attempted, false otherwise.
 */
function navigateStorylineInnerFrame(iframeWin, targetSlide) {
    try {
        if (iframeWin.GetPlayer) {
            var player = iframeWin.GetPlayer();
            if (player) {
                // Storyline uses 0-based slide indices, but some use variable names
                try {
                    if (player.SetVar) player.SetVar('JumpToSlide', targetSlide);
                } catch(e) {}
                try {
                    if (player.SetVar) player.SetVar('Jump', targetSlide);
                } catch(e) {}
                return true;
            }
        }
    } catch (e) {
        // Inner iframe Storyline error
    }
    return false;
}
JSEOF;
    }
}

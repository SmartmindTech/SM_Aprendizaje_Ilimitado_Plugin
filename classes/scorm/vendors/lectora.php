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
 * Trivantis Lectora vendor-specific detection, polling, and navigation JavaScript.
 *
 * Extracted from tracking_js.php for modularity. Provides:
 * - findLectoraPlayer() — Detects Lectora player in iframes (trivExternalCall, TrivAPI, etc.)
 * - getLectoraCurrentPage() — Reads current page via multiple methods (variables, APIs, DOM, hash)
 * - Polling setup — 1s interval after 4s delay
 * - navigateViaLectora() — Direct navigation via TrivAPI.GoToPage or trivExternalCall
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\scorm\vendors;

defined('MOODLE_INTERNAL') || die();

/**
 * Generates Trivantis Lectora detection, polling, and navigation JavaScript.
 */
class lectora {

    /**
     * Returns the JavaScript code for Trivantis Lectora detection, polling, and navigation.
     *
     * @return string JavaScript code as a nowdoc string.
     */
    public static function get_js() {
        return <<<'JSEOF'
// === TRIVANTIS LECTORA — Detection, Polling & Navigation ===

var lectoraPageIndex = null;
var lectoraCheckInterval = null;

// Function to find the Lectora player in iframes.
function findLectoraPlayer() {
    var iframes = document.querySelectorAll('iframe');
    for (var i = 0; i < iframes.length; i++) {
        try {
            var iframeWin = iframes[i].contentWindow;
            // Lectora has trivExternalCall, trivantis object, or lectora global
            if (iframeWin.trivExternalCall ||
                iframeWin.trivantis ||
                iframeWin.lectora ||
                iframeWin.TrivAPI) {
                return { iframe: iframes[i], window: iframeWin };
            }
            // Check for Lectora's page tracking variable
            if (typeof iframeWin.currentPage !== 'undefined' ||
                typeof iframeWin.pageNum !== 'undefined') {
                return { iframe: iframes[i], window: iframeWin };
            }
        } catch (e) {
            // Cross-origin, skip.
        }
    }
    return null;
}

// Function to get current page from Lectora player.
function getLectoraCurrentPage(playerInfo) {
    if (!playerInfo || !playerInfo.window) return null;

    try {
        var win = playerInfo.window;

        // Method 1: Direct currentPage or pageNum variable
        if (typeof win.currentPage !== 'undefined') {
            return win.currentPage;
        }
        if (typeof win.pageNum !== 'undefined') {
            return win.pageNum;
        }

        // Method 2: trivantis object
        if (win.trivantis) {
            if (win.trivantis.currentPage !== undefined) {
                return win.trivantis.currentPage;
            }
            if (win.trivantis.pageIndex !== undefined) {
                return win.trivantis.pageIndex + 1;
            }
        }

        // Method 3: TrivAPI object
        if (win.TrivAPI) {
            if (win.TrivAPI.GetCurrentPage) {
                var page = win.TrivAPI.GetCurrentPage();
                return page;
            }
            if (win.TrivAPI.currentPage !== undefined) {
                return win.TrivAPI.currentPage;
            }
        }

        // Method 4: lectora global object
        if (win.lectora) {
            if (win.lectora.currentPageNumber !== undefined) {
                return win.lectora.currentPageNumber;
            }
            if (win.lectora.pageNum !== undefined) {
                return win.lectora.pageNum;
            }
        }

        // Method 5: Look for Lectora's page elements in DOM
        var doc = win.document;
        var pages = doc.querySelectorAll('.page, .lectora-page, [id^="page"], [class*="lecPage"]');
        for (var i = 0; i < pages.length; i++) {
            var page = pages[i];
            var style = win.getComputedStyle(page);
            if (style.display !== 'none' && style.visibility !== 'hidden') {
                // Try to extract page number from ID or class
                var id = page.id || page.className;
                var match = id.match(/page[_\-]?(\d+)/i);
                if (match) {
                    return parseInt(match[1], 10);
                }
                return i + 1;
            }
        }

        // Method 6: Check URL hash for page reference
        var hash = win.location.hash;
        if (hash) {
            var match = hash.match(/page[_\-]?(\d+)/i);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

    } catch (e) {
        // Error accessing player
    }

    return null;
}

// Start Lectora-specific monitoring.
setTimeout(function() {
    lectoraCheckInterval = setInterval(function() {
        var playerInfo = findLectoraPlayer();
        if (playerInfo) {
            var currentPage = getLectoraCurrentPage(playerInfo);
            if (currentPage !== null && currentPage !== lectoraPageIndex) {
                lectoraPageIndex = currentPage;
                if (currentPage !== lastSlide) {
                    sendProgressUpdate(null, null, null, currentPage);
                }
            }
        }
    }, 1000);
}, 4000);

window.addEventListener('beforeunload', function() {
    if (lectoraCheckInterval) {
        clearInterval(lectoraCheckInterval);
    }
});

// Navigate to a specific page via Lectora APIs.
function navigateViaLectora(targetSlide) {
    var lectoraPlayer = findLectoraPlayer();
    if (!lectoraPlayer || !lectoraPlayer.window) {
        return false;
    }

    try {
        var win = lectoraPlayer.window;

        // Method 1: TrivAPI.GoToPage
        if (win.TrivAPI && win.TrivAPI.GoToPage) {
            win.TrivAPI.GoToPage(targetSlide);
            return true;
        }

        // Method 2: trivExternalCall
        if (win.trivExternalCall) {
            win.trivExternalCall('GoToPage', targetSlide);
            return true;
        }
    } catch (e) {
        // Lectora navigation error
    }

    return false;
}
JSEOF;
    }
}

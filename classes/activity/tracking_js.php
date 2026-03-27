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
 * Activity position tracking JavaScript generator.
 *
 * Generates tracking JS for quiz, book, and lesson activities that:
 *   1. Detects current position from URL parameters
 *   2. Tracks furthest position in sessionStorage
 *   3. Reports position via postMessage using 'activity-progress' type
 *   4. Listens for navigation requests (activity-navigate-to-position)
 *   5. Handles pending navigation from tag clicks
 *
 * Uses 'activity-progress' message type (not 'scorm-progress') to clearly
 * distinguish non-SCORM activities. The SmartLearning frontend handles both
 * message types for position bar, tagging, go-back button, and progress tracking.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\activity;

defined('MOODLE_INTERNAL') || die();

/**
 * Generates position tracking JavaScript for non-SCORM activities.
 *
 * Each activity type has a static entry point that queries the DB for item
 * data and delegates to the generic script generator.
 */
class tracking_js {

    /**
     * Generate tracking JS for quiz attempt/review pages.
     *
     * Quiz position is based on ACTUAL questions (excluding description pages).
     * Description/info pages share the same position as the next real question.
     * URL parameter: ?page=N (0-based).
     *
     * @param int $cmid Course module ID.
     * @param int $quizid Quiz instance ID.
     * @return string HTML <script> block, or empty string if quiz has only 1 page.
     */
    public static function get_quiz_script(int $cmid, int $quizid): string {
        global $DB;

        // Get all quiz slots with their page numbers and question types.
        // We need to identify which pages have only description questions.
        // Moodle 4.0+ uses question_references, older uses quiz_slots.questionid directly.
        $slots = $DB->get_records_sql(
            "SELECT qs.id, qs.slot, qs.page, q.qtype
             FROM {quiz_slots} qs
             LEFT JOIN {question_references} qr ON qr.itemid = qs.id
                 AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
             LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
             LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
             LEFT JOIN {question} q ON q.id = qv.questionid
             WHERE qs.quizid = :quizid
             ORDER BY qs.slot ASC",
            ['quizid' => $quizid]
        );

        // Fallback for older Moodle versions without question_references
        if (empty($slots)) {
            $slots = $DB->get_records_sql(
                "SELECT qs.id, qs.slot, qs.page, q.qtype
                 FROM {quiz_slots} qs
                 LEFT JOIN {question} q ON q.id = qs.questionid
                 WHERE qs.quizid = :quizid
                 ORDER BY qs.slot ASC",
                ['quizid' => $quizid]
            );
        }

        if (empty($slots)) {
            return '';
        }

        // Group slots by page and identify which pages have only descriptions
        $pageinfo = [];
        foreach ($slots as $slot) {
            $page = (int)$slot->page;
            if (!isset($pageinfo[$page])) {
                $pageinfo[$page] = ['hasRealQuestion' => false, 'slots' => []];
            }
            $pageinfo[$page]['slots'][] = $slot;
            // A page has a real question if any slot is NOT a description
            if ($slot->qtype !== 'description' && $slot->qtype !== null) {
                $pageinfo[$page]['hasRealQuestion'] = true;
            }
        }

        // Build position map: description-only pages share position with next real question page
        // Position 1 = first real question (and any preceding description pages)
        // Position 2 = second real question (and any preceding description pages)
        // etc.
        //
        // IMPORTANT: Quiz URL uses 0-based page numbers (?page=0 for first page),
        // but database stores 1-based (page column = 1, 2, 3...).
        // We need to use 0-based keys in reverseMap and 0-based values in itemMap.
        $reversemap = []; // 0-based URL page => position
        $itemmap = [];    // position => 0-based URL page
        $position = 0;
        $pendingDescPages = [];

        ksort($pageinfo); // Ensure pages are in order (1-based from DB)
        foreach ($pageinfo as $dbpage => $info) {
            $urlpage = $dbpage - 1; // Convert to 0-based for URL
            if ($info['hasRealQuestion']) {
                $position++;
                // This page and all pending description pages get this position
                $itemmap[$position] = $urlpage;
                $reversemap[$urlpage] = $position;
                foreach ($pendingDescPages as $descUrlPage) {
                    $reversemap[$descUrlPage] = $position;
                }
                $pendingDescPages = [];
            } else {
                // Description-only page - wait for next real question
                $pendingDescPages[] = $urlpage;
            }
        }

        // Handle trailing description pages (assign to last position)
        if (!empty($pendingDescPages) && $position > 0) {
            foreach ($pendingDescPages as $descUrlPage) {
                $reversemap[$descUrlPage] = $position;
            }
        }

        $totalpositions = $position;
        if ($totalpositions <= 1) {
            return '';
        }

        return self::get_generic_script($cmid, 'quiz', $totalpositions, $itemmap, $reversemap, 'page');
    }

    /**
     * Generate tracking JS for book chapter pages.
     *
     * Book position is chapter-based. URL parameter: ?chapterid=N (DB ID).
     *
     * @param int $cmid Course module ID.
     * @param int $bookid Book instance ID.
     * @return string HTML <script> block, or empty string if book has ≤1 chapter.
     */
    public static function get_book_script(int $cmid, int $bookid): string {
        global $DB;

        $chapters = $DB->get_records('book_chapters',
            ['bookid' => $bookid, 'hidden' => 0],
            'pagenum ASC',
            'id, pagenum'
        );

        if (count($chapters) <= 1) {
            return '';
        }

        // Build maps: position (1-based) ↔ chapter ID.
        $itemmap = [];
        $reversemap = [];
        $pos = 1;
        foreach ($chapters as $chapter) {
            $itemmap[$pos] = (int)$chapter->id;
            $reversemap[(int)$chapter->id] = $pos;
            $pos++;
        }

        return self::get_generic_script($cmid, 'book', count($chapters), $itemmap, $reversemap, 'chapterid');
    }

    /**
     * Generate tracking JS for whole-activity tagging.
     *
     * For activities without position tracking (page, resource, url, assign, forum,
     * folder, label, etc.), this sends an activity-progress message with position 1/1.
     * This enables tagging comments to the whole activity.
     *
     * @param int $cmid Course module ID.
     * @param string $modtype Activity type name (for logging).
     * @return string HTML <script> block.
     */
    public static function get_whole_activity_script(int $cmid, string $modtype): string {
        return <<<HTML
<script>
// ============================================================
// Whole-Activity Tagging ({$modtype}) — cmid {$cmid}
// Reports position 1/1 for activities without position tracking.
// Enables tagging comments to the whole activity.
// ============================================================
(function() {
    var cmid = {$cmid};
    var modtype = '{$modtype}';

    function sendProgress() {
        var message = {
            type: 'activity-progress',
            activityType: modtype,
            cmid: cmid,
            currentPosition: 1,
            totalPositions: 1,
            furthestPosition: 1,
            status: 'completed',
            source: 'whole-activity',
            timestamp: Date.now(),
            progressPercent: 100,
            currentPercent: 100
        };
        try { window.parent.postMessage(message, '*'); } catch (e) {}
        try {
            if (window.top !== window.parent) {
                window.top.postMessage(message, '*');
            }
        } catch (e) {}
    }

    // Send progress on load and after delays.
    sendProgress();
    setTimeout(sendProgress, 500);
    setTimeout(sendProgress, 2000);
})();
</script>
HTML;
    }

    /**
     * Generate tracking JS for lesson pages.
     *
     * Lesson position is page-based. URL parameter: ?pageid=N (DB ID).
     * Excludes non-content pages: endofbranch (21), cluster (30), endofcluster (31).
     *
     * @param int $cmid Course module ID.
     * @param int $lessonid Lesson instance ID.
     * @return string HTML <script> block, or empty string if lesson has ≤1 page.
     */
    public static function get_lesson_script(int $cmid, int $lessonid): string {
        global $DB;

        // Get page IDs and titles for matching
        $pages = $DB->get_records_sql(
            "SELECT id, title FROM {lesson_pages}
             WHERE lessonid = :lessonid AND qtype NOT IN (21, 30, 31)
             ORDER BY id ASC",
            ['lessonid' => $lessonid]
        );

        if (count($pages) <= 1) {
            return '';
        }

        // Build maps: position (1-based) ↔ page ID, and title → position
        $itemmap = [];
        $reversemap = [];
        $titlemap = [];
        $pos = 1;
        foreach ($pages as $page) {
            $itemmap[$pos] = (int)$page->id;
            $reversemap[(int)$page->id] = $pos;
            // Normalize title for matching (lowercase, trim)
            $normalizedTitle = strtolower(trim($page->title));
            $titlemap[$normalizedTitle] = $pos;
            $pos++;
        }

        return self::get_lesson_script_with_titles($cmid, count($pages), $itemmap, $reversemap, $titlemap);
    }

    /**
     * Generate lesson-specific tracking JS with title matching.
     *
     * @param int $cmid Course module ID.
     * @param int $totalitems Total number of pages.
     * @param array $itemmap Position → page ID.
     * @param array $reversemap Page ID → position.
     * @param array $titlemap Normalized title → position.
     * @return string HTML <script> block.
     */
    private static function get_lesson_script_with_titles(
        int $cmid,
        int $totalitems,
        array $itemmap,
        array $reversemap,
        array $titlemap
    ): string {
        $itemmapjson = json_encode((object)$itemmap);
        $reversemapjson = json_encode((object)$reversemap);
        $titlemapjson = json_encode((object)$titlemap);

        return <<<HTML
<script>
// ============================================================
// Lesson Position Tracking — cmid {$cmid}
// Detects position from URL pageid or page title matching.
// ============================================================
(function() {
    var cmid = {$cmid};
    var totalItems = {$totalitems};
    var itemMap = {$itemmapjson};
    var reverseMap = {$reversemapjson};
    var titleMap = {$titlemapjson};

    // 1. Get furthest position from storage
    var storageKey = 'activity_furthest_position_' + cmid;
    var currentPosKey = 'activity_current_position_' + cmid;
    var furthestPosition = 0;
    var lastKnownPosition = 1;
    try {
        furthestPosition = parseInt(sessionStorage.getItem(storageKey)) || 0;
        lastKnownPosition = parseInt(sessionStorage.getItem(currentPosKey)) || 1;
    } catch (e) {}
    if (furthestPosition <= 0) {
        try {
            furthestPosition = parseInt(localStorage.getItem(storageKey)) || 0;
        } catch (e) {}
    }

    // 2. Detect current position
    var params = new URLSearchParams(window.location.search);
    var pageid = params.get('pageid');
    var currentPosition = lastKnownPosition;
    var pageInMap = false;

    console.log('[lesson Tracking] URL:', window.location.href);
    console.log('[lesson Tracking] pageid:', pageid);

    // Check for END OF LESSON (pageid=-9)
    if (pageid === '-9') {
        console.log('[lesson Tracking] END OF LESSON detected');
        currentPosition = totalItems;
        pageInMap = true;
    }
    // Check for valid pageid in reverseMap
    else if (pageid !== null && pageid !== '' && pageid !== '0') {
        var key = parseInt(pageid);
        if (key > 0 && reverseMap[key] !== undefined) {
            currentPosition = reverseMap[key];
            pageInMap = true;
            console.log('[lesson Tracking] Found pageid', key, '→ position', currentPosition);
        }
    }

    // If no pageid, try to match page title
    if (!pageInMap) {
        var pageTitle = null;

        // Method 1: Look for lesson-{pageid} links (Moodle's page heading format)
        var lessonLinks = document.querySelectorAll('a[id^="lesson-"]');
        for (var i = 0; i < lessonLinks.length; i++) {
            var linkId = lessonLinks[i].getAttribute('id');
            var linkPageId = parseInt(linkId.replace('lesson-', ''));
            if (linkPageId > 0 && reverseMap[linkPageId] !== undefined) {
                currentPosition = reverseMap[linkPageId];
                pageInMap = true;
                console.log('[lesson Tracking] Found lesson link id:', linkId, '→ position', currentPosition);
                break;
            }
        }

        // Method 2: Look for page title in content area (not the main heading)
        if (!pageInMap) {
            // The .contents div usually contains the page content
            var contentsDiv = document.querySelector('.contents');
            if (contentsDiv) {
                // Get the first significant text that might be the page title
                var firstParagraph = contentsDiv.querySelector('p');
                var firstHeading = contentsDiv.querySelector('h1, h2, h3, h4');
                var titleElement = firstHeading || firstParagraph;

                if (titleElement) {
                    pageTitle = titleElement.textContent.trim().toLowerCase();
                    console.log('[lesson Tracking] Content title found:', pageTitle);
                }
            }

            // Method 3: Look for the actual page content as a title match
            if (!pageTitle) {
                // Try to find text in the main content that matches a page title
                var mainContent = document.querySelector('#region-main .box.generalbox');
                if (mainContent) {
                    var contentText = mainContent.innerText.substring(0, 500).toLowerCase();
                    console.log('[lesson Tracking] Checking content text (first 200 chars):', contentText.substring(0, 200));

                    // Check if any page title appears in the content
                    for (var title in titleMap) {
                        if (contentText.indexOf(title) !== -1) {
                            currentPosition = titleMap[title];
                            pageInMap = true;
                            console.log('[lesson Tracking] Content contains title:', title, '→ position', currentPosition);
                            break;
                        }
                    }
                }
            }

            // Try matching the found title with flexible keyword matching
            if (pageTitle && !pageInMap) {
                if (titleMap[pageTitle] !== undefined) {
                    currentPosition = titleMap[pageTitle];
                    pageInMap = true;
                    console.log('[lesson Tracking] Exact title match → position', currentPosition);
                } else {
                    // Try keyword matching - extract significant words from both
                    for (var title in titleMap) {
                        // Get keywords from the page title (remove common prefixes)
                        var titleKeywords = title.replace(/^(pregunta|question|introduccion|introduction|conclusion)[:.]?\s*/i, '')
                                                 .split(/\s+/)
                                                 .filter(function(w) { return w.length > 2; });

                        // Check if any keyword from title appears in content
                        var matchFound = false;
                        for (var k = 0; k < titleKeywords.length; k++) {
                            if (pageTitle.indexOf(titleKeywords[k]) !== -1) {
                                matchFound = true;
                                break;
                            }
                        }

                        // Also check direct containment
                        if (!matchFound && (pageTitle.indexOf(title) !== -1 || title.indexOf(pageTitle) !== -1)) {
                            matchFound = true;
                        }

                        if (matchFound) {
                            currentPosition = titleMap[title];
                            pageInMap = true;
                            console.log('[lesson Tracking] Keyword match:', title, '→ position', currentPosition);
                            break;
                        }
                    }
                }
            }
        }

        // Check for completion page
        if (!pageInMap) {
            var bodyText = document.body.innerText || '';
            if (bodyText.indexOf('Félicitations') !== -1 ||
                bodyText.indexOf('Congratulations') !== -1 ||
                bodyText.indexOf('terminée') !== -1 ||
                bodyText.indexOf('completed the lesson') !== -1) {
                currentPosition = totalItems;
                pageInMap = true;
                console.log('[lesson Tracking] Completion text detected');
            }
        }
    }

    console.log('[lesson Tracking] Final: position=' + currentPosition + '/' + totalItems + ', pageInMap=' + pageInMap);

    // 3. Update furthest (never decrease)
    if (currentPosition > furthestPosition) {
        furthestPosition = currentPosition;
    }
    try {
        var storedFurthest = parseInt(sessionStorage.getItem(storageKey)) || 0;
        if (storedFurthest > furthestPosition) {
            furthestPosition = storedFurthest;
        }
        sessionStorage.setItem(storageKey, String(furthestPosition));
        localStorage.setItem(storageKey, String(furthestPosition));
        if (pageInMap) {
            sessionStorage.setItem(currentPosKey, String(currentPosition));
        }
    } catch (e) {}

    // 4. Send progress message
    var progressPercent = totalItems > 0 ? Math.min(100, Math.round(furthestPosition / totalItems * 100)) : 0;
    var currentPercent = totalItems > 0 ? Math.min(100, Math.round(currentPosition / totalItems * 100)) : 0;

    function sendProgress() {
        var message = {
            type: 'activity-progress',
            activityType: 'lesson',
            cmid: cmid,
            currentPosition: currentPosition,
            totalPositions: totalItems,
            furthestPosition: furthestPosition,
            status: furthestPosition >= totalItems ? 'completed' : 'incomplete',
            source: 'navigation',
            timestamp: Date.now(),
            progressPercent: progressPercent,
            currentPercent: currentPercent
        };
        try { window.parent.postMessage(message, '*'); } catch (e) {}
        try {
            if (window.top !== window.parent) {
                window.top.postMessage(message, '*');
            }
        } catch (e) {}
    }

    sendProgress();
    setTimeout(sendProgress, 500);
    setTimeout(sendProgress, 2000);

    // 5. Listen for navigation requests
    window.addEventListener('message', function(event) {
        if (!event.data) return;
        var isActivityNav = event.data.type === 'activity-navigate-to-position' && event.data.cmid === cmid;
        var isLegacyNav = event.data.type === 'scorm-navigate-to-slide' && event.data.cmid === cmid;
        if (isActivityNav || isLegacyNav) {
            var targetPosition = event.data.position || event.data.slide;
            if (targetPosition && itemMap[targetPosition] !== undefined) {
                var targetPageId = itemMap[targetPosition];
                var url = new URL(window.location.href);
                url.pathname = url.pathname.replace(/continue\.php$/, 'view.php');
                url.searchParams.set('pageid', String(targetPageId));
                window.location.href = url.toString();
            }
        }
    }, false);

    // 6. Hide lesson end links
    setTimeout(function() {
        var links = document.querySelectorAll('a');
        links.forEach(function(link) {
            var href = link.getAttribute('href') || '';
            var text = link.textContent || '';
            if (href.indexOf('course/view.php') !== -1 ||
                href.indexOf('grade/report') !== -1 ||
                href.indexOf('pageid=0') !== -1 ||
                text.indexOf('Revoir') !== -1 ||
                text.indexOf('Retour') !== -1 ||
                text.indexOf('Afficher') !== -1 ||
                text.indexOf('Review') !== -1 ||
                text.indexOf('Return') !== -1 ||
                text.indexOf('View grades') !== -1) {
                link.style.display = 'none';
            }
        });
    }, 100);
})();
</script>
HTML;
    }

    /**
     * Generate the generic tracking JS IIFE.
     *
     * This JS runs inside the Moodle activity page (which is loaded in an iframe
     * from SmartLearning). It detects position, reports progress, and handles
     * navigation using 'activity-progress' and 'activity-navigate-to-position'
     * message types.
     *
     * @param int $cmid Course module ID.
     * @param string $modtype Activity type ('quiz', 'book', 'lesson').
     * @param int $totalitems Total number of position items.
     * @param array $itemmap Position (1-based) → URL param value.
     * @param array $reversemap URL param value → position (1-based).
     * @param string $urlparam URL parameter name ('page', 'chapterid', 'pageid').
     * @return string HTML <script> block.
     */
    private static function get_generic_script(
        int $cmid,
        string $modtype,
        int $totalitems,
        array $itemmap,
        array $reversemap,
        string $urlparam
    ): string {
        $itemmapjson = json_encode((object)$itemmap);
        $reversemapjson = json_encode((object)$reversemap);

        return <<<HTML
<script>
// ============================================================
// Activity Position Tracking ({$modtype}) — cmid {$cmid}
// Detects position from URL, reports via postMessage, handles navigation.
// Uses activity-progress message type for non-SCORM activities.
// ============================================================
(function() {
    var cmid = {$cmid};
    var modtype = '{$modtype}';
    var totalItems = {$totalitems};
    var itemMap = {$itemmapjson};
    var reverseMap = {$reversemapjson};
    var urlParam = '{$urlparam}';

    // 1. Get furthest position from storage first (before detecting current).
    var storageKey = 'activity_furthest_position_' + cmid;
    var currentPosKey = 'activity_current_position_' + cmid;
    var furthestPosition = 0;
    var lastKnownPosition = 1;
    try {
        furthestPosition = parseInt(sessionStorage.getItem(storageKey)) || 0;
        lastKnownPosition = parseInt(sessionStorage.getItem(currentPosKey)) || 1;
    } catch (e) {}
    if (furthestPosition <= 0) {
        try {
            furthestPosition = parseInt(localStorage.getItem(storageKey)) || 0;
        } catch (e) {}
    }

    // 2. Detect current position from URL parameter.
    // If page not in map (e.g., answer/feedback page), keep last known position.
    var params = new URLSearchParams(window.location.search);
    var paramValue = params.get(urlParam);
    var currentPosition = lastKnownPosition; // Default to last known, not 1
    var pageInMap = false;

    // Debug logging
    console.log('[' + modtype + ' Tracking] URL:', window.location.href);
    console.log('[' + modtype + ' Tracking] urlParam:', urlParam, 'value:', paramValue);

    // For lessons: special handling for Moodle's lesson navigation
    if (modtype === 'lesson') {
        // pageid=-9 means END OF LESSON in Moodle
        if (paramValue === '-9') {
            console.log('[' + modtype + ' Tracking] Detected END OF LESSON (pageid=-9)');
            currentPosition = totalItems;
            pageInMap = true;
        }
        // Check for completion page content (Félicitations, 100%, etc.)
        else if (paramValue === null || paramValue === '' || paramValue === '0' || paramValue === '-9') {
            var bodyText = document.body.innerText || '';
            var hasCompletionText = bodyText.indexOf('Félicitations') !== -1 ||
                bodyText.indexOf('Congratulations') !== -1 ||
                bodyText.indexOf('terminée') !== -1 ||
                bodyText.indexOf('completed') !== -1 ||
                (bodyText.indexOf('100') !== -1 && bodyText.indexOf('%') !== -1);
            if (hasCompletionText) {
                console.log('[' + modtype + ' Tracking] Detected completion page via content');
                currentPosition = totalItems;
                pageInMap = true;
            }
        }
    }

    // Check if we have a valid pageid in the URL (positive number in reverseMap)
    if (!pageInMap && paramValue !== null && paramValue !== '' && paramValue !== '0') {
        var key = isNaN(Number(paramValue)) ? paramValue : Number(paramValue);
        console.log('[' + modtype + ' Tracking] Looking up key:', key, 'found:', reverseMap[key]);
        if (key > 0 && reverseMap[key] !== undefined) {
            currentPosition = reverseMap[key];
            pageInMap = true;
        }
    }

    console.log('[' + modtype + ' Tracking] Final: currentPosition=' + currentPosition + ', pageInMap=' + pageInMap + ', furthest=' + furthestPosition);

    // 3. Update furthest if current is higher, save to storage.
    // IMPORTANT: furthest should NEVER decrease
    if (currentPosition > furthestPosition) {
        furthestPosition = currentPosition;
    }
    // Re-read furthest from storage in case another tab updated it
    try {
        var storedFurthest = parseInt(sessionStorage.getItem(storageKey)) || 0;
        if (storedFurthest > furthestPosition) {
            furthestPosition = storedFurthest;
        }
    } catch (e) {}
    try {
        sessionStorage.setItem(storageKey, String(furthestPosition));
        localStorage.setItem(storageKey, String(furthestPosition));
        // Only update current position if page is in our map
        if (pageInMap) {
            sessionStorage.setItem(currentPosKey, String(currentPosition));
        }
    } catch (e) {}

    // For lessons: hide end-of-lesson navigation links in embed mode
    if (modtype === 'lesson') {
        setTimeout(function() {
            var links = document.querySelectorAll('a');
            links.forEach(function(link) {
                var href = link.getAttribute('href') || '';
                var text = link.textContent || '';
                // Hide links that navigate away from the lesson
                if (href.indexOf('course/view.php') !== -1 ||
                    href.indexOf('grade/report') !== -1 ||
                    href.indexOf('pageid=0') !== -1 ||
                    text.indexOf('Revoir') !== -1 ||
                    text.indexOf('Retour') !== -1 ||
                    text.indexOf('Afficher') !== -1 ||
                    text.indexOf('Review') !== -1 ||
                    text.indexOf('Return') !== -1 ||
                    text.indexOf('View grades') !== -1) {
                    link.style.display = 'none';
                }
            });
        }, 100);
    }

    // 4. Build and send progress message (activity-progress for non-SCORM activities).
    var progressPercent = totalItems > 0
        ? Math.min(100, Math.round(furthestPosition / totalItems * 100)) : 0;
    var currentPercent = totalItems > 0
        ? Math.min(100, Math.round(currentPosition / totalItems * 100)) : 0;

    function sendProgress() {
        var message = {
            type: 'activity-progress',
            activityType: modtype,
            cmid: cmid,
            currentPosition: currentPosition,
            totalPositions: totalItems,
            furthestPosition: furthestPosition,
            status: furthestPosition >= totalItems ? 'completed' : 'incomplete',
            source: 'navigation',
            timestamp: Date.now(),
            progressPercent: progressPercent,
            currentPercent: currentPercent
        };
        try { window.parent.postMessage(message, '*'); } catch (e) {}
        try {
            if (window.top !== window.parent) {
                window.top.postMessage(message, '*');
            }
        } catch (e) {}
    }

    // Send progress on load and after delays (iframe may not be ready immediately).
    sendProgress();
    setTimeout(sendProgress, 500);
    setTimeout(sendProgress, 2000);

    // 4. Listen for navigation requests from SmartLearning.
    // Accepts both 'activity-navigate-to-position' and legacy 'scorm-navigate-to-slide'.
    window.addEventListener('message', function(event) {
        if (!event.data) return;
        var isActivityNav = event.data.type === 'activity-navigate-to-position' && event.data.cmid === cmid;
        var isLegacyNav = event.data.type === 'scorm-navigate-to-slide' && event.data.cmid === cmid;
        if (isActivityNav || isLegacyNav) {
            var targetPosition = event.data.position || event.data.slide;
            if (targetPosition && itemMap[targetPosition] !== undefined) {
                var targetValue = itemMap[targetPosition];
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set(urlParam, String(targetValue));
                    window.location.href = url.toString();
                } catch (e) {}
            }
        }
    }, false);

    // 5. Check sessionStorage for pending navigation (from tag click before iframe loaded).
    // Supports both new and legacy storage keys.
    try {
        var pendingKey = 'activity_pending_navigation_' + cmid;
        var legacyPendingKey = 'scorm_pending_navigation_' + cmid;
        var pending = sessionStorage.getItem(pendingKey) || sessionStorage.getItem(legacyPendingKey);
        if (pending) {
            sessionStorage.removeItem(pendingKey);
            sessionStorage.removeItem(legacyPendingKey);
            var navData = JSON.parse(pending);
            var targetPos = navData.position || navData.slide;
            if (targetPos && targetPos !== currentPosition && itemMap[targetPos] !== undefined) {
                var url = new URL(window.location.href);
                url.searchParams.set(urlParam, String(itemMap[targetPos]));
                window.location.href = url.toString();
            }
        }
    } catch (e) {}
})();
</script>
HTML;
    }
}

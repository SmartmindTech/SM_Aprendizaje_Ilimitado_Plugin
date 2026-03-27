/**
 * SmartMind Dashboard — Scroll navigation for horizontal and vertical carousels.
 *
 * @module     theme_smartmind/dashboard_scroll
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Scroll amount per click (in pixels).
     */
    var SCROLL_AMOUNT = 320;

    /**
     * Initialize scroll buttons for all scroll regions on the page.
     */
    function init() {
        var scrollRegions = document.querySelectorAll('[data-region="smartmind-scroll"]');

        scrollRegions.forEach(function(region) {
            var track = region.querySelector('[data-region="scroll-track"]');
            var prevBtn = region.querySelector('[data-action="scroll-prev"]');
            var nextBtn = region.querySelector('[data-action="scroll-next"]');

            if (!track) {
                return;
            }

            var isVertical = region.classList.contains('smartmind-scroll--vertical');

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (isVertical) {
                        track.scrollBy({top: -SCROLL_AMOUNT, behavior: 'smooth'});
                    } else {
                        track.scrollBy({left: -SCROLL_AMOUNT, behavior: 'smooth'});
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if (isVertical) {
                        track.scrollBy({top: SCROLL_AMOUNT, behavior: 'smooth'});
                    } else {
                        track.scrollBy({left: SCROLL_AMOUNT, behavior: 'smooth'});
                    }
                });
            }

            // Mouse wheel scrolls the track and prevents the page from scrolling.
            track.addEventListener('wheel', function(e) {
                if (e.deltaY === 0) {
                    return;
                }

                // Only capture wheel if the track has overflow to scroll.
                var hasOverflow = isVertical
                    ? track.scrollHeight > track.clientHeight
                    : track.scrollWidth > track.clientWidth;

                if (!hasOverflow) {
                    return;
                }

                e.preventDefault();

                if (isVertical) {
                    track.scrollBy({top: e.deltaY, behavior: 'smooth'});
                } else {
                    track.scrollBy({left: e.deltaY, behavior: 'smooth'});
                }
            }, {passive: false});
        });
    }

    return {
        init: init
    };
});

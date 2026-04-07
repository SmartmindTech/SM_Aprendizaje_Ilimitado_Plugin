/**
 * SmartMind Prefetch — instant.page-style link prefetching for Moodle.
 *
 * On hover (with a small delay), mousedown, or touchstart, injects a
 * <link rel="prefetch"> for the target URL. The browser's native HTTP
 * cache then has the document ready when the user actually clicks,
 * making navigation feel instantaneous WITHOUT any DOM swap, eval, or
 * lifecycle interference. Pure progressive enhancement.
 *
 * Replaces the more invasive theme_smartmind/turbo module after we hit
 * Moodle AMD lifecycle issues with full SPA-style navigation.
 *
 * @module theme_smartmind/prefetch
 */

const HOVER_DELAY_MS = 65;

// URLs we must NOT prefetch. These either depend on session-based state
// (IOMAD company context, sesskey-bound flows) or have side effects on
// GET. A cached error response would then be served on the user's real
// click, surfacing as "Access denied" / "Cannot find record" errors.
const SKIP_PATTERNS = [
    /\/blocks\/iomad/,
    /\/local\/iomad/,
    /\/local\/sm_graphics_plugin\/pages\/iomad/,
    /\/login\//,
    /\/logout\.php/,
    /\/admin\//,
    /[?&]sesskey=/,
    /[?&]edit=1/,
];

const prefetched = new Set();
let hoverTimer = null;

function prefetch(url) {
    if (prefetched.has(url)) {
        return;
    }
    prefetched.add(url);
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = url;
    link.setAttribute('data-sm-prefetch', '');
    document.head.appendChild(link);
}

function shouldPrefetch(anchor) {
    if (!anchor || !anchor.href) {
        return false;
    }
    if (anchor.origin !== location.origin) {
        return false;
    }
    if (anchor.hasAttribute('download') || anchor.target === '_blank') {
        return false;
    }
    // Skip same-page (hash) links.
    if (anchor.pathname === location.pathname && anchor.search === location.search) {
        return false;
    }
    // Skip non-GET-able assets.
    const path = anchor.pathname;
    if (/\.(zip|pdf|exe|dmg|tar|gz)$/i.test(path)) {
        return false;
    }
    // Skip URLs whose access/response depends on session state.
    const fullPath = anchor.pathname + anchor.search;
    for (const re of SKIP_PATTERNS) {
        if (re.test(fullPath)) {
            return false;
        }
    }
    return true;
}

function init() {
    if (window.__smPrefetchInitialized) {
        return;
    }
    window.__smPrefetchInitialized = true;

    // Hover with small delay — only "intentional" hovers prefetch.
    document.addEventListener('mouseover', function(e) {
        const anchor = e.target.closest && e.target.closest('a[href]');
        if (!shouldPrefetch(anchor)) {
            return;
        }
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(() => prefetch(anchor.href), HOVER_DELAY_MS);
    });

    document.addEventListener('mouseout', function(e) {
        if (e.target.closest && e.target.closest('a[href]')) {
            clearTimeout(hoverTimer);
        }
    });

    // mousedown fires ~80ms before click — gives us a head start.
    document.addEventListener('mousedown', function(e) {
        const anchor = e.target.closest && e.target.closest('a[href]');
        if (shouldPrefetch(anchor)) {
            prefetch(anchor.href);
        }
    }, true);

    // Touch devices: same trick on touchstart.
    document.addEventListener('touchstart', function(e) {
        const anchor = e.target.closest && e.target.closest('a[href]');
        if (shouldPrefetch(anchor)) {
            prefetch(anchor.href);
        }
    }, {capture: true, passive: true});
}

export {init};

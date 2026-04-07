/**
 * SmartMind Turbo — SPA-like navigation for Moodle.
 *
 * Intercepts internal link clicks, fetches the new page via AJAX,
 * and swaps only #page-content + secondary-navigation + inline styles,
 * keeping the shell (navbar, drawers, footer) intact.
 *
 * @module theme_smartmind/turbo
 */

const SWAP_SELECTOR = '#page-content';
const SEC_NAV_SELECTOR = '.secondary-navigation';
const PAGE_HEADER_SELECTOR = '.smartmind-page-banner';
// Primary nav must be swapped on each navigation so the "active" state
// (set server-side by PHP based on the page URL) refreshes correctly.
// List of selectors — all matching elements get swapped from the new doc.
const PRIMARY_NAV_SELECTORS = ['nav.smgp-topnav', '.primary-navigation'];
const PROGRESS_ID = 'sm-turbo-progress';

// Pages that must do a full reload (login, admin, external, activities
// with their own JS lifecycle that doesn't tolerate re-initialization).
const BYPASS_PATTERNS = [
    /\/login\//,
    /\/admin\//,
    /\/pluginfile\.php/,
    /\/draftfile\.php/,
    /\/tokenpluginfile\.php/,
    /logout\.php/,
    /\.zip$/,
    /\.pdf$/,
    /\/mod\/quiz\//,
    /\/mod\/scorm\//,
    /\/mod\/h5pactivity\//,
    /\/mod\/lesson\//,
    /\/mod\/assign\//,
    /\/mod\/bigbluebuttonbn\//,
    /[?&]edit=1/,
    // IOMAD: blocks rely on session-based company state ($companyid in
    // $SESSION->currenteditingcompany). Background prefetch / SPA swap
    // races against the session writes and yields "Cannot find record
    // in company" errors. Always full-load IOMAD admin pages.
    /\/blocks\/iomad/,
    /\/local\/iomad/,
];

let currentAbort = null;
let prefetchCache = new Map();

// ── Progress bar ──────────────────────────────────────────────────

function getOrCreateProgress() {
    let bar = document.getElementById(PROGRESS_ID);
    if (!bar) {
        bar = document.createElement('div');
        bar.id = PROGRESS_ID;
        bar.innerHTML = '<div class="sm-turbo-progress__bar"></div>';
        document.body.appendChild(bar);
    }
    return bar.firstElementChild;
}

// Progress bar disabled — kept as no-ops so navigate() callsites don't
// need to change. The fade on #page-content already conveys load state.
function showProgress() {}
function completeProgress() {}

// ── Helpers ───────────────────────────────────────────────────────

function shouldIntercept(anchor) {
    // Must be same origin.
    if (anchor.origin !== location.origin) {
        return false;
    }
    // Skip anchors, downloads, new tabs.
    if (anchor.hasAttribute('download') || anchor.target === '_blank') {
        return false;
    }
    // Skip hash-only links.
    if (anchor.pathname === location.pathname && anchor.hash) {
        return false;
    }
    // Per-link opt-out: <a data-turbo="false" ...>
    if (anchor.getAttribute('data-turbo') === 'false') {
        return false;
    }
    // Skip bypass patterns.
    const path = anchor.pathname + anchor.search;
    for (const re of BYPASS_PATTERNS) {
        if (re.test(path)) {
            return false;
        }
    }
    return true;
}

function extractContent(doc) {
    const content = doc.querySelector(SWAP_SELECTOR);
    const secNav = doc.querySelector(SEC_NAV_SELECTOR);
    const pageHeader = doc.querySelector(PAGE_HEADER_SELECTOR);
    // Pull every primary-nav candidate from the new doc, in selector order.
    const primaryNavs = {};
    PRIMARY_NAV_SELECTORS.forEach(sel => {
        const el = doc.querySelector(sel);
        if (el) {
            primaryNavs[sel] = el;
        }
    });
    const title = doc.title;

    // Collect inline <style> from body (templates inject them).
    const styles = [];
    doc.querySelectorAll('body style, body link[rel="stylesheet"]').forEach(el => {
        styles.push(el.cloneNode(true));
    });

    // Collect <script> blocks that Moodle injects in the body ({{#js}} blocks).
    const scripts = [];
    doc.querySelectorAll('body script').forEach(el => {
        scripts.push(el.textContent);
    });

    // Detect body classes for layout switching.
    const bodyClasses = doc.body.className;

    return {content, secNav, pageHeader, primaryNavs, title, styles, scripts, bodyClasses};
}

function swapContent(data) {
    // Swap #page-content.
    const currentContent = document.querySelector(SWAP_SELECTOR);
    if (currentContent && data.content) {
        // The new node hasn't been inserted yet — start it dimmed and fade in.
        data.content.classList.add('sm-turbo-loading');
        currentContent.replaceWith(data.content);
        // Force reflow then remove the dim class so the CSS transition kicks in.
        // eslint-disable-next-line no-unused-expressions
        data.content.offsetWidth;
        data.content.classList.remove('sm-turbo-loading');
    }

    // Swap secondary navigation.
    const currentSecNav = document.querySelector(SEC_NAV_SELECTOR);
    if (data.secNav) {
        if (currentSecNav) {
            currentSecNav.replaceWith(data.secNav);
        } else {
            // Insert before #page-content if it didn't exist.
            const pc = document.querySelector(SWAP_SELECTOR);
            if (pc) {
                pc.parentNode.insertBefore(data.secNav, pc);
            }
        }
    } else if (currentSecNav) {
        currentSecNav.remove();
    }

    // Swap primary navigation(s) so the active item highlights correctly.
    PRIMARY_NAV_SELECTORS.forEach(sel => {
        const current = document.querySelector(sel);
        const fresh = data.primaryNavs[sel];
        if (current && fresh) {
            current.replaceWith(fresh);
        }
    });

    // Swap page header/banner.
    const currentPageHeader = document.querySelector(PAGE_HEADER_SELECTOR);
    if (data.pageHeader) {
        if (currentPageHeader) {
            currentPageHeader.replaceWith(data.pageHeader);
        }
    } else if (currentPageHeader) {
        currentPageHeader.remove();
    }

    // Remove old inline turbo styles, inject new ones.
    document.querySelectorAll('[data-turbo-style]').forEach(el => el.remove());
    data.styles.forEach(el => {
        el.setAttribute('data-turbo-style', '');
        document.body.appendChild(el);
    });

    // Update title.
    document.title = data.title;

    // Update body classes (layout may differ).
    document.body.className = data.bodyClasses;

    // Execute JS blocks from the new page.
    data.scripts.forEach(code => {
        try {
            // eslint-disable-next-line no-eval
            eval(code);
        } catch (e) {
            // Silently ignore — some scripts may fail outside their context.
        }
    });

    // Scroll to top.
    window.scrollTo(0, 0);

    // Re-initialize Bootstrap tooltips/popovers on new content.
    try {
        const tooltips = document.querySelectorAll('#page-content [data-bs-toggle="tooltip"]');
        tooltips.forEach(el => new window.bootstrap.Tooltip(el));
    } catch (_) {
        // Bootstrap may not be globally available.
    }
}

// ── Navigation ────────────────────────────────────────────────────

async function navigate(url, pushState = true) {
    // Cancel any in-flight request.
    if (currentAbort) {
        currentAbort.abort();
    }
    currentAbort = new AbortController();

    showProgress();

    try {
        // Check prefetch cache first.
        let html = prefetchCache.get(url);
        if (!html) {
            const resp = await fetch(url, {
                signal: currentAbort.signal,
                credentials: 'same-origin',
                headers: {'X-Turbo': '1'},
            });
            if (!resp.ok || !resp.headers.get('content-type')?.includes('text/html')) {
                // Not HTML — do full navigation.
                window.location.href = url;
                return;
            }
            html = await resp.text();
        }
        prefetchCache.delete(url);

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const data = extractContent(doc);
        if (!data.content) {
            // Couldn't find content container — fall back to full load.
            window.location.href = url;
            return;
        }

        swapContent(data);

        if (pushState) {
            history.pushState({turbo: true}, '', url);
        }

        completeProgress();

        // Fire custom event for other modules to react.
        document.dispatchEvent(new CustomEvent('turbo:load', {detail: {url}}));

    } catch (err) {
        if (err.name === 'AbortError') {
            return; // Navigation was cancelled by a newer one.
        }
        // On any error, fall back to normal navigation.
        window.location.href = url;
    }
}

// ── Prefetch on hover ─────────────────────────────────────────────

function prefetch(url) {
    if (prefetchCache.has(url)) {
        return;
    }
    // Mark as in-progress so we don't double-fetch.
    prefetchCache.set(url, null);
    fetch(url, {credentials: 'same-origin', headers: {'X-Turbo': '1'}})
        .then(resp => {
            if (resp.ok && resp.headers.get('content-type')?.includes('text/html')) {
                return resp.text();
            }
            return null;
        })
        .then(html => {
            if (html) {
                prefetchCache.set(url, html);
            } else {
                prefetchCache.delete(url);
            }
        })
        .catch(() => prefetchCache.delete(url));
}

// ── Proactive prefetch ────────────────────────────────────────────
// Right after the shell loads, fetch the most likely next destinations
// in the background so navigation feels instantaneous when the user
// finally clicks. Re-runs every 3 minutes to fight staleness.

const COMMON_ROUTES = [
    '/my/',
    '/my/courses.php',
    '/user/profile.php',
    '/message/index.php',
    '/calendar/view.php',
];

function getWwwRoot() {
    if (window.M && window.M.cfg && window.M.cfg.wwwroot) {
        return window.M.cfg.wwwroot;
    }
    return location.origin;
}

function prefetchCommonRoutes() {
    const root = getWwwRoot();
    COMMON_ROUTES.forEach(path => {
        try {
            const url = new URL(root + path).toString();
            // Don't prefetch the page we're already on.
            if (url === location.href) {
                return;
            }
            prefetch(url);
        } catch (_) {
            // Ignore malformed URLs.
        }
    });
}

// ── Event listeners ───────────────────────────────────────────────

function init() {
    // Guard against double-init across turbo swaps.
    if (window.__smTurboInitialized) {
        return;
    }
    window.__smTurboInitialized = true;

    // Intercept link clicks.
    document.addEventListener('click', function(e) {
        const anchor = e.target.closest('a[href]');
        if (!anchor || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) {
            return;
        }
        if (!shouldIntercept(anchor)) {
            return;
        }
        e.preventDefault();
        navigate(anchor.href);
    });

    // Handle back/forward.
    window.addEventListener('popstate', function() {
        navigate(location.href, false);
    });

    // Prefetch on hover (200ms debounce).
    let hoverTimer = null;
    document.addEventListener('mouseover', function(e) {
        const anchor = e.target.closest('a[href]');
        if (!anchor || !shouldIntercept(anchor)) {
            return;
        }
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(() => prefetch(anchor.href), 200);
    });
    document.addEventListener('mouseout', function(e) {
        if (e.target.closest('a[href]')) {
            clearTimeout(hoverTimer);
        }
    });

    // Intercept form submissions (GET forms only).
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.method && form.method.toUpperCase() !== 'GET') {
            return;
        }
        const action = form.action || location.href;
        try {
            const url = new URL(action);
            if (url.origin !== location.origin) {
                return;
            }
            const formData = new FormData(form);
            for (const [key, value] of formData.entries()) {
                url.searchParams.set(key, value);
            }
            e.preventDefault();
            navigate(url.toString());
        } catch (_) {
            // Invalid URL — let the browser handle it.
        }
    });

    // Mark initial page as turbo-enabled for popstate.
    history.replaceState({turbo: true}, '');

    // Kick off proactive prefetch after a small delay so we don't compete
    // with the initial page render. Re-run periodically to fight staleness.
    setTimeout(prefetchCommonRoutes, 1500);
    setInterval(() => {
        prefetchCache.clear();
        prefetchCommonRoutes();
    }, 3 * 60 * 1000);
}

export {init};

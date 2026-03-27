/**
 * Course Page — Udemy-style course dedicated page layout.
 *
 * Takes over #region-main on course-view pages. Shows ONE activity at a time
 * in the content area, loaded via AJAX. The learning path sidebar lets users
 * click between activities. Prev/Next buttons cycle through items within an
 * activity first (book chapters, slides, questions), then between activities.
 *
 * Render modes:
 * - inline: HTML rendered directly in content area (page, book, label, resource)
 * - iframe: Activity page loaded in embedded iframe (quiz, assign, scorm, etc.)
 * - redirect: Navigate to real Moodle URL (forum, chat, bigbluebuttonbn, lti)
 *
 * @module     local_sm_graphics_plugin/course_page
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str'], function(Ajax, Str) {

    var REDIRECT_TYPES = ['forum', 'chat', 'bigbluebuttonbn', 'lti'];
    var GRANULAR_TYPES = ['book', 'quiz', 'lesson', 'scorm'];

    var state = {
        courseid: 0,
        activities: [],
        currentIndex: -1,
        currentItemNum: 0,
        totalItems: 0,
        totalPages: 0,
        counterLabel: '',
        sidebarCollapsed: false,
        focusMode: false,
        commentsLoaded: false,
        loading: false,
        progressTimer: null,
        videoTimerActive: false,
        furthestItem: 0,
        videoFurthestPct: 0,
        pollingInterval: null,
        previousCompletionMap: {},
        activityProgress: {},
        dwellTimer: null,
        pendingTagNavigation: null
    };

    var els = {};

    /** Cached reference to the course_comments AMD module. */
    var CourseCommentsModule = null;

    /**
     * Initialize the course page.
     */
    var init = function() {
        var source = document.getElementById('smgp-course-page-source');
        if (!source) {
            return;
        }

        var regionMain = document.getElementById('region-main');
        if (!regionMain) {
            return;
        }

        // Hide page header and secondary navigation.
        var pageHeader = document.getElementById('page-header');
        if (pageHeader) {
            pageHeader.style.display = 'none';
        }
        var secNav = document.querySelector('.secondary-navigation');
        if (secNav) {
            secNav.style.display = 'none';
        }

        // Replace #region-main with our course page shell.
        regionMain.innerHTML = source.innerHTML;
        source.remove();

        // Remove card styling from region-main.
        regionMain.style.border = 'none';
        regionMain.style.boxShadow = 'none';
        regionMain.style.borderRadius = '0';
        regionMain.style.padding = '0';
        regionMain.style.backgroundColor = 'transparent';

        // Reveal (FOUC fix).
        regionMain.style.opacity = '1';
        regionMain.classList.add('smgp-course-page-ready');

        // Cache DOM references.
        var cp = regionMain.querySelector('.smgp-course-page');
        if (!cp) {
            return;
        }

        els.page = cp;
        els.contentArea = cp.querySelector('#smgp-course-content-area');
        els.activityName = cp.querySelector('#smgp-current-activity-name');
        els.activityIcon = cp.querySelector('#smgp-current-activity-icon');
        els.activityInfoSection = cp.querySelector('#smgp-course-activity-info');
        els.activityInfoTitle = cp.querySelector('#smgp-activity-info-title');
        els.activityInfoMeta = cp.querySelector('#smgp-activity-info-meta');
        els.breadcrumbMycourses = cp.querySelector('#smgp-breadcrumb-mycourses');
        els.itemCounter = cp.querySelector('#smgp-activity-item-counter');
        els.progressText = cp.querySelector('#smgp-activity-progress-text');
        els.sidebar = cp.querySelector('#smgp-course-sidebar');
        els.sidebarToggle = cp.querySelector('#smgp-sidebar-toggle');
        els.prevBtn = cp.querySelector('#smgp-nav-prev');
        els.nextBtn = cp.querySelector('#smgp-nav-next');
        els.focusBtn = cp.querySelector('#smgp-course-focus-btn');
        els.progressBar = cp.querySelector('#smgp-course-progress-bar');
        els.progressFill = cp.querySelector('#smgp-progress-fill');
        els.progressCursor = cp.querySelector('#smgp-progress-cursor');

        state.courseid = parseInt(cp.getAttribute('data-courseid'), 10) || 0;

        // Build flat activity list from sidebar DOM (including labels).
        buildActivityList();

        // Initialize activityProgress map from server-rendered completion classes.
        state.activities.forEach(function(activity) {
            state.activityProgress[activity.cmid] =
                activity.element.classList.contains('smgp-course-activity--complete') ? 1.0 : 0.0;
        });

        // Restore video progress from localStorage for non-complete activities.
        state.activities.forEach(function(activity) {
            if (state.activityProgress[activity.cmid] < 1.0) {
                try {
                    var videoData = JSON.parse(localStorage.getItem('smgp-video-' + activity.cmid));
                    if (videoData && videoData.pct > 0) {
                        var videoProg = videoData.pct / 100;
                        if (videoProg > state.activityProgress[activity.cmid]) {
                            state.activityProgress[activity.cmid] = videoProg;
                        }
                    }
                } catch (e) { /* ignore */ }
            }
        });

        // Initialize progress rings.
        initProgressRings();

        // Bind events.
        bindSectionToggles();
        bindActivityClicks();
        bindNavigation();
        bindTabs();
        bindSidebarToggle();
        bindFocusMode();
        bindProgressBar();
        bindScormProgress();
        bindActivityProgress();

        // Listen for in-player tag navigation events from the comments module.
        document.addEventListener('smgp-navigate-to-activity', function(e) {
            if (e.detail) {
                navigateToActivityPosition(e.detail.cmid, e.detail.position, e.detail.type);
            }
        });

        // Resume activity: URL param smgp_cmid > localStorage > first activity.
        var resumeIdx = -1;
        var storageKey = 'smgp-course-last-activity-' + state.courseid;

        // 1. Check URL parameter (from dashboard "continue learning" link).
        var urlCmid = parseInt(new URLSearchParams(window.location.search).get('smgp_cmid'), 10);
        if (urlCmid) {
            for (var ui = 0; ui < state.activities.length; ui++) {
                if (state.activities[ui].cmid === urlCmid &&
                    REDIRECT_TYPES.indexOf(state.activities[ui].modname) === -1) {
                    resumeIdx = ui;
                    break;
                }
            }
        }

        // 2. Fallback to localStorage.
        if (resumeIdx < 0) {
            try {
                var savedCmid = parseInt(localStorage.getItem(storageKey), 10);
                if (savedCmid) {
                    for (var ri = 0; ri < state.activities.length; ri++) {
                        if (state.activities[ri].cmid === savedCmid &&
                            REDIRECT_TYPES.indexOf(state.activities[ri].modname) === -1) {
                            resumeIdx = ri;
                            break;
                        }
                    }
                }
            } catch (e) { /* localStorage unavailable */ }
        }

        if (resumeIdx < 0) {
            // Fallback: first non-redirect activity.
            resumeIdx = 0;
            while (resumeIdx < state.activities.length &&
                   REDIRECT_TYPES.indexOf(state.activities[resumeIdx].modname) !== -1) {
                resumeIdx++;
            }
        }

        if (resumeIdx >= 0 && resumeIdx < state.activities.length) {
            selectActivity(resumeIdx);
        }

        // Fetch server-side fractional progress immediately (replaces binary init values).
        refreshProgress();

        // Fetch durations from DB and enable admin editing.
        fetchDurations();
        bindDurationEditing();

        // Breadcrumb navigation.
        if (els.breadcrumbMycourses) {
            els.breadcrumbMycourses.addEventListener('click', function() {
                var url = els.breadcrumbMycourses.getAttribute('data-url');
                if (url) {
                    window.location.href = url;
                }
            });
        }

        // Apply icon box colors based on modname.
        applyIconBoxColors();
    };

    /**
     * Fetch durations from DB and populate sidebar + current activity meta.
     */
    var fetchDurations = function() {
        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_get_activity_durations',
            args: {courseid: state.courseid}
        }])[0].done(function(response) {
            if (response && response.durations) {
                response.durations.forEach(function(d) {
                    if (d.duration > 0) {
                        var spans = els.page.querySelectorAll('.smgp-course-activity__duration[data-cmid="' + d.cmid + '"]');
                        spans.forEach(function(span) {
                            span.textContent = d.duration + ' min';
                        });
                    }
                });
                // Refresh current activity info meta with duration.
                updateActivityInfoMeta();
            }
        }).fail(function() {
            // Silently fail — durations are non-critical.
        });
    };

    /**
     * Update the activity info meta line below content.
     */
    var updateActivityInfoMeta = function() {
        if (state.currentIndex < 0 || !els.activityInfoMeta) {
            return;
        }
        var act = state.activities[state.currentIndex];
        var sectionName = act.element.getAttribute('data-sectionname') || '';
        var resIdx = act.element.getAttribute('data-resourceindex') || '0';
        var sectionTotal = act.element.getAttribute('data-sectiontotalcount') || '0';
        var durSpan = act.element.querySelector('.smgp-course-activity__duration');
        var dur = (durSpan && durSpan.textContent.trim()) ? durSpan.textContent.trim() : '';
        var meta = sectionName + ' \u00B7 ' + resIdx + ' / ' + sectionTotal;
        if (dur) {
            meta += ' \u00B7 ' + dur;
        }
        els.activityInfoMeta.textContent = meta;
    };

    /**
     * Auto-save video duration when metadata loads (if no duration set yet).
     * @param {HTMLVideoElement} video
     */
    var autoSaveVideoDuration = function(video) {
        if (!video || !video.duration || isNaN(video.duration) || !isFinite(video.duration)) {
            return;
        }
        if (state.currentIndex < 0) {
            return;
        }
        var activity = state.activities[state.currentIndex];
        var durSpan = activity.element.querySelector('.smgp-course-activity__duration');
        // Only auto-save if no duration is set yet.
        if (durSpan && durSpan.textContent.trim() && durSpan.textContent.indexOf('min') !== -1) {
            return; // Already has a duration.
        }
        var minutes = Math.ceil(video.duration / 60);
        if (minutes < 1) {
            minutes = 1;
        }
        // Save via AJAX.
        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_set_activity_duration',
            args: {cmid: parseInt(activity.cmid, 10), duration: minutes}
        }])[0].done(function(result) {
            if (result.success) {
                els.page.querySelectorAll('.smgp-course-activity__duration[data-cmid="' + activity.cmid + '"]').forEach(function(s) {
                    s.textContent = result.duration + ' min';
                });
                updateActivityInfoMeta();
            }
        });
    };

    /**
     * Make duration labels editable for admins (click to edit).
     */
    var bindDurationEditing = function() {
        var canEdit = els.page.getAttribute('data-canedit') === '1';
        if (!canEdit) {
            return;
        }

        els.page.querySelectorAll('.smgp-course-activity__duration').forEach(function(span) {
            span.style.cursor = 'pointer';
            span.title = 'Click to edit duration';

            span.addEventListener('click', function(e) {
                e.stopPropagation();
                var cmid = parseInt(span.getAttribute('data-cmid'), 10);
                if (!cmid) {
                    return;
                }

                // Parse current value.
                var currentText = span.textContent.trim();
                var currentMin = 0;
                var match = currentText.match(/(\d+)/);
                if (match) {
                    currentMin = parseInt(match[1], 10);
                }

                var input = prompt('Duration in minutes for this activity:', currentMin);
                if (input === null) {
                    return; // Cancelled.
                }
                var newMin = parseInt(input, 10);
                if (isNaN(newMin) || newMin < 0) {
                    return;
                }

                // Save via AJAX.
                span.textContent = '...';
                Ajax.call([{
                    methodname: 'local_sm_graphics_plugin_set_activity_duration',
                    args: {cmid: cmid, duration: newMin}
                }])[0].done(function(result) {
                    if (result.success) {
                        // Update all spans with this cmid.
                        els.page.querySelectorAll('.smgp-course-activity__duration[data-cmid="' + cmid + '"]').forEach(function(s) {
                            s.textContent = result.duration + ' min';
                        });
                        updateActivityInfoMeta();
                    }
                }).fail(function() {
                    span.textContent = currentMin + ' min';
                });
            });
        });
    };

    /**
     * Apply colored backgrounds to icon boxes based on activity type.
     */
    var ICON_BG_MAP = {
        'page': '#EEF2FF',
        'book': '#EEF2FF',
        'resource': '#EEF2FF',
        'scorm': '#EEF2FF',
        'wiki': '#EEF2FF',
        'quiz': '#E6F5F0',
        'lesson': '#E6F5F0',
        'h5pactivity': '#FEF3C7',
        'url': '#FEF3C7',
        'assign': '#FDF2F8',
        'bigbluebuttonbn': '#FDF2F8',
        'chat': '#FDF2F8',
    };

    var applyIconBoxColors = function() {
        els.page.querySelectorAll('.smgp-course-activity').forEach(function(item) {
            var modname = item.getAttribute('data-modname');
            var iconBox = item.querySelector('.smgp-course-activity__icon-box');
            if (iconBox && ICON_BG_MAP[modname]) {
                iconBox.style.background = ICON_BG_MAP[modname];
            }
        });
    };

    /**
     * Build flat activity list from sidebar DOM (ALL activities including labels).
     */
    var buildActivityList = function() {
        var items = els.page.querySelectorAll('.smgp-course-activity');
        state.activities = [];
        items.forEach(function(item) {
            var cmid = item.getAttribute('data-cmid');
            if (cmid) {
                state.activities.push({
                    cmid: parseInt(cmid, 10),
                    url: item.getAttribute('data-url') || '',
                    name: item.getAttribute('data-name') || '',
                    modname: item.getAttribute('data-modname') || '',
                    iconclass: item.getAttribute('data-iconclass') || 'icon-file',
                    element: item
                });
            }
        });
    };

    /**
     * Select and load an activity by index.
     */
    var selectActivity = function(idx, itemnum) {
        if (idx < 0 || idx >= state.activities.length || state.loading) {
            return;
        }

        // Debug: log activity switch.
        var _act = state.activities[idx];
        if (typeof window._slDebugLog === 'function' && _act) {
            window._slDebugLog({
                timestamp: Date.now(),
                level: 'info',
                component: 'course_page',
                message: '[Activity] Switched to "' + _act.name + '" (' + _act.modname + ', cmid=' + _act.cmid + ', index=' + idx + ')',
                source: 'frontend'
            });
        }

        // Save video position before switching away.
        if (state.videoTimerActive && state.currentIndex >= 0) {
            var prevVideo = els.contentArea ? els.contentArea.querySelector('video') : null;
            if (prevVideo) {
                var prevCmid = state.activities[state.currentIndex].cmid;
                try {
                    localStorage.setItem('smgp-video-' + prevCmid, JSON.stringify({
                        time: prevVideo.currentTime,
                        pct: state.videoFurthestPct
                    }));
                } catch (e) { /* localStorage unavailable */ }
            }
        }

        // Clear dwell completion timer.
        if (state.dwellTimer) {
            clearTimeout(state.dwellTimer);
            state.dwellTimer = null;
        }

        var activity = state.activities[idx];

        // Redirect-type activities: navigate to real Moodle URL.
        if (REDIRECT_TYPES.indexOf(activity.modname) !== -1 && activity.url) {
            window.location.href = activity.url;
            return;
        }

        stopProgressPolling();
        state.furthestItem = 0;
        state.videoFurthestPct = 0;

        state.currentIndex = idx;
        state.currentItemNum = 0;
        state.totalItems = 0;
        state.totalPages = 0;
        state.counterLabel = '';
        state.videoTimerActive = false;

        // Persist last viewed activity for resume on page refresh.
        try {
            localStorage.setItem('smgp-course-last-activity-' + state.courseid, activity.cmid);
        } catch (e) { /* localStorage unavailable */ }

        // Update activity bar (legacy — kept for backward compat).
        if (els.activityName) {
            els.activityName.textContent = activity.name;
        }
        if (els.activityIcon) {
            els.activityIcon.className = activity.iconclass;
        }

        // Update activity info below content.
        if (els.activityInfoSection) {
            els.activityInfoSection.style.display = '';
        }
        if (els.activityInfoTitle) {
            els.activityInfoTitle.textContent = activity.name;
        }
        if (els.activityInfoMeta) {
            var sectionName = activity.element.getAttribute('data-sectionname') || '';
            var sectionIdx = activity.element.getAttribute('data-sectionindex') || '0';
            var resIdx = activity.element.getAttribute('data-resourceindex') || '0';
            var sectionTotal = activity.element.getAttribute('data-sectiontotalcount') || '0';
            var durSpan = activity.element.querySelector('.smgp-course-activity__duration');
            var dur = durSpan ? durSpan.textContent : '-- min';
            els.activityInfoMeta.textContent = sectionName + ' \u00B7 ' +
                resIdx + ' / ' + sectionTotal + ' \u00B7 ' + dur;
        }

        // Highlight in sidebar.
        state.activities.forEach(function(a) {
            a.element.classList.remove('smgp-course-activity--active');
        });
        activity.element.classList.add('smgp-course-activity--active');

        // Auto-expand parent section.
        var section = activity.element.closest('.smgp-course-section');
        if (section && !section.classList.contains('smgp-course-section--expanded')) {
            section.classList.add('smgp-course-section--expanded');
            var header = section.querySelector('.smgp-course-section__header');
            if (header) {
                header.setAttribute('aria-expanded', 'true');
            }
        }

        // Scroll activity into view in sidebar.
        activity.element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        updateNavButtons();
        loadActivityContent(activity.cmid, itemnum || 0);
    };

    /**
     * Load activity content via AJAX.
     */
    var loadActivityContent = function(cmid, itemnum) {
        if (!els.contentArea) {
            return;
        }

        itemnum = itemnum || 0;
        state.loading = true;
        state.videoTimerActive = false;

        // Debug: log content load start.
        if (typeof window._slDebugLog === 'function') {
            window._slDebugLog({
                timestamp: Date.now(),
                level: 'info',
                component: 'course_page',
                message: '[Activity] Loading content for cmid=' + cmid + ', itemnum=' + itemnum,
                source: 'frontend'
            });
        }

        els.contentArea.innerHTML = '<div class="smgp-course-content__loading">'
            + '<div class="spinner-border text-primary" role="status"></div>'
            + '</div>';

        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_get_activity_content',
            args: { cmid: cmid, itemnum: itemnum }
        }])[0].done(function(response) {
            state.loading = false;

            // Debug: log content loaded.
            if (typeof window._slDebugLog === 'function') {
                window._slDebugLog({
                    timestamp: Date.now(),
                    level: 'info',
                    component: 'course_page',
                    message: '[Activity] Content loaded for cmid=' + cmid +
                        ', rendermode=' + (response.rendermode || 'inline') +
                        ', totalItems=' + (response.itemcount || 0),
                    source: 'frontend'
                });
            }

            if (response.rendermode === 'iframe' && response.iframeurl) {
                renderIframe(response.iframeurl, response.name);
            } else if (response.rendermode === 'redirect' && response.url) {
                window.location.href = response.url;
                return;
            } else {
                els.contentArea.innerHTML = response.html;
            }

            // Update item-level state.
            state.totalItems = response.itemcount || 0;
            state.currentItemNum = response.currentitem || 0;
            state.counterLabel = response.counterlabel || '';
            state.totalPages = response.totalpages || 0;

            // Furthest reached: max of current, server-known completed, and previous furthest.
            var serverCompleted = response.completeditems || 0;
            state.furthestItem = Math.max(state.furthestItem, state.currentItemNum, serverCompleted);

            // Update activity progress from item navigation (never downgrade).
            if (state.totalItems > 0 && state.currentIndex >= 0) {
                var actCmid = state.activities[state.currentIndex].cmid;
                var itemProg = state.furthestItem / state.totalItems;
                if (itemProg > (state.activityProgress[actCmid] || 0)) {
                    state.activityProgress[actCmid] = itemProg;
                    updateRings();
                }
            }

            // Update item counter in nav bar.
            updateItemCounter(response.itemcount, response.currentitem, response.counterlabel);

            // Check for inline video and set up timestamp counter.
            setupVideoCounter();

            // Show/hide timeline bar based on whether activity has items or is video.
            updateTimelineVisibility();

            // Update progress bar cursor position for item-based activities.
            updateProgressCursor();

            updateNavButtons();

            if (response.rendermode === 'iframe') {
                startProgressPolling();
            } else {
                scheduleProgressCheck();
            }

            // Dwell completion: for non-granular activities, mark complete after 3 seconds.
            if (state.dwellTimer) {
                clearTimeout(state.dwellTimer);
                state.dwellTimer = null;
            }
            var currentAct = state.currentIndex >= 0 ? state.activities[state.currentIndex] : null;
            if (currentAct && GRANULAR_TYPES.indexOf(currentAct.modname) === -1 && !state.videoTimerActive) {
                var dwellCmid = currentAct.cmid;
                state.dwellTimer = setTimeout(function() {
                    Ajax.call([{
                        methodname: 'local_sm_graphics_plugin_mark_activity_complete',
                        args: { cmid: dwellCmid }
                    }])[0].done(function() {
                        refreshProgress();
                    });
                }, 3000);
            }

            // Update comments module with current activity context.
            updateCommentsContext();

        }).fail(function() {
            state.loading = false;
            state.totalItems = 0;
            state.currentItemNum = 0;
            state.counterLabel = '';
            state.videoTimerActive = false;
            els.contentArea.innerHTML = '<div class="smgp-course-content__error">'
                + '<i class="icon-triangle-alert"></i>'
                + '<p>Failed to load activity content.</p>'
                + '</div>';
            updateItemCounter(0, 0, '');
            updateNavButtons();
        });
    };

    /**
     * Render an iframe for embedded activity display.
     */
    var renderIframe = function(url, title) {
        if (!els.contentArea) {
            return;
        }
        var iframe = document.createElement('iframe');
        iframe.className = 'smgp-course-content__iframe';

        // Genially: use direct URL with extended permissions and responsive class.
        var isGenially = (url.indexOf('genial.ly') !== -1 || url.indexOf('genially.com') !== -1);
        if (isGenially) {
            iframe.classList.add('smgp-course-content__iframe--genially');
            iframe.setAttribute('allow', 'autoplay; encrypted-media; fullscreen; clipboard-write');
        } else {
            iframe.setAttribute('allow', 'autoplay; encrypted-media');
        }

        iframe.src = url;
        iframe.title = title || '';
        iframe.setAttribute('allowfullscreen', 'true');

        // Detect form submissions: skip first load (initial src), catch subsequent (submit/redirect).
        var loadCount = 0;
        iframe.addEventListener('load', function() {
            loadCount++;
            if (loadCount > 1) {
                refreshProgress();
                refreshItemCount();
            }
        });

        var wrap = document.createElement('div');
        wrap.className = 'smgp-course-content__iframe-wrap';
        wrap.appendChild(iframe);
        els.contentArea.innerHTML = '';
        els.contentArea.appendChild(wrap);
    };

    /**
     * Update the item counter in the nav bar.
     */
    var updateItemCounter = function(itemcount, currentitem, label) {
        if (!els.itemCounter) {
            return;
        }

        if (itemcount > 0) {
            var icon = '<i class="icon-layers"></i> ';
            els.itemCounter.innerHTML = icon + label + ' ' + currentitem + '/' + itemcount;
            els.itemCounter.style.display = 'inline-flex';
        } else {
            els.itemCounter.innerHTML = '';
            els.itemCounter.style.display = 'none';
        }
    };

    /**
     * Format seconds into M:SS string.
     */
    var formatTime = function(seconds) {
        if (!seconds || isNaN(seconds) || !isFinite(seconds)) {
            return '0:00';
        }
        var m = Math.floor(seconds / 60);
        var s = Math.floor(seconds % 60);
        return m + ':' + (s < 10 ? '0' : '') + s;
    };

    /**
     * Format seconds into HH:MM:SS string for activity tags.
     */
    var formatTagTime = function(seconds) {
        if (!seconds || isNaN(seconds) || !isFinite(seconds)) {
            return '00:00:00';
        }
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = Math.floor(seconds % 60);
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    };

    /**
     * Set up real-time video timestamp counter for inline video resources.
     * Shows current time / total duration in the nav bar counter.
     * Does NOT affect prev/next navigation (itemcount stays 0 → navigates activities).
     */
    var setupVideoCounter = function() {
        if (!els.contentArea || !els.itemCounter) {
            return;
        }

        var video = els.contentArea.querySelector('video');
        if (!video) {
            return;
        }

        state.videoTimerActive = true;

        // Video position persistence via localStorage.
        var videoCmid = state.currentIndex >= 0 ? state.activities[state.currentIndex].cmid : 0;
        var videoStorageKey = videoCmid ? 'smgp-video-' + videoCmid : '';
        var savedTime = 0;
        var savedPct = 0;
        if (videoStorageKey) {
            try {
                var saved = JSON.parse(localStorage.getItem(videoStorageKey));
                if (saved && saved.time > 0) {
                    savedTime = saved.time;
                    savedPct = saved.pct || 0;
                }
            } catch (e) { /* localStorage unavailable */ }
        }

        var lastSaveTime = 0;
        var saveVideoPosition = function() {
            if (!videoStorageKey) {
                return;
            }
            try {
                localStorage.setItem(videoStorageKey, JSON.stringify({
                    time: video.currentTime,
                    pct: state.videoFurthestPct
                }));
            } catch (e) { /* localStorage unavailable */ }
        };

        // Restore saved position once metadata is available.
        var restored = false;
        var restorePosition = function() {
            if (restored || !savedTime || !video.duration) {
                return;
            }
            restored = true;
            video.currentTime = Math.min(savedTime, video.duration - 0.5);
            state.videoFurthestPct = Math.max(state.videoFurthestPct, savedPct);
        };

        var updateDisplay = function() {
            if (!state.videoTimerActive) {
                return;
            }
            var current = formatTime(video.currentTime);
            var total = formatTime(video.duration);
            els.itemCounter.innerHTML = '<i class="icon-clock"></i> ' + current + ' / ' + total;
            els.itemCounter.style.display = 'inline-flex';

            // Track furthest-reached for video.
            if (els.progressCursor && els.progressFill && video.duration > 0) {
                var currentPct = (video.currentTime / video.duration) * 100;
                state.videoFurthestPct = Math.max(state.videoFurthestPct, currentPct);

                // Cursor at current position, fill at furthest.
                els.progressCursor.style.left = currentPct + '%';
                els.progressFill.style.width = state.videoFurthestPct + '%';

                // Update activity progress map + rings for real-time video progress.
                if (state.currentIndex >= 0) {
                    var cmid = state.activities[state.currentIndex].cmid;
                    state.activityProgress[cmid] = state.videoFurthestPct / 100;
                    updateRings();
                }
            }
        };

        // When video ends (or reaches ~98%), mark the activity as complete.
        var videoCompletionTriggered = false;
        var checkVideoCompletion = function() {
            if (videoCompletionTriggered || !video.duration) {
                return;
            }
            if (state.videoFurthestPct >= 98 && state.currentIndex >= 0) {
                videoCompletionTriggered = true;
                var cmid = state.activities[state.currentIndex].cmid;
                Ajax.call([{
                    methodname: 'local_sm_graphics_plugin_mark_activity_complete',
                    args: { cmid: cmid }
                }])[0].done(function() {
                    refreshProgress();
                });
                saveVideoPosition();
            }
        };

        video.addEventListener('timeupdate', function() {
            updateDisplay();
            checkVideoCompletion();
            // Save position every 3 seconds.
            var now = Date.now();
            if (now - lastSaveTime > 3000) {
                lastSaveTime = now;
                saveVideoPosition();
            }
        });
        video.addEventListener('loadedmetadata', function() {
            restorePosition();
            updateDisplay();
            // Auto-save video duration if not already set.
            autoSaveVideoDuration(video);
        });
        video.addEventListener('ended', function() {
            state.videoFurthestPct = 100;
            updateDisplay();
            checkVideoCompletion();
            saveVideoPosition();
        });
        video.addEventListener('pause', saveVideoPosition);

        // Show initial state if metadata already loaded.
        if (video.readyState >= 1) {
            restorePosition();
            updateDisplay();
        } else {
            // Show placeholder until metadata loads.
            els.itemCounter.innerHTML = '<i class="icon-clock"></i> 0:00 / --:--';
            els.itemCounter.style.display = 'inline-flex';
        }
    };

    /**
     * Update the comments module with the current activity context.
     * Called after each activity load so activity tags reflect the current activity.
     */
    var updateCommentsContext = function() {
        if (state.currentIndex < 0) {
            return;
        }

        var activity = state.activities[state.currentIndex];

        var contextData = {
            cmid: activity.cmid,
            activityName: activity.name,
            activityType: activity.modname,
            positionIndex: state.currentItemNum,
            counterLabel: state.counterLabel,
            isVideo: state.videoTimerActive
        };

        if (CourseCommentsModule) {
            CourseCommentsModule.updateContext(contextData);
        }
    };

    /**
     * Get the current video timestamp for activity tag insertion.
     * Returns formatted time string or empty if no video is playing.
     */
    var getVideoTimestamp = function() {
        if (!state.videoTimerActive || !els.contentArea) {
            return '';
        }
        var video = els.contentArea.querySelector('video');
        if (video && video.currentTime > 0) {
            return formatTagTime(video.currentTime);
        }
        return '00:00:00';
    };

    /**
     * Schedule a progress check after activity load.
     */
    var scheduleProgressCheck = function() {
        if (state.progressTimer) {
            clearTimeout(state.progressTimer);
        }
        state.progressTimer = setTimeout(function() {
            refreshProgress();
        }, 3000);
    };

    /**
     * Refresh the item counter for the current activity (quiz questions, book chapters, etc.).
     * Re-fetches item counts from the server and updates the nav bar counter + progress ring.
     */
    var refreshItemCount = function() {
        if (state.currentIndex < 0 || !state.courseid) {
            return;
        }
        var activity = state.activities[state.currentIndex];
        // Only refresh for granular types that have item counts.
        if (GRANULAR_TYPES.indexOf(activity.modname) === -1) {
            return;
        }

        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_get_activity_content',
            args: { cmid: activity.cmid, itemnum: 0 }
        }])[0].done(function(response) {
            if (response.itemcount > 0) {
                state.totalItems = response.itemcount;
                state.counterLabel = response.counterlabel || '';
                if (response.totalpages) {
                    state.totalPages = response.totalpages;
                }

                // For SCORM, the real-time postMessage tracking is the source of truth
                // for current slide position. The server value lags behind because the
                // SCORM API commit hasn't reached the DB yet. Only update currentItemNum
                // from the server for non-SCORM activities.
                if (activity.modname !== 'scorm') {
                    state.currentItemNum = response.currentitem || 0;
                }

                var serverCompleted = response.completeditems || 0;
                state.furthestItem = Math.max(state.furthestItem, state.currentItemNum, serverCompleted);

                // Update activity progress ring (never downgrade).
                if (state.currentIndex >= 0) {
                    var newProg = state.furthestItem / state.totalItems;
                    var curProg = state.activityProgress[activity.cmid] || 0;
                    if (newProg > curProg) {
                        state.activityProgress[activity.cmid] = newProg;
                        updateRings();
                    }
                }

                updateItemCounter(response.itemcount, state.currentItemNum, response.counterlabel);
                updateProgressCursor();
                updateNavButtons();
            }
        });
    };

    /**
     * Start continuous polling for iframe activities (every 8s).
     */
    var startProgressPolling = function() {
        stopProgressPolling();
        refreshProgress();
        state.pollingInterval = setInterval(function() {
            refreshProgress();
            refreshItemCount();
        }, 8000);
    };

    /**
     * Stop continuous polling and any pending single-fire timer.
     */
    var stopProgressPolling = function() {
        if (state.pollingInterval) {
            clearInterval(state.pollingInterval);
            state.pollingInterval = null;
        }
        if (state.progressTimer) {
            clearTimeout(state.progressTimer);
            state.progressTimer = null;
        }
    };

    /**
     * Refresh progress data from the server and update UI.
     */
    var refreshProgress = function() {
        if (!state.courseid) {
            return;
        }

        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_get_course_progress',
            args: { courseid: state.courseid }
        }])[0].done(function(response) {
            var completionMap = {};
            response.activities.forEach(function(act) {
                completionMap[act.cmid] = act.completed;
            });

            // Detect newly completed activities (0 → 1 transitions).
            var newlyCompleted = [];
            if (Object.keys(state.previousCompletionMap).length > 0) {
                response.activities.forEach(function(act) {
                    if (act.completed === 1 && state.previousCompletionMap[act.cmid] === 0) {
                        newlyCompleted.push(act.cmid);
                    }
                });
            }
            state.previousCompletionMap = completionMap;

            // Populate activityProgress from server response.
            var currentCmid = state.currentIndex >= 0 ? state.activities[state.currentIndex].cmid : 0;
            response.activities.forEach(function(act) {
                var oldProg = state.activityProgress[act.cmid] || 0;
                var newProg = Math.max(act.progress, oldProg);
                if (Math.abs(newProg - oldProg) > 0.001) {
                    console.log('[SMGP Progress] cmid=' + act.cmid +
                        ' server=' + act.progress.toFixed(4) +
                        ' local=' + oldProg.toFixed(4) +
                        ' → ' + newProg.toFixed(4) +
                        (act.cmid === currentCmid ? ' (current, max)' : ' (non-current, server wins)'));
                }
                state.activityProgress[act.cmid] = newProg;
            });

            // For current video activity: override with local video progress.
            if (state.videoTimerActive && currentCmid) {
                var videoProg = state.videoFurthestPct / 100;
                if (videoProg > (state.activityProgress[currentCmid] || 0)) {
                    state.activityProgress[currentCmid] = videoProg;
                }
            }

            // Update checkmarks and completion classes.
            var actRingCirc = 2 * Math.PI * 7;
            state.activities.forEach(function(activity) {
                var completed = completionMap[activity.cmid];
                if (typeof completed === 'undefined') {
                    return;
                }

                var completionEl = activity.element.querySelector('.smgp-course-activity__completion');
                if (!completionEl) {
                    return;
                }

                if (activity.modname === 'forum') {
                    completionEl.innerHTML = '';
                } else if (completed) {
                    activity.element.classList.add('smgp-course-activity--complete');
                    completionEl.innerHTML = '<i class="icon-check-circle smgp-course-activity__check"></i>';
                } else {
                    activity.element.classList.remove('smgp-course-activity--complete');
                    var prog = state.activityProgress[activity.cmid] || 0;
                    var ringOffset = actRingCirc - (prog * actRingCirc);
                    completionEl.innerHTML = '<svg class="smgp-activity-ring" viewBox="0 0 20 20">'
                        + '<circle class="smgp-activity-ring__bg" cx="10" cy="10" r="7" fill="none" stroke-width="2"/>'
                        + '<circle class="smgp-activity-ring__fill" cx="10" cy="10" r="7" fill="none" stroke-width="2"'
                        + ' style="stroke-dasharray:' + actRingCirc + ';stroke-dashoffset:' + ringOffset + '"/>'
                        + '</svg>';
                }

                // Flash animation for newly completed activities.
                if (newlyCompleted.indexOf(activity.cmid) !== -1) {
                    activity.element.classList.add('smgp-course-activity--just-completed');
                    setTimeout(function() {
                        activity.element.classList.remove('smgp-course-activity--just-completed');
                    }, 600);
                }
            });

            // Update section completion counts (binary, excluding forums).
            var sections = els.page.querySelectorAll('.smgp-course-section');
            sections.forEach(function(section) {
                var sectionActivities = section.querySelectorAll('.smgp-course-activity');
                var sectionTotal = 0;
                var sectionCompleted = 0;

                sectionActivities.forEach(function(actEl) {
                    var cmid = parseInt(actEl.getAttribute('data-cmid'), 10);
                    if (cmid && actEl.getAttribute('data-modname') !== 'forum') {
                        sectionTotal++;
                        if (completionMap[cmid]) {
                            sectionCompleted++;
                        }
                    }
                });

                var countEl = section.querySelector('.smgp-course-section__count');
                if (countEl) {
                    if (sectionTotal === 0) {
                        countEl.style.display = 'none';
                    } else {
                        countEl.style.display = '';
                        countEl.textContent = sectionCompleted + '/' + sectionTotal;
                    }
                }
            });

            // Update rings from fractional progress.
            updateRings();

            // Update sidebar completed text (binary count).
            var sidebarCompleted = document.getElementById('smgp-sidebar-completed');
            if (sidebarCompleted) {
                sidebarCompleted.textContent = response.completedcount;
            }

            if (els.progressText) {
                els.progressText.textContent = response.completedcount + '/' + response.totalcount + ' Complete';
            }

        }).fail(function() {
            // Silently fail — progress sync is best-effort.
        });
    };

    /**
     * Update prev/next buttons state with item-level awareness.
     */
    var updateNavButtons = function() {
        if (els.prevBtn) {
            var canGoPrev = (state.totalItems > 0 && state.currentItemNum > 1) ||
                            (state.currentIndex > 0);
            els.prevBtn.disabled = !canGoPrev;
        }
        if (els.nextBtn) {
            var canGoNext = (state.totalItems > 0 && state.currentItemNum < state.totalItems) ||
                            (state.currentIndex < state.activities.length - 1);
            els.nextBtn.disabled = !canGoNext;
        }
    };

    /**
     * Initialize SVG progress rings.
     */
    var initProgressRings = function() {
        var headerRing = els.page.querySelector('.smgp-progress-ring__fill');
        if (headerRing) {
            var progress = parseInt(headerRing.getAttribute('data-progress'), 10) || 0;
            var circumference = 2 * Math.PI * 18;
            var offset = circumference - (progress / 100) * circumference;
            headerRing.style.strokeDasharray = circumference;
            headerRing.style.strokeDashoffset = offset;
        }

        var sectionRings = els.page.querySelectorAll('.smgp-section-progress-ring__fill');
        sectionRings.forEach(function(ring) {
            var prog = parseInt(ring.getAttribute('data-progress'), 10) || 0;
            var circ = 2 * Math.PI * 9;
            var off = circ - (prog / 100) * circ;
            ring.style.strokeDasharray = circ;
            ring.style.strokeDashoffset = off;
        });
    };

    /**
     * Recalculate and update all progress rings from state.activityProgress.
     * Uses fractional per-activity progress for smooth ring growth.
     */
    var _lastRingLog = {};
    var updateRings = function() {
        var activityRingCirc = 2 * Math.PI * 7; // ~43.98
        var caller = new Error().stack.split('\n')[2] || 'unknown';

        // -- Per-activity rings --
        state.activities.forEach(function(activity) {
            if (activity.modname === 'forum') {
                return;
            }
            if (activity.element.classList.contains('smgp-course-activity--complete')) {
                return;
            }
            var ring = activity.element.querySelector('.smgp-activity-ring__fill');
            if (!ring) {
                return;
            }
            var prog = state.activityProgress[activity.cmid] || 0;
            var offset = activityRingCirc - (prog * activityRingCirc);
            ring.style.strokeDasharray = activityRingCirc;
            ring.style.strokeDashoffset = offset;
        });

        // -- Section rings --
        var sections = els.page.querySelectorAll('.smgp-course-section');
        sections.forEach(function(section) {
            var sectionActivities = section.querySelectorAll('.smgp-course-activity');
            var sectionTotal = 0;
            var sectionProgressSum = 0;
            var sectionDetails = [];

            sectionActivities.forEach(function(actEl) {
                var cmid = parseInt(actEl.getAttribute('data-cmid'), 10);
                if (cmid && actEl.getAttribute('data-modname') !== 'forum') {
                    sectionTotal++;
                    var actProg = state.activityProgress[cmid] || 0;
                    sectionProgressSum += actProg;
                    sectionDetails.push(cmid + ':' + actProg.toFixed(4));
                }
            });

            var sectionRing = section.querySelector('.smgp-section-progress-ring');
            var sectionRingFill = section.querySelector('.smgp-section-progress-ring__fill');
            var sectionNum = section.getAttribute('data-section');
            if (sectionTotal === 0) {
                if (sectionRing) {
                    sectionRing.style.display = 'none';
                }
                var countEl = section.querySelector('.smgp-course-section__count');
                if (countEl) {
                    countEl.style.display = 'none';
                }
            } else if (sectionRingFill) {
                if (sectionRing) {
                    sectionRing.style.display = '';
                }
                var sectionPct = Math.round((sectionProgressSum / sectionTotal) * 100);
                var circ = 2 * Math.PI * 9;
                var offset = circ - (sectionPct / 100) * circ;

                // Log only when value changes.
                var logKey = 'section-' + sectionNum;
                if (_lastRingLog[logKey] !== sectionPct) {
                    console.log('[SMGP Rings] Section ' + sectionNum +
                        ': pct=' + sectionPct + '% (sum=' + sectionProgressSum.toFixed(4) +
                        ' / total=' + sectionTotal + ') activities=[' + sectionDetails.join(', ') + ']' +
                        ' caller=' + caller.trim());
                    _lastRingLog[logKey] = sectionPct;
                }

                sectionRingFill.setAttribute('data-progress', sectionPct);
                sectionRingFill.style.strokeDasharray = circ;
                sectionRingFill.style.strokeDashoffset = offset;
            }
        });

        // -- Header ring --
        var totalActivities = 0;
        var totalProgressSum = 0;
        state.activities.forEach(function(activity) {
            if (activity.modname === 'forum') {
                return;
            }
            totalActivities++;
            totalProgressSum += (state.activityProgress[activity.cmid] || 0);
        });

        var overallPct = totalActivities > 0 ? Math.round((totalProgressSum / totalActivities) * 100) : 0;

        // Log overall ring changes.
        if (_lastRingLog['overall'] !== overallPct) {
            console.log('[SMGP Rings] Overall: pct=' + overallPct + '% (sum=' + totalProgressSum.toFixed(4) +
                ' / count=' + totalActivities + ') caller=' + caller.trim());
            _lastRingLog['overall'] = overallPct;
        }

        var headerRing = els.page.querySelector('.smgp-progress-ring__fill');
        if (headerRing) {
            var circumference = 2 * Math.PI * 18;
            var ringOffset = circumference - (overallPct / 100) * circumference;
            headerRing.setAttribute('data-progress', overallPct);
            headerRing.style.strokeDasharray = circumference;
            headerRing.style.strokeDashoffset = ringOffset;
        }
        var headerText = els.page.querySelector('.smgp-progress-ring__text');
        if (headerText) {
            headerText.textContent = overallPct + '%';
        }
    };

    var bindSectionToggles = function() {
        var headers = els.page.querySelectorAll('.smgp-course-section__header');
        headers.forEach(function(header) {
            header.addEventListener('click', function() {
                var section = header.closest('.smgp-course-section');
                var isExpanded = section.classList.contains('smgp-course-section--expanded');
                section.classList.toggle('smgp-course-section--expanded');
                header.setAttribute('aria-expanded', !isExpanded);
            });
        });
    };

    var bindActivityClicks = function() {
        state.activities.forEach(function(activity, idx) {
            activity.element.addEventListener('click', function() {
                selectActivity(idx);
            });
        });
    };

    /**
     * Try to navigate within an iframe by clicking its own prev/next button.
     * Works for quiz (input[name="next"]/input[name="previous"]) and similar.
     * Returns true if a button was found and clicked, false otherwise.
     *
     * @param {string} direction 'next' or 'previous'
     * @return {boolean}
     */
    var navigateIframe = function(direction) {
        var iframe = els.contentArea ? els.contentArea.querySelector('.smgp-course-content__iframe') : null;
        if (!iframe) {
            return false;
        }
        try {
            var doc = iframe.contentDocument || iframe.contentWindow.document;
            if (!doc) {
                return false;
            }
            var selectors = direction === 'next'
                ? 'input[name="next"], button[name="next"], .mod_quiz-next-nav'
                : 'input[name="previous"], button[name="previous"], .mod_quiz-prev-nav';
            var btn = doc.querySelector(selectors);
            if (btn) {
                btn.click();
                return true;
            }
        } catch (e) {
            // Cross-origin or iframe not loaded — fall through.
        }
        return false;
    };

    /**
     * Bind prev/next navigation with within-activity item support.
     * For iframe activities (quiz), navigates via the iframe's own buttons.
     * For inline activities (book), navigates via loadActivityContent with itemnum.
     */
    var bindNavigation = function() {
        if (els.prevBtn) {
            els.prevBtn.addEventListener('click', function() {
                if (state.loading) {
                    return;
                }

                var activity = state.currentIndex >= 0 ? state.activities[state.currentIndex] : null;
                var iframe = els.contentArea ? els.contentArea.querySelector('.smgp-course-content__iframe') : null;

                // SCORM: navigate via postMessage.
                if (iframe && activity && activity.modname === 'scorm' && state.totalItems > 0) {
                    if (state.currentItemNum > 1) {
                        try {
                            iframe.contentWindow.postMessage({
                                type: 'scorm-navigate-to-slide',
                                cmid: activity.cmid,
                                slide: state.currentItemNum - 1
                            }, '*');
                        } catch (e) { /* cross-origin */ }
                        return;
                    }
                    // At first slide → go to previous activity.
                    if (state.currentIndex > 0) {
                        selectActivity(state.currentIndex - 1);
                    }
                    return;
                }

                // Iframe activities: try navigating within the iframe first.
                if (iframe && state.totalItems > 0) {
                    if (navigateIframe('previous')) {
                        return;
                    }
                    // No prev button in iframe (first page) → go to previous activity.
                    if (state.currentIndex > 0) {
                        selectActivity(state.currentIndex - 1);
                    }
                    return;
                }

                // Inline activities with items (book chapters).
                if (state.totalItems > 0 && state.currentItemNum > 1) {
                    loadActivityContent(activity.cmid, state.currentItemNum - 1);
                } else if (state.currentIndex > 0) {
                    selectActivity(state.currentIndex - 1);
                }
            });
        }
        if (els.nextBtn) {
            els.nextBtn.addEventListener('click', function() {
                if (state.loading) {
                    return;
                }

                var activity = state.currentIndex >= 0 ? state.activities[state.currentIndex] : null;
                var iframe = els.contentArea ? els.contentArea.querySelector('.smgp-course-content__iframe') : null;

                // SCORM: navigate via postMessage.
                if (iframe && activity && activity.modname === 'scorm' && state.totalItems > 0) {
                    if (state.currentItemNum < state.totalItems) {
                        try {
                            iframe.contentWindow.postMessage({
                                type: 'scorm-navigate-to-slide',
                                cmid: activity.cmid,
                                slide: state.currentItemNum + 1
                            }, '*');
                        } catch (e) { /* cross-origin */ }
                        return;
                    }
                    // At last slide → go to next activity.
                    if (state.currentIndex < state.activities.length - 1) {
                        selectActivity(state.currentIndex + 1);
                    }
                    return;
                }

                // Iframe activities: try navigating within the iframe first.
                if (iframe && state.totalItems > 0) {
                    if (navigateIframe('next')) {
                        return;
                    }
                    // No next button in iframe (last page) → go to next activity.
                    if (state.currentIndex < state.activities.length - 1) {
                        selectActivity(state.currentIndex + 1);
                    }
                    return;
                }

                // Inline activities with items (book chapters).
                if (state.totalItems > 0 && state.currentItemNum < state.totalItems) {
                    loadActivityContent(activity.cmid, state.currentItemNum + 1);
                } else if (state.currentIndex < state.activities.length - 1) {
                    selectActivity(state.currentIndex + 1);
                }
            });
        }
    };

    /**
     * Bind floating focus mode button.
     * Focus mode hides header, activity bar, sidebar, sidebar toggle, and tabs.
     * Only shows content area + nav bar.
     */
    var bindFocusMode = function() {
        if (!els.focusBtn || !els.page) {
            return;
        }

        els.focusBtn.addEventListener('click', function() {
            state.focusMode = !state.focusMode;
            els.page.classList.toggle('smgp-course-page--focus', state.focusMode);
            document.body.classList.toggle('smgp-course-focus-active', state.focusMode);

            var icon = els.focusBtn.querySelector('i');
            if (icon) {
                icon.className = state.focusMode ? 'icon-minimize' : 'icon-maximize';
            }
        });
    };

    var bindTabs = function() {
        var tabs = els.page.querySelectorAll('.smgp-course-tabs__tab');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var tabName = tab.getAttribute('data-tab');

                tabs.forEach(function(t) {
                    t.classList.remove('smgp-course-tabs__tab--active');
                });
                tab.classList.add('smgp-course-tabs__tab--active');

                var panels = els.page.querySelectorAll('.smgp-course-tabs__panel');
                panels.forEach(function(p) {
                    p.classList.remove('smgp-course-tabs__panel--active');
                });
                var panel = els.page.querySelector('[data-panel="' + tabName + '"]');
                if (panel) {
                    panel.classList.add('smgp-course-tabs__panel--active');
                }

                if (tabName === 'comments' && !state.commentsLoaded) {
                    loadComments();
                }
            });
        });
    };

    /**
     * Load the course comments into the comments tab.
     */
    var loadComments = function() {
        state.commentsLoaded = true;

        var commentsSource = document.getElementById('smgp-comments-source');
        var target = document.getElementById('smgp-course-page-comments-target');

        if (commentsSource && target) {
            commentsSource.style.display = '';
            commentsSource.removeAttribute('style');
            target.appendChild(commentsSource);

            require(['local_sm_graphics_plugin/course_comments'], function(CourseComments) {
                CourseCommentsModule = CourseComments;
                CourseComments.init();

                // Update comments with the current activity context immediately.
                updateCommentsContext();
            });
        } else if (target) {
            target.innerHTML = '<div class="smgp-course-tabs__empty">'
                + '<i class="icon-message-circle"></i>'
                + '<p>Comments are not available.</p></div>';
        }
    };

    /**
     * Show/hide the timeline progress bar based on whether activity has countable items.
     * Only displayed for activities with slides/chapters/pages/questions/timestamp.
     */
    var updateTimelineVisibility = function() {
        if (!els.progressBar) {
            return;
        }
        var hasTimeline = state.totalItems > 0 || state.videoTimerActive;
        els.progressBar.style.display = hasTimeline ? '' : 'none';
    };

    /**
     * Update the progress bar cursor position based on current item within activity.
     * For items (slides/chapters/pages/questions): position = currentItem / totalItems.
     * For video: position updated via timeupdate events.
     */
    var updateProgressCursor = function() {
        if (!els.progressCursor || !els.progressFill) {
            return;
        }

        if (state.totalItems > 0) {
            // Cursor at current position.
            var cursorPct = state.currentItemNum > 0
                ? (state.currentItemNum / state.totalItems) * 100 : 0;
            els.progressCursor.style.left = cursorPct + '%';

            // Fill to furthest reached (never shrinks).
            var fillPct = state.furthestItem > 0
                ? (state.furthestItem / state.totalItems) * 100 : 0;
            els.progressFill.style.width = fillPct + '%';
        } else {
            els.progressCursor.style.left = '0%';
            els.progressFill.style.width = '0%';
        }
    };

    /**
     * Navigate an iframe to a specific page number (0-based).
     * Looks for attempt.php or player.php in the iframe's current URL and sets the page param.
     *
     * @param {number} targetPage 0-based page number.
     * @return {boolean} true if navigation was initiated.
     */
    var navigateIframePage = function(targetPage) {
        var iframe = els.contentArea ? els.contentArea.querySelector('.smgp-course-content__iframe') : null;
        if (!iframe) {
            return false;
        }
        try {
            var currentHref = iframe.contentWindow.location.href;
            // Only works for quiz attempt pages and SCORM player pages.
            if (currentHref.indexOf('attempt.php') === -1 && currentHref.indexOf('player.php') === -1) {
                return false;
            }
            var url = new URL(currentHref);
            url.searchParams.set('page', targetPage);
            iframe.contentWindow.location.href = url.toString();
            return true;
        } catch (e) {
            // Cross-origin or iframe not yet loaded.
            return false;
        }
    };

    /**
     * Execute a seek/jump based on a percentage position on the progress bar.
     * Handles video seek, inline item navigation, and iframe page navigation.
     *
     * @param {number} pct Click/drag position as fraction 0.0–1.0.
     */
    var seekToPosition = function(pct) {
        pct = Math.max(0, Math.min(pct, 1));

        if (state.videoTimerActive) {
            // Video: seek to timestamp.
            var video = els.contentArea ? els.contentArea.querySelector('video') : null;
            if (video && video.duration) {
                video.currentTime = pct * video.duration;
            }
            return;
        }

        if (state.totalItems <= 0) {
            return;
        }

        var iframe = els.contentArea ? els.contentArea.querySelector('.smgp-course-content__iframe') : null;
        var activity = state.currentIndex >= 0 ? state.activities[state.currentIndex] : null;

        if (iframe && activity && activity.modname === 'scorm') {
            // SCORM: send postMessage to navigate to target slide.
            var targetSlide = Math.max(1, Math.min(Math.round(pct * state.totalItems), state.totalItems));
            try {
                iframe.contentWindow.postMessage({
                    type: 'scorm-navigate-to-slide',
                    cmid: activity.cmid,
                    slide: targetSlide
                }, '*');
            } catch (e) { /* cross-origin */ }
        } else if (iframe && state.totalPages > 0) {
            // Iframe with pages (quiz): navigate to target page (0-based).
            var targetPage = Math.max(0, Math.min(Math.floor(pct * state.totalPages), state.totalPages - 1));
            navigateIframePage(targetPage);
        } else if (!iframe) {
            // Inline activity (book chapters, slides): load specific item.
            var itemNum = Math.max(1, Math.min(Math.round(pct * state.totalItems), state.totalItems));
            if (activity) {
                loadActivityContent(activity.cmid, itemNum);
            }
        }
    };

    /**
     * Bind click and drag events on the progress bar to jump/scrub within activity.
     */
    var bindProgressBar = function() {
        if (!els.progressBar) {
            return;
        }

        // Hidden by default until an activity with items is loaded.
        els.progressBar.style.display = 'none';

        var track = els.progressBar.querySelector('.smgp-course-progress-bar__track');
        if (!track) {
            return;
        }

        var dragging = false;
        var dragDebounce = null;

        /** Get percentage (0–1) from a mouse/touch event relative to the track. */
        var getPct = function(e) {
            var rect = track.getBoundingClientRect();
            var clientX = e.touches ? e.touches[0].clientX : e.clientX;
            return Math.max(0, Math.min((clientX - rect.left) / rect.width, 1));
        };

        /** Update cursor position visually during drag (no navigation yet). */
        var updateCursorVisual = function(pct) {
            if (els.progressCursor) {
                els.progressCursor.style.left = (pct * 100) + '%';
                els.progressCursor.style.transition = 'none';
            }
        };

        /** Restore CSS transition on cursor after drag ends. */
        var restoreCursorTransition = function() {
            if (els.progressCursor) {
                els.progressCursor.style.transition = '';
            }
        };

        // --- Click: immediate jump ---
        track.addEventListener('click', function(e) {
            if (state.loading || dragging) {
                return;
            }
            seekToPosition(getPct(e));
        });

        // --- Drag: mousedown on track or cursor starts drag ---
        var startDrag = function(e) {
            if (state.loading) {
                return;
            }
            // Only primary mouse button or touch.
            if (e.type === 'mousedown' && e.button !== 0) {
                return;
            }
            dragging = true;
            e.preventDefault();
            updateCursorVisual(getPct(e));
        };

        var moveDrag = function(e) {
            if (!dragging) {
                return;
            }
            e.preventDefault();
            var pct = getPct(e);
            updateCursorVisual(pct);

            // For video, seek in real time while dragging.
            if (state.videoTimerActive) {
                var video = els.contentArea ? els.contentArea.querySelector('video') : null;
                if (video && video.duration) {
                    video.currentTime = pct * video.duration;
                }
            }
        };

        var endDrag = function(e) {
            if (!dragging) {
                return;
            }
            dragging = false;
            restoreCursorTransition();

            // Determine final position.
            var pct;
            if (e.type === 'touchend' || e.type === 'touchcancel') {
                // For touchend, use last known position from cursor style.
                pct = parseFloat(els.progressCursor.style.left) / 100 || 0;
            } else {
                pct = getPct(e);
            }

            // Navigate to final position (debounced to avoid double-fire with click).
            clearTimeout(dragDebounce);
            dragDebounce = setTimeout(function() {
                seekToPosition(pct);
            }, 50);
        };

        // Mouse events.
        track.addEventListener('mousedown', startDrag);
        if (els.progressCursor) {
            els.progressCursor.addEventListener('mousedown', startDrag);
        }
        document.addEventListener('mousemove', moveDrag);
        document.addEventListener('mouseup', endDrag);

        // Touch events.
        track.addEventListener('touchstart', startDrag, { passive: false });
        if (els.progressCursor) {
            els.progressCursor.addEventListener('touchstart', startDrag, { passive: false });
        }
        document.addEventListener('touchmove', moveDrag, { passive: false });
        document.addEventListener('touchend', endDrag);
        document.addEventListener('touchcancel', endDrag);
    };

    /**
     * Listen for real-time SCORM progress messages from the tracking IIFE
     * injected into the SCORM player iframe. Direct handler — SM_Estratoos
     * is silenced via smgp_embed guard, so no debounce needed.
     */
    var bindScormProgress = function() {
        window.addEventListener('message', function(event) {
            // Debug: log ALL incoming postMessages (before filtering).
            if (typeof window._slDebugLog === 'function' && event.data && typeof event.data === 'object') {
                var _d = event.data;
                window._slDebugLog({
                    timestamp: Date.now(),
                    level: 'debug',
                    component: 'course_page',
                    message: '[SCORM postMessage] type=' + (_d.type || '?') +
                        ' source=' + (_d.source || '?') +
                        ' currentSlide=' + (_d.currentSlide || '?') +
                        ' totalSlides=' + (_d.totalSlides || '?') +
                        ' furthestSlide=' + (_d.furthestSlide || '?'),
                    source: 'frontend'
                });
            }

            if (!event.data || event.data.type !== 'scorm-progress') {
                // Debug: log rejected messages.
                if (typeof window._slDebugLog === 'function' && event.data && typeof event.data === 'object' && event.data.type) {
                    window._slDebugLog({
                        timestamp: Date.now(),
                        level: 'debug',
                        component: 'course_page',
                        message: '[SCORM postMessage REJECTED] type=' + (event.data.type || '?') +
                            ' source=' + (event.data.source || '?') +
                            ' (expected type=scorm-progress)',
                        source: 'frontend'
                    });
                }
                return;
            }

            var data = event.data;
            var slide = parseInt(data.currentSlide, 10);
            if (isNaN(slide) || slide < 1) {
                return;
            }

            // Verify this message is for the currently active SCORM activity.
            if (state.currentIndex < 0) {
                return;
            }
            var activity = state.activities[state.currentIndex];
            if (!activity || activity.modname !== 'scorm') {
                return;
            }

            handleScormProgressMessage(data);
        }, false);
    };

    /**
     * Handle a validated SCORM progress message. Updates state, counters,
     * progress bar, progress rings, and comments context.
     */
    var handleScormProgressMessage = function(data) {
        var slide = parseInt(data.currentSlide, 10);
        var total = parseInt(data.totalSlides, 10);
        var furthest = parseInt(data.furthestSlide, 10);

        if (isNaN(slide) || slide < 1) {
            return;
        }

        // Debug: log accepted SCORM slide change with full context.
        if (typeof window._slDebugLog === 'function') {
            var _prevSlide = state.currentItemNum || 0;
            var _actName = '';
            var _actCmid = 0;
            if (state.currentIndex >= 0 && state.activities[state.currentIndex]) {
                _actName = state.activities[state.currentIndex].name;
                _actCmid = state.activities[state.currentIndex].cmid;
            }
            window._slDebugLog({
                timestamp: Date.now(),
                level: 'info',
                component: 'course_page',
                message: '[SCORM] Slide ' + _prevSlide + '→' + slide + '/' + (total || '?') +
                    ' furthest=' + (furthest || slide) +
                    ' (Activity: "' + _actName + '" cmid=' + _actCmid + ')',
                source: 'frontend'
            });
        }

        // Update item-level state.
        state.currentItemNum = slide;
        if (!isNaN(total) && total > 0) {
            state.totalItems = total;
        }
        if (!isNaN(furthest) && furthest > state.furthestItem) {
            state.furthestItem = furthest;
        }
        if (slide > state.furthestItem) {
            state.furthestItem = slide;
        }

        state.counterLabel = 'Slide';

        // Update UI.
        updateItemCounter(state.totalItems, state.currentItemNum, state.counterLabel);
        updateProgressCursor();
        updateNavButtons();
        updateTimelineVisibility();

        // Update activity progress ring.
        if (state.currentIndex >= 0 && state.totalItems > 0) {
            var cmid = state.activities[state.currentIndex].cmid;
            var progressFraction = state.furthestItem / state.totalItems;
            var oldProg = state.activityProgress[cmid] || 0;
            if (progressFraction > oldProg) {
                console.log('[SMGP Progress] SCORM postMessage: cmid=' + cmid +
                    ' furthest=' + state.furthestItem + '/' + state.totalItems +
                    ' fraction=' + progressFraction.toFixed(4) +
                    ' old=' + oldProg.toFixed(4));
                state.activityProgress[cmid] = progressFraction;
                updateRings();
            }
        }

        // Update comments with current position.
        updateCommentsContext();
    };

    /**
     * Bind listener for non-SCORM activity progress postMessages.
     * Handles quiz (question), book (chapter), lesson (page), video (second),
     * and whole-activity (1/1) position updates from injected tracking JS.
     */
    var bindActivityProgress = function() {
        var labelMap = {
            'quiz': Str.get_string('course_page_counter_question', 'local_sm_graphics_plugin'),
            'book': Str.get_string('course_page_counter_chapter', 'local_sm_graphics_plugin'),
            'lesson': Str.get_string('course_page_counter_page', 'local_sm_graphics_plugin'),
            'video': Str.get_string('course_page_counter_video', 'local_sm_graphics_plugin'),
            'page': Str.get_string('course_page_counter_page', 'local_sm_graphics_plugin'),
            'scorm': Str.get_string('course_page_counter_slide', 'local_sm_graphics_plugin')
        };
        // Resolve all promises into a simple map.
        var resolvedLabels = {};
        Object.keys(labelMap).forEach(function(key) {
            if (labelMap[key] && typeof labelMap[key].then === 'function') {
                labelMap[key].then(function(val) { resolvedLabels[key] = val; });
            } else {
                resolvedLabels[key] = labelMap[key];
            }
        });

        window.addEventListener('message', function(event) {
            if (!event.data || event.data.type !== 'activity-progress') {
                return;
            }

            var data = event.data;
            var cmid = parseInt(data.cmid, 10);
            if (!cmid || state.currentIndex < 0) {
                return;
            }

            // Verify this message is for the currently active activity.
            var activity = state.activities[state.currentIndex];
            if (!activity || activity.cmid !== cmid) {
                return;
            }

            var current = parseInt(data.currentPosition, 10) || 0;
            var total = parseInt(data.totalPositions, 10) || 0;
            var furthest = parseInt(data.furthestPosition, 10) || current;
            var actType = data.activityType || '';

            // Update state.
            if (total > 0) {
                state.totalItems = total;
                state.currentItemNum = current;
                if (furthest > state.furthestItem) {
                    state.furthestItem = furthest;
                }
                state.counterLabel = resolvedLabels[actType] || resolvedLabels['page'] || 'Item';

                // Mark that we have real-time tracking — reduce polling.
                state.hasRealtimeTracking = true;

                // Update UI.
                updateItemCounter();
                updateProgressCursor();
                updateNavButtons();
            }

            // Update fractional progress for the activity ring.
            if (total > 0 && furthest > 0) {
                var progressFraction = furthest / total;
                var oldProg = state.activityProgress[cmid] || 0;
                if (progressFraction > oldProg) {
                    state.activityProgress[cmid] = progressFraction;
                    updateRings();
                }
            }

            // Mark complete if status says so.
            if (data.status === 'completed' && activity) {
                if (!state.previousCompletionMap || !state.previousCompletionMap[cmid]) {
                    Ajax.call([{
                        methodname: 'local_sm_graphics_plugin_mark_activity_complete',
                        args: {cmid: cmid}
                    }]);
                }
            }

            // Update comments with current position.
            updateCommentsContext();
        }, false);
    };

    /**
     * Navigate to a specific position within an activity. Used for comment tag navigation.
     * Switches to the target activity if needed, then seeks to the position.
     *
     * @param {number} cmid Course module ID of the target activity.
     * @param {number|string} position Position index (slide number, page, timestamp).
     * @param {string} type Activity type hint ('scorm', 'quiz', 'book', 'video').
     */
    var navigateToActivityPosition = function(cmid, position, type) {
        cmid = parseInt(cmid, 10);
        position = parseInt(position, 10) || 0;

        // Find the activity index by cmid.
        var targetIdx = -1;
        for (var i = 0; i < state.activities.length; i++) {
            if (state.activities[i].cmid === cmid) {
                targetIdx = i;
                break;
            }
        }

        if (targetIdx < 0) {
            return;
        }

        var isSameActivity = (targetIdx === state.currentIndex);

        if (isSameActivity) {
            // Already viewing this activity — just navigate within it.
            navigateWithinActivity(position, type);
        } else {
            // Switch to the target activity first, then navigate after it loads.
            state.pendingTagNavigation = { position: position, type: type };
            selectActivity(targetIdx);

            // For SCORM, wait for the iframe to load and the tracking IIFE to initialize.
            var activity = state.activities[targetIdx];
            if (activity && activity.modname === 'scorm') {
                setTimeout(function() {
                    if (state.pendingTagNavigation) {
                        navigateWithinActivity(
                            state.pendingTagNavigation.position,
                            state.pendingTagNavigation.type
                        );
                        state.pendingTagNavigation = null;
                    }
                }, 2500);
            } else {
                // For other types, navigate after a short delay for content to render.
                setTimeout(function() {
                    if (state.pendingTagNavigation) {
                        navigateWithinActivity(
                            state.pendingTagNavigation.position,
                            state.pendingTagNavigation.type
                        );
                        state.pendingTagNavigation = null;
                    }
                }, 500);
            }
        }
    };

    /**
     * Navigate within the currently loaded activity to a specific position.
     *
     * @param {number} position Target position (slide, page, timestamp).
     * @param {string} type Activity type hint.
     */
    var navigateWithinActivity = function(position, type) {
        if (state.currentIndex < 0) {
            return;
        }
        var activity = state.activities[state.currentIndex];
        if (!activity) {
            return;
        }

        var iframe = els.contentArea ? els.contentArea.querySelector('.smgp-course-content__iframe') : null;

        if (activity.modname === 'scorm' && iframe) {
            // SCORM: send postMessage to the tracking IIFE inside the iframe.
            try {
                iframe.contentWindow.postMessage({
                    type: 'scorm-navigate-to-slide',
                    cmid: activity.cmid,
                    slide: position
                }, '*');
            } catch (e) { /* cross-origin */ }
        } else if (activity.modname === 'quiz' && iframe) {
            // Quiz: navigate iframe to target page (0-based).
            navigateIframePage(position - 1);
        } else if (activity.modname === 'book') {
            // Book: load specific chapter.
            loadActivityContent(activity.cmid, position);
        } else if (state.videoTimerActive) {
            // Video: seek to timestamp (position is in seconds).
            var video = els.contentArea ? els.contentArea.querySelector('video') : null;
            if (video) {
                video.currentTime = position;
            }
        }
    };

    var bindSidebarToggle = function() {
        if (!els.sidebarToggle || !els.sidebar) {
            return;
        }

        els.sidebarToggle.addEventListener('click', function() {
            state.sidebarCollapsed = !state.sidebarCollapsed;
            els.sidebar.classList.toggle('smgp-course-sidebar--collapsed', state.sidebarCollapsed);
            els.page.classList.toggle('smgp-course-page--sidebar-collapsed', state.sidebarCollapsed);

            var icon = els.sidebarToggle.querySelector('i');
            if (icon) {
                icon.className = state.sidebarCollapsed ? 'icon-chevron-left' : 'icon-chevron-right';
            }
        });
    };

    return {
        init: init,
        getVideoTimestamp: getVideoTimestamp,
        navigateToActivityPosition: navigateToActivityPosition
    };
});

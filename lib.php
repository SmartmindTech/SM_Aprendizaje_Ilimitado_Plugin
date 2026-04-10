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
 * SM Graphic Layer Plugin - library functions.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Read the plugin's .env file as a flat key→value array.
 *
 * Used by the SharePoint client and the SMTP/Azure helpers to pick up
 * credentials that aren't worth exposing in Moodle's admin UI. Keys are
 * normalised to lowercase so call sites can use either casing.
 *
 * Result is cached for the request, so repeated calls are free.
 *
 * @return array
 */
function local_sm_graphics_plugin_load_config(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $path = __DIR__ . '/.env';
    if (!file_exists($path)) {
        return $cache;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = strtolower(trim(substr($line, 0, $pos)));
        $value = trim(substr($line, $pos + 1));
        $cache[$key] = $value;
    }
    return $cache;
}

/**
 * Build a URL pointing at a Vue SPA hash route.
 *
 * All user-facing navigation in testnuxt lives inside the SPA shell at
 * `pages/spa.php`; the concrete page is chosen by the hash fragment.
 *
 * @param string $hashroute  Route without leading slash, e.g. "courses/42/landing".
 * @return moodle_url
 */
function local_sm_graphics_plugin_spa_url(string $hashroute = ''): moodle_url {
    $url = new moodle_url('/local/sm_graphics_plugin/pages/spa.php');
    if ($hashroute !== '') {
        $url->set_anchor('/' . ltrim($hashroute, '/'));
    }
    return $url;
}

/**
 * Enrol a user into a course using the manual enrolment plugin.
 *
 * @param int $userid  The ID of the user to enrol.
 * @param int $courseid The ID of the course.
 * @param int $roleid  The role to assign (default 5 = student).
 * @return bool True on success, false if manual enrolment is unavailable.
 */
function local_sm_graphics_plugin_enroll_user(int $userid, int $courseid, int $roleid = 5): bool {
    global $DB;

    $enrol = enrol_get_plugin('manual');
    if (!$enrol) {
        return false;
    }

    $instances = $DB->get_records('enrol', [
        'courseid' => $courseid,
        'enrol'    => 'manual',
        'status'   => ENROL_INSTANCE_ENABLED,
    ]);
    if (empty($instances)) {
        return false;
    }

    $enrol->enrol_user(reset($instances), $userid, $roleid);
    return true;
}

/**
 * Rename core navigation nodes to match SmartMind branding.
 *
 * Also injects company-limit fields into the IOMAD company edit form
 * and blocks access to user-creation pages when the limit is reached.
 *
 * Moodle calls this callback on every page load.
 *
 * @param global_navigation $navigation The global navigation object.
 */
function local_sm_graphics_plugin_extend_navigation(global_navigation $navigation) {
    global $PAGE, $USER, $DB;

    $renames = [
        'home'      => get_string('nav_home',      'local_sm_graphics_plugin'),
        'myhome'    => get_string('nav_dashboard',  'local_sm_graphics_plugin'),
        'mycourses' => get_string('nav_mycourses',  'local_sm_graphics_plugin'),
    ];

    foreach ($renames as $key => $label) {
        $node = $navigation->find($key, null);
        if ($node) {
            $node->text = $label;
        }
    }

    // ── Embed mode ─────────────────────────────────────────────────────
    // When an activity is loaded inside the course player iframe
    // (smgp_embed=1), force Moodle to use the minimal 'embedded' layout
    // so it never renders navbar, drawers, or footer in the first place.
    // This replaces the old approach of rendering full chrome and hiding
    // it with CSS — faster, cleaner, no FOUC.
    $isembed = !empty($_GET['smgp_embed']) || !empty($_COOKIE['smgp_embed']);
    if ($isembed) {
        try {
            $PAGE->set_pagelayout('embedded');
        } catch (\Throwable $e) {
            // $PAGE may not be ready yet on rare code paths — the CSS
            // fallback (body.smgp-embedded) in before_standard_top_of_body_html
            // handles those cases.
        }
    }

    // Detect current script path reliably (works even before $PAGE->url is set).
    $scriptpath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';

    // ── SPA redirects ─────────────────────────────────────────────────────
    // Redirect Moodle core pages and plugin pages to the Vue SPA.
    $spapage = '/local/sm_graphics_plugin/pages/spa.php';

    // Enrolment page → SPA course landing.
    if (strpos($scriptpath, '/enrol/index.php') !== false) {
        $courseid = optional_param('id', 0, PARAM_INT);
        if ($courseid && isloggedin() && !isguestuser()) {
            redirect(new moodle_url($spapage, [], 'courses/' . $courseid . '/landing'));
        }
    }

    // Personal Space (/my/) → SPA dashboard (students/managers) or
    // admin settings (site admins).
    if (substr($scriptpath, -13) === '/my/index.php' || $scriptpath === '/my/') {
        if (isloggedin() && !isguestuser()) {
            if (is_siteadmin()) {
                redirect(new moodle_url($spapage, [], 'admin/settings'));
            }
            redirect(new moodle_url($spapage, [], 'dashboard'));
        }
    }

    // Course catalogue (/?redirect=0 or site home) → SPA catalogue.
    if ($scriptpath === '/index.php' && optional_param('redirect', -1, PARAM_INT) === 0) {
        if (isloggedin() && !isguestuser()) {
            redirect(new moodle_url($spapage, [], 'catalogue'));
        }
    }

    // IOMAD dashboard → SPA admin IOMAD dashboard.
    // Site admins are intentionally allowed through to the real Moodle
    // admin pages — without this exception, clicking "Site administration"
    // (or our plugin's own settings link) bounces back into the SPA in a
    // loop and the admin can never reach /admin/settings.php.
    if (strpos($scriptpath, 'iomad_company_admin/index.php') !== false && isloggedin() && !isguestuser()) {
        redirect(new moodle_url($spapage, [], 'admin/iomad-dashboard'));
    }

    // IOMAD company edit → SPA company editor (managers only).
    if (strpos($scriptpath, 'company_edit_form.php') !== false && !is_siteadmin()) {
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if ($managerrec) {
            $companyid = optional_param('companyid', 0, PARAM_INT);
            redirect(new moodle_url($spapage, [], 'admin/company-editor' . ($companyid ? '?companyid=' . $companyid : '')));
        }
    }

    // IOMAD user creation → SPA create user (managers only).
    if (strpos($scriptpath, 'company_user_create_form.php') !== false && !is_siteadmin()) {
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if ($managerrec) {
            redirect(new moodle_url($spapage, [], 'admin/create-user'));
        }
    }

    // Catalogue category dropdown is now handled by course_form_handler.php hook
    // (injected into the General section via insertElementBefore).

    // Inject "Max students" field into IOMAD company edit form.
    if (strpos($scriptpath, 'company_edit_form.php') !== false) {
        $companyid = optional_param('companyid', 0, PARAM_INT);
        $currentlimit = $companyid ? local_sm_graphics_plugin_get_company_limit($companyid) : 0;
        $label    = get_string('companylimits_field_label', 'local_sm_graphics_plugin');
        $required = get_string('required');
        $PAGE->requires->js_amd_inline("
            require(['jquery'], function($) {
                $(document).ready(function() {
                    // Insert right after the 'code' field (last main field before address).
                    var codeRow = $('#id_code').closest('.fitem, .form-group, .row');
                    if (!codeRow.length) {
                        // Fallback: before the address field.
                        codeRow = $('#id_address').closest('.fitem, .form-group, .row');
                    }
                    var field = '<div class=\"fitem row form-group\" id=\"fitem_smgp_maxstudents\">' +
                        '<div class=\"col-md-3 col-form-label d-flex pb-0 pr-md-0\">' +
                        '<label for=\"id_smgp_maxstudents\">" . addslashes_js($label) . " <abbr class=\"initialism text-danger\" title=\"" . addslashes_js($required) . "\"><i class=\"icon fa fa-exclamation-circle text-danger\" aria-hidden=\"true\"></i></abbr></label>' +
                        '</div>' +
                        '<div class=\"col-md-9 form-inline align-items-start felement\" data-fieldtype=\"text\">' +
                        '<input type=\"number\" name=\"smgp_maxstudents\" id=\"id_smgp_maxstudents\" value=\"" . $currentlimit . "\" min=\"1\" required class=\"form-control\" style=\"max-width:200px\">' +
                        '</div></div>';
                    if (codeRow.length) {
                        codeRow.after(field);
                    }
                });
            });
        ");
    }

    // Block access to user creation when company student limit is reached.
    if (strpos($scriptpath, 'company_user_create_form.php') !== false) {
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if ($managerrec && local_sm_graphics_plugin_is_company_limit_reached($managerrec->companyid)) {
            redirect(
                local_sm_graphics_plugin_spa_url('management/users'),
                get_string('usermgmt_limit_reached', 'local_sm_graphics_plugin'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
    }

    // Block CSV upload if rows in file would exceed the company limit.
    if (strpos($scriptpath, 'uploaduser.php') !== false) {
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);

        if ($managerrec) {
            $companyid = $managerrec->companyid;
            $limit = local_sm_graphics_plugin_get_company_limit($companyid);

            if ($limit > 0) {
                $currentcount = local_sm_graphics_plugin_get_company_student_count($companyid);

                // Already at limit — block access entirely.
                if ($currentcount >= $limit) {
                    redirect(
                        local_sm_graphics_plugin_spa_url('management/users'),
                        get_string('usermgmt_limit_reached', 'local_sm_graphics_plugin'),
                        null,
                        \core\output\notification::NOTIFY_WARNING
                    );
                }

                // CSV submitted (form2 POST) — check if rows would exceed limit.
                $readcount = optional_param('readcount', 0, PARAM_INT);
                $iid = optional_param('iid', 0, PARAM_INT);
                if ($iid && $readcount && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                    // readcount includes the header row — subtract 1 for actual users.
                    $usercount = max(0, $readcount - 1);
                    $remaining = $limit - $currentcount;
                    if ($usercount > $remaining) {
                        redirect(
                            local_sm_graphics_plugin_spa_url('management/users'),
                            get_string('usermgmt_upload_exceeds', 'local_sm_graphics_plugin', (object) [
                                'csvcount'  => $usercount,
                                'remaining' => $remaining,
                                'limit'     => $limit,
                            ]),
                            null,
                            \core\output\notification::NOTIFY_WARNING
                        );
                    }
                }
            }
        }
    }
}

/**
 * Inject Bootstrap Icons CDN link on course-view pages.
 *
 * @return string HTML to inject before body content.
 */
function local_sm_graphics_plugin_before_standard_top_of_body_html(): string {
    global $PAGE;

    $pagetype = $PAGE->pagetype ?? '';

    // Redirect course-view pages to the landing page, unless:
    // - entering from landing page (smgp_enter=1)
    // - actual script is not /course/view.php (e.g. backup/view.php sets course-view-* pagetype too)
    $scriptpath = $_SERVER['SCRIPT_NAME'] ?? '';
    $isRealCourseView = strpos($pagetype, 'course-view-') === 0
        && (strpos($scriptpath, '/course/view.php') !== false);

    if ($isRealCourseView && empty($_GET['smgp_enter'])) {
        $course = $PAGE->course;
        if ($course->id != SITEID && isloggedin() && !isguestuser()) {
            // ALL users see the landing page first (course hub/dashboard).
            // The landing page has Start/Continue button that links to the player with smgp_enter=1.
            redirect(new moodle_url('/local/sm_graphics_plugin/pages/spa.php', [], 'courses/' . $course->id . '/landing'));
        }
    }

    // Track course browsing on the enrolment page (non-enrolled users).
    if ($pagetype === 'enrol-index' && isloggedin() && !isguestuser()) {
        global $DB, $USER;
        $course = $PAGE->course;
        if ($course->id != SITEID) {
            $coursecontext = context_course::instance($course->id);
            $isenrolled = is_enrolled($coursecontext, $USER->id, '', true);
            if (!$isenrolled) {
                $existing = $DB->get_record('local_smgp_course_browsing', [
                    'userid' => $USER->id,
                    'courseid' => $course->id,
                ]);
                if ($existing) {
                    $existing->timeaccess = time();
                    $DB->update_record('local_smgp_course_browsing', $existing);
                } else {
                    $DB->insert_record('local_smgp_course_browsing', (object) [
                        'userid' => $USER->id,
                        'courseid' => $course->id,
                        'timeaccess' => time(),
                    ]);
                }
            }
        }
    }

    // Wrap IOMAD admin pages in SmartMind styles for company managers and site admins.
    if (strpos($scriptpath, '/blocks/iomad_company_admin/') !== false && isloggedin()) {
        global $USER;
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if ($managerrec || is_siteadmin()) {
            return '<style id="smgp-iomad-wrap">'
                . '.iomad_dashboard_nav, .tabtree, .nav-tabs { display: none !important; }'
                . '#page, .main-inner, #page-content, #region-main-box, #region-main,'
                . '.smartmind-page-banner, .smartmind-page-banner__content {'
                . '  max-width: 100% !important; width: 100% !important; box-sizing: border-box !important;'
                . '}'
                . '.main-inner { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }'
                . '#page-content, #region-main-box, #region-main { padding-left: 0 !important; padding-right: 0 !important; }'
                . '</style>';
        }
    }

    // Embed mode: when an activity is loaded inside our course page iframe,
    // hide all Moodle chrome via theme_smartmind/scss/smartmind/_embed.scss.
    // Detected by smgp_embed=1 GET param OR cookie (cookie persists across
    // internal iframe navigations like wiki create.php).
    //
    // The body class can't be added via $PAGE->add_body_class() because every
    // hook Moodle exposes runs AFTER $OUTPUT->header() has frozen body
    // attributes. We add it client-side via a tiny inline script that runs
    // synchronously as the very first node inside <body>, before any visible
    // content paints — no FOUC.
    //
    // To change how the iframe content sits, edit _embed.scss — not this file.
    $isembed = !empty($_GET['smgp_embed']) || !empty($_COOKIE['smgp_embed']);

    if (!empty($_GET['smgp_embed']) && empty($_COOKIE['smgp_embed'])) {
        setcookie('smgp_embed', '1', 0, '/mod/'); // Scoped to /mod/ paths.
    }

    $scriptEmbed = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!empty($_COOKIE['smgp_embed']) && strpos($scriptEmbed, '/mod/') === false) {
        setcookie('smgp_embed', '', time() - 3600, '/mod/');
        $isembed = false;
    }

    if ($isembed) {
        // Body class + embed styles injected via before_footer hook.
        return '<script>document.body.classList.add("smgp-embedded");</script>';
    }

    // Backup/restore wizard pages: intentionally no body injection.
    // Phase 6 of the Vue migration replaces the native wizard with a Vue SPA
    // restore flow, so SmartMind fields are no longer injected into native pages.
    if (strpos($pagetype, 'backup-restore') !== false) {
        return '';
    }

    // After a course restore, save the custom field values from sessionStorage to the DB.
    $restoreScript = '';
    if (isloggedin() && !isguestuser()) {
        $courseid = $PAGE->course->id ?? 0;
        if ($courseid && $courseid != SITEID) {
            $restoreScript = '<script>
            (function() {
                try {
                    var saved = sessionStorage.getItem("smgp_restore_fields");
                    if (!saved) return;
                    var data = JSON.parse(saved);
                    if (!data || typeof data !== "object") return;
                    sessionStorage.removeItem("smgp_restore_fields");
                    var courseid = ' . (int) $courseid . ';
                    require(["core/ajax"], function(Ajax) {
                        var fields = {
                            "duration_hours": data.smgp_duration_hours || "",
                            "smartmind_code": data.smgp_smartmind_code || "",
                            "sepe_code": data.smgp_sepe_code || "",
                            "level": data.smgp_level || "beginner",
                            "completion_percentage": data.smgp_completion_percentage || "100",
                            "description": data.smgp_description || ""
                        };
                        Object.keys(fields).forEach(function(field) {
                            if (fields[field]) {
                                Ajax.call([{
                                    methodname: "local_sm_graphics_plugin_update_course_info",
                                    args: {courseid: courseid, field: field, value: fields[field]}
                                }]);
                            }
                        });
                        if (data.smgp_catalogue_cat && data.smgp_catalogue_cat !== "0") {
                            Ajax.call([{
                                methodname: "local_sm_graphics_plugin_update_course_info",
                                args: {courseid: courseid, field: "categoryid", value: data.smgp_catalogue_cat}
                            }]);
                        }
                    });
                } catch(e) {}
            })();
            </script>';
        }
    }

    $scriptpathBody = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($pagetype, 'course-view-') !== 0 || strpos($scriptpathBody, '/course/view.php') === false) {
        return $restoreScript;
    }

    // Pre-seed localStorage with the target activity from the URL (smgp_cmid).
    // This runs BEFORE the AMD course_page module, so the existing resume logic picks it up.
    $smgpCmid = isset($_GET['smgp_cmid']) ? (int) $_GET['smgp_cmid'] : 0;
    $courseId  = $PAGE->course->id;
    $output = '';
    if ($smgpCmid > 0) {
        $output .= '<script>'
            . 'try{localStorage.setItem("smgp-course-last-activity-' . $courseId . '",' . $smgpCmid . ')}catch(e){}'
            . '</script>';
    }

    $output .= '<script>document.body.classList.add("smgp-course-player");</script>';
    // Lucide icons are loaded via the theme SCSS — no extra stylesheet needed here.

    // Hide elements not needed on the course page player.
    $output .= '<style id="smgp-fouc-fix">'
        . '.secondary-navigation{display:none!important}'
        . '#page-header{display:none!important}'
        . '#topofscroll{padding-top:0!important}'
        . '#region-main{padding:0!important;margin-top:0!important}'
        // Hamburger menu (drawer toggle).
        . '.drawer-toggles,.smartmind-sidebar-toggle,[data-toggler="drawers"]{display:none!important}'
        // Help/footer popover button (?).
        . '.btn-footer-popover,.footer-popover,#page-footer'
        . '{display:none!important}'
        // Fix sidebar nav taking 25% width — force actual 56px.
        . 'nav.d-flex.flex-column.w-25[aria-label]{width:56px!important;min-width:56px!important;max-width:56px!important;flex:0 0 56px!important}'
        . '</style>';

    return $output;
}

/**
 * Inject course page components on course-view pages.
 *
 * Renders the course page player and comments, wraps them in a hidden source
 * div, and loads the AMD module to take over the page.
 *
 * @return string HTML to inject before footer.
 */
function local_sm_graphics_plugin_before_footer(): string {
    global $PAGE;

    $output = '';

    // Embed mode: inject tracking JS + inline styles for real-time position
    // updates via postMessage + hide SCORM chrome + fill iframe.
    if (!empty($_GET['smgp_embed']) || !empty($_COOKIE['smgp_embed'])) {
        $output .= local_sm_graphics_plugin_inject_embed_tracking();
        $output .= local_sm_graphics_plugin_inject_embed_styles();
    }

    $pagetype = $PAGE->pagetype ?? '';
    $scriptpath = $_SERVER['SCRIPT_NAME'] ?? '';
    $isRealCourseView = strpos($pagetype, 'course-view-') === 0
        && strpos($scriptpath, '/course/view.php') !== false;

    if ($isRealCourseView) {
    try {
        $courserenderer = new \local_sm_graphics_plugin\output\course_page_renderer();
        $coursepagehtml = $courserenderer->render();

        $commentsrenderer = new \local_sm_graphics_plugin\output\comments_renderer();
        $commentshtml = $commentsrenderer->render();

        if (!empty($coursepagehtml)) {
            $output .= '<div id="smgp-course-page-source" style="display:none;">';
            $output .= $coursepagehtml;
            $output .= '</div>';

            $output .= '<div id="smgp-comments-source" style="display:none;">';
            $output .= $commentshtml;
            $output .= '</div>';

            $PAGE->requires->js_call_amd('local_sm_graphics_plugin/course_page', 'init');
        }
    } catch (\Exception $e) {
        debugging('SM Graphics Plugin: course page render failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
    } // End course-view-* check.

    // Inject Genially into Moodle's native activity chooser.
    $output .= '<script>
    (function() {
        // Watch for the activity chooser modal to open and inject Genially.
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return;
                    var chooserBody = node.querySelector ? node.querySelector(".chooser-container .optionscontainer, .modchooser .optionsummary") : null;
                    if (!chooserBody) chooserBody = node.classList && node.classList.contains("chooser-container") ? node.querySelector(".optionscontainer") : null;
                    if (!chooserBody) return;
                    // Check if Genially is already there.
                    if (chooserBody.querySelector("[data-smgp-genially]")) return;
                    // Find the grid of activity icons.
                    var grid = chooserBody.querySelector(".optionlist, .options");
                    if (!grid) return;
                    // Create Genially card matching the style of other cards.
                    var firstCard = grid.querySelector(".option");
                    if (!firstCard) return;
                    var card = firstCard.cloneNode(true);
                    card.setAttribute("data-smgp-genially", "1");
                    // Update icon.
                    var icon = card.querySelector(".icon, img, .activityicon");
                    if (icon) {
                        if (icon.tagName === "IMG") {
                            icon.src = "";
                            icon.style.display = "none";
                        }
                        var biIcon = document.createElement("i");
                        biIcon.className = "icon-presentation";
                        biIcon.style.fontSize = "2rem";
                        biIcon.style.color = "#f97316";
                        icon.parentNode.insertBefore(biIcon, icon);
                    }
                    // Update name.
                    var nameEl = card.querySelector(".optioninfo .optionname, .typename, .modtypetitle");
                    if (nameEl) nameEl.textContent = "Genially";
                    // Update click — show Genially URL input.
                    card.addEventListener("click", function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var sectionnum = 0;
                        // Try to get section from the chooser context.
                        var chooser = card.closest("[data-sectionid]");
                        if (chooser) sectionnum = parseInt(chooser.getAttribute("data-sectionnum") || "0", 10);
                        var name = prompt("Genially activity name:");
                        if (!name) return;
                        var url = prompt("Genially embed URL (https://view.genial.ly/...):");
                        if (!url) return;
                        require(["core/ajax"], function(Ajax) {
                            var courseid = parseInt(document.body.className.match(/course-(\\d+)/)?.[1] || "0", 10);
                            if (!courseid) courseid = M.cfg.courseId || 0;
                            Ajax.call([{
                                methodname: "local_sm_graphics_plugin_add_activity",
                                args: {courseid: courseid, sectionnum: sectionnum, type: "genially", name: name, url: url}
                            }])[0].then(function(r) { if (r.success) window.location.reload(); });
                        });
                    });
                    // Insert at the beginning of the grid.
                    grid.insertBefore(card, grid.firstChild);
                });
            });
        });
        observer.observe(document.body, {childList: true, subtree: true});
    })();
    </script>';

    // Fix Bootstrap 5 components — data-api may not initialize in Boost child themes.
    $output .= '<script>
    (function() {
        function smgpToggleCollapse(link, target) {
            if (target.smgpBusy) return;
            target.smgpBusy = true;
            var isVisible = target.classList.contains("show");
            if (isVisible) {
                var h = target.scrollHeight;
                target.style.height = h + "px";
                target.offsetHeight;
                target.classList.add("collapsing");
                target.classList.remove("collapse", "show");
                target.style.height = "0";
                link.classList.add("collapsed");
                link.setAttribute("aria-expanded", "false");
                setTimeout(function() { target.classList.remove("collapsing"); target.classList.add("collapse"); target.style.height = ""; target.smgpBusy = false; }, 350);
            } else {
                target.classList.remove("collapse");
                target.classList.add("collapsing");
                target.style.height = "0";
                target.offsetHeight;
                target.style.height = target.scrollHeight + "px";
                link.classList.remove("collapsed");
                link.setAttribute("aria-expanded", "true");
                setTimeout(function() { target.classList.remove("collapsing"); target.classList.add("collapse", "show"); target.style.height = ""; target.smgpBusy = false; }, 350);
            }
        }

        // Capture phase — fires BEFORE Bootstrap handlers.
        document.addEventListener("click", function(e) {
            var toggler = e.target.closest(".ftoggler");
            if (!toggler) return;
            var link = toggler.querySelector("[data-bs-toggle=\'collapse\']");
            if (!link) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            var sel = link.getAttribute("href") || link.getAttribute("data-bs-target");
            if (!sel) return;
            var target = document.querySelector(sel);
            if (target) smgpToggleCollapse(link, target);
        }, true);


        // Expand-all / Collapse-all button.
        document.addEventListener("click", function(e) {
            var btn = e.target.closest(".collapseexpand");
            if (!btn) return;
            e.preventDefault();
            var form = btn.closest(".mform");
            if (!form) return;
            var expand = btn.classList.contains("collapsed");
            form.querySelectorAll(".fcontainer.collapseable").forEach(function(c) {
                var lnk = c.parentElement.querySelector("[data-bs-toggle=\'collapse\']");
                c.classList.remove("collapsing");
                c.style.height = "";
                if (expand) {
                    c.classList.add("collapse", "show");
                    if (lnk) { lnk.classList.remove("collapsed"); lnk.setAttribute("aria-expanded", "true"); }
                } else {
                    c.classList.add("collapse");
                    c.classList.remove("show");
                    if (lnk) { lnk.classList.add("collapsed"); lnk.setAttribute("aria-expanded", "false"); }
                }
            });
            btn.classList.toggle("collapsed", !expand);
            btn.setAttribute("aria-expanded", expand ? "true" : "false");
        }, true);

        // Dropdown toggle — capture phase to beat Bootstrap.
        function smgpCloseAllDropdowns(except) {
            document.querySelectorAll(".dropdown-menu.show").forEach(function(m) {
                if (m === except) return;
                m.classList.remove("show");
                var p = m.closest(".dropdown") || m.parentElement;
                if (p) p.classList.remove("show");
                var t = p ? p.querySelector("[data-bs-toggle=\'dropdown\']") : null;
                if (t) t.setAttribute("aria-expanded", "false");
            });
        }
        document.addEventListener("click", function(e) {
            var dropToggle = e.target.closest("[data-bs-toggle=\'dropdown\']");
            if (dropToggle) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var parent = dropToggle.closest(".dropdown") || dropToggle.parentElement;
                var menu = parent ? parent.querySelector(".dropdown-menu") : null;
                if (!menu) return;
                var isOpen = menu.classList.contains("show");
                smgpCloseAllDropdowns(null);
                if (!isOpen) {
                    menu.classList.add("show");
                    parent.classList.add("show");
                    dropToggle.setAttribute("aria-expanded", "true");
                }
                return;
            }
            // Close dropdowns on outside click (but not when clicking inside a menu).
            if (!e.target.closest(".dropdown-menu")) {
                smgpCloseAllDropdowns(null);
            }
        }, true);
    })();
    </script>';

    return $output;
}

// ── Company manager functions ────────────────────────────────────────────────

/**
 * Return the company_users record for a user if they are a company manager.
 *
 * @param int $userid The user ID to check.
 * @return stdClass|null The record (with companyid, etc.) or null.
 */
function local_sm_graphics_plugin_get_manager_record(int $userid): ?stdClass {
    global $CFG, $DB;

    if (!file_exists($CFG->dirroot . '/blocks/iomad_company_admin')) {
        return null;
    }
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('company_users')) {
        return null;
    }
    $rec = $DB->get_record('company_users', ['userid' => $userid, 'managertype' => 1]);
    return $rec ?: null;
}

/**
 * Return students (managertype 0) that belong to a given company.
 *
 * @param int $companyid The IOMAD company ID.
 * @param int $page      Current page (0-based).
 * @param int $perpage   Users per page (0 = all).
 * @return array Template-ready array of user rows.
 */
function local_sm_graphics_plugin_get_company_users(int $companyid, int $page = 0, int $perpage = 0): array {
    global $DB;

    $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess
              FROM {user} u
              JOIN {company_users} cu ON cu.userid = u.id
             WHERE cu.companyid = :companyid
               AND cu.managertype = 0
               AND u.deleted = 0
          ORDER BY u.lastname ASC, u.firstname ASC";

    $limitfrom = ($perpage > 0) ? $page * $perpage : 0;
    $limitnum  = ($perpage > 0) ? $perpage : 0;
    $records = $DB->get_records_sql($sql, ['companyid' => $companyid], $limitfrom, $limitnum);
    $editbaseurl = new moodle_url('/blocks/iomad_company_admin/editadvanced.php');
    $neverstr = get_string('usermgmt_never', 'local_sm_graphics_plugin');

    $pageurl = local_sm_graphics_plugin_spa_url('management/users');

    $users = [];
    foreach ($records as $r) {
        $users[] = [
            'fullname'   => fullname($r),
            'email'      => $r->email,
            'lastaccess' => $r->lastaccess ? userdate($r->lastaccess) : $neverstr,
            'editurl'    => (new moodle_url($editbaseurl, ['id' => $r->id]))->out(),
            'deleteurl'  => (new moodle_url($pageurl, [
                'deleteuser' => $r->id,
                'sesskey'    => sesskey(),
            ]))->out(),
        ];
    }
    return $users;
}

/**
 * Build the category / sub-option structure for management pages.
 *
 * Reads menu items dynamically from IOMAD's own db/iomadmenu.php files
 * and filters by capability using the manager's company context.
 *
 * @param string     $component Language component identifier.
 * @param int        $companyid The IOMAD company ID.
 * @param array|null $customtabmap Optional tab map override. When null the
 *                                 default manager subset is used. Pass a full
 *                                 map to show all IOMAD categories (admin dashboard).
 * @return array Template-ready array of categories with nested options.
 */
function local_sm_graphics_plugin_get_othermgmt_categories(string $component, int $companyid, ?array $customtabmap = null): array {
    global $CFG;

    require_once($CFG->dirroot . '/local/iomad/lib/iomad.php');
    $companycontext = \core\context\company::instance($companyid);

    if ($customtabmap !== null) {
        $tabmap = $customtabmap;
    } else {
        $tabmap = [
            1 => ['key' => 'companies',   'icon' => 'fa-building',    'title' => get_string('othermgmt_companies',   $component)],
            3 => ['key' => 'courses',     'icon' => 'fa-file-text',   'title' => get_string('othermgmt_courses',     $component)],
            4 => ['key' => 'licenses',    'icon' => 'fa-legal',       'title' => get_string('othermgmt_licenses',    $component)],
            5 => ['key' => 'competences', 'icon' => 'fa-cubes',       'title' => get_string('othermgmt_competences', $component)],
            8 => ['key' => 'reports',     'icon' => 'fa-bar-chart-o', 'title' => get_string('othermgmt_reports',     $component)],
        ];
    }

    $allmenus = [];
    $plugins = get_plugins_with_function('menu', 'db/iomadmenu.php', true);
    foreach ($plugins as $plugintype) {
        foreach ($plugintype as $menufunc) {
            $allmenus += $menufunc();
        }
    }

    $categories = [];
    foreach ($tabmap as $tab => $meta) {
        $options = [];
        foreach ($allmenus as $item) {
            if (($item['tab'] ?? 0) != $tab) {
                continue;
            }
            if (!empty($item['cap']) && !iomad::has_capability($item['cap'], $companycontext)) {
                continue;
            }
            if (substr($item['url'], 0, 1) === '/') {
                $url = new moodle_url($item['url']);
            } else {
                $url = new moodle_url('/blocks/iomad_company_admin/' . $item['url']);
            }
            $options[] = [
                'url'         => $url->out(),
                'icon'        => $item['iconsmall'] ?? $item['icon'] ?? 'fa-circle-o',
                'title'       => $item['name'] ?? '',
                'description' => '',
            ];
        }
        if (!empty($options)) {
            $categories[] = [
                'key'     => $meta['key'],
                'icon'    => $meta['icon'],
                'title'   => $meta['title'],
                'options' => $options,
            ];
        }
    }
    return $categories;
}

// ── Company student limit functions ──────────────────────────────────────────

/**
 * Count students (managertype 0) in a company.
 *
 * @param int $companyid
 * @return int
 */
function local_sm_graphics_plugin_get_company_student_count(int $companyid): int {
    global $DB;
    return (int) $DB->count_records_select('company_users',
        'companyid = :cid AND managertype = 0',
        ['cid' => $companyid]
    );
}

/**
 * Get the maximum students allowed for a company (0 = unlimited).
 *
 * @param int $companyid
 * @return int
 */
function local_sm_graphics_plugin_get_company_limit(int $companyid): int {
    global $DB;
    $rec = $DB->get_record('local_smgp_company_limits', ['companyid' => $companyid]);
    return $rec ? (int) $rec->maxstudents : 0;
}

/**
 * Check whether a company has reached its student limit.
 *
 * @param int $companyid
 * @return bool
 */
function local_sm_graphics_plugin_is_company_limit_reached(int $companyid): bool {
    $limit = local_sm_graphics_plugin_get_company_limit($companyid);
    if ($limit <= 0) {
        return false;
    }
    return local_sm_graphics_plugin_get_company_student_count($companyid) >= $limit;
}

/**
 * Save (upsert) the maximum student limit for a company.
 *
 * @param int $companyid
 * @param int $maxstudents 0 = unlimited.
 */
function local_sm_graphics_plugin_save_company_limit(int $companyid, int $maxstudents): void {
    global $DB;
    $now = time();
    $existing = $DB->get_record('local_smgp_company_limits', ['companyid' => $companyid]);
    if ($existing) {
        $existing->maxstudents = $maxstudents;
        $existing->timemodified = $now;
        $DB->update_record('local_smgp_company_limits', $existing);
    } else {
        $DB->insert_record('local_smgp_company_limits', (object) [
            'companyid'    => $companyid,
            'maxstudents'  => $maxstudents,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }
}

/**
 * Get all companies with their student count and limit (for admin page).
 *
 * @return array
 */
function local_sm_graphics_plugin_get_all_company_limits(): array {
    global $DB;
    $companies = $DB->get_records('company', null, 'name ASC', 'id, name, shortname');
    $result = [];
    foreach ($companies as $c) {
        $count = local_sm_graphics_plugin_get_company_student_count($c->id);
        $limit = local_sm_graphics_plugin_get_company_limit($c->id);
        $result[] = [
            'companyid'    => $c->id,
            'companyname'  => $c->name,
            'shortname'    => $c->shortname,
            'studentcount' => $count,
            'maxstudents'  => $limit,
            'unlimited'    => ($limit <= 0),
            'limitreached' => ($limit > 0 && $count >= $limit),
        ];
    }
    return $result;
}

// ── Certificate verification code ────────────────────────────────────────────

/**
 * Get or create a persistent verification code for a user+course certificate.
 *
 * @param int $userid
 * @param int $courseid
 * @return string 10-char uppercase alphanumeric code.
 */
function local_sm_graphics_plugin_get_cert_code(int $userid, int $courseid): string {
    global $CFG, $DB;

    // Return existing code if one was already generated.
    $existing = $DB->get_record('local_smgp_cert_codes', [
        'userid' => $userid,
        'courseid' => $courseid,
    ]);
    if ($existing) {
        return $existing->code;
    }

    // Generate a deterministic code from user+course+site secret.
    $salt = $CFG->passwordsaltmain ?? ($CFG->dbpass ?? 'smartmind');
    $code = strtoupper(substr(hash('sha256', $userid . '-' . $courseid . '-' . $salt), 0, 10));

    // Insert, handling the unlikely collision on the code unique index.
    try {
        $DB->insert_record('local_smgp_cert_codes', (object) [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'code'        => $code,
            'timecreated' => time(),
        ]);
    } catch (\dml_write_exception $e) {
        // Collision — re-read in case another request inserted it concurrently.
        $existing = $DB->get_record('local_smgp_cert_codes', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        if ($existing) {
            return $existing->code;
        }
        // True code collision with a different user+course — append a random suffix.
        $code = strtoupper(substr(hash('sha256', $userid . '-' . $courseid . '-' . $salt . '-' . random_int(0, 99999)), 0, 10));
        $DB->insert_record('local_smgp_cert_codes', (object) [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'code'        => $code,
            'timecreated' => time(),
        ]);
    }

    return $code;
}

/**
 * Inject activity tracking JavaScript when in embed mode (smgp_embed=1).
 *
 * Injects SCORM tracking IIFE for SCORM activities, or position-tracking JS
 * for quiz/book/lesson/other activities. This enables real-time postMessage
 * progress updates from inside the iframe to the parent course player.
 *
 * @return string HTML/JS to inject.
 */
function local_sm_graphics_plugin_inject_embed_tracking(): string {
    global $PAGE, $DB;

    // Skip if SM_Estratoos plugin is already handling tracking.
    if (class_exists('\local_sm_estratoos_plugin\scorm\tracking_js')) {
        return '';
    }

    $pagepath = $PAGE->url->get_path() ?? '';
    $output = '';

    // --- SCORM player page ---
    if (strpos($pagepath, '/mod/scorm/player.php') !== false) {
        $scormid = optional_param('a', 0, PARAM_INT);
        $cmid = 0;
        $slidescount = 0;
        if ($scormid > 0) {
            $cm = $DB->get_record_sql(
                "SELECT cm.id FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                 WHERE cm.instance = :instance",
                ['instance' => $scormid]
            );
            if ($cm) {
                $cmid = (int) $cm->id;
                $slidescount = \local_sm_graphics_plugin\scorm\slidecount::detect($cmid, $scormid);
            }
        }
        $output .= \local_sm_graphics_plugin\scorm\tracking_js::get_script($cmid, $scormid, $slidescount);
        return $output;
    }

    // --- Non-SCORM activity pages ---
    if (strpos($pagepath, '/mod/') !== false) {
        $cmid = optional_param('id', 0, PARAM_INT);
        if (!$cmid) {
            $cmid = optional_param('cmid', 0, PARAM_INT);
        }
        if ($cmid > 0) {
            $cm = get_coursemodule_from_id('', $cmid);
            if ($cm) {
                switch ($cm->modname) {
                    case 'quiz':
                        // Only track on attempt.php and review.php (not view.php).
                        if (strpos($pagepath, '/attempt.php') !== false
                            || strpos($pagepath, '/review.php') !== false) {
                            $output .= \local_sm_graphics_plugin\activity\tracking_js::get_quiz_script(
                                $cmid, (int) $cm->instance);
                        }
                        break;
                    case 'book':
                        $output .= \local_sm_graphics_plugin\activity\tracking_js::get_book_script(
                            $cmid, (int) $cm->instance);
                        break;
                    case 'lesson':
                        $output .= \local_sm_graphics_plugin\activity\tracking_js::get_lesson_script(
                            $cmid, (int) $cm->instance);
                        break;
                    default:
                        // All other activities: whole-activity tagging (position 1/1).
                        $output .= \local_sm_graphics_plugin\activity\tracking_js::get_whole_activity_script(
                            $cmid, $cm->modname);
                        break;
                }
            }
        }
    }

    return $output;
}


/**
 * Inject inline CSS for embed mode — hides SCORM chrome, fills viewport.
 * Called from before_footer() alongside tracking JS injection.
 * @return string
 */
function local_sm_graphics_plugin_inject_embed_styles(): string {
    return '<style id="smgp-embed-styles">
#scorm_navpanel, #scorm_toc, #scorm_toc_title, #scorm_toc_toggle,
.scorm_toc_title, #toc, #page-header, #page-footer, footer,
.navbar, .smgp-topnav, #nav-drawer, .drawer-toggles,
.breadcrumb, .breadcrumb-nav, #page-navbar,
.secondary-navigation, .activity-navigation, .activity-header,
nav[aria-label="Navigation bar"],
.smgp-custom-header, .smgp-sidebar, .smgp-sidebar__overlay,
#scorm_layout > select,
#scormtop,
.scormnav.scorm-right,
.scormnav {
  display: none !important;
}
/* Transparent background so parent iframe rounded corners show through */
body#page-mod-scorm-player,
body#page-mod-scorm-player #page-wrapper,
body#page-mod-scorm-player #page {
  background: transparent !important;
}
</style>
<script>
// After hiding #scormtop, trigger a window resize so Moodle SCORM JS
// recalculates the content iframe height to fill the freed space.
setTimeout(function(){ window.dispatchEvent(new Event("resize")); }, 800);
setTimeout(function(){ window.dispatchEvent(new Event("resize")); }, 2000);
</script>';
}

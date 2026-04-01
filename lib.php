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
 * Redirect user/profile.php to our custom profile page.
 */
function local_sm_graphics_plugin_before_http_headers() {
    global $PAGE, $CFG;

    $pagepath = $PAGE->url->get_path();
    if (preg_match('#/user/profile\.php#', $pagepath)) {
        $id = optional_param('id', 0, PARAM_INT);
        $params = $id ? ['id' => $id] : [];
        $url = new moodle_url('/local/sm_graphics_plugin/pages/profile.php', $params);
        redirect($url);
    }
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

    // Detect current script path reliably (works even before $PAGE->url is set).
    $scriptpath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';

    // Redirect enrolment page to our course landing page.
    if (strpos($scriptpath, '/enrol/index.php') !== false) {
        $courseid = optional_param('id', 0, PARAM_INT);
        if ($courseid && isloggedin() && !isguestuser()) {
            redirect(new moodle_url('/local/sm_graphics_plugin/pages/course_landing.php', ['id' => $courseid]));
        }
    }

    // Redirect company managers from the dashboard to the user management page.
    if (substr($scriptpath, -13) === '/my/index.php' || $scriptpath === '/my/') {
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if ($managerrec) {
            redirect(new moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php'));
        }
    }

    // Redirect IOMAD dashboard to SmartMind card-based dashboard.
    if (strpos($scriptpath, 'iomad_company_admin/index.php') !== false) {
        $managerrec = local_sm_graphics_plugin_get_manager_record($USER->id);
        if ($managerrec) {
            redirect(new moodle_url('/local/sm_graphics_plugin/pages/othermanagement.php'));
        } else if (is_siteadmin()) {
            redirect(new moodle_url('/local/sm_graphics_plugin/pages/iomaddashboard.php'));
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
                new moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php'),
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
                        new moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php'),
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
                            new moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php'),
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
            // Write any pending restore fields to DB before redirecting.
            // This fires when Moodle redirects to /course/view.php after restore.
            global $SESSION;
            if (!empty($SESSION->smgp_restore_pending)) {
                $pending = $SESSION->smgp_restore_pending;
                unset($SESSION->smgp_restore_pending);
                $fields = (array) ($pending['fields'] ?? []);
                error_log('[SMGP-RESTORE] Writing pending fields to DB for course ' . $course->id);
                \local_sm_graphics_plugin\observer::write_restore_fields((int) $course->id, $fields);
            }
            // ALL users see the landing page first (course hub/dashboard).
            // The landing page has Start/Continue button that links to the player with smgp_enter=1.
            redirect(new moodle_url('/local/sm_graphics_plugin/pages/course_landing.php', ['id' => $course->id]));
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
    // hide all Moodle chrome. Detected by smgp_embed=1 GET param OR cookie
    // (cookie persists across internal iframe navigations like wiki create.php).
    $isembed = !empty($_GET['smgp_embed']) || !empty($_COOKIE['smgp_embed']);

    // Set cookie on first embed request so subsequent navigations keep embed mode.
    if (!empty($_GET['smgp_embed']) && empty($_COOKIE['smgp_embed'])) {
        setcookie('smgp_embed', '1', 0, '/mod/'); // Scoped to /mod/ paths only.
    }

    // Clear embed cookie on non-/mod/ pages (user left the embed context).
    $scriptEmbed = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!empty($_COOKIE['smgp_embed']) && strpos($scriptEmbed, '/mod/') === false) {
        setcookie('smgp_embed', '', time() - 3600, '/mod/');
        $isembed = false;
    }

    if ($isembed) {
        $css = '<style id="smgp-embed-fix">'
            // Hide ALL chrome: navbar, headers, drawers, footers, navigation, completion info.
            . '.navbar,.smgp-topnav,#page-header,.secondary-navigation,.drawer-left,.drawer-right,'
            . '.drawer-toggles,.drawer,[data-region="drawer"],#page-footer,footer,'
            . '.breadcrumb-nav,nav[aria-label="Navigation bar"],.activity-navigation,'
            . '.activity-header .moremenu,'
            . '#scormnav,#scormtop,.scorm-right,#scorm_toc_toggle,#scorm_toc,'
            . '.activity-information,.automatic-completion-conditions,.completionrequirements,'
            . '.completion-info,[data-region="completion-info"],'
            . '[data-region="completionrequirements"],[data-region="activity-information"],'
            // SmartMind theme-specific elements.
            . '.smartmind-course-banner,.smartmind-page-header,'
            . 'nav.d-flex.flex-column[aria-label],'
            . '.btn-footer-popover,.footer-popover'
            . '{display:none!important}'
            // Reset layout containers — no grid, no margins.
            . '#page,#page.drawers,#page.drawers.drag-container'
            . '{display:block!important;grid-template-columns:none!important;margin:0!important;padding:0!important}'
            . '#topofscroll,#page-content{margin:0!important;padding:0!important}'
            . '#page-wrapper{display:block!important}'
            // Content area — full width, no horizontal overflow.
            . '#region-main{border:none!important;box-shadow:none!important;border-radius:0!important;'
            . 'padding:0!important;margin:0!important;max-width:100%!important;width:100%!important}'
            . 'html,body{overflow-x:hidden!important;max-width:100vw!important}'
            . '.main-inner,.header-maxwidth{max-width:100%!important;padding:0!important;margin:0!important}'
            . '#region-main-box{padding:0!important;margin:0!important;max-width:100%!important}'
            . '</style>';
        // Quiz: keep right drawer for question navigation.
        $pagepathEmbed = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($pagepathEmbed, '/mod/quiz/') !== false) {
            $css .= '<style>#mod_quiz_navblock{display:block!important}</style>';
        }
        $css .= '<script>document.body.classList.add("smgp-embedded");</script>';

        // Append full SmartLearning activity styling (quiz questions, answers, theme tokens).
        $pagepathAssets = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($pagepathAssets, '/mod/quiz/') !== false) {
            $css .= \local_sm_graphics_plugin\embed\activity_embed_assets::get_quiz_css_js();
        } else {
            $css .= \local_sm_graphics_plugin\embed\activity_embed_assets::get_css_js();
        }

        return $css;
    }

    // Quiz view: layout is handled by theme renderer override (quiz_renderer.php).

    // Inject SmartMind fields + destination page enhancements into restore pages.
    if ($pagetype === 'backup-restore') {
        // Capture SmartMind fields from POST when the schema form is submitted.
        // This is the only reliable server-side capture point — the fields are
        // raw HTML (not Moodle form elements), so they appear in $_POST but not
        // in Moodle's $form->get_data(). Store in $SESSION for the observer.
        global $SESSION;
        $smgpFields = [
            'smgp_duration_hours', 'smgp_level', 'smgp_completion_percentage',
            'smgp_catalogue_cat', 'smgp_smartmind_code', 'smgp_sepe_code',
            'smgp_description', 'smgp_objectives_data', 'smgp_course_structure',
        ];
        $hasSmgpPost = false;
        foreach ($smgpFields as $f) {
            if (isset($_POST[$f])) {
                $hasSmgpPost = true;
                break;
            }
        }
        // DEBUG: log what we see in POST.
        error_log('[SMGP-RESTORE] pagetype=backup-restore, hasSmgpPost=' . ($hasSmgpPost ? 'YES' : 'NO')
            . ', POST keys: ' . implode(',', array_keys($_POST)));
        if ($hasSmgpPost) {
            $clean = [];
            foreach ($smgpFields as $f) {
                if (isset($_POST[$f])) {
                    $clean[$f] = clean_param($_POST[$f], PARAM_RAW);
                }
            }
            $SESSION->smgp_restore_pending = [
                'courseid' => 0,
                'fields'   => $clean,
            ];
            error_log('[SMGP-RESTORE] Staged in SESSION: ' . json_encode($clean));
        }

        return local_sm_graphics_plugin_restore_schema_fields()
            . local_sm_graphics_plugin_restore_destination_js()
            . local_sm_graphics_plugin_restore_settings_js();
    }

    // Restyle the restore file upload page.
    if ($pagetype === 'backup-restorefile') {
        return local_sm_graphics_plugin_restore_file_styles();
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
                        // Single batched call saves all meta fields atomically (no race condition).
                        Ajax.call([{
                            methodname: "local_sm_graphics_plugin_save_restore_fields",
                            args: {
                                courseid:    courseid,
                                fields_json: JSON.stringify(data)
                            }
                        }]);

                        // Save learning objectives + auto-translate (separate endpoint).
                        if (data.smgp_objectives_data && data.smgp_objectives_data !== "[]") {
                            try {
                                var objs = JSON.parse(data.smgp_objectives_data);
                                if (Array.isArray(objs) && objs.length > 0) {
                                    Ajax.call([{
                                        methodname: "local_sm_graphics_plugin_save_objectives",
                                        args: {courseid: courseid, objectives_json: data.smgp_objectives_data, translate: true}
                                    }]);
                                }
                            } catch(ex) {}
                        }

                        // Trigger description translation (needs summary saved first, slight delay).
                        if (data.smgp_description && data.smgp_description.trim()) {
                            setTimeout(function() {
                                Ajax.call([{
                                    methodname: "local_sm_graphics_plugin_translate_course",
                                    args: {courseid: courseid}
                                }]);
                            }, 2000);
                        }

                        // Assign course to selected companies (from restore destination page).
                        try {
                            var companyIds = sessionStorage.getItem("smgp_restore_companies");
                            if (companyIds) {
                                sessionStorage.removeItem("smgp_restore_companies");
                                var ids = JSON.parse(companyIds);
                                if (Array.isArray(ids)) {
                                    ids.forEach(function(compId) {
                                        Ajax.call([{
                                            methodname: "local_sm_graphics_plugin_assign_course_company",
                                            args: {courseid: courseid, companyid: parseInt(compId)}
                                        }]);
                                    });
                                }
                            }
                        } catch(ex) {}
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

    // Embed mode: inject tracking JS for real-time position updates via postMessage.
    if (!empty($_GET['smgp_embed'])) {
        $output .= local_sm_graphics_plugin_inject_embed_tracking();
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

    // For site admins: run update checker.
    try {
        \local_sm_graphics_plugin\update_checker::check();
    } catch (\Exception $e) {
        debugging('SM Graphics Plugin update check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

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

    $pageurl = new moodle_url('/local/sm_graphics_plugin/pages/usermanagement.php');

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
 * Generate inline JS/HTML to inject SmartMind fields into the restore schema page.
 *
 * @return string
 */
/**
 * Restyle the restore file upload page with icons, colors, and better layout.
 *
 * @return string
 */
function local_sm_graphics_plugin_restore_file_styles(): string {
    global $SESSION;
    // A new restore is starting — discard any pending data from a previous restore.
    unset($SESSION->smgp_restore_pending);

    return '<script>
    // Clear browser-side restore data from any previous restore session.
    try { sessionStorage.removeItem("smgp_restore_fields"); } catch(e) {}
    try { sessionStorage.removeItem("smgp_restore_companies"); } catch(e) {}
    </script>
    <style>
    /* Page heading */
    #page-backup-restorefile [role="main"] > h2:first-of-type {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1e293b;
    }
    #page-backup-restorefile [role="main"] > h2:first-of-type::before {
        content: "\e148";
        font-family: "lucide" !important;
        color: #10b981;
        margin-right: 0.5rem;
        color: #10b981;
    }
    /* Subtitle text */
    #page-backup-restorefile [role="main"] > .pb-3 {
        color: #64748b;
        font-size: 0.95rem;
        margin-bottom: 1.5rem !important;
    }
    /* Import section heading */
    #page-backup-restorefile [role="main"] > h2:nth-of-type(2) {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px 10px 0 0;
        margin-bottom: 0 !important;
    }
    #page-backup-restorefile [role="main"] > h2:nth-of-type(2)::before {
        content: "\e19e";
        font-family: "lucide" !important;
        color: #10b981;
        margin-right: 0.5rem;
    }
    /* Form wrapper */
    #page-backup-restorefile [role="main"] > div:has(.mform) {
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 10px 10px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        background: #fff;
    }
    /* Restore button */
    #page-backup-restorefile .mform input[type="submit"] {
        background: #10b981 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 8px !important;
        padding: 0.5rem 1.5rem !important;
        font-weight: 600 !important;
        font-size: 0.9rem !important;
        cursor: pointer;
        transition: background 0.15s;
    }
    #page-backup-restorefile .mform input[type="submit"]:hover {
        background: #059669 !important;
    }
    /* Section headings (h3) */
    #page-backup-restorefile h3 {
        font-size: 1.15rem;
        font-weight: 600;
        color: #1e293b;
        margin-top: 2rem !important;
        margin-bottom: 0.5rem !important;
    }
    #page-backup-restorefile h3::before {
        font-family: "lucide" !important;
        margin-right: 0.4rem;
        font-size: 1.1rem;
    }
    /* Course backup zone icon — green */
    #page-backup-restorefile h3:first-of-type::before {
        content: "\e0d7";
        color: #10b981;
    }
    /* User backup zone icon — green */
    #page-backup-restorefile h3:nth-of-type(2)::before {
        content: "\e19f";
        color: #10b981;
    }
    /* Section headings — align icon with text */
    #page-backup-restorefile h3 {
        display: flex;
        align-items: center;
    }
    /* Backup section description */
    #page-backup-restorefile h3 + .mb-3 {
        color: #64748b;
        font-size: 0.875rem;
        margin-left: 0.25rem;
    }
    /* Form field row — stack label on top, picker below */
    #page-backup-restorefile .mform .fitem {
        display: block !important;
        padding: 0.75rem 1.5rem;
        margin: 0 !important;
    }
    /* Remove fieldset bottom padding/margin */
    #page-backup-restorefile .mform fieldset {
        margin: 0 !important;
        padding: 0.5rem 0 !important;
    }
    #page-backup-restorefile .mform .fitem .col-md-3 {
        width: 100% !important;
        max-width: none !important;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0 0 0.5rem 0 !important;
        float: none !important;
    }
    #page-backup-restorefile .mform .fitem .col-md-3 p {
        font-weight: 600;
        font-size: 0.9rem;
        color: #1e293b;
        margin: 0;
    }
    #page-backup-restorefile .mform .fitem .col-md-9 {
        width: 100% !important;
        max-width: none !important;
        padding: 0 !important;
        float: none !important;
    }
    /* Required icon — next to label, smaller */
    #page-backup-restorefile .mform .fitem .col-md-3 .form-label-addon {
        margin: 0 !important;
        padding: 0 !important;
    }
    #page-backup-restorefile .mform .fitem .col-md-3 .text-danger {
        font-size: 0.7rem;
    }
    /* File picker button — left aligned */
    #page-backup-restorefile .fp-btn-choose {
        display: inline-block !important;
        margin: 0 0 0.75rem 0 !important;
    }
    /* Import card — inner padding */
    #page-backup-restorefile [role="main"] > div:has(.mform) {
        padding: 1.5rem !important;
    }
    /* Required text at bottom */
    #page-backup-restorefile .fdescription.required {
        padding: 0.75rem 1.5rem;
        font-size: 0.8rem;
        color: #94a3b8;
    }
    /* Tables */
    #page-backup-restorefile .backup-files-table {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }
    #page-backup-restorefile .backup-files-table thead th {
        background: #f8fafc !important;
        color: #475569;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: 2px solid #e2e8f0;
    }
    #page-backup-restorefile .backup-files-table td {
        font-size: 0.875rem;
        color: #334155;
    }
    #page-backup-restorefile .backup-files-table td a {
        color: #10b981;
        font-weight: 500;
    }
    #page-backup-restorefile .backup-files-table td a:hover {
        color: #059669;
    }
    /* Manage backups button */
    #page-backup-restorefile .singlebutton input[type="submit"],
    #page-backup-restorefile .singlebutton button {
        background: transparent !important;
        color: #10b981 !important;
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 0.4rem 1rem !important;
        font-weight: 500 !important;
        font-size: 0.85rem !important;
        transition: border-color 0.15s, color 0.15s;
    }
    #page-backup-restorefile .singlebutton input[type="submit"]:hover,
    #page-backup-restorefile .singlebutton button:hover {
        border-color: #10b981 !important;
    }

    /* Better spacing */
    #page-backup-restorefile [role="main"] > h2 {
        margin-top: 0.5rem !important;
    }
    #page-backup-restorefile .backup-files-table {
        margin-top: 0.75rem;
    }
    /* 1 — Hide label col, remove Bootstrap gutter so drag-drop is flush/centered */
    #page-backup-restorefile #fitem_id_backupfile > .col-md-3 {
        display: none !important;
    }
    #page-backup-restorefile #fitem_id_backupfile {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    #page-backup-restorefile #fitem_id_backupfile > .col-md-9 {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    #page-backup-restorefile .mform .fitem .col-md-9,
    #page-backup-restorefile .mform .fitem .felement,
    #page-backup-restorefile .mform .fitem .felement > fieldset,
    #page-backup-restorefile .mdl-left,
    #page-backup-restorefile .filepicker-filelist,
    #page-backup-restorefile [id^="filepicker-wrapper-"] {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    /* 2 — Drag-drop container: flex column, centered content */
    #page-backup-restorefile .filepicker-filelist {
        border: none !important;
        overflow: visible !important;
    }
    #page-backup-restorefile .filepicker-container {
        position: relative !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 130px !important;
        width: 100% !important;
        border: 2px dashed #10b981 !important;
        border-radius: 12px !important;
        padding: 1.5rem !important;
        background: rgba(16, 185, 129, 0.02) !important;
        box-sizing: border-box !important;
    }
    /* 3 — Message: flex column, icon on top (JS puts arrow first), text below */
    #page-backup-restorefile .dndupload-message {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        gap: 0.75rem !important;
        width: 100% !important;
        border: none !important;
        color: #64748b;
        font-size: 0.9rem;
        padding: 0 !important;
        background: transparent !important;
        text-align: center !important;
    }
    #page-backup-restorefile .dndupload-arrow {
        margin: 0 !important;
        display: flex !important;
        justify-content: center !important;
    }
    #page-backup-restorefile .dndupload-arrow i,
    #page-backup-restorefile .dndupload-arrow i::before {
        color: #10b981 !important;
        margin: 0 !important;
    }
    /* Remove separator lines and dark-mode background from form elements */
    #page-backup-restorefile .mform .fitem,
    #page-backup-restorefile .mform .felement {
        border: none !important;
    }
    #page-backup-restorefile .mform fieldset {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
    #page-backup-restorefile .mform hr {
        display: none !important;
    }
    /* Restaurar button — centered */
    #page-backup-restorefile .mform input[name="submitbutton"] {
        display: block !important;
        margin: 0.25rem auto 0 !important;
    }
    /* File picker button — green, left-aligned above drag area */
    #page-backup-restorefile .fp-btn-choose {
        background: #10b981 !important;
        color: #fff !important;
        border-radius: 8px !important;
        border: none !important;
        font-weight: 600 !important;
        padding: 0.5rem 1.25rem !important;
        margin-bottom: 0.75rem;
    }
    #page-backup-restorefile .fp-btn-choose:hover {
        background: #059669 !important;
    }
    /* Hide ALL empty col-md-3 spacers (the ones after col-md-9, and the submit fitem label) */
    #page-backup-restorefile .fitem .col-md-9 ~ .col-md-3,
    #page-backup-restorefile .fitem[data-fieldtype="submit"] .col-md-3 {
        display: none !important;
    }
    /* Form label — bold, left aligned */
    #page-backup-restorefile .mform .fitem .col-md-3 label,
    #page-backup-restorefile .mform .fitem .col-md-3 span {
        font-weight: 600;
        font-size: 0.9rem;
        color: #1e293b;
    }
    /* Required icon — smaller, muted */
    #page-backup-restorefile .mform .fitem .col-md-3 .text-danger {
        font-size: 0.75rem;
    }
    /* Manage backups button — green hover */
    #page-backup-restorefile .singlebutton input[type="submit"]:hover,
    #page-backup-restorefile .singlebutton button:hover {
        border-color: #10b981 !important;
        background: rgba(16, 185, 129, 0.04) !important;
    }
    /* Table rows — hover effect */
    #page-backup-restorefile .backup-files-table tbody tr:hover {
        background: #f8fafc;
    }
    /* Status check marks — green */
    #page-backup-restorefile .backup-files-table .text-success,
    #page-backup-restorefile .backup-files-table .fa-check {
        color: #10b981 !important;
    }
    /* Overall page max width */
    #page-backup-restorefile [role="main"] {
        max-width: 960px;
        margin: 0 auto;
    }
    </style>
    <script>
    // Wait for Moodle YUI filepicker to create the drag-drop area.
    function smgpFixFilepicker() {
        // Remove separator line between file picker and submit button.
        var fitem = document.querySelector("#page-backup-restorefile .mform #fitem_id_backupfile");
        if (fitem) fitem.style.borderBottom = "none";
        // Also hide any hr elements.
        document.querySelectorAll("#page-backup-restorefile .mform hr").forEach(function(hr) { hr.style.display = "none"; });
        // Reduce space before Restaurar.
        var submitBtn = document.querySelector("#page-backup-restorefile .mform input[name=submitbutton]");
        if (submitBtn && submitBtn.parentElement) {
            submitBtn.parentElement.style.borderTop = "none";
            submitBtn.parentElement.style.paddingTop = "0.5rem";
        }
    }
    // Retry until the dndupload-message exists (Moodle creates it after page load).
    var smgpRetries = 0;
    var smgpInterval = setInterval(function() {
        smgpRetries++;
        var dndMsg = document.querySelector("#page-backup-restorefile .dndupload-message");
        if (dndMsg || smgpRetries > 50) {
            clearInterval(smgpInterval);
            if (dndMsg) {
                // Extract text and arrow, rebuild as real elements so flex works reliably.
                var arrow = dndMsg.querySelector(".dndupload-arrow");
                var textContent = "";
                dndMsg.childNodes.forEach(function(n) {
                    if (n.nodeType === 3 && n.textContent.trim()) textContent += n.textContent.trim();
                });
                dndMsg.innerHTML = "";
                // Arrow first (top).
                if (arrow) {
                    arrow.className = "";
                    var icon = arrow.querySelector("i");
                    if (icon) { icon.className = "fa fa-arrow-circle-o-down fa-3x"; icon.style.color = "#10b981"; }
                    dndMsg.appendChild(arrow);
                }
                // Text below.
                if (textContent) {
                    var txt = document.createElement("span");
                    txt.textContent = textContent;
                    dndMsg.appendChild(txt);
                }
            }
            smgpFixFilepicker();
            // Hide ALL empty/tiny col-md-3 divs on the page (spacers).
            document.querySelectorAll("#page-backup-restorefile .col-md-3").forEach(function(col) {
                if (col.offsetHeight < 15 && col.textContent.trim() === "") {
                    col.style.display = "none";
                }
            });
        }
    }, 200);
    </script>';
}

/**
 * JS enhancements for the restore destination page (step 2).
 * Relocates search bars to top-right of section cards and makes
 * "Seleccione" detail-pairs full-width.
 *
 * @return string
 */
function local_sm_graphics_plugin_restore_destination_js(): string {
    global $DB;

    // Pre-fetch companies with their category IDs for JS.
    $companies = [];
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('company')) {
        $recs = $DB->get_records_sql(
            "SELECT c.id, c.name, c.shortname, c.category
               FROM {company} c ORDER BY c.name ASC"
        );
        foreach ($recs as $r) {
            $companies[] = [
                'id' => (int) $r->id,
                'name' => format_string($r->name),
                'shortname' => $r->shortname,
                'categoryid' => (int) $r->category,
            ];
        }
    }
    $companiesJson = json_encode($companies);

    // Lang strings.
    $selectCompany = addslashes(get_string('restore_select_company', 'local_sm_graphics_plugin'));
    $selectAll = addslashes(get_string('restore_select_all', 'local_sm_graphics_plugin'));
    $companyLabel = addslashes(get_string('restore_company', 'local_sm_graphics_plugin'));
    $companyShort = addslashes(get_string('restore_company_short', 'local_sm_graphics_plugin'));

    return '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var selector = document.querySelector(".backup-course-selector");
        if (!selector) return;

        // Fix vertical layout — convert float-based detail-pairs to block layout.
        selector.querySelectorAll(".backup-section").forEach(function(sec) {
            // Make the section a vertical flex container.
            sec.style.display = "flex";
            sec.style.flexDirection = "column";
            sec.style.alignItems = "stretch";

            sec.querySelectorAll(".detail-pair").forEach(function(dp) {
                // Remove float layout — make each detail-pair a full-width row.
                dp.style.display = "flex";
                dp.style.width = "100%";
                dp.style.float = "none";
                dp.style.clear = "both";
                dp.style.padding = "0.5rem 0";

                var lbl = dp.querySelector(".detail-pair-label");
                var val = dp.querySelector(".detail-pair-value");
                if (lbl) {
                    lbl.style.width = "auto";
                    lbl.style.float = "none";
                    lbl.style.flex = "1";
                    lbl.style.fontWeight = "500";
                    lbl.style.fontSize = "0.875rem";
                    lbl.style.color = "#475569";
                }
                if (val) {
                    val.style.width = "auto";
                    val.style.float = "none";
                    val.style.flexShrink = "0";
                }
            });
        });

        // Green icon override for search magnifier.
        var gs = document.createElement("style");
        gs.textContent = ".smgp-green-icon, .smgp-green-icon::before { color: #10b981 !important; }";
        document.head.appendChild(gs);

        var companies = ' . $companiesJson . ';

        // ===== "RESTAURAR COMO CURSO NUEVO" section =====
        var newSection = selector.querySelector(".bcs-new-course");
        if (newSection && companies.length > 0) {
            // Find the "Seleccione una categoría" detail-pair.
            var searchDiv = newSection.querySelector(".restore-course-search");
            var tableParent = searchDiv ? searchDiv.closest(".detail-pair") : null;

            if (tableParent) {
                tableParent.classList.add("smgp-select-fullwidth");

                // Change label text to "Seleccione una empresa".
                var label = tableParent.querySelector(".detail-pair-label");
                if (label) {
                    // Build search input inline with label.
                    var searchHtml = \'<div class="smgp-search-relocated">\' +
                        \'<input type="text" id="smgp-company-search" placeholder="' . $selectCompany . '..." class="form-control">\' +
                        \'<span class="smgp-search-btn"><i class="fa fa-search smgp-green-icon"></i></span>\' +
                        \'</div>\';
                    label.innerHTML = \'' . $selectCompany . '\' + searchHtml;
                    label.style.display = "flex";
                    label.style.justifyContent = "space-between";
                    label.style.alignItems = "center";
                }

                // Build company table with checkboxes.
                var valueDiv = tableParent.querySelector(".detail-pair-value");
                if (valueDiv) {
                    var tableHtml = \'<table class="generaltable table table-hover" id="smgp-company-table" style="width:100%">\' +
                        \'<thead><tr>\' +
                        \'<th style="width:44px;text-align:center;vertical-align:middle;"><span id="smgp-select-all" class="smgp-fake-radio" title="' . $selectAll . '"></span></th>\' +
                        \'<th>' . $companyLabel . '</th>\' +
                        \'<th>' . $companyShort . '</th>\' +
                        \'</tr></thead><tbody>\';

                    companies.forEach(function(c) {
                        tableHtml += \'<tr data-name="\' + c.name.toLowerCase() + \' \' + c.shortname.toLowerCase() + \'">\' +
                            \'<td style="width:44px;text-align:center;vertical-align:middle;">\' +
                            \'<input type="radio" name="smgp_company_radio" value="\' + c.id + \'" data-catid="\' + c.categoryid + \'" style="cursor:pointer;">\' +
                            \'</td>\' +
                            \'<td><strong>\' + c.name + \'</strong></td>\' +
                            \'<td style="color:#94a3b8;font-size:0.8rem;">\' + c.shortname + \'</td>\' +
                            \'</tr>\';
                    });

                    tableHtml += \'</tbody></table>\';
                    valueDiv.innerHTML = tableHtml;

                    // Make radios work as multi-select toggles (click to toggle on/off).
                    var companyRadios = document.querySelectorAll("#smgp-company-table tbody input[type=radio]");
                    companyRadios.forEach(function(radio) {
                        radio.addEventListener("click", function(e) {
                            // Prevent default radio single-select behavior.
                            if (radio.dataset.wasChecked === "true") {
                                radio.checked = false;
                                radio.dataset.wasChecked = "false";
                            } else {
                                // Allow multiple: dont uncheck others.
                                companyRadios.forEach(function(r) { r.dataset.wasChecked = r.checked ? "true" : "false"; });
                                radio.dataset.wasChecked = "true";
                            }
                        });
                        radio.addEventListener("mousedown", function() {
                            radio.dataset.wasChecked = radio.checked ? "true" : "false";
                        });
                    });

                    // Select-all toggle (fake radio span).
                    var selectAllBtn = document.getElementById("smgp-select-all");
                    if (selectAllBtn) {
                        selectAllBtn.addEventListener("click", function() {
                            var isOn = selectAllBtn.classList.toggle("smgp-fake-radio--checked");
                            companyRadios.forEach(function(r) {
                                if (r.closest("tr").style.display !== "none") {
                                    r.checked = isOn;
                                    r.dataset.wasChecked = isOn ? "true" : "false";
                                }
                            });
                        });
                    }

                    // Real-time search filtering.
                    var searchInput = document.getElementById("smgp-company-search");
                    if (searchInput) {
                        searchInput.addEventListener("input", function() {
                            var q = searchInput.value.toLowerCase().trim();
                            var rows = document.querySelectorAll("#smgp-company-table tbody tr");
                            rows.forEach(function(row) {
                                var text = row.getAttribute("data-name") || "";
                                row.style.display = (!q || text.indexOf(q) !== -1) ? "" : "none";
                            });
                        });
                    }
                }

                // Intercept "Continuar" to set the category from the first selected company.
                var form = newSection.closest("form");
                if (form) {
                    form.addEventListener("submit", function(e) {
                        var checked = document.querySelectorAll("#smgp-company-table tbody input[type=radio]:checked");
                        if (checked.length === 0) return;

                        // Set the Moodle category radio to the first selected company\'s category.
                        var catId = checked[0].getAttribute("data-catid");
                        // Find or create hidden input for category.
                        var catInput = form.querySelector("input[name=\'targetid\']");
                        if (!catInput) {
                            catInput = document.createElement("input");
                            catInput.type = "hidden";
                            catInput.name = "targetid";
                            form.appendChild(catInput);
                        }
                        catInput.value = catId;

                        // Store all selected company IDs in sessionStorage for post-restore assignment.
                        var ids = [];
                        checked.forEach(function(cb) { ids.push(cb.value); });
                        sessionStorage.setItem("smgp_restore_companies", JSON.stringify(ids));
                    });
                }
            }
        }

        // ===== "RESTAURAR EN UN CURSO EXISTENTE" section =====
        var existingSection = selector.querySelector(".bcs-existing-course");
        if (existingSection) {
            var searchDiv2 = existingSection.querySelector(".restore-course-search");
            var tableParent2 = searchDiv2 ? searchDiv2.closest(".detail-pair") : null;

            if (tableParent2) {
                tableParent2.classList.add("smgp-select-fullwidth");

                // Real-time search for courses.
                var label2 = tableParent2.querySelector(".detail-pair-label");
                if (label2) {
                    var labelText = label2.textContent.trim();
                    var searchHtml2 = \'<div class="smgp-search-relocated">\' +
                        \'<input type="text" id="smgp-course-search" placeholder="\' + labelText + \'..." class="form-control">\' +
                        \'<span class="smgp-search-btn"><i class="fa fa-search smgp-green-icon"></i></span>\' +
                        \'</div>\';
                    label2.innerHTML = labelText + searchHtml2;
                    label2.style.display = "flex";
                    label2.style.justifyContent = "space-between";
                    label2.style.alignItems = "center";
                }

                // Hide ALL original search elements below the table.
                var hideTargets = searchDiv2.querySelectorAll("form, input, button");
                hideTargets.forEach(function(el) {
                    if (!el.closest(".smgp-search-relocated") && !el.closest("table")) {
                        el.style.display = "none";
                    }
                });

                // Fix the course table header — ensure the radio column header matches.
                // Style course table headers and inject a CSS rule
                // to extend the header background over the radio column area.
                var courseTable = tableParent2.querySelector("table");
                if (courseTable) {
                    courseTable.id = "smgp-course-table";
                    // Style ALL header cells (th AND td.header).
                    var headerCells = courseTable.querySelectorAll("thead th, thead td");
                    var maxH = 0;
                    headerCells.forEach(function(cell) {
                        cell.style.background = "#f8fafc";
                        cell.style.borderBottom = "1px solid #e2e8f0";
                        cell.style.borderTop = "none";
                        cell.style.borderLeft = "none";
                        cell.style.borderRight = "none";
                        cell.style.padding = "0.6rem 1rem";
                        cell.style.boxSizing = "border-box";
                        var h = cell.getBoundingClientRect().height;
                        if (h > maxH) maxH = h;
                    });
                    // Force all to the tallest height.
                    if (maxH > 0) {
                        headerCells.forEach(function(cell) {
                            cell.style.height = maxH + "px";
                        });
                    }
                }

                var courseSearch = document.getElementById("smgp-course-search");
                if (courseSearch) {
                    courseSearch.addEventListener("input", function() {
                        var q = courseSearch.value.toLowerCase().trim();
                        var table = tableParent2.querySelector("table");
                        if (!table) return;
                        var rows = table.querySelectorAll("tbody tr");
                        rows.forEach(function(row) {
                            var text = row.textContent.toLowerCase();
                            row.style.display = (!q || text.indexOf(q) !== -1) ? "" : "none";
                        });
                    });
                }
            }
        }
    });
    </script>';
}

/**
 * JS enhancements for the restore settings page (step 3).
 * Restyles the checkbox/settings list with bullets, checkboxes at end, full-width grid.
 *
 * @return string
 */
function local_sm_graphics_plugin_restore_settings_js(): string {
    return '<style>
    /* Settings page (step 3) — injected to win specificity */
    /* Scoped to #id_rootsettingscontainer — step 3 settings only */
    #id_rootsettingscontainer {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 0 2rem !important;
    }
    #id_rootsettingscontainer > .root_setting,
    #id_rootsettingscontainer > .include_setting,
    #id_rootsettingscontainer > .normal_setting {
        float: none !important;
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
    }
    #id_rootsettingscontainer .fitem.row {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: nowrap !important;
        padding: 0.5rem 0.5rem !important;
        margin: 0 !important;
        border-bottom: 1px solid #f8fafc;
        border-radius: 6px;
        transition: background 0.1s;
    }
    #id_rootsettingscontainer .fitem.row:hover {
        background: #f8fafc;
    }
    #id_rootsettingscontainer .fitem.row > .col-md-3 {
        flex: 1 !important;
        width: auto !important;
        max-width: none !important;
        padding: 0 !important;
        order: 1 !important;
        text-align: left !important;
    }
    #id_rootsettingscontainer .fitem.row > .col-md-3 label,
    #id_rootsettingscontainer .fitem.row > .col-md-3 p,
    #id_rootsettingscontainer .fitem.row > .col-md-3 span {
        font-size: 0.875rem !important;
        font-weight: 500 !important;
        color: #0f172a !important;
        margin: 0 !important;
        text-align: left !important;
    }
    #id_rootsettingscontainer .fitem.row > .col-md-9 {
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: none !important;
        padding: 0 !important;
        order: 2 !important;
        text-align: right !important;
    }
    #id_rootsettingscontainer .fitem.row > .col-md-9 input[type="checkbox"] {
        accent-color: #10b981;
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    /* Settings fieldset padding — scoped to step 3 only */
    .path-backup .mform fieldset:has(#id_rootsettingscontainer) {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }
    .path-backup .mform fieldset:has(#id_rootsettingscontainer) .ftoggler h3,
    .path-backup .mform fieldset:has(#id_rootsettingscontainer) > .d-flex > h3,
    .path-backup .mform fieldset:has(#id_rootsettingscontainer) > legend {
        margin-bottom: 0.75rem !important;
        padding-bottom: 0.5rem !important;
        border-bottom: none !important;
    }
    /* Buttons centered */
    .path-backup .mform #id_submitbuttons {
        display: flex !important;
        justify-content: center !important;
        gap: 0.75rem !important;
        padding-top: 1rem !important;
        border-top: 1px solid #e2e8f0 !important;
        margin-top: 0.5rem !important;
        grid-column: 1 / -1 !important;
    }
    .path-backup .mform #id_submitbuttons .col-md-9,
    .path-backup .mform #id_submitbuttons .col-md-3 {
        width: auto !important;
        max-width: none !important;
        flex: none !important;
        padding: 0 !important;
    }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Find setting items ONLY in step 3 container (not step 4 schema).
        var rootContainer = document.getElementById("id_rootsettingscontainer");
        if (!rootContainer) return;
        var items = rootContainer.querySelectorAll(".root_setting, .include_setting, .normal_setting");
        if (!items.length) return;
        var n = 1;
        items.forEach(function(item) {
            var fitem = item.querySelector(".fitem");
            if (!fitem) return;

            var col3 = fitem.querySelector(".col-md-3");
            var col9 = fitem.querySelector(".col-md-9");
            if (!col3 || !col9) return;

            // Find label text element.
            var label = col3.querySelector("span.d-inline-block");
            var isCheckbox = false;
            if (!label) {
                label = col9.querySelector("label");
                isCheckbox = true;
            }
            if (!label) label = col3.querySelector("label, p");
            if (!label || label.querySelector(".smgp-num")) return;

            // For checkbox items: move label text to col-md-3, checkbox to col-md-9.
            if (isCheckbox) {
                var checkbox = col9.querySelector("input[type=checkbox]");
                var labelText = label.textContent.trim();

                // Put label text in col-md-3.
                var newLabel = document.createElement("span");
                newLabel.className = "d-inline-block";
                newLabel.textContent = labelText;
                col3.insertBefore(newLabel, col3.firstChild);

                // Keep only checkbox in col-md-9.
                if (checkbox) {
                    col9.innerHTML = "";
                    col9.appendChild(checkbox);
                    col9.style.textAlign = "right";
                }

                label = newLabel;
            }

            // Add number badge.
            var num = document.createElement("span");
            num.className = "smgp-num";
            num.style.cssText = "display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;border-radius:50%;background:#e2e8f0;color:#475569;font-size:0.7rem;font-weight:600;margin-right:0.5rem;flex-shrink:0;";
            num.textContent = n;
            label.style.display = "flex";
            label.style.alignItems = "center";
            label.insertBefore(num, label.firstChild);
            n++;
        });
    });
    </script>';
}

function local_sm_graphics_plugin_restore_schema_fields(): string {
    global $DB, $PAGE;

    // Pre-populate from existing course when restoring over an existing course.
    $precourseid = (isset($PAGE->course->id) && $PAGE->course->id > 0 && $PAGE->course->id != SITEID)
                   ? (int) $PAGE->course->id : 0;
    $premeta   = ($precourseid > 0) ? $DB->get_record('local_smgp_course_meta', ['courseid' => $precourseid]) : null;
    $precatrow = ($precourseid > 0) ? $DB->get_record('local_smgp_course_category', ['courseid' => $precourseid]) : null;
    $preduration   = $premeta ? (float) $premeta->duration_hours : 0;
    $prelevel      = ($premeta && in_array($premeta->level, ['beginner', 'medium', 'advanced'])) ? $premeta->level : 'beginner';
    $precompletion = $premeta ? (int) $premeta->completion_percentage : 100;
    $presmcode     = $premeta ? htmlspecialchars($premeta->smartmind_code ?? '') : '';
    $presepecode   = $premeta ? htmlspecialchars($premeta->sepe_code ?? '') : '';
    $predescription = $premeta ? htmlspecialchars($premeta->description ?? '') : '';
    $precatid      = $precatrow ? (int) $precatrow->categoryid : 0;

    // Pre-load learning objectives from source course (source language only).
    $preobjectives = '[]';
    if ($precourseid > 0) {
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_smgp_learning_objectives')) {
            $objs = $DB->get_records('local_smgp_learning_objectives',
                ['courseid' => $precourseid, 'lang' => 'es'], 'sortorder ASC', 'objective');
            if (empty($objs)) {
                $objs = $DB->get_records_sql(
                    "SELECT DISTINCT objective FROM {local_smgp_learning_objectives}
                      WHERE courseid = ? ORDER BY sortorder ASC",
                    [$precourseid]
                );
            }
            if ($objs) {
                $texts = array_values(array_map(function($o) { return $o->objective; }, $objs));
                $preobjectives = htmlspecialchars(json_encode($texts, JSON_UNESCAPED_UNICODE));
            }
        }
    }

    $durationLabel = addslashes(get_string('course_hours', 'local_sm_graphics_plugin'));
    $categoryLabel = addslashes(get_string('course_category_field', 'local_sm_graphics_plugin'));
    $categoryNone  = addslashes(get_string('course_category_none', 'local_sm_graphics_plugin'));
    $smcodeLabel   = addslashes(get_string('smartmind_code', 'local_sm_graphics_plugin'));
    $sepeLabel     = addslashes(get_string('sepe_code', 'local_sm_graphics_plugin'));
    $descLabel     = addslashes(get_string('course_description', 'local_sm_graphics_plugin'));
    $levelLabel    = addslashes(get_string('course_level', 'local_sm_graphics_plugin'));
    $levelBeginner = addslashes(get_string('level_beginner', 'local_sm_graphics_plugin'));
    $levelMedium   = addslashes(get_string('level_medium', 'local_sm_graphics_plugin'));
    $levelAdvanced = addslashes(get_string('level_advanced', 'local_sm_graphics_plugin'));
    $completionLabel = addslashes(get_string('completion_percentage', 'local_sm_graphics_plugin'));
    $objectivesLabel = addslashes(get_string('objectives_header', 'local_sm_graphics_plugin'));
    $objectivesHint  = addslashes(get_string('objectives_restore_hint', 'local_sm_graphics_plugin'));

    // Help texts for tooltip hints.
    $durationHint   = addslashes(get_string('course_hours_help', 'local_sm_graphics_plugin'));
    $categoryHint   = addslashes(get_string('course_category_field_help', 'local_sm_graphics_plugin'));
    $smcodeHint     = addslashes(get_string('smartmind_code_help', 'local_sm_graphics_plugin'));
    $sepeHint       = addslashes(get_string('sepe_code_help', 'local_sm_graphics_plugin'));
    $levelHint      = addslashes(get_string('course_level_help', 'local_sm_graphics_plugin'));
    $completionHint = addslashes(get_string('completion_percentage_help', 'local_sm_graphics_plugin'));

    // Build category dropdown from DB.
    $catOptions = '<option value="0">' . htmlspecialchars($categoryNone) . '</option>';
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_smgp_categories')) {
        $cats = $DB->get_records('local_smgp_categories', null, 'sortorder ASC', 'id, name');
        foreach ($cats as $cat) {
            $selected = ($cat->id == $precatid) ? ' selected' : '';
            $catOptions .= '<option value="' . $cat->id . '"' . $selected . '>' . htmlspecialchars($cat->name) . '</option>';
        }
    }

    // Helper: build a label with icon and info tooltip.
    $lbl = function($icon, $text, $hint = '') {
        $h = '<label class="form-label fw-semibold d-flex align-items-center gap-1" style="color:#1e293b;">'
           . '<i class="' . $icon . '" style="margin-right:0.2rem;"></i>' . $text;
        if ($hint) {
            $h .= ' <i class="icon-info" style="font-size:0.8rem;color:#94a3b8;cursor:help;" title="' . $hint . '"></i>';
        }
        $h .= '</label>';
        return $h;
    };

    $html = '<div class="smgp-restore-fields" style="display:none"><div class="normal_setting">'
        // Section header.
        . '<h4 style="margin:1.5rem 0 1rem;font-weight:600;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:0.5rem;">'
        . '<i class="icon-settings" style="margin-right:0.4rem;"></i> SmartMind</h4>'
        // Row 1: 4 equal columns — Hours, Level, Completion %, Category.
        . '<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,0.8fr) minmax(0,1.7fr);gap:1rem;" class="mb-3">'
        . '<div style="min-width:0;">' . $lbl('icon-clock', $durationLabel, $durationHint)
        . '<input type="number" name="smgp_duration_hours" class="form-control" step="0.1" min="0" value="' . $preduration . '"></div>'
        . '<div style="min-width:0;">' . $lbl('icon-gauge', $levelLabel, $levelHint)
        . '<select name="smgp_level" class="form-select">'
        . '<option value="beginner"' . ($prelevel === 'beginner' ? ' selected' : '') . '>' . $levelBeginner . '</option>'
        . '<option value="medium"' . ($prelevel === 'medium' ? ' selected' : '') . '>' . $levelMedium . '</option>'
        . '<option value="advanced"' . ($prelevel === 'advanced' ? ' selected' : '') . '>' . $levelAdvanced . '</option>'
        . '</select></div>'
        . '<div style="min-width:0;">' . $lbl('icon-badge-check', $completionLabel, $completionHint)
        . '<div class="d-flex align-items-center gap-1"><input type="number" name="smgp_completion_percentage" class="form-control smgp-completion-input" min="0" max="100" value="' . $precompletion . '"><span>%</span></div></div>'
        . '<div style="min-width:0;">' . $lbl('icon-bookmark', $categoryLabel, $categoryHint)
        . '<select name="smgp_catalogue_cat" class="form-select">' . $catOptions . '</select></div>'
        . '</div>'
        // Row 2: 2 columns — SmartMind Code, SEPE Code.
        . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="mb-3">'
        . '<div>' . $lbl('icon-qr-code', $smcodeLabel, $smcodeHint)
        . '<input type="text" name="smgp_smartmind_code" class="form-control" value="' . $presmcode . '"></div>'
        . '<div>' . $lbl('icon-file-code', $sepeLabel, $sepeHint)
        . '<input type="text" name="smgp_sepe_code" class="form-control" value="' . $presepecode . '"></div>'
        . '</div>'
        // Row 3: Full-width — Course description with editor.
        . '<div class="mb-3">' . $lbl('icon-file-text', $descLabel, addslashes(get_string('restore_desc_hint', 'local_sm_graphics_plugin')))
        . '<div id="smgp-restore-editor-wrap">'
        . '<textarea name="smgp_description" id="smgp-restore-description" class="form-control" rows="5">' . $predescription . '</textarea>'
        . '</div></div>'
        // Row 4: Full-width — Learning objectives.
        . '<div class="mb-3">' . $lbl('icon-list-checks', $objectivesLabel, addslashes(get_string('restore_objectives_hint', 'local_sm_graphics_plugin')))
        . '<input type="hidden" name="smgp_objectives_data" value="' . $preobjectives . '">'
        . '<div id="smgp-objectives-container" class="smgp-objectives-editor"></div></div>'
        // Hidden input for course structure data (populated by course_structure.js).
        . '<input type="hidden" name="smgp_course_structure" value="">'
        . '</div></div>';

    $html .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var fields = document.querySelector(".smgp-restore-fields");
        if (!fields) return;

        // Check which step we are on.
        var currentStep = document.querySelector(".backup_stage_current");
        var stepText = currentStep ? currentStep.textContent.trim() : "";
        var isSchema = (stepText.indexOf("4") !== -1 || stepText.indexOf("Schema") !== -1 || stepText.indexOf("Esquema") !== -1);

        // On Review page (step 5) — show read-only summary of SmartMind values.
        if (!isSchema) {
            var container = document.getElementById("id_coursesettingscontainer");
            if (!container) return;
            try {
                var saved = JSON.parse(sessionStorage.getItem("smgp_restore_fields") || "{}");
                if (!saved || typeof saved !== "object") return;
                var hasValues = Object.keys(saved).some(function(k) { return saved[k] && saved[k] !== "[]"; });
                if (!hasValues) return;

                var smLabels = ' . json_encode([
                    'smgp_duration_hours' => get_string('course_hours', 'local_sm_graphics_plugin'),
                    'smgp_level' => get_string('course_level', 'local_sm_graphics_plugin'),
                    'smgp_completion_percentage' => get_string('completion_percentage', 'local_sm_graphics_plugin'),
                    'smgp_catalogue_cat' => get_string('course_category_field', 'local_sm_graphics_plugin'),
                    'smgp_smartmind_code' => get_string('smartmind_code', 'local_sm_graphics_plugin'),
                    'smgp_sepe_code' => get_string('sepe_code', 'local_sm_graphics_plugin'),
                    'smgp_description' => get_string('course_description', 'local_sm_graphics_plugin'),
                ]) . ';
                var smLevelLabels = ' . json_encode([
                    'beginner' => get_string('level_beginner', 'local_sm_graphics_plugin'),
                    'medium' => get_string('level_medium', 'local_sm_graphics_plugin'),
                    'advanced' => get_string('level_advanced', 'local_sm_graphics_plugin'),
                ]) . ';
                var smObjLabel = ' . json_encode(get_string('objectives_header', 'local_sm_graphics_plugin')) . ';

                var h = "<div class=\\"smgp-restore-fields\\" style=\\"margin-top:1rem;\\">" +
                    "<div class=\\"normal_setting\\" style=\\"width:100%!important;float:none!important;clear:both!important;display:block!important;\\">" +
                    "<h4 style=\\"margin:1rem 0 0.75rem;font-weight:600;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:0.5rem;\\">" +
                    "<i class=\\"icon-settings\\" style=\\"margin-right:0.4rem;\\"></i> SmartMind</h4>" +
                    "<div style=\\"display:grid;grid-template-columns:1fr 1fr;gap:0.5rem 2rem;\\">";

                ["smgp_duration_hours","smgp_level","smgp_completion_percentage","smgp_catalogue_cat","smgp_smartmind_code","smgp_sepe_code"].forEach(function(key) {
                    var val = saved[key] || "-";
                    if (key === "smgp_level") val = smLevelLabels[val] || val;
                    if (key === "smgp_completion_percentage" && val !== "-") val = val + "%";
                    h += "<div style=\\"padding:0.35rem 0;border-bottom:1px solid #f1f5f9;\\">" +
                        "<span style=\\"font-size:0.8rem;color:#64748b;\\">" + smLabels[key] + "</span><br>" +
                        "<span style=\\"font-size:0.875rem;font-weight:500;color:#1e293b;\\">" + val + "</span></div>";
                });
                h += "</div>";

                // Description — always show.
                var descVal = saved.smgp_description || "-";
                h += "<div style=\\"margin-top:0.5rem;padding:0.35rem 0;border-bottom:1px solid #f1f5f9;\\">" +
                    "<span style=\\"font-size:0.8rem;color:#64748b;\\">" + smLabels.smgp_description + "</span><br>" +
                    "<div style=\\"font-size:0.875rem;color:#1e293b;margin-top:0.25rem;\\">" + descVal + "</div></div>";

                // Objectives — always show.
                var objsHtml = "-";
                if (saved.smgp_objectives_data && saved.smgp_objectives_data !== "[]") {
                    try {
                        var objs = JSON.parse(saved.smgp_objectives_data);
                        if (Array.isArray(objs) && objs.length > 0) {
                            objsHtml = "<ul style=\\"margin:0.25rem 0 0 1rem;padding:0;font-size:0.875rem;color:#1e293b;\\">";
                            objs.forEach(function(o) { if (o.trim()) objsHtml += "<li>" + o + "</li>"; });
                            objsHtml += "</ul>";
                        }
                    } catch(ex) {}
                }
                h += "<div style=\\"margin-top:0.5rem;padding:0.35rem 0;border-bottom:1px solid #f1f5f9;\\">" +
                    "<span style=\\"font-size:0.8rem;color:#64748b;\\">" + smObjLabel + "</span><br>" +
                    "<div style=\\"font-size:0.875rem;color:#1e293b;margin-top:0.25rem;\\">" + objsHtml + "</div></div>";

                h += "</div></div>";

                var settingDivs = container.querySelectorAll(":scope > .normal_setting");
                var lastSetting = settingDivs[settingDivs.length - 1];
                if (lastSetting) {
                    lastSetting.insertAdjacentHTML("afterend", h);
                }

                // Restyle Moodle fields to match SmartMind layout (label on top, value below).
                settingDivs.forEach(function(sd) {
                    var fitem = sd.querySelector(".fitem");
                    if (!fitem) return;
                    var col3 = fitem.querySelector(".col-md-3");
                    var col9 = fitem.querySelector(".col-md-9");
                    if (!col3 || !col9) return;

                    var labelEl = col3.querySelector("span.d-inline-block, label, p");
                    var valueEl = col9.querySelector(".form-control-static, input, select");
                    var labelText = labelEl ? labelEl.textContent.trim() : "";
                    var valueText = valueEl ? valueEl.textContent.trim() : "";

                    fitem.style.cssText = "display:block!important;padding:0.35rem 0;border-bottom:1px solid #f1f5f9;";
                    fitem.classList.remove("row");
                    col3.style.cssText = "width:100%!important;max-width:none!important;padding:0!important;";
                    col3.classList.remove("col-md-3","col-form-label","d-flex","pb-0","pe-md-0");
                    col9.style.cssText = "width:100%!important;max-width:none!important;padding:0!important;";
                    col9.classList.remove("col-md-9");

                    if (labelEl) {
                        labelEl.style.cssText = "font-size:0.8rem;color:#64748b;display:block;margin-bottom:0.1rem;";
                    }
                    if (valueEl) {
                        valueEl.style.cssText = "font-size:0.875rem;font-weight:500;color:#1e293b;";
                    }
                });

            } catch(e) {}
            return;
        }

        var container = document.getElementById("id_coursesettingscontainer");
        if (!container) return;

        // --- Restyle existing Moodle fields ---
        var moodleFields = [
            {id: "id_setting_course_course_fullname_label", icon: "icon-type",
             fitem: "fitem_id_setting_course_course_fullname"},
            {id: "id_setting_course_course_shortname_label", icon: "icon-hash",
             fitem: "fitem_id_setting_course_course_shortname"},
            {id: "id_setting_course_course_startdate_label", icon: "icon-calendar",
             fitem: "fitem_id_setting_course_course_startdate"}
        ];
        moodleFields.forEach(function(f) {
            // Add icon to the visible <p> label.
            var el = document.getElementById(f.id);
            if (el && !el.querySelector(".smgp-icon-added")) {
                el.style.fontWeight = "600";
                el.style.color = "#1e293b";
                el.style.fontSize = "0.9rem";
                var icon = document.createElement("i");
                icon.className = f.icon + " smgp-icon-added";
                icon.style.marginRight = "0.3rem";
                el.insertBefore(icon, el.firstChild);
            }
            // Restyle the fitem: label on top, input below (instead of side-by-side).
            var fitem = document.getElementById(f.fitem);
            if (fitem) {
                fitem.classList.remove("row");
                fitem.style.display = "flex";
                fitem.style.flexDirection = "column";
                fitem.style.gap = "0.25rem";
                // Remove col-md-3/col-md-9 column classes.
                var labelCol = fitem.querySelector(".col-md-3");
                var inputCol = fitem.querySelector(".col-md-9");
                if (labelCol) {
                    labelCol.classList.remove("col-md-3", "col-form-label", "d-flex", "pb-0", "pe-md-0");
                    labelCol.style.padding = "0";
                }
                if (inputCol) {
                    inputCol.classList.remove("col-md-9");
                    inputCol.style.padding = "0";
                }
            }
        });

        // Place SmartMind section after the 3rd normal_setting (startdate).
        // Force it to span both columns of the fcontainer grid.
        var settingDivs = container.querySelectorAll(":scope > .normal_setting");
        fields.style.display = "";
        var insertAfter = settingDivs[2] || settingDivs[settingDivs.length - 1];
        if (insertAfter) {
            insertAfter.after(fields);
        } else {
            container.appendChild(fields);
        }
        // Override styles for the restore page.
        var style = document.createElement("style");
        style.textContent = ""
            // Force SmartMind section full width (override float:left width:50%).
            + ".smgp-restore-fields, .smgp-restore-fields > .normal_setting "
            + "{ width: 100% !important; float: none !important; clear: both !important; display: block !important; }"
            + ".smgp-restore-fields .form-control, .smgp-restore-fields .form-select "
            + "{ width: 100% !important; max-width: 100% !important; }";
        document.head.appendChild(style);

        // --- Preserve values across restore steps via sessionStorage + PHP session ---
        var storageKey = "smgp_restore_fields";
        var fieldNames = ["smgp_description", "smgp_duration_hours", "smgp_catalogue_cat",
                          "smgp_smartmind_code", "smgp_sepe_code", "smgp_level", "smgp_completion_percentage",
                          "smgp_objectives_data"];
        // Destination course ID (0 for new-course restores, known for restore-over-existing).
        var smgpDestCourseId = ' . (int) $precourseid . ';

        // If courseid is unknown from PHP, try to extract from URL (contextid -> courseid
        // is not directly available, but we can fall back to 0 and let the observer
        // use the event courseid instead).
        if (!smgpDestCourseId) {
            try {
                var m = window.location.search.match(/[?&]course[id]?=(\d+)/i);
                if (m) smgpDestCourseId = parseInt(m[1]);
            } catch(ex) {}
        }

        // Restore saved values from previous step (sessionStorage fallback).
        try {
            var saved = JSON.parse(sessionStorage.getItem(storageKey) || "{}");
            fieldNames.forEach(function(name) {
                if (saved[name] !== undefined && saved[name] !== "") {
                    var el = fields.querySelector("[name=\'" + name + "\']");
                    if (el) el.value = saved[name];
                }
            });
        } catch(e) {}

        // Helper: collect all field values.
        function collectFields() {
            if (window.tinymce) { try { window.tinymce.triggerSave(); } catch(x) {} }
            var data = {};
            fieldNames.forEach(function(name) {
                var el = fields.querySelector("[name=\'" + name + "\']");
                if (el) data[name] = el.value;
            });
            return data;
        }

        // Helper: save all field values to sessionStorage AND PHP session.
        // Uses synchronous XHR (keepalive) to ensure the request completes
        // even when the browser is navigating away (form submit).
        function saveToSession() {
            try {
                var data = collectFields();
                sessionStorage.setItem(storageKey, JSON.stringify(data));
                // Persist server-side via synchronous XHR so it survives page navigation.
                var payload = JSON.stringify([{
                    index: 0,
                    methodname: "local_sm_graphics_plugin_save_restore_fields",
                    args: {
                        courseid:    smgpDestCourseId || 0,
                        fields_json: JSON.stringify(data)
                    }
                }]);
                var xhr = new XMLHttpRequest();
                xhr.open("POST", M.cfg.wwwroot + "/lib/ajax/service.php?sesskey=" + M.cfg.sesskey, false);
                xhr.setRequestHeader("Content-Type", "application/json");
                xhr.send(payload);
            } catch(e) {}
        }

        // Save on form submit and button clicks.
        document.addEventListener("submit", saveToSession, true);
        document.addEventListener("click", function(e) {
            var btn = e.target.closest("input[type=\'submit\'], button[type=\'submit\'], .btn-primary");
            if (btn) saveToSession();
        }, true);

        // Initialize drag-and-drop objectives editor (reuse AMD module).
        if (typeof require !== "undefined") {
            require(["local_sm_graphics_plugin/course_objectives"], function(Obj) {
                Obj.init();
            });
            // Initialize course structure editor (restyle + interactive).
            require(["local_sm_graphics_plugin/course_structure"], function(S) {
                S.init();
            });
        }

        // Initialize TinyMCE rich text editor on description field.
        (function initEditor() {
            var descEl = document.getElementById("smgp-restore-description");
            if (!descEl) return;

            // Try loading TinyMCE from Moodle\'s lib path.
            function loadTiny() {
                if (window.tinymce) {
                    startTiny();
                    return;
                }
                var script = document.createElement("script");
                script.src = M.cfg.wwwroot + "/lib/editor/tiny/js/tinymce/tinymce.min.js";
                script.onload = startTiny;
                script.onerror = function() {}; // Fallback: plain textarea.
                document.head.appendChild(script);
            }

            function startTiny() {
                if (!window.tinymce) return;
                window.tinymce.init({
                    target: descEl,
                    menubar: false,
                    statusbar: false,
                    toolbar: "bold italic underline strikethrough | bullist numlist | link removeformat",
                    plugins: "lists link",
                    height: 220,
                    skin: "oxide",
                    content_css: false,
                    promotion: false,
                    branding: false,
                    setup: function(editor) {
                        // Sync editor content back to textarea on change (for sessionStorage).
                        editor.on("change input", function() {
                            editor.save();
                        });
                    }
                });
            }

            loadTiny();
        })();
    });
    </script>';

    return $html;
}

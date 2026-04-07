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
 * Theme functions.
 *
 * @package    theme_smartmind
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build the IOMAD company selector HTML for the top navbar.
 *
 * @return string HTML for the company selector dropdown, or empty string.
 */
function theme_smartmind_get_company_selector(): string {
    global $CFG, $USER, $SESSION;

    if (!isloggedin() || isguestuser()) {
        return '';
    }
    if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
        return '';
    }

    require_once($CFG->dirroot . '/local/iomad/lib/company.php');

    $syscontext = context_system::instance();
    if (!iomad::has_capability('block/iomad_company_admin:company_add', $syscontext)) {
        return '';
    }

    $selectedcompany = '';
    if (!empty($SESSION->currenteditingcompany)) {
        $selectedcompany = $SESSION->currenteditingcompany;
    } else if (!empty($USER->profile->company)) {
        try {
            $usercompany = company::by_userid($USER->id);
            $selectedcompany = $usercompany->id;
        } catch (\Exception $e) {
            // User has no company.
        }
    }

    $companylist = company::get_companies_select();
    if (empty($companylist)) {
        return '';
    }

    // Get current company name.
    $currentname = get_string('select_company', 'theme_smartmind');
    if (!empty($selectedcompany) && isset($companylist[$selectedcompany])) {
        $currentname = $companylist[$selectedcompany];
    }

    // Build a custom dropdown (no ugly select box).
    $actionurl = $CFG->wwwroot . '/blocks/iomad_company_admin/index.php';
    $html = '<div class="smgp-company-dropdown position-relative" tabindex="-1"'
        . ' onfocusout="var self=this;setTimeout(function(){if(!self.contains(document.activeElement)){document.getElementById(\'smgp-company-menu\').classList.remove(\'show\');document.getElementById(\'smgp-company-toggle\').setAttribute(\'aria-expanded\',\'false\');}},100);">';
    $html .= '<button type="button" class="smgp-company-dropdown__toggle" id="smgp-company-toggle"'
        . ' aria-expanded="false"'
        . ' onclick="var m=document.getElementById(\'smgp-company-menu\');var o=m.classList.toggle(\'show\');this.setAttribute(\'aria-expanded\',o);">'
        . '<span class="smgp-company-dropdown__name">' . s($currentname) . '</span>'
        . ' <i class="icon-chevron-down" style="font-size:0.75em;"></i>'
        . '</button>';
    $html .= '<div class="smgp-company-dropdown__menu" id="smgp-company-menu">';
    foreach ($companylist as $cid => $cname) {
        $active = ($cid == $selectedcompany) ? ' smgp-company-dropdown__item--active' : '';
        $html .= '<a href="' . $actionurl . '?company=' . $cid . '"'
            . ' class="smgp-company-dropdown__item' . $active . '">'
            . s($cname) . '</a>';
    }
    $html .= '</div></div>';

    return $html;
}

/**
 * Post process the CSS tree.
 *
 * @param string $tree The CSS tree.
 * @param theme_config $theme The theme config object.
 */
function theme_smartmind_css_tree_post_processor($tree, $theme) {
    error_log('theme_smartmind_css_tree_post_processor() is deprecated. Required' .
        'prefixes for Bootstrap are now in theme/smartmind/scss/moodle/prefixes.scss');
    $prefixer = new theme_smartmind\autoprefixer($tree);
    $prefixer->prefix();
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_smartmind_get_extra_scss($theme) {
    $content = '';
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');

    // Sets the background image, and its settings.
    if (!empty($imageurl)) {
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl'); background-size: cover;";
        $content .= ' } }';
    }

    // Sets the login background image.
    $loginbackgroundimageurl = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($loginbackgroundimageurl)) {
        $content .= 'body.pagelayout-login #page { ';
        $content .= "background-image: url('$loginbackgroundimageurl'); background-size: cover;";
        $content .= ' }';
    }

    // Always return the background image with the scss when we have it.
    return !empty($theme->settings->scss) ? "{$theme->settings->scss}  \n  {$content}" : $content;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_smartmind_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage' ||
        $filearea === 'loginbackgroundimage')) {
        $theme = theme_config::load('smartmind');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

/**
 * Get the current user preferences that are available
 *
 * @return array[]
 */
function theme_smartmind_user_preferences(): array {
    return [
        'drawer-open-block' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'drawer-open-index' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_smartmind_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/smartmind/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/smartmind/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_smartmind', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/smartmind/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get compiled css.
 *
 * @return string compiled css
 */
function theme_smartmind_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/smartmind/style/moodle.css');
}

/**
 * Build custom sidebar navigation for IOMAD company managers.
 *
 * When the current user is a company manager (managertype = 1 in the
 * company_users table) the sidebar shows a focused set of links instead
 * of the default Moodle primary nav.
 *
 * @return array  Empty when the user is not a company manager; otherwise an
 *                array of nav-item arrays ready for the sidemenu template.
 */
function theme_smartmind_get_companymanager_nav(): array {
    global $CFG, $DB, $USER, $PAGE;

    // Bail out early when IOMAD is not installed.
    if (!file_exists($CFG->dirroot . '/blocks/iomad_company_admin')) {
        return [];
    }

    // Check the company_users table: managertype 1 = company manager.
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('company_users')) {
        return [];
    }
    if (!$DB->record_exists('company_users', ['userid' => $USER->id, 'managertype' => 1])) {
        return [];
    }

    $currenturl = $PAGE->url->out(false);

    $nav = [];

    $spa = fn(string $route) => (function() use ($route) {
        $u = new moodle_url('/local/sm_graphics_plugin/pages/spa.php');
        $u->set_anchor('/' . ltrim($route, '/'));
        return $u;
    })();

    // 1. User management — Vue SPA.
    $usersurl = $spa('management/users');
    $nav[] = [
        'url'      => $usersurl->out(),
        'text'     => get_string('nav_usermanagement', 'theme_smartmind'),
        'key'      => 'sm-usermanagement',
        'isactive' => strpos($currenturl, 'usermanagement') !== false
                      || strpos($currenturl, 'company_user_create_form') !== false
                      || strpos($currenturl, 'editusers') !== false
                      || strpos($currenturl, 'editadvanced') !== false
                      || strpos($currenturl, 'company_users') !== false
                      || strpos($currenturl, 'company_department_users') !== false
                      || strpos($currenturl, 'uploaduser') !== false
                      || strpos($currenturl, 'user_bulk_download') !== false,
        'disabled' => false,
        'badge'    => '',
    ];

    // 2. Other management — links to the custom options page.
    $otherurl = $spa('management');
    $nav[] = [
        'url'      => $otherurl->out(),
        'text'     => get_string('nav_othermanagement', 'theme_smartmind'),
        'key'      => 'sm-othermanagement',
        'isactive' => strpos($currenturl, 'othermanagement') !== false
                      || strpos($currenturl, 'iomad_dashboard') !== false
                      || strpos($currenturl, 'company_edit') !== false
                      || strpos($currenturl, 'company_courses') !== false
                      || strpos($currenturl, 'company_license') !== false
                      || strpos($currenturl, 'company_competency') !== false
                      || (strpos($currenturl, 'iomad_company_admin') !== false
                          && strpos($currenturl, 'company_users') === false
                          && strpos($currenturl, 'company_user_create_form') === false
                          && strpos($currenturl, 'editusers') === false
                          && strpos($currenturl, 'editadvanced') === false
                          && strpos($currenturl, 'company_department_users') === false
                          && strpos($currenturl, 'uploaduser') === false
                          && strpos($currenturl, 'user_bulk_download') === false),
        'disabled' => false,
        'badge'    => '',
    ];

    // 3. Grades & Certificates.
    $nav[] = [
        'url'      => $spa('grades-certificates')->out(),
        'text'     => get_string('nav_gradescerts', 'theme_smartmind'),
        'key'      => 'sm-gradescerts',
        'isactive' => strpos($currenturl, 'grades_certificates') !== false,
        'disabled' => false,
        'badge'    => '',
    ];

    // 4. Statistics.
    $statsurl = $spa('statistics');
    $nav[] = [
        'url'      => $statsurl->out(),
        'text'     => get_string('nav_statistics', 'theme_smartmind'),
        'key'      => 'sm-statistics',
        'isactive' => strpos($currenturl, 'statistics') !== false,
        'disabled' => false,
        'badge'    => '',
    ];

    return $nav;
}

/**
 * Inject "Grades & Certificates" nav node into primary menu for students.
 *
 * Students = logged-in, non-guest, non-admin, non-company-manager users.
 * Call this from every layout file after building $primarymenu.
 *
 * @param array &$primarymenu  The primary menu array from export_for_template().
 * @param array $companymanagernav  The company manager nav (empty = not a manager).
 * @param moodle_page $PAGE  The current page object.
 */
function theme_smartmind_inject_student_nav(array &$primarymenu, ?array $companymanagernav, $PAGE) {
    if (!empty($companymanagernav) || !isloggedin() || isguestuser() || is_siteadmin()) {
        return;
    }

    $mycoursesnode = [
        'key' => 'sm-mycourses',
        'text' => get_string('mycourses_nav', 'local_sm_graphics_plugin'),
        'url' => (function() { $u = new moodle_url('/local/sm_graphics_plugin/pages/spa.php'); $u->set_anchor('/courses'); return $u->out(false); })(),
        'action' => '',
        'isactive' => strpos($PAGE->url->out(false), 'mycourses') !== false,
        'haschildren' => false,
        'disabled' => false,
        'title' => '',
        'classes' => [],
    ];

    $gradescertsnode = [
        'key' => 'sm-gradescerts',
        'text' => get_string('gradescerts_nav', 'local_sm_graphics_plugin'),
        'url' => (function() { $u = new moodle_url('/local/sm_graphics_plugin/pages/spa.php'); $u->set_anchor('/grades-certificates'); return $u->out(false); })(),
        'action' => '',
        'isactive' => strpos($PAGE->url->out(false), 'grades_certificates') !== false,
        'haschildren' => false,
        'disabled' => false,
        'title' => '',
        'classes' => [],
    ];

    $profilenode = [
        'key' => 'sm-profile',
        'text' => get_string('myprofile', 'moodle'),
        'url' => (function() { $u = new moodle_url('/local/sm_graphics_plugin/pages/spa.php'); $u->set_anchor('/profile'); return $u->out(false); })(),
        'action' => '',
        'isactive' => strpos($PAGE->url->out(false), 'profile') !== false,
        'haschildren' => false,
        'disabled' => false,
        'title' => '',
        'classes' => [],
    ];

    if (!empty($primarymenu['moremenu']['nodecollection']['nodes'])) {
        $nodes = &$primarymenu['moremenu']['nodecollection']['nodes'];
        $insertpos = count($nodes);
        foreach ($nodes as $i => $n) {
            if (($n['key'] ?? '') === 'siteadminnode') {
                $insertpos = $i;
                break;
            }
        }
        array_splice($nodes, $insertpos, 0, [$mycoursesnode, $gradescertsnode, $profilenode]);
        unset($nodes);
    } else if (!empty($primarymenu['moremenu']['nodearray'])) {
        $primarymenu['moremenu']['nodearray'][] = $mycoursesnode;
        $primarymenu['moremenu']['nodearray'][] = $gradescertsnode;
        $primarymenu['moremenu']['nodearray'][] = $profilenode;
    }
}

/**
 * Rename primary navigation labels in the exported menu array.
 *
 * extend_navigation() only touches global_navigation; the primary navigation
 * used by the sidebar is a separate object, so we rename nodes here after
 * export_for_template().
 *
 * @param array &$primarymenu The primary menu array from export_for_template().
 */
function theme_smartmind_rename_primary_nav(array &$primarymenu) {
    $renames = [
        'home'      => get_string('nav_home',      'local_sm_graphics_plugin'),
        'myhome'    => get_string('nav_dashboard',  'local_sm_graphics_plugin'),
    ];

    // For site admins, redirect "mycourses" to course management page.
    // For everyone else, hide "mycourses" entirely.
    $adminoverrides = [];
    $removenodes    = [];
    if (is_siteadmin()) {
        $adminoverrides['mycourses'] = [
            'text' => get_string('nav_coursemanagement', 'local_sm_graphics_plugin'),
            'url'  => (function() { $u = new \moodle_url('/local/sm_graphics_plugin/pages/spa.php'); $u->set_anchor('/management/courses'); return $u->out(false); })(),
        ];
        $removenodes[] = 'myhome';
    } else {
        $removenodes[] = 'mycourses';
    }

    // Desired order: myhome first, then home (catalogue), then mycourses.
    $desiredorder = ['myhome', 'home', 'sm-mycourses'];

    // nodecollection path.
    if (!empty($primarymenu['moremenu']['nodecollection']['nodes'])) {
        $primarymenu['moremenu']['nodecollection']['nodes'] = array_values(
            array_filter($primarymenu['moremenu']['nodecollection']['nodes'], function($node) use ($removenodes) {
                return !in_array($node['key'] ?? '', $removenodes);
            })
        );
        foreach ($primarymenu['moremenu']['nodecollection']['nodes'] as &$node) {
            $key = $node['key'] ?? '';
            if (isset($adminoverrides[$key])) {
                $node['text'] = $adminoverrides[$key]['text'];
                $node['url']  = $adminoverrides[$key]['url'];
            } else if (isset($renames[$key])) {
                $node['text'] = $renames[$key];
            }
        }
        unset($node);
        $primarymenu['moremenu']['nodecollection']['nodes'] =
            theme_smartmind_reorder_nodes($primarymenu['moremenu']['nodecollection']['nodes'], $desiredorder);
    }

    // nodearray path.
    if (!empty($primarymenu['moremenu']['nodearray'])) {
        $primarymenu['moremenu']['nodearray'] = array_values(
            array_filter($primarymenu['moremenu']['nodearray'], function($node) use ($removenodes) {
                return !in_array($node['key'] ?? '', $removenodes);
            })
        );
        foreach ($primarymenu['moremenu']['nodearray'] as &$node) {
            $key = $node['key'] ?? '';
            if (isset($adminoverrides[$key])) {
                $node['text'] = $adminoverrides[$key]['text'];
                $node['url']  = $adminoverrides[$key]['url'];
            } else if (isset($renames[$key])) {
                $node['text'] = $renames[$key];
            }
        }
        unset($node);
        $primarymenu['moremenu']['nodearray'] =
            theme_smartmind_reorder_nodes($primarymenu['moremenu']['nodearray'], $desiredorder);
    }
}

/**
 * Reorder nav nodes so that keys in $order appear first, in order.
 *
 * @param array $nodes The nav nodes array.
 * @param array $order List of keys that should appear first.
 * @return array Reordered nodes.
 */
function theme_smartmind_reorder_nodes(array $nodes, array $order): array {
    $keyed = [];
    $rest = [];
    foreach ($nodes as $node) {
        $key = $node['key'] ?? '';
        if (in_array($key, $order)) {
            $keyed[$key] = $node;
        } else {
            $rest[] = $node;
        }
    }
    $sorted = [];
    foreach ($order as $key) {
        if (isset($keyed[$key])) {
            $sorted[] = $keyed[$key];
        }
    }
    return array_merge($sorted, $rest);
}

/**
 * Ensure only one navigation item is marked active.
 *
 * Moodle can mark multiple nodes as active (e.g. "home" on every page).
 * This function keeps only the most specific active item:
 * custom sm-* items take priority over Moodle's built-in ones.
 *
 * @param array &$primarymenu The primary menu array.
 */
function theme_smartmind_fix_active_nav(array &$primarymenu) {
    global $PAGE;

    $currenturl = $PAGE->url->out(false);
    $currentpath = parse_url($currenturl, PHP_URL_PATH);

    // Map current page paths to the nav node key that should be active.
    // This handles pages whose URL doesn't match the node's URL directly.
    $pathtokeymap = [
        '/local/sm_graphics_plugin/pages/iomaddashboard.php' => 'ioaddashboardnode',
        '/local/sm_estratoos_plugin/index.php'               => 'ioaddashboardnode',
        '/blocks/iomad_company_admin/index.php'              => 'ioaddashboardnode',
        '/local/sm_graphics_plugin/pages/coursemanagement.php' => 'mycourses',
    ];

    $forcedkey = $pathtokeymap[$currentpath] ?? null;

    $paths = [
        ['moremenu', 'nodecollection', 'nodes'],
        ['moremenu', 'nodearray'],
    ];

    foreach ($paths as $path) {
        $ref = &$primarymenu;
        $valid = true;
        foreach ($path as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $valid = false;
                break;
            }
            $ref = &$ref[$key];
        }
        if (!$valid || empty($ref)) {
            continue;
        }

        // First pass: find match by forced key map or by URL.
        $match = null;
        foreach ($ref as $i => &$node) {
            $nodekey = $node['key'] ?? '';

            // Match by key map.
            if ($forcedkey !== null && $nodekey === $forcedkey) {
                $match = $i;
                break;
            }

            // Match by URL path.
            $nodeurl = $node['url'] ?? '';
            if (is_object($nodeurl) && method_exists($nodeurl, 'out')) {
                $nodeurl = $nodeurl->out(false);
            }
            if (!empty($nodeurl) && is_string($nodeurl)) {
                $nodepath = parse_url($nodeurl, PHP_URL_PATH);
                if ($nodepath && $currentpath === $nodepath) {
                    $match = $i;
                    break;
                }
            }
        }
        unset($node);

        // If we found a match, mark only that node as active.
        if ($match !== null) {
            foreach ($ref as $i => &$node) {
                $ref[$i]['isactive'] = ($i === $match);
            }
            unset($node);
            continue;
        }

        // No match — fall back to keeping only one active node.
        $activeindexes = [];
        $customactive = null;
        foreach ($ref as $i => &$node) {
            if (!empty($node['isactive'])) {
                $activeindexes[] = $i;
                if (strpos($node['key'] ?? '', 'sm-') === 0) {
                    $customactive = $i;
                }
            }
        }
        unset($node);

        if (count($activeindexes) > 1) {
            $keep = $customactive !== null ? $customactive : end($activeindexes);
            foreach ($activeindexes as $idx) {
                if ($idx !== $keep) {
                    $ref[$idx]['isactive'] = false;
                }
            }
        }
    }
}

/**
 * Strip user-menu items, keeping only Preferences and Log out.
 *
 * @param array &$primarymenu The primary menu array from export_for_template().
 */
function theme_smartmind_filter_usermenu(array &$primarymenu) {
    if (empty($primarymenu['user']['items'])) {
        return;
    }

    $keepurls = [
        '/user/preferences.php',
        '/login/logout.php',
    ];

    $filtered = [];
    foreach ($primarymenu['user']['items'] as $item) {
        // Items may be arrays or stdClass objects.
        $arr = is_object($item) ? (array) $item : $item;

        // Keep dividers that sit right before a kept item (we'll clean up later).
        if (!empty($arr['divider'])) {
            $filtered[] = $item;
            continue;
        }

        $url = '';
        if (!empty($arr['link'])) {
            $link = is_object($arr['link']) ? (array) $arr['link'] : $arr['link'];
            $url = $link['url'] ?? '';
        } else if (!empty($arr['submenulink'])) {
            // Submenu links (e.g. Language) — drop them.
            continue;
        }

        $keep = false;
        foreach ($keepurls as $pattern) {
            if (strpos($url, $pattern) !== false) {
                $keep = true;
                break;
            }
        }

        if (!$keep) {
            // Remove the divider that preceded this dropped item.
            if (!empty($filtered)) {
                $last = is_object(end($filtered)) ? (array) end($filtered) : end($filtered);
                if (!empty($last['divider'])) {
                    array_pop($filtered);
                }
            }
            continue;
        }

        $filtered[] = $item;
    }

    // Remove leading/trailing dividers.
    $isDivider = function ($el) {
        $a = is_object($el) ? (array) $el : $el;
        return !empty($a['divider']);
    };
    while (!empty($filtered) && $isDivider(reset($filtered))) {
        array_shift($filtered);
    }
    while (!empty($filtered) && $isDivider(end($filtered))) {
        array_pop($filtered);
    }

    $primarymenu['user']['items'] = array_values($filtered);
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_smartmind_get_pre_scss($theme) {
    global $CFG;

    $scss = '';
    $configurable = [
        // Config key => [variableName, ...].
        'brandcolor' => ['primary'],
    ];

    // Prepend variables first.
    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    // Add a new variable to indicate that we are running behat.
    if (defined('BEHAT_SITE_RUNNING')) {
        $scss .= "\$behatsite: true;\n";
    }

    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

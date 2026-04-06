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

namespace local_sm_graphics_plugin\task;

/**
 * Scheduled task: sync SharePoint course folders into local cache.
 *
 * Scans the configured SharePoint site's Shared Documents for folders
 * containing .mbz files and caches them in local_smgp_sp_courses.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_sharepoint_courses extends \core\task\scheduled_task {

    public function get_name(): string {
        return 'Sync SharePoint course folders';
    }

    public function execute(): void {
        global $DB;

        if (!\local_sm_graphics_plugin\sharepoint\client::is_configured()) {
            mtrace('SharePoint not configured — skipping sync.');
            return;
        }

        $siteurl = get_config('local_sm_graphics_plugin', 'sp_site_url');
        if (empty($siteurl)) {
            mtrace('SharePoint site URL not set — skipping sync.');
            return;
        }

        $docsurl = rtrim($siteurl, '/') . '/Shared Documents';
        $parsed = \local_sm_graphics_plugin\sharepoint\client::parse_sharepoint_url($docsurl);
        if (!$parsed) {
            mtrace('Failed to parse SharePoint URL: ' . $docsurl);
            return;
        }

        $siteid  = $parsed['site_id'];
        $driveid = $parsed['drive_id'];

        // List root folders.
        $rootitems = \local_sm_graphics_plugin\sharepoint\client::list_folder($siteid, $driveid, '/');
        if ($rootitems === null) {
            mtrace('Failed to list SharePoint root: ' . \local_sm_graphics_plugin\sharepoint\client::get_last_error());
            return;
        }

        // Only scan the PRIV_CATALOGO folder (where actual courses live).
        $scanfolders = ['PRIV_CATALOGO'];

        $now = time();
        $foundurls = [];
        $scannedfolders = 0;
        $foundcourses = 0;

        foreach ($scanfolders as $rootname) {
            $subpath = '/' . $rootname;
            mtrace("  Scanning /{$rootname}...");

            $subitems = \local_sm_graphics_plugin\sharepoint\client::list_folder($siteid, $driveid, $subpath);
            if ($subitems === null) {
                mtrace("    Failed to list /{$rootname}: " . \local_sm_graphics_plugin\sharepoint\client::get_last_error());
                continue;
            }

            foreach ($subitems as $sub) {
                if (!$sub['is_folder']) {
                    continue;
                }
                $scannedfolders++;
                $coursepath = $subpath . '/' . $sub['name'];

                $coursefiles = \local_sm_graphics_plugin\sharepoint\client::list_folder($siteid, $driveid, $coursepath);
                if ($coursefiles === null) {
                    continue;
                }

                // Check for .mbz in the main folder, or inside subfolders named "MBZ" / containing "MBZ".
                $hasmbz = false;
                foreach ($coursefiles as $cf) {
                    if (!$cf['is_folder'] && preg_match('/\.mbz$/i', $cf['name'])) {
                        $hasmbz = true;
                        break;
                    }
                }
                if (!$hasmbz) {
                    foreach ($coursefiles as $cf) {
                        if ($cf['is_folder'] && stripos($cf['name'], 'MBZ') !== false) {
                            $mbzpath = $coursepath . '/' . $cf['name'];
                            $mbzfiles = \local_sm_graphics_plugin\sharepoint\client::list_folder($siteid, $driveid, $mbzpath);
                            if ($mbzfiles) {
                                foreach ($mbzfiles as $mf) {
                                    if (!$mf['is_folder'] && preg_match('/\.mbz$/i', $mf['name'])) {
                                        $hasmbz = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }

                if ($hasmbz) {
                    $foundcourses++;
                    $weburl = $sub['web_url'];
                    $foundurls[] = $weburl;

                    // Upsert: match on web_url.
                    $existing = $DB->get_record_select('local_smgp_sp_courses',
                        $DB->sql_compare_text('web_url') . ' = ?', [$weburl]);
                    if ($existing) {
                        $existing->name = $sub['name'];
                        $existing->parent_folder = $rootname;
                        $existing->timemodified = $now;
                        $DB->update_record('local_smgp_sp_courses', $existing);
                    } else {
                        $DB->insert_record('local_smgp_sp_courses', (object) [
                            'name'          => $sub['name'],
                            'web_url'       => $weburl,
                            'parent_folder' => $rootname,
                            'timecreated'   => $now,
                            'timemodified'  => $now,
                        ]);
                    }
                }

                if ($scannedfolders % 50 === 0) {
                    mtrace("    Progress: {$scannedfolders} folders checked, {$foundcourses} courses found...");
                }
            }

            mtrace("  Done /{$rootname}: {$scannedfolders} folders, {$foundcourses} courses.");
        }

        // Remove stale entries no longer in SharePoint.
        $allcached = $DB->get_records('local_smgp_sp_courses', null, '', 'id, web_url');
        $deleted = 0;
        foreach ($allcached as $cached) {
            if (!in_array($cached->web_url, $foundurls)) {
                $DB->delete_records('local_smgp_sp_courses', ['id' => $cached->id]);
                $deleted++;
            }
        }

        mtrace("SharePoint sync complete: {$foundcourses} courses cached, {$deleted} stale entries removed.");
    }
}

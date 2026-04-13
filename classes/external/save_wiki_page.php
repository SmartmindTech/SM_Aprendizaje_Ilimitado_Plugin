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
 * AJAX: Save/edit a wiki page (mod_wiki).
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class save_wiki_page extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID'),
            'pageid'  => new external_value(PARAM_INT, 'Wiki page ID (0 to create new page)'),
            'title'   => new external_value(PARAM_TEXT, 'Page title (for new pages)', VALUE_DEFAULT, ''),
            'content' => new external_value(PARAM_RAW, 'Page content (wiki markup or HTML)'),
        ]);
    }

    public static function execute(int $cmid, int $pageid, string $title, string $content): array {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'pageid' => $pageid,
            'title' => $title, 'content' => $content,
        ]);

        $cm = get_coursemodule_from_id('wiki', $cmid, 0, true, MUST_EXIST);
        $context = \context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/wiki:editpage', $context);

        require_once($CFG->dirroot . '/mod/wiki/locallib.php');

        $wiki = $DB->get_record('wiki', ['id' => $cm->instance], '*', MUST_EXIST);

        if ($params['pageid'] > 0) {
            // Edit existing page.
            $page = $DB->get_record('wiki_pages', ['id' => $params['pageid']], '*', MUST_EXIST);

            // Save new version.
            $newversion = new \stdClass();
            $newversion->pageid = $page->id;
            $newversion->content = $params['content'];
            $newversion->contentformat = 'html';
            $newversion->version = $DB->count_records('wiki_versions', ['pageid' => $page->id]) + 1;
            $newversion->timecreated = time();
            $newversion->userid = $USER->id;
            $DB->insert_record('wiki_versions', $newversion);

            // Update the page's cached content.
            $page->cachedcontent = $params['content'];
            $page->timemodified = time();
            $page->userid = $USER->id;
            $page->timerendered = time();
            $DB->update_record('wiki_pages', $page);

            $resultpageid = $page->id;
        } else {
            // Create new page.
            $subwiki = $DB->get_record('wiki_subwikis', [
                'wikiid' => $wiki->id,
                'groupid' => 0,
                'userid' => ($wiki->wikimode === 'individual') ? $USER->id : 0,
            ]);
            if (!$subwiki) {
                $subwiki = new \stdClass();
                $subwiki->wikiid = $wiki->id;
                $subwiki->groupid = 0;
                $subwiki->userid = ($wiki->wikimode === 'individual') ? $USER->id : 0;
                $subwiki->id = $DB->insert_record('wiki_subwikis', $subwiki);
            }

            $page = new \stdClass();
            $page->subwikiid = $subwiki->id;
            $page->title = $params['title'] ?: 'New page';
            $page->cachedcontent = $params['content'];
            $page->timecreated = time();
            $page->timemodified = time();
            $page->timerendered = time();
            $page->userid = $USER->id;
            $page->pageviews = 0;
            $page->readonly = 0;
            $page->id = $DB->insert_record('wiki_pages', $page);

            // Create initial version.
            $version = new \stdClass();
            $version->pageid = $page->id;
            $version->content = $params['content'];
            $version->contentformat = 'html';
            $version->version = 1;
            $version->timecreated = time();
            $version->userid = $USER->id;
            $DB->insert_record('wiki_versions', $version);

            $resultpageid = $page->id;
        }

        // Trigger event.
        $event = \mod_wiki\event\page_updated::create([
            'context'  => $context,
            'objectid' => $resultpageid,
            'relateduserid' => $USER->id,
            'other' => ['newcontent' => $params['content']],
        ]);
        $event->trigger();

        return ['success' => true, 'pageid' => $resultpageid, 'message' => ''];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether save succeeded'),
            'pageid'  => new external_value(PARAM_INT, 'Saved page ID'),
            'message' => new external_value(PARAM_TEXT, 'Error message if failed'),
        ]);
    }
}

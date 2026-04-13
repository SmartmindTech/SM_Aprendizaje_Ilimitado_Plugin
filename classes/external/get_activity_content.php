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
 * AJAX: Get rendered content for a single activity module.
 *
 * Returns the activity's description/content as HTML for inline display
 * in the course page player. Categorizes activities into three render modes:
 * inline (HTML in content area), iframe (embedded page), or redirect (navigate away).
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
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * External function to get a single activity's rendered content.
 */
class get_activity_content extends external_api {

    /** @var string[] Activity types rendered inline (AJAX HTML). */
    private static $inline_types = [
        'page', 'book', 'label', 'resource', 'url', 'glossary',
        'folder', 'choice', 'survey', 'feedback', 'wiki', 'data',
        'quiz', 'assign', 'lesson', 'workshop', 'scorm',
    ];

    /** @var string[] Activity types that redirect to the real Moodle page. */
    private static $redirect_types = ['forum', 'chat', 'bigbluebuttonbn', 'lti'];

    /**
     * Define parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'itemnum' => new external_value(PARAM_INT, 'Item number to display (1-based, 0 = default)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute: render activity content.
     *
     * @param int $cmid Course module ID.
     * @return array {html, name, modname, url, rendermode, iframeurl, itemcount, currentitem, counterlabel}
     */
    public static function execute(int $cmid, int $itemnum = 0): array {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'itemnum' => $itemnum]);
        $cmid = $params['cmid'];
        $itemnum = $params['itemnum'];

        // Get course module.
        $cm = get_coursemodule_from_id('', $cmid, 0, true, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cmid);

        // Validate context and access.
        $context = \context_module::instance($cmid);
        self::validate_context($context);

        if (!$cminfo->uservisible) {
            throw new \moodle_exception('nopermissions', 'error', '', 'view this activity');
        }

        $modname = $cm->modname;
        $url = $cminfo->url ? $cminfo->url->out(false) : '';
        $name = format_string($cm->name);

        // Auto-resume: for book activities with no explicit item, resume at last viewed chapter.
        if ($modname === 'book' && $itemnum === 0) {
            try {
                $lastviewed = $DB->get_record_sql(
                    "SELECT objectid FROM {logstore_standard_log}
                     WHERE component = 'mod_book' AND eventname = :eventname
                       AND userid = :userid AND contextinstanceid = :cmid
                     ORDER BY timecreated DESC LIMIT 1",
                    ['eventname' => '\\mod_book\\event\\chapter_viewed',
                     'userid' => $USER->id, 'cmid' => $cmid]
                );
                if ($lastviewed) {
                    $book = $DB->get_record('book', ['id' => $cm->instance], 'id', MUST_EXIST);
                    $chapters = array_values($DB->get_records('book_chapters',
                        ['bookid' => $book->id, 'hidden' => 0], 'pagenum ASC'));
                    $chapternum = 0;
                    foreach ($chapters as $chapter) {
                        $chapternum++;
                        if ($chapter->id == $lastviewed->objectid) {
                            $itemnum = $chapternum;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silent — default to first chapter.
            }
        }

        // Determine render mode.
        $rendermode = self::get_render_mode($modname);

        $html = '';
        $iframeurl = '';

        $inline = null;

        switch ($rendermode) {
            case 'inline':
                // Build the structured inline payload once. The Vue frontend
                // consumes this directly via the `inline` field. The legacy
                // AMD frontend (`amd/src/course_page.js`) still reads `html`,
                // which is mechanically composed from the same structured data
                // by `compose_legacy_html()` — a thin wrapper that exists only
                // until AMD goes away.
                $inline = self::build_inline_data($cm, $course, $context, $modname, $itemnum);
                $html = self::compose_legacy_html($inline);
                // Trigger viewed event for inline activities.
                self::trigger_viewed_event($cm, $course, $context, $modname);
                break;

            case 'iframe':
                $iframeurl = self::build_iframe_url($cm, $modname);
                break;

            case 'redirect':
                // JS handles navigation; no content needed.
                break;
        }

        // Get item counts for counter display.
        $itemdata = self::get_item_count($cm, $course, $context, $modname, $itemnum);

        $result = [
            'html'         => $html,
            'name'         => $name,
            'modname'      => $modname,
            'url'          => $url,
            'rendermode'   => $rendermode,
            'iframeurl'    => $iframeurl,
            'itemcount'    => $itemdata['itemcount'],
            'currentitem'  => $itemdata['currentitem'],
            'counterlabel' => $itemdata['counterlabel'],
            'completeditems' => $itemdata['completeditems'],
            'totalpages'   => $itemdata['totalpages'],
        ];
        if ($inline !== null) {
            $result['inline'] = $inline;
        }
        return $result;
    }

    /**
     * Determine the render mode for a given module type.
     *
     * @param string $modname Module type name.
     * @return string 'inline', 'iframe', or 'redirect'.
     */
    private static function get_render_mode(string $modname): string {
        if (in_array($modname, self::$inline_types)) {
            return 'inline';
        }
        if (in_array($modname, self::$redirect_types)) {
            return 'redirect';
        }
        return 'iframe';
    }

    /**
     * Build the iframe URL for embedded activity display.
     *
     * @param object $cm Course module record.
     * @param string $modname Module type name.
     * @return string URL for iframe src.
     */
    private static function build_iframe_url($cm, string $modname): string {
        global $DB;

        if ($modname === 'scorm') {
            // Use SCORM's built-in popup player.
            $scorm = $DB->get_record('scorm', ['id' => $cm->instance], 'id', MUST_EXIST);
            $sco = $DB->get_record_select(
                'scorm_scoes',
                "scorm = :scormid AND scormtype = 'sco' AND launch <> ''",
                ['scormid' => $scorm->id],
                'id',
                IGNORE_MULTIPLE
            );
            $scoid = $sco ? $sco->id : 0;
            $url = new \moodle_url('/mod/scorm/player.php', [
                'a'        => $scorm->id,
                'scoid'    => $scoid,
                'display'  => 'popup',
                'smgp_embed' => 1,
            ]);
            return $url->out(false);
        }

        // Genially: embed the external URL directly (not through Moodle's URL view wrapper).
        if ($modname === 'url') {
            $urlrecord = $DB->get_record('url', ['id' => $cm->instance], 'externalurl, display');
            if ($urlrecord && self::is_genially_url($urlrecord->externalurl)) {
                return self::normalize_genially_url($urlrecord->externalurl);
            }
        }

        // Generic: use the activity view page with embed flag.
        $url = new \moodle_url('/mod/' . $modname . '/view.php', [
            'id'       => $cm->id,
            'smgp_embed' => 1,
        ]);
        return $url->out(false);
    }

    /**
     * Check if a URL is a Genially embed URL.
     */
    private static function is_genially_url(string $url): bool {
        return (strpos($url, 'genial.ly') !== false || strpos($url, 'genially.com') !== false);
    }

    /**
     * Normalize a Genially URL for embedding.
     */
    private static function normalize_genially_url(string $url): string {
        // Ensure it uses view.genial.ly for embedding.
        $url = trim($url);
        // Strip trailing slashes.
        $url = rtrim($url, '/');
        return $url;
    }

    /**
     * Get item count data for the activity bar counter.
     *
     * @param object $cm Course module record.
     * @param object $course Course record.
     * @param \context_module $context Module context.
     * @param string $modname Module type name.
     * @return array {itemcount, currentitem, counterlabel}
     */
    private static function get_item_count($cm, $course, $context, string $modname, int $itemnum = 0): array {
        global $DB, $USER;

        $result = ['itemcount' => 0, 'currentitem' => 0, 'counterlabel' => '', 'completeditems' => 0, 'totalpages' => 0];

        try {
            switch ($modname) {
                case 'scorm':
                    $scorm = $DB->get_record('scorm', ['id' => $cm->instance], 'id', MUST_EXIST);
                    $slidecount = self::detect_scorm_slides($context, $scorm->id);
                    $currentslide = self::get_scorm_current_slide($scorm->id, $USER->id, $cm->id);
                    $result['itemcount'] = $slidecount;
                    $result['currentitem'] = $currentslide;
                    $result['completeditems'] = $currentslide;
                    $result['counterlabel'] = get_string('course_page_counter_slide', 'local_sm_graphics_plugin');
                    break;

                case 'book':
                    $book = $DB->get_record('book', ['id' => $cm->instance], 'id', MUST_EXIST);
                    $chaptercount = $DB->count_records('book_chapters', ['bookid' => $book->id, 'hidden' => 0]);
                    $currentchapter = ($itemnum > 0 && $itemnum <= $chaptercount) ? $itemnum : 1;
                    $result['itemcount'] = $chaptercount;
                    $result['currentitem'] = $currentchapter;
                    $result['counterlabel'] = get_string('course_page_counter_chapter', 'local_sm_graphics_plugin');
                    // Count unique chapters viewed from log.
                    $viewedchapters = $DB->count_records_sql(
                        "SELECT COUNT(DISTINCT objectid) FROM {logstore_standard_log}
                         WHERE component = 'mod_book' AND eventname = :eventname
                         AND userid = :userid AND contextinstanceid = :cmid",
                        ['eventname' => '\\mod_book\\event\\chapter_viewed',
                         'userid' => $USER->id, 'cmid' => $cm->id]
                    );
                    $result['completeditems'] = (int)$viewedchapters;
                    break;

                case 'quiz':
                    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id', MUST_EXIST);
                    $slotcount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);

                    // Subtract description-type questions (context/instruction pages, not real questions).
                    $descriptioncount = (int) $DB->count_records_sql(
                        "SELECT COUNT(DISTINCT qs.id)
                         FROM {quiz_slots} qs
                         JOIN {question_references} qr ON qr.component = 'mod_quiz'
                             AND qr.questionarea = 'slot' AND qr.itemid = qs.id
                         JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                         JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                         JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'description'
                         WHERE qs.quizid = :quizid",
                        ['quizid' => $quiz->id]
                    );
                    $slotcount -= $descriptioncount;

                    // Track position via currentpage and answered questions separately.
                    $currentitem = 0;
                    $completeditems = 0;
                    $attempt = $DB->get_record_sql(
                        "SELECT id, currentpage FROM {quiz_attempts}
                         WHERE quiz = :quizid AND userid = :userid AND state = 'inprogress'
                         ORDER BY attempt DESC LIMIT 1",
                        ['quizid' => $quiz->id, 'userid' => $USER->id]
                    );
                    if ($attempt) {
                        // currentpage is 0-based, quiz_slots.page is 1-based → add 1.
                        $pagenum = $attempt->currentpage + 1;

                        // Current position = non-description slots up to and including the current page.
                        $slotsuptopage = (int) $DB->count_records_select(
                            'quiz_slots',
                            "quizid = :quizid AND page <= :page",
                            ['quizid' => $quiz->id, 'page' => $pagenum]
                        );
                        $descuptopage = (int) $DB->count_records_sql(
                            "SELECT COUNT(DISTINCT qs.id)
                             FROM {quiz_slots} qs
                             JOIN {question_references} qr ON qr.component = 'mod_quiz'
                                 AND qr.questionarea = 'slot' AND qr.itemid = qs.id
                             JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                             JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                             JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'description'
                             WHERE qs.quizid = :quizid AND qs.page <= :page",
                            ['quizid' => $quiz->id, 'page' => $pagenum]
                        );
                        $currentitem = $slotsuptopage - $descuptopage;

                        // Completed items = questions actually answered (for progress ring).
                        $completeditems = (int) $DB->count_records_select(
                            'question_attempts',
                            "questionusageid = (SELECT uniqueid FROM {quiz_attempts} WHERE id = :attemptid)
                             AND responsesummary IS NOT NULL AND responsesummary <> ''",
                            ['attemptid' => $attempt->id]
                        );
                    }
                    $result['itemcount'] = max($slotcount, 0);
                    $result['currentitem'] = max($currentitem, 0);
                    $result['completeditems'] = $completeditems;
                    $result['totalpages'] = (int) $DB->get_field_sql(
                        "SELECT MAX(page) FROM {quiz_slots} WHERE quizid = :quizid",
                        ['quizid' => $quiz->id]
                    );
                    $result['counterlabel'] = get_string('course_page_counter_question', 'local_sm_graphics_plugin');
                    break;

                case 'lesson':
                    $lesson = $DB->get_record('lesson', ['id' => $cm->instance], 'id', MUST_EXIST);
                    $pagecount = $DB->count_records('lesson_pages', ['lessonid' => $lesson->id]);
                    // Count unique viewed pages.
                    $currentitem = 0;
                    $viewedpages = $DB->count_records_sql(
                        "SELECT COUNT(DISTINCT pageid) FROM {lesson_branch}
                         WHERE lessonid = :lessonid AND userid = :userid",
                        ['lessonid' => $lesson->id, 'userid' => $USER->id]
                    );
                    $currentitem = (int)$viewedpages;
                    $result['itemcount'] = $pagecount;
                    $result['currentitem'] = $currentitem;
                    $result['completeditems'] = $currentitem;
                    $result['counterlabel'] = get_string('course_page_counter_page', 'local_sm_graphics_plugin');
                    break;
            }
        } catch (\Exception $e) {
            debugging('Error getting item count: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $result;
    }

    /**
     * Detect slide count in a SCORM package.
     *
     * Uses multiple strategies: Articulate slide files → generic slide files →
     * slides.xml manifest → SCO count → fallback to 1.
     *
     * @param \context_module $context Module context.
     * @param int $scormid SCORM instance ID.
     * @return int Number of slides detected.
     */
    public static function detect_scorm_slides($context, int $scormid): int {
        global $DB;

        $scocount = $DB->count_records('scorm_scoes', ['scorm' => $scormid, 'scormtype' => 'sco']);

        try {
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, 'sortorder', false);

            $slidenumbers = [];
            $slidesxmlfile = null;
            $ispringslides = [];
            $captivateslides = [];

            foreach ($files as $file) {
                $path = $file->get_filepath() . $file->get_filename();

                // Strategy 1: Articulate Storyline — story_content/slide1.xml
                if (preg_match('#/story_content/slide(\d+)\.xml$#i', $path, $m)) {
                    $slidenumbers[$m[1]] = true;
                }

                // Strategy 2: Generic authoring tools — slides/slide1.html, content/slide5.js
                if (preg_match('#/(?:res/data|slides|content|data)/slide(\d+)\.(js|html|css)$#i', $path, $m)) {
                    $slidenumbers[$m[1]] = true;
                }

                // Strategy 3: iSpring — data/slide1.html or pres/slide*.html
                if (preg_match('#/(?:pres|presentation)/slide(\d+)\.(html|js)$#i', $path, $m)) {
                    $ispringslides[$m[1]] = true;
                }

                // Strategy 4: Adobe Captivate — cpSlide*.swf or widgets/slide*.html
                if (preg_match('#/(?:widgets|assets)/slide(\d+)\.(html|swf|js)$#i', $path, $m)) {
                    $captivateslides[$m[1]] = true;
                }

                if ($path === '/story_content/slides.xml') {
                    $slidesxmlfile = $file;
                }
            }

            if (!empty($slidenumbers)) {
                return count($slidenumbers);
            }

            // Parse Storyline's slides.xml manifest.
            if ($slidesxmlfile) {
                $content = $slidesxmlfile->get_content();
                $count = preg_match_all('/<sld\s/i', $content);
                if ($count > 0) {
                    return $count;
                }
            }

            // iSpring slides.
            if (!empty($ispringslides)) {
                return count($ispringslides);
            }

            // Captivate slides.
            if (!empty($captivateslides)) {
                return count($captivateslides);
            }
        } catch (\Exception $e) {
            debugging('Error detecting SCORM slides: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $scocount ?: 1;
    }

    /**
     * Get the current slide position from SCORM tracking data.
     *
     * Supports both Moodle 4.x+ normalized tables and legacy scorm_scoes_track.
     *
     * @param int $scormid SCORM instance ID.
     * @param int $userid User ID.
     * @param int $cmid Course module ID.
     * @return int Current slide number (1-based), 0 if unknown.
     */
    public static function get_scorm_current_slide(int $scormid, int $userid, int $cmid): int {
        global $DB;

        try {
            // Find primary launchable SCO.
            $primarysco = $DB->get_record_select(
                'scorm_scoes',
                "scorm = :scormid AND scormtype = 'sco' AND launch <> ''",
                ['scormid' => $scormid],
                'id',
                IGNORE_MULTIPLE
            );

            $dbman = $DB->get_manager();
            $usenormalized = $dbman->table_exists('scorm_scoes_value');
            $tracks = [];

            if ($usenormalized) {
                $attemptrecord = $DB->get_record_sql(
                    "SELECT id, attempt FROM {scorm_attempt}
                     WHERE scormid = :scormid AND userid = :userid
                     ORDER BY attempt DESC LIMIT 1",
                    ['scormid' => $scormid, 'userid' => $userid]
                );

                if ($attemptrecord) {
                    $scoidclause = '';
                    $params = ['attemptid' => $attemptrecord->id];
                    if ($primarysco) {
                        $scoidclause = ' AND v.scoid = :scoid';
                        $params['scoid'] = $primarysco->id;
                    }

                    $trackrecords = $DB->get_records_sql(
                        "SELECT e.element, v.value
                         FROM {scorm_scoes_value} v
                         JOIN {scorm_element} e ON e.id = v.elementid
                         WHERE v.attemptid = :attemptid $scoidclause
                         ORDER BY v.timemodified DESC",
                        $params
                    );

                    foreach ($trackrecords as $track) {
                        if (!isset($tracks[$track->element])) {
                            $tracks[$track->element] = $track->value;
                        }
                    }
                }
            } else {
                $attempt = $DB->get_field('scorm_scoes_track', 'MAX(attempt)', [
                    'scormid' => $scormid,
                    'userid' => $userid,
                ]);

                if ($attempt) {
                    $trackparams = [
                        'scormid' => $scormid,
                        'userid' => $userid,
                        'attempt' => $attempt,
                    ];
                    $scoidclause = '';
                    if ($primarysco) {
                        $trackparams['scoid'] = $primarysco->id;
                        $scoidclause = ' AND scoid = :scoid';
                    }

                    $trackrecords = $DB->get_records_sql(
                        "SELECT id, element, value
                         FROM {scorm_scoes_track}
                         WHERE scormid = :scormid AND userid = :userid AND attempt = :attempt $scoidclause
                         ORDER BY timemodified DESC",
                        $trackparams
                    );

                    foreach ($trackrecords as $track) {
                        if (!isset($tracks[$track->element])) {
                            $tracks[$track->element] = $track->value;
                        }
                    }
                }
            }

            // Extract current slide from lesson_location / location.
            $locationslide = self::parse_slide_from_location($tracks);
            if ($locationslide > 0) {
                return $locationslide;
            }

            // Fallback: parse suspend_data (multi-format: JSON, Captivate, URL-encoded, Base64, text patterns).
            $suspendslide = self::parse_slide_from_suspend_data($tracks);
            if ($suspendslide > 0) {
                return $suspendslide;
            }

            // Fallback: estimate slide from score.raw (score-based progress).
            $scoreslide = self::estimate_slide_from_score($tracks, $scormid, $cmid);
            if ($scoreslide > 0) {
                return $scoreslide;
            }
        } catch (\Exception $e) {
            debugging('SCORM tracking error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return 0;
    }

    /**
     * Parse slide number from lesson_location / cmi.location.
     *
     * Supports formats from multiple SCORM authoring tools:
     * - Pure number: "5"
     * - Rise 360: "section_0" (0-based → +1)
     * - Trailing number: "slide_5", "scene1_slide5"
     * - Fraction: "5/10"
     * - Articulate: "slide5"
     *
     * @param array $tracks SCORM tracking data.
     * @return int Slide number (1-based), 0 if unknown.
     */
    private static function parse_slide_from_location(array $tracks): int {
        foreach (['cmi.core.lesson_location', 'cmi.location'] as $key) {
            if (!isset($tracks[$key]) || $tracks[$key] === '') {
                continue;
            }
            $location = trim($tracks[$key]);

            // Pure number.
            if (is_numeric($location)) {
                $val = (int)$location;
                return $val > 0 ? $val : 1;
            }

            // Rise 360: "section_0" format (0-based).
            if (preg_match('/^section_(\d+)$/i', $location, $m)) {
                return (int)$m[1] + 1;
            }

            // Articulate Storyline: "slide5" or "Slide_5".
            if (preg_match('/slide[_\-]?(\d+)/i', $location, $m)) {
                return (int)$m[1];
            }

            // Scene_slide format: "1_5" (Storyline scene/slide, 0-based).
            if (preg_match('/^(\d+)_(\d+)$/', $location, $m)) {
                return (int)$m[2] + 1;
            }

            // Fraction: "5/10".
            if (preg_match('/^(\d+)\//', $location, $m)) {
                return (int)$m[1];
            }

            // Trailing number fallback: "anything_5".
            if (preg_match('/(\d+)$/', $location, $m)) {
                return (int)$m[1];
            }

            break;
        }
        return 0;
    }

    /**
     * Parse slide number from suspend_data using multi-format detection.
     *
     * Supports formats from various SCORM authoring tools:
     * - JSON with common field names (currentSlide, bookmark, resume, position, slide)
     * - Captivate: "cs=N,vs=...,qt=...,qr=...,ts=..."
     * - URL-encoded: "slide=5&page=3"
     * - Storyline: LZ-compressed Base64 JSON with "resume":"0_14" (Base64 decoded)
     * - Text patterns: various "slide:N", "page=N" etc.
     *
     * @param array $tracks SCORM tracking data.
     * @return int Slide number (1-based), 0 if unknown.
     */
    private static function parse_slide_from_suspend_data(array $tracks): int {
        foreach (['cmi.suspend_data', 'cmi.core.suspend_data'] as $key) {
            if (!isset($tracks[$key]) || $tracks[$key] === '') {
                continue;
            }
            $data = $tracks[$key];

            // 1. Try JSON directly.
            $slide = self::parse_slide_from_json($data);
            if ($slide > 0) {
                return $slide;
            }

            // 2. Captivate format: "cs=N,vs=0:1:2:3,qt=0,qr=,ts=30013"
            if (strpos($data, ',') !== false && preg_match('/\bcs=(\d+)/', $data, $m)) {
                return (int)$m[1] + 1; // cs is 0-based.
            }

            // 3. URL-encoded format: "slide=5&page=3".
            if (strpos($data, '=') !== false && strpos($data, '&') !== false) {
                parse_str($data, $params);
                foreach (['slide', 'current', 'page', 'currentSlide', 'position'] as $field) {
                    if (isset($params[$field]) && is_numeric($params[$field])) {
                        return (int)$params[$field];
                    }
                }
            }

            // 4. Try Base64 decode (Storyline sometimes uses plain Base64).
            if (preg_match('/^[A-Za-z0-9+\/=]{20,}$/', $data)) {
                $decoded = @base64_decode($data, true);
                if ($decoded !== false) {
                    $slide = self::parse_slide_from_json($decoded);
                    if ($slide > 0) {
                        return $slide;
                    }
                    // Try text pattern matching on decoded data.
                    $slide = self::parse_slide_from_text($decoded);
                    if ($slide > 0) {
                        return $slide;
                    }
                }
            }

            // 5. Text pattern matching on raw suspend_data.
            $slide = self::parse_slide_from_text($data);
            if ($slide > 0) {
                return $slide;
            }
        }
        return 0;
    }

    /**
     * Try to parse a slide number from JSON data.
     *
     * @param string $data JSON string (or possibly JSON).
     * @return int Slide number (1-based), 0 if not found.
     */
    private static function parse_slide_from_json(string $data): int {
        $parsed = @json_decode($data, true);
        if (!is_array($parsed)) {
            return 0;
        }

        // Direct properties: currentSlide, slide, current, position.
        foreach (['currentSlide', 'slide', 'current', 'position'] as $field) {
            if (isset($parsed[$field]) && is_numeric($parsed[$field])) {
                $val = (int)$parsed[$field];
                return $val > 0 ? $val : $val + 1;
            }
        }

        // Rise 360: "bookmark" field (e.g., "section_0").
        if (isset($parsed['bookmark'])) {
            $bm = (string)$parsed['bookmark'];
            if (preg_match('/^section_(\d+)$/i', $bm, $m)) {
                return (int)$m[1] + 1;
            }
            if (is_numeric($bm)) {
                return max(1, (int)$bm);
            }
            if (preg_match('/(\d+)$/', $bm, $m)) {
                return (int)$m[1];
            }
        }

        // Storyline: "resume" field (e.g., "0_14" — scene_slide, 0-based).
        if (isset($parsed['resume'])) {
            $resume = (string)$parsed['resume'];
            if (preg_match('/^(\d+)_(\d+)$/', $resume, $m)) {
                return (int)$m[2] + 1;
            }
            if (preg_match('/^(\d+)$/', $resume, $m)) {
                return (int)$m[1] + 1;
            }
        }

        // Storyline d-array: [{n: "Resume", v: "0_14"}, ...].
        if (isset($parsed['d']) && is_array($parsed['d'])) {
            foreach ($parsed['d'] as $item) {
                if (isset($item['n']) && strtolower($item['n']) === 'resume' && isset($item['v'])) {
                    $resume = (string)$item['v'];
                    if (preg_match('/^(\d+)_(\d+)$/', $resume, $m)) {
                        return (int)$m[2] + 1;
                    }
                    if (preg_match('/^(\d+)$/', $resume, $m)) {
                        return (int)$m[1] + 1;
                    }
                }
            }
        }

        // Nested: data.slide, v.current, variables.CurrentSlideIndex.
        if (isset($parsed['data']['slide']) && is_numeric($parsed['data']['slide'])) {
            return max(1, (int)$parsed['data']['slide']);
        }
        if (isset($parsed['v']['current']) && is_numeric($parsed['v']['current'])) {
            return max(1, (int)$parsed['v']['current']);
        }
        if (isset($parsed['variables']['CurrentSlideIndex']) && is_numeric($parsed['variables']['CurrentSlideIndex'])) {
            return (int)$parsed['variables']['CurrentSlideIndex'] + 1;
        }

        return 0;
    }

    /**
     * Parse slide number from text using regex patterns.
     *
     * @param string $text Raw text to scan.
     * @return int Slide number (1-based), 0 if not found.
     */
    private static function parse_slide_from_text(string $text): int {
        // Storyline resume patterns (0-based → +1).
        $patterns = [
            '/["\']?resume["\']?\s*[:=]\s*["\']?(\d+)_(\d+)["\']?/i',      // "resume": "1_5"
            '/["\']?resume["\']?\s*[:=]\s*["\']?(\d+)["\']?/i',             // "resume": "5"
            '/["\']?CurrentSlideIndex["\']?\s*[:=]\s*["\']?(\d+)["\']?/i',   // "CurrentSlideIndex": 5
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                if (isset($m[2])) {
                    return (int)$m[2] + 1;
                }
                return (int)$m[1] + 1;
            }
        }

        // General patterns (assumed 1-based or detect from context).
        $generalpatterns = [
            '/["\']?currentSlide["\']?\s*[:=]\s*["\']?(\d+)["\']?/i',
            '/["\']?slide["\']?\s*[:=]\s*["\']?(\d+)["\']?/i',
            '/["\']?page["\']?\s*[:=]\s*["\']?(\d+)["\']?/i',
            '/["\']?position["\']?\s*[:=]\s*["\']?(\d+)["\']?/i',
        ];
        foreach ($generalpatterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return max(1, (int)$m[1]);
            }
        }

        return 0;
    }

    /**
     * Estimate current slide from score.raw as a progress proxy.
     *
     * If the SCORM reports a score, map it proportionally to the total slide count.
     * E.g., score 50 with 20 total slides → estimated slide 10.
     *
     * @param array $tracks SCORM tracking data.
     * @param int $scormid SCORM instance ID.
     * @param int $cmid Course module ID.
     * @return int Estimated slide number, 0 if not calculable.
     */
    private static function estimate_slide_from_score(array $tracks, int $scormid, int $cmid): int {
        $score = null;
        foreach (['cmi.core.score.raw', 'cmi.score.raw'] as $key) {
            if (isset($tracks[$key]) && is_numeric($tracks[$key])) {
                $score = (float)$tracks[$key];
                break;
            }
        }

        if ($score === null || $score <= 0) {
            return 0;
        }

        try {
            $context = \context_module::instance($cmid);
            $total = self::detect_scorm_slides($context, $scormid);
            if ($total > 1 && $score <= 100) {
                return (int)round(($score / 100) * $total);
            }
        } catch (\Exception $e) {
            // Silent.
        }

        return 0;
    }

    /**
     * Trigger the viewed event for inline-rendered activities.
     *
     * Iframe/redirect activities fire their own events when loaded.
     *
     * @param object $cm Course module record.
     * @param object $course Course record.
     * @param \context_module $context Module context.
     * @param string $modname Module type name.
     */
    private static function trigger_viewed_event($cm, $course, $context, string $modname): void {
        global $DB, $CFG, $USER;

        try {
            require_once($CFG->libdir . '/completionlib.php');

            // 1. Fire the module-specific viewed event (if it exists).
            $eventclass = "\\mod_{$modname}\\event\\course_module_viewed";
            if (class_exists($eventclass)) {
                $event = $eventclass::create([
                    'objectid' => $cm->instance,
                    'context'  => $context,
                ]);
                $record = $DB->get_record($modname, ['id' => $cm->instance]);
                if ($record) {
                    $event->add_record_snapshot($modname, $record);
                }
                $event->add_record_snapshot('course_modules', $cm);
                $event->add_record_snapshot('course', $course);
                $event->trigger();
            }

            // 2. Record the view for Moodle's completion system.
            // Skip SCORM — it manages its own completion via the SCORM API
            // (cmi.core.lesson_status). Calling set_module_viewed here would
            // mark the activity as complete on first load, defeating granular
            // progress tracking.
            if ($modname !== 'scorm') {
                $modinfo = get_fast_modinfo($course);
                $cminfo = $modinfo->get_cm($cm->id);
                $completion = new \completion_info($course);
                if ($completion->is_enabled($cminfo)) {
                    $completion->set_module_viewed($cminfo);
                    if ($modname === 'page' || $modname === 'label') {
                        $completion->update_state($cminfo, COMPLETION_COMPLETE);
                    }
                }
            }

            // 3. For activities without native Moodle events (labels, etc.),
            //    write a log entry so our log-based progress fallback detects the view.
            if (!class_exists($eventclass)) {
                $exists = $DB->record_exists_sql(
                    "SELECT 1 FROM {logstore_standard_log}
                     WHERE action = 'viewed' AND target = 'course_module'
                       AND contextinstanceid = :cmid AND userid = :userid",
                    ['cmid' => $cm->id, 'userid' => $USER->id]
                );
                if (!$exists) {
                    $DB->insert_record('logstore_standard_log', (object)[
                        'eventname'         => '\\local_sm_graphics_plugin\\event\\activity_viewed',
                        'component'         => 'local_sm_graphics_plugin',
                        'action'            => 'viewed',
                        'target'            => 'course_module',
                        'objecttable'       => $modname,
                        'objectid'          => $cm->instance,
                        'contextid'         => $context->id,
                        'contextlevel'      => CONTEXT_MODULE,
                        'contextinstanceid' => $cm->id,
                        'userid'            => $USER->id,
                        'courseid'          => $course->id,
                        'timecreated'       => time(),
                        'origin'            => 'web',
                        'ip'                => getremoteaddr(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            debugging('Could not trigger viewed event for ' . $modname . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Inline content data builders.
    //
    // These return STRUCTURED DATA (kind + content fields) — never composed
    // HTML chrome. The Vue frontend (player.vue) renders all wrapper markup,
    // file previews, navigation chrome and download buttons from this data.
    //
    // The only HTML strings here are the per-activity Moodle-formatted user
    // content (page body, book chapter body, resource intro, label content),
    // which are produced by Moodle's `format_text()` and are irreducible:
    // they include pluginfile URL rewrites, filter pipelines and embedded
    // attachments. Vue consumes them via `v-html`.
    //
    // For backwards compatibility with the legacy AMD module
    // (`amd/src/course_page.js`), `compose_legacy_html()` derives the old
    // `html` field from the same structured data. When the AMD frontend
    // is removed, that wrapper can also go away.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Build the structured inline payload for a single activity.
     *
     * @return array {kind: string, content?: string, intro?: string,
     *                chapter?: array, file?: array}
     */
    private static function build_inline_data($cm, $course, $context, string $modname, int $itemnum = 0): array {
        switch ($modname) {
            case 'page':
                return self::build_page_data($cm, $context);

            case 'book':
                return self::build_book_data($cm, $context, $itemnum);

            case 'resource':
                return self::build_resource_data($cm, $context);

            case 'label':
                return self::build_label_data($cm, $context);

            case 'url':
                return self::build_url_data($cm, $context);

            case 'glossary':
                return self::build_glossary_data($cm, $context);

            case 'folder':
                return self::build_folder_data($cm, $context);

            case 'choice':
                return self::build_choice_data($cm, $course, $context);

            case 'survey':
                return self::build_survey_data($cm, $course, $context);

            case 'feedback':
                return self::build_feedback_data($cm, $course, $context);

            case 'wiki':
                return self::build_wiki_data($cm, $context, $itemnum);

            case 'data':
                return self::build_data_data($cm, $context);

            case 'quiz':
                return self::build_quiz_data($cm, $course, $context);

            case 'assign':
                return self::build_assign_data($cm, $course, $context);

            case 'lesson':
                return self::build_lesson_data($cm, $course, $context, $itemnum);

            case 'workshop':
                return self::build_workshop_data($cm, $course, $context);

            case 'scorm':
                return self::build_scorm_data($cm, $context);

            default:
                return ['kind' => 'unsupported'];
        }
    }

    /**
     * Build mod_page payload: full page body as Moodle-formatted HTML.
     */
    private static function build_page_data($cm, $context): array {
        global $DB;
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
        $content = file_rewrite_pluginfile_urls(
            $page->content, 'pluginfile.php', $context->id, 'mod_page', 'content', $page->revision
        );
        return [
            'kind'    => 'page',
            'content' => format_text($content, $page->contentformat, ['context' => $context]),
        ];
    }

    /**
     * Build mod_book payload: chapter body + chapter metadata.
     *
     * Triggers the chapter_viewed event as a side effect (books rely on
     * it for completion tracking instead of course_module_viewed).
     */
    private static function build_book_data($cm, $context, int $chapternum = 0): array {
        global $DB, $USER, $CFG;
        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        $chapters = array_values($DB->get_records('book_chapters', ['bookid' => $book->id, 'hidden' => 0], 'pagenum ASC'));

        if (empty($chapters)) {
            return ['kind' => 'book', 'empty' => true];
        }

        // Select requested chapter (1-based) or default to first.
        $idx = ($chapternum > 0 && $chapternum <= count($chapters)) ? $chapternum - 1 : 0;

        // Pre-render ALL chapters so the frontend navigates instantly.
        $allchapters = [];
        foreach ($chapters as $i => $ch) {
            $chcontent = file_rewrite_pluginfile_urls(
                $ch->content, 'pluginfile.php', $context->id, 'mod_book', 'chapter', $ch->id
            );
            $allchapters[] = [
                'title'   => format_string($ch->title),
                'content' => format_text($chcontent, $ch->contentformat, ['context' => $context]),
            ];
        }

        // Trigger chapter_viewed event for the current chapter.
        $chapter = $chapters[$idx];
        try {
            $event = \mod_book\event\chapter_viewed::create([
                'objectid' => $chapter->id,
                'context'  => $context,
                'other'    => ['bookid' => $book->id],
            ]);
            $event->add_record_snapshot('book_chapters', $chapter);
            $event->trigger();
        } catch (\Exception $e) {
            debugging('Could not trigger chapter_viewed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Check if all chapters have now been viewed → mark complete.
        $totalchapters = count($chapters);
        $viewedcount = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT objectid) FROM {logstore_standard_log}
             WHERE component = 'mod_book' AND eventname = :eventname
             AND userid = :userid AND contextinstanceid = :cmid",
            ['eventname' => '\\mod_book\\event\\chapter_viewed',
             'userid' => $USER->id, 'cmid' => $cm->id]
        );

        if ($viewedcount >= $totalchapters) {
            try {
                require_once($CFG->libdir . '/completionlib.php');
                $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
                $modinfo = get_fast_modinfo($course);
                $cminfo = $modinfo->get_cm($cm->id);
                $completion = new \completion_info($course);
                if ($completion->is_enabled($cminfo)) {
                    $completion->update_state($cminfo, COMPLETION_COMPLETE);
                }
            } catch (\Exception $e) {
                debugging('Book completion error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return [
            'kind'    => 'book',
            'content' => $allchapters[$idx]['content'],
            'chapter' => [
                'title'   => $allchapters[$idx]['title'],
                'current' => $idx + 1,
                'total'   => $totalchapters,
            ],
            'allchapters' => $allchapters,
            'viewedcount' => $viewedcount,
        ];
    }

    /**
     * Build mod_resource payload: intro text + file metadata
     * (no preview/download HTML — that's the frontend's job).
     */
    private static function build_resource_data($cm, $context): array {
        global $DB;
        $resource = $DB->get_record('resource', ['id' => $cm->instance], '*', MUST_EXIST);

        $intro = self::format_intro_text($resource, $context, 'resource');

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        $file = reset($files);

        $filedata = null;
        if ($file) {
            $mimetype = $file->get_mimetype();
            $filename = $file->get_filename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $kind = 'other';
            if (strpos($mimetype, 'image/') === 0) {
                $kind = 'image';
            } else if ($mimetype === 'application/pdf') {
                $kind = 'pdf';
            } else if (strpos($mimetype, 'video/') === 0) {
                $kind = 'video';
            } else if (strpos($mimetype, 'audio/') === 0) {
                $kind = 'audio';
            } else if (in_array($ext, ['ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'odp'], true)) {
                // Office documents — embed via Google Docs Viewer.
                $kind = 'document';
            }

            $filedata = [
                'url'      => \moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                    $file->get_itemid(), $file->get_filepath(), $filename
                )->out(false),
                'name'     => $filename,
                'size'     => display_size($file->get_filesize()),
                'mimetype' => $mimetype,
                'kind'     => $kind,
            ];
        }

        $data = ['kind' => 'resource'];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        if ($filedata !== null) {
            $data['file'] = $filedata;
        }
        return $data;
    }

    /**
     * Build mod_label payload: just the formatted intro text.
     */
    private static function build_label_data($cm, $context): array {
        global $DB;
        $label = $DB->get_record('label', ['id' => $cm->instance], '*', MUST_EXIST);
        return [
            'kind'    => 'label',
            'content' => format_text($label->intro, $label->introformat, ['context' => $context]),
        ];
    }

    /**
     * Build mod_url payload: external URL + optional intro.
     *
     * Genially URLs get a special 'embed' kind so the frontend can render
     * them in a responsive iframe without Moodle's wrapper.
     */
    private static function build_url_data($cm, $context): array {
        global $DB;
        $urlrecord = $DB->get_record('url', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($urlrecord, $context, 'url');
        $externalurl = trim($urlrecord->externalurl);

        $kind = 'link';
        $embedurl = $externalurl;
        if (self::is_genially_url($externalurl)) {
            $kind = 'embed';
            $embedurl = self::normalize_genially_url($externalurl);
        } else if (preg_match('/youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\//i', $externalurl)) {
            $kind = 'embed';
            $embedurl = self::normalize_youtube_url($externalurl);
        } else if (preg_match('/vimeo\.com\/\d+/i', $externalurl)) {
            $kind = 'embed';
            $embedurl = self::normalize_vimeo_url($externalurl);
        }

        $data = [
            'kind'       => 'url',
            'url'        => $externalurl,
            'embedurl'   => $embedurl,
            'urlkind'    => $kind,
            'name'       => format_string($urlrecord->name),
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    /**
     * Convert a YouTube URL to an embeddable format.
     */
    private static function normalize_youtube_url(string $url): string {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        return $url;
    }

    /**
     * Convert a Vimeo URL to an embeddable format.
     */
    private static function normalize_vimeo_url(string $url): string {
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return $url;
    }

    /**
     * Build mod_glossary payload: intro + list of entries.
     */
    private static function build_glossary_data($cm, $context): array {
        global $DB;
        $glossary = $DB->get_record('glossary', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($glossary, $context, 'glossary');

        $entries = $DB->get_records('glossary_entries', [
            'glossaryid' => $glossary->id,
            'approved'   => 1,
        ], 'concept ASC', 'id, concept, definition, definitionformat');

        $items = [];
        foreach ($entries as $entry) {
            $definition = file_rewrite_pluginfile_urls(
                $entry->definition, 'pluginfile.php', $context->id,
                'mod_glossary', 'entry', $entry->id
            );
            $items[] = [
                'id'         => (int) $entry->id,
                'concept'    => format_string($entry->concept),
                'definition' => format_text($definition, $entry->definitionformat, ['context' => $context]),
            ];
        }

        $data = [
            'kind'    => 'glossary',
            'entries' => $items,
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    /**
     * Build mod_scorm payload: minimal — tells the Vue player to use
     * CoursePlayerScorm component. Full CMI data loaded via get_scorm_cmi_data.
     */
    private static function build_scorm_data($cm, $context): array {
        global $DB;
        $scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($scorm, $context, 'scorm');

        $data = [
            'kind'     => 'scorm',
            'scormid'  => (int) $scorm->id,
            'name'     => format_string($scorm->name),
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 1+: New inline data builders (folder, choice, survey, etc.)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Build mod_folder payload: list of files in the folder.
     */
    private static function build_folder_data($cm, $context): array {
        global $DB;
        $folder = $DB->get_record('folder', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($folder, $context, 'folder');

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_folder', 'content', 0, 'filepath, filename', false);

        $items = [];
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $mimetype = $file->get_mimetype();
            $filename = $file->get_filename();
            $items[] = [
                'url'      => \moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                    $file->get_itemid(), $file->get_filepath(), $filename
                )->out(false),
                'name'     => $filename,
                'path'     => trim($file->get_filepath(), '/'),
                'size'     => display_size($file->get_filesize()),
                'mimetype' => $mimetype,
                'icon'     => self::get_file_icon($mimetype, $filename),
            ];
        }

        $data = [
            'kind'  => 'folder',
            'files' => $items,
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    /**
     * Map MIME type to a Bootstrap icon class.
     */
    private static function get_file_icon(string $mimetype, string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (strpos($mimetype, 'image/') === 0) return 'bi-file-image';
        if ($mimetype === 'application/pdf') return 'bi-file-pdf';
        if (strpos($mimetype, 'video/') === 0) return 'bi-file-play';
        if (strpos($mimetype, 'audio/') === 0) return 'bi-file-music';
        if (in_array($ext, ['doc', 'docx', 'odt'])) return 'bi-file-word';
        if (in_array($ext, ['xls', 'xlsx', 'ods'])) return 'bi-file-excel';
        if (in_array($ext, ['ppt', 'pptx', 'odp'])) return 'bi-file-ppt';
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) return 'bi-file-zip';
        return 'bi-file-earmark';
    }

    /**
     * Build mod_choice payload: question + options + current response + results.
     */
    private static function build_choice_data($cm, $course, $context): array {
        global $DB, $USER;
        $choice = $DB->get_record('choice', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($choice, $context, 'choice');

        $options = $DB->get_records('choice_options', ['choiceid' => $choice->id], 'id ASC');
        $answers = $DB->get_records('choice_answers', [
            'choiceid' => $choice->id,
            'userid'   => $USER->id,
        ]);
        $myselected = array_map(fn($a) => (int)$a->optionid, $answers);

        // Build options list.
        $optionslist = [];
        foreach ($options as $opt) {
            $optionslist[] = [
                'id'       => (int) $opt->id,
                'text'     => format_string($opt->text),
                'selected' => in_array((int)$opt->id, $myselected),
            ];
        }

        // Build results (if visible to user).
        $results = null;
        $showresults = (int)$choice->showresults;
        $hasanswered = !empty($answers);
        // CHOICE_SHOWRESULTS_ALWAYS=1, AFTER_ANSWER=2, AFTER_CLOSE=3, NOT=0.
        $showresultnow = ($showresults === 1)
            || ($showresults === 2 && $hasanswered)
            || ($showresults === 3 && $choice->timeclose > 0 && time() > $choice->timeclose);

        if ($showresultnow) {
            $allanswers = $DB->get_records('choice_answers', ['choiceid' => $choice->id]);
            $counts = [];
            foreach ($allanswers as $a) {
                $counts[(int)$a->optionid] = ($counts[(int)$a->optionid] ?? 0) + 1;
            }
            $results = [];
            foreach ($options as $opt) {
                $results[] = [
                    'optionid' => (int) $opt->id,
                    'text'     => format_string($opt->text),
                    'count'    => $counts[(int)$opt->id] ?? 0,
                ];
            }
        }

        $data = [
            'kind'         => 'choice',
            'choiceid'     => (int) $choice->id,
            'text'         => format_string($choice->name),
            'allowupdate'  => (bool) $choice->allowupdate,
            'allowmultiple' => (bool) $choice->allowmultiple,
            'hasanswered'  => $hasanswered,
            'options'      => $optionslist,
            'isclosed'     => ($choice->timeclose > 0 && time() > $choice->timeclose),
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        if ($results !== null) {
            $data['results'] = $results;
        }
        return $data;
    }

    /**
     * Build mod_survey payload: predefined Moodle survey (ATTLS / COLLES).
     */
    private static function build_survey_data($cm, $course, $context): array {
        global $DB, $USER;
        $survey = $DB->get_record('survey', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($survey, $context, 'survey');

        // Get the template questions.
        $questions = $DB->get_records_list('survey_questions', 'id',
            explode(',', $survey->questions), 'id ASC');

        $questionlist = [];
        foreach ($questions as $q) {
            if (empty(trim($q->text))) continue;
            $questionlist[] = [
                'id'       => (int) $q->id,
                'text'     => get_string($q->text, 'survey'),
                'shorttext' => $q->shorttext ? get_string($q->shorttext, 'survey') : '',
                'type'     => (int) $q->type,
                'options'  => $q->options ? get_string($q->options, 'survey') : '',
            ];
        }

        // Check if already answered.
        $done = $DB->record_exists('survey_answers', [
            'survey' => $survey->id,
            'userid' => $USER->id,
        ]);

        $data = [
            'kind'       => 'survey',
            'surveyid'   => (int) $survey->id,
            'name'       => format_string($survey->name),
            'questions'  => $questionlist,
            'done'       => $done,
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    /**
     * Build mod_feedback payload: multi-page feedback form.
     */
    private static function build_feedback_data($cm, $course, $context): array {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/feedback/lib.php');
        $feedback = $DB->get_record('feedback', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($feedback, $context, 'feedback');

        // Check completion.
        $iscomplete = $DB->record_exists('feedback_completed', [
            'feedback' => $feedback->id,
            'userid'   => $USER->id,
        ]);

        // Get items for page-based rendering.
        $items = $DB->get_records('feedback_item', ['feedback' => $feedback->id], 'position ASC');
        $pages = []; // Group items by pagebreak.
        $currentpage = [];
        $pagenum = 0;
        foreach ($items as $item) {
            if ($item->typ === 'pagebreak') {
                if (!empty($currentpage)) {
                    $pages[] = $currentpage;
                }
                $currentpage = [];
                $pagenum++;
                continue;
            }
            $currentpage[] = [
                'id'        => (int) $item->id,
                'typ'       => $item->typ,
                'name'      => format_string($item->name),
                'label'     => format_string($item->label),
                'required'  => (bool) $item->required,
                'options'   => $item->presentation,
                'dependitem' => (int) $item->dependitem,
                'dependvalue' => $item->dependvalue,
                'position'  => (int) $item->position,
            ];
        }
        if (!empty($currentpage)) {
            $pages[] = $currentpage;
        }

        // Get saved tmp values if user has an in-progress submission.
        $savedvalues = [];
        $tmpcompletion = $DB->get_record('feedback_completedtmp', [
            'feedback' => $feedback->id,
            'userid'   => $USER->id,
        ]);
        if ($tmpcompletion) {
            $tmpvalues = $DB->get_records('feedback_valuetmp', ['completed' => $tmpcompletion->id]);
            foreach ($tmpvalues as $v) {
                $savedvalues[(int)$v->item] = $v->value;
            }
        }

        $data = [
            'kind'          => 'feedback',
            'feedbackid'    => (int) $feedback->id,
            'name'          => format_string($feedback->name),
            'anonymous'     => (bool) $feedback->anonymous,
            'iscomplete'    => $iscomplete,
            'pages'         => $pages,
            'totalpages'    => count($pages),
            'savedvalues'   => $savedvalues,
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    /**
     * Build mod_wiki payload: wiki page content + metadata.
     */
    private static function build_wiki_data($cm, $context, int $pageid = 0): array {
        global $DB, $USER;
        $wiki = $DB->get_record('wiki', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($wiki, $context, 'wiki');

        // Get or create subwiki.
        $subwiki = $DB->get_record('wiki_subwikis', [
            'wikiid' => $wiki->id,
            'groupid' => 0,
            'userid' => ($wiki->wikimode === 'individual') ? $USER->id : 0,
        ]);

        $pagedata = null;
        $pagelist = [];

        if ($subwiki) {
            // If a specific page is requested.
            if ($pageid > 0) {
                $page = $DB->get_record('wiki_pages', ['id' => $pageid, 'subwikiid' => $subwiki->id]);
            } else {
                // Get first page (usually the wiki's first page).
                $page = $DB->get_record('wiki_pages', [
                    'subwikiid' => $subwiki->id,
                    'title'     => $wiki->firstpagetitle,
                ]);
                if (!$page) {
                    // Fallback to any page.
                    $page = $DB->get_record_sql(
                        "SELECT * FROM {wiki_pages} WHERE subwikiid = :swid ORDER BY id ASC LIMIT 1",
                        ['swid' => $subwiki->id]
                    );
                }
            }

            if ($page) {
                $content = file_rewrite_pluginfile_urls(
                    $page->cachedcontent, 'pluginfile.php', $context->id,
                    'mod_wiki', 'attachments', $subwiki->id
                );
                $pagedata = [
                    'id'       => (int) $page->id,
                    'title'    => format_string($page->title),
                    'content'  => format_text($content, FORMAT_HTML, ['context' => $context]),
                    'timemodified' => (int) $page->timemodified,
                    'userid'   => (int) $page->userid,
                ];
            }

            // Get page list for navigation.
            $pages = $DB->get_records('wiki_pages', ['subwikiid' => $subwiki->id], 'title ASC', 'id, title');
            foreach ($pages as $p) {
                $pagelist[] = [
                    'id'    => (int) $p->id,
                    'title' => format_string($p->title),
                ];
            }
        }

        $data = [
            'kind'       => 'wiki',
            'wikiid'     => (int) $wiki->id,
            'wikimode'   => $wiki->wikimode,
            'firstpage'  => format_string($wiki->firstpagetitle),
            'pages'      => $pagelist,
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        if ($pagedata !== null) {
            $data['page'] = $pagedata;
        }
        return $data;
    }

    /**
     * Build mod_data (database) payload: entries table + field definitions.
     */
    private static function build_data_data($cm, $context): array {
        global $DB, $USER;
        $database = $DB->get_record('data', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($database, $context, 'data');

        // Get field definitions.
        $fields = $DB->get_records('data_fields', ['dataid' => $database->id], 'id ASC');
        $fieldlist = [];
        foreach ($fields as $f) {
            $fieldlist[] = [
                'id'          => (int) $f->id,
                'name'        => format_string($f->name),
                'description' => format_string($f->description),
                'type'        => $f->type,
                'required'    => (bool) $f->required,
                'param1'      => $f->param1 ?? '',
                'param2'      => $f->param2 ?? '',
                'param3'      => $f->param3 ?? '',
            ];
        }

        // Get approved entries (latest 50).
        $entries = $DB->get_records_sql(
            "SELECT e.id, e.userid, e.timecreated, e.timemodified, e.approved
             FROM {data_records} e
             WHERE e.dataid = :dataid AND e.approved = 1
             ORDER BY e.timecreated DESC
             LIMIT 50",
            ['dataid' => $database->id]
        );

        $entrylist = [];
        foreach ($entries as $entry) {
            // Get content for each field.
            $contents = $DB->get_records('data_content', ['recordid' => $entry->id]);
            $fieldvalues = [];
            foreach ($contents as $c) {
                $fieldvalues[(int)$c->fieldid] = [
                    'content'  => $c->content ?? '',
                    'content1' => $c->content1 ?? '',
                    'content2' => $c->content2 ?? '',
                    'content3' => $c->content3 ?? '',
                    'content4' => $c->content4 ?? '',
                ];
            }
            $user = $DB->get_record('user', ['id' => $entry->userid], 'id, firstname, lastname');
            $entrylist[] = [
                'id'          => (int) $entry->id,
                'userid'      => (int) $entry->userid,
                'userfullname' => $user ? fullname($user) : '',
                'timecreated' => (int) $entry->timecreated,
                'fields'      => $fieldvalues,
            ];
        }

        $data = [
            'kind'         => 'data',
            'dataid'       => (int) $database->id,
            'name'         => format_string($database->name),
            'fields'       => $fieldlist,
            'entries'      => $entrylist,
            'totalentries' => (int) $DB->count_records('data_records', [
                'dataid' => $database->id, 'approved' => 1,
            ]),
            'canaddentry'  => has_capability('mod/data:writeentry', $context),
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    /**
     * Build mod_quiz payload: quiz state + questions for Vue rendering.
     */
    private static function build_quiz_data($cm, $course, $context): array {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($quiz, $context, 'quiz');

        // Count attempts.
        $attemptsused = $DB->count_records('quiz_attempts', [
            'quiz'   => $quiz->id,
            'userid' => $USER->id,
            'preview' => 0,
        ]);

        // Find in-progress attempt.
        $inattempt = $DB->get_record_sql(
            "SELECT id, attempt, currentpage, timestart, uniqueid
             FROM {quiz_attempts}
             WHERE quiz = :quizid AND userid = :userid AND state = 'inprogress'
             ORDER BY attempt DESC LIMIT 1",
            ['quizid' => $quiz->id, 'userid' => $USER->id]
        );

        // Find last finished attempt for review.
        $lastattempt = null;
        if (!$inattempt) {
            $lastattempt = $DB->get_record_sql(
                "SELECT id, attempt, state, sumgrades, timestart, timefinish, uniqueid
                 FROM {quiz_attempts}
                 WHERE quiz = :quizid AND userid = :userid AND state = 'finished' AND preview = 0
                 ORDER BY attempt DESC LIMIT 1",
                ['quizid' => $quiz->id, 'userid' => $USER->id]
            );
        }

        $data = [
            'kind'            => 'quiz',
            'quizid'          => (int) $quiz->id,
            'name'            => format_string($quiz->name),
            'attemptsallowed' => (int) $quiz->attempts,
            'attemptsused'    => $attemptsused,
            'timelimit'       => (int) $quiz->timelimit,
            'grademethod'     => (int) $quiz->grademethod,
            'state'           => 'notstarted',
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }

        if ($inattempt) {
            $data['state'] = 'inprogress';
            $data['attemptid'] = (int) $inattempt->id;
            $data['currentpage'] = (int) $inattempt->currentpage;
            $data['timestarted'] = (int) $inattempt->timestart;

            // Extract questions for the current page.
            $data['questions'] = self::extract_quiz_questions(
                $quiz, $inattempt, (int)$inattempt->currentpage, $context
            );

            $data['totalpages'] = (int) $DB->get_field_sql(
                "SELECT MAX(page) FROM {quiz_slots} WHERE quizid = :quizid",
                ['quizid' => $quiz->id]
            ) + 1; // pages are 0-based in slots.
        } else if ($lastattempt) {
            $data['state'] = 'finished';
            $data['lastattemptid'] = (int) $lastattempt->id;
            $data['grade'] = self::calculate_quiz_grade($quiz, $lastattempt);
            $data['reviewavailable'] = self::is_quiz_review_available($quiz, $lastattempt);
        }

        // Can start new attempt?
        $data['canstartnew'] = ($quiz->attempts == 0 || $attemptsused < $quiz->attempts)
            && !$inattempt;

        return $data;
    }

    /**
     * Extract quiz questions for a given page from an in-progress attempt.
     */
    private static function extract_quiz_questions($quiz, $attempt, int $page, $context): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $questions = [];
        try {
            $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
            $slots = $DB->get_records('quiz_slots', [
                'quizid' => $quiz->id,
                'page'   => $page,
            ], 'slot ASC');

            foreach ($slots as $slotrecord) {
                $slot = (int)$slotrecord->slot;
                try {
                    $qa = $quba->get_question_attempt($slot);
                    $question = $qa->get_question();
                    $qtype = $question->get_type_name();

                    $qdata = [
                        'slot'          => $slot,
                        'type'          => $qtype,
                        'text'          => format_text($question->questiontext, $question->questiontextformat, ['context' => $context]),
                        'sequencecheck' => $qa->get_sequence_check_count(),
                        'flagged'       => $qa->is_flagged(),
                        'hasresponse'   => $qa->get_last_step()->has_qt_var('answer'),
                    ];

                    // Type-specific fields.
                    switch ($qtype) {
                        case 'multichoice':
                            $order = $qa->get_step(0)->get_qt_var('_order');
                            $choiceorder = $order ? explode(',', $order) : [];
                            $choices = [];
                            foreach ($choiceorder as $i => $choicenum) {
                                $ans = $question->answers[$choicenum] ?? null;
                                if ($ans) {
                                    $choices[] = [
                                        'value'   => (int) $i,
                                        'label'   => format_text($ans->answer, $ans->answerformat, ['context' => $context]),
                                        'checked' => false, // Frontend fills from saved response.
                                    ];
                                }
                            }
                            $qdata['choices'] = $choices;
                            $qdata['single'] = ((int)$question->single === 1);
                            break;

                        case 'truefalse':
                            $qdata['choices'] = [
                                ['value' => 1, 'label' => get_string('true', 'qtype_truefalse'), 'checked' => false],
                                ['value' => 0, 'label' => get_string('false', 'qtype_truefalse'), 'checked' => false],
                            ];
                            break;

                        case 'shortanswer':
                        case 'numerical':
                            $qdata['inputtype'] = ($qtype === 'numerical') ? 'number' : 'text';
                            break;

                        case 'essay':
                            $qdata['responseformat'] = $question->responseformat ?? 'editor';
                            $qdata['attachments'] = $question->attachments ?? 0;
                            break;

                        case 'match':
                        case 'matching':
                            $stems = [];
                            $choices = [];
                            if (isset($question->stems)) {
                                foreach ($question->stems as $key => $stem) {
                                    $stems[] = [
                                        'key'  => $key,
                                        'text' => format_string($stem),
                                    ];
                                }
                            }
                            if (isset($question->choices)) {
                                foreach ($question->choices as $key => $choice) {
                                    $choices[] = [
                                        'key'  => $key,
                                        'text' => format_string($choice),
                                    ];
                                }
                            }
                            $qdata['stems'] = $stems;
                            $qdata['matchoptions'] = $choices;
                            break;

                        case 'description':
                            // Description questions are just text, no answer needed.
                            $qdata['isinfo'] = true;
                            break;
                    }

                    // Restore saved answer if any.
                    $laststep = $qa->get_last_step();
                    $savedresponse = [];
                    foreach ($laststep->get_all_data() as $name => $value) {
                        if (strpos($name, '-') === false && strpos($name, '_') !== 0) {
                            $savedresponse[$name] = $value;
                        }
                    }
                    $qdata['savedresponse'] = $savedresponse;

                    $questions[] = $qdata;
                } catch (\Exception $e) {
                    debugging('Quiz question extract error slot ' . $slot . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        } catch (\Exception $e) {
            debugging('Quiz question usage load error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $questions;
    }

    /**
     * Calculate the final grade for a quiz attempt.
     */
    private static function calculate_quiz_grade($quiz, $attempt): ?float {
        if ($attempt->sumgrades === null) return null;
        $grade = $quiz->grade * ($attempt->sumgrades / $quiz->sumgrades);
        return round($grade, 2);
    }

    /**
     * Check if quiz review is available based on quiz settings.
     */
    private static function is_quiz_review_available($quiz, $attempt): bool {
        // Simplified: check if review after close is enabled.
        return (bool)($quiz->reviewattempt & 0x10000);
    }

    /**
     * Build mod_assign payload: assignment description + submission status.
     */
    private static function build_assign_data($cm, $course, $context): array {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($assign, $context, 'assign');

        // Get submission status.
        $submission = $DB->get_record_sql(
            "SELECT id, status, timecreated, timemodified, attemptnumber
             FROM {assign_submission}
             WHERE assignment = :assignid AND userid = :userid AND latest = 1",
            ['assignid' => $assign->id, 'userid' => $USER->id]
        );

        // Get grade/feedback.
        $grade = $DB->get_record_sql(
            "SELECT id, grade, grader, timemodified AS timegraded
             FROM {assign_grades}
             WHERE assignment = :assignid AND userid = :userid AND attemptnumber = :attempt",
            ['assignid' => $assign->id, 'userid' => $USER->id,
             'attempt' => $submission ? $submission->attemptnumber : 0]
        );

        // Determine submission types enabled.
        $plugins = $DB->get_records('assign_plugin_config', [
            'assignment' => $assign->id,
            'subtype'    => 'assignsubmission',
            'name'       => 'enabled',
        ]);
        $submissiontypes = [];
        foreach ($plugins as $p) {
            if ($p->value === '1') {
                $submissiontypes[] = $p->plugin;
            }
        }

        // Get existing online text submission.
        $onlinetext = '';
        if ($submission && in_array('onlinetext', $submissiontypes)) {
            $textrecord = $DB->get_record('assignsubmission_onlinetext', [
                'assignment' => $assign->id,
                'submission' => $submission->id,
            ]);
            if ($textrecord) {
                $onlinetext = format_text($textrecord->onlinetext, $textrecord->onlineformat, ['context' => $context]);
            }
        }

        // Get existing file submissions.
        $filesubmissions = [];
        if ($submission && in_array('file', $submissiontypes)) {
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'assignsubmission_file',
                'submission_files', $submission->id, 'sortorder', false);
            foreach ($files as $file) {
                $filesubmissions[] = [
                    'name'     => $file->get_filename(),
                    'size'     => display_size($file->get_filesize()),
                    'url'      => \moodle_url::make_pluginfile_url(
                        $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                        $file->get_itemid(), $file->get_filepath(), $file->get_filename()
                    )->out(false),
                ];
            }
        }

        // Feedback comments.
        $feedbackcomments = '';
        if ($grade) {
            $feedbackrecord = $DB->get_record('assignfeedback_comments', [
                'assignment' => $assign->id,
                'grade'      => $grade->id,
            ]);
            if ($feedbackrecord) {
                $feedbackcomments = format_text($feedbackrecord->commenttext,
                    $feedbackrecord->commentformat, ['context' => $context]);
            }
        }

        $data = [
            'kind'             => 'assign',
            'assignid'         => (int) $assign->id,
            'name'             => format_string($assign->name),
            'duedate'          => (int) $assign->duedate,
            'cutoffdate'       => (int) $assign->cutoffdate,
            'submissiontypes'  => $submissiontypes,
            'maxfilesubmissions' => (int) ($assign->maxfilesubmissions ?? 0),
            'maxsubmissionsizebytes' => (int) ($assign->maxsubmissionsizebytes ?? 0),
            'attemptreopenmethod' => $assign->attemptreopenmethod ?? 'none',
            'maxattempts'      => (int) ($assign->maxattempts ?? -1),
            'submissionstatus' => $submission ? $submission->status : 'nosubmission',
            'submissionid'     => $submission ? (int) $submission->id : null,
            'onlinetext'       => $onlinetext,
            'filesubmissions'  => $filesubmissions,
            'gradevalue'       => $grade ? (float) $grade->grade : null,
            'grademax'         => (float) $assign->grade,
            'feedbackcomments'  => $feedbackcomments,
            'isgraded'         => ($grade && $grade->grade !== null && (float)$grade->grade >= 0),
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    /**
     * Build mod_lesson payload: current page content + navigation.
     */
    private static function build_lesson_data($cm, $course, $context, int $pageid = 0): array {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/lesson/locallib.php');
        $lesson = $DB->get_record('lesson', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($lesson, $context, 'lesson');

        $pages = $DB->get_records('lesson_pages', ['lessonid' => $lesson->id], 'ordering ASC');
        $totalcontentpages = count($pages);

        // Determine current page.
        $currentpage = null;
        if ($pageid > 0) {
            $currentpage = $DB->get_record('lesson_pages', ['id' => $pageid, 'lessonid' => $lesson->id]);
        }
        if (!$currentpage && !empty($pages)) {
            // Check for in-progress attempt: last visited page.
            $lastbranch = $DB->get_record_sql(
                "SELECT pageid FROM {lesson_branch}
                 WHERE lessonid = :lessonid AND userid = :userid
                 ORDER BY timeseen DESC LIMIT 1",
                ['lessonid' => $lesson->id, 'userid' => $USER->id]
            );
            if ($lastbranch) {
                $currentpage = $pages[$lastbranch->pageid] ?? reset($pages);
            } else {
                $currentpage = reset($pages);
            }
        }

        $pagedata = null;
        $answers = [];
        if ($currentpage) {
            $content = file_rewrite_pluginfile_urls(
                $currentpage->contents, 'pluginfile.php', $context->id,
                'mod_lesson', 'page_contents', $currentpage->id
            );
            $pagedata = [
                'id'       => (int) $currentpage->id,
                'title'    => format_string($currentpage->title),
                'content'  => format_text($content, $currentpage->contentsformat, ['context' => $context]),
                'type'     => (int) $currentpage->qtype,
                'typelabel' => self::get_lesson_page_type_label($currentpage->qtype),
            ];

            // Get answer options for question pages.
            $answerrecords = $DB->get_records('lesson_answers', [
                'pageid'   => $currentpage->id,
                'lessonid' => $lesson->id,
            ], 'id ASC');
            foreach ($answerrecords as $ans) {
                $answers[] = [
                    'id'      => (int) $ans->id,
                    'text'    => format_text($ans->answer, $ans->answerformat, ['context' => $context]),
                    'jumpto'  => (int) $ans->jumpto,
                ];
            }
        }

        // Check completion.
        $iscomplete = $DB->record_exists('lesson_grades', [
            'lessonid' => $lesson->id,
            'userid'   => $USER->id,
        ]);

        $data = [
            'kind'       => 'lesson',
            'lessonid'   => (int) $lesson->id,
            'name'       => format_string($lesson->name),
            'totalpages' => $totalcontentpages,
            'iscomplete' => $iscomplete,
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        if ($pagedata !== null) {
            $data['page'] = $pagedata;
            $data['answers'] = $answers;
        }
        return $data;
    }

    /**
     * Get a human-readable label for a lesson page type.
     */
    private static function get_lesson_page_type_label(int $qtype): string {
        switch ($qtype) {
            case 20: return 'content'; // LESSON_PAGE_BRANCHTABLE
            case 1:  return 'truefalse';
            case 2:  return 'multichoice';
            case 3:  return 'shortanswer';
            case 5:  return 'matching';
            case 8:  return 'numerical';
            case 10: return 'essay';
            default: return 'unknown';
        }
    }

    /**
     * Build mod_workshop payload: multi-phase workshop with submissions + assessments.
     */
    private static function build_workshop_data($cm, $course, $context): array {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/workshop/locallib.php');
        $workshop = $DB->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST);
        $intro = self::format_intro_text($workshop, $context, 'workshop');

        // Determine current phase.
        $phases = [
            10 => 'setup',
            20 => 'submission',
            30 => 'assessment',
            40 => 'grading',
            50 => 'closed',
        ];
        $currentphase = $phases[$workshop->phase] ?? 'unknown';

        // Get user's submission.
        $submission = $DB->get_record('workshop_submissions', [
            'workshopid' => $workshop->id,
            'authorid'   => $USER->id,
        ]);

        $submissiondata = null;
        if ($submission) {
            $content = file_rewrite_pluginfile_urls(
                $submission->content, 'pluginfile.php', $context->id,
                'mod_workshop', 'submission_content', $submission->id
            );
            $submissiondata = [
                'id'        => (int) $submission->id,
                'title'     => format_string($submission->title),
                'content'   => format_text($content, $submission->contentformat, ['context' => $context]),
                'grade'     => $submission->grade !== null ? (float) $submission->grade : null,
                'timecreated' => (int) $submission->timecreated,
            ];
        }

        // Get assessments assigned to this user.
        $assessments = [];
        $myassessments = $DB->get_records_sql(
            "SELECT a.id, a.submissionid, a.grade, a.feedbackauthor,
                    s.title AS submissiontitle
             FROM {workshop_assessments} a
             JOIN {workshop_submissions} s ON s.id = a.submissionid
             WHERE a.reviewerid = :userid AND s.workshopid = :workshopid
             ORDER BY a.id ASC",
            ['userid' => $USER->id, 'workshopid' => $workshop->id]
        );
        foreach ($myassessments as $a) {
            $assessments[] = [
                'id'              => (int) $a->id,
                'submissionid'    => (int) $a->submissionid,
                'submissiontitle' => format_string($a->submissiontitle),
                'grade'           => $a->grade !== null ? (float) $a->grade : null,
                'feedbackauthor'  => $a->feedbackauthor ? format_text($a->feedbackauthor, FORMAT_HTML, ['context' => $context]) : '',
            ];
        }

        $data = [
            'kind'         => 'workshop',
            'workshopid'   => (int) $workshop->id,
            'name'         => format_string($workshop->name),
            'phase'        => $currentphase,
            'phasecode'    => (int) $workshop->phase,
            'submissiontypes' => [
                'text' => (bool) ($workshop->submissiontypetext ?? 1),
                'file' => (bool) ($workshop->submissiontypefile ?? 1),
            ],
            'cansubmit'    => has_capability('mod/workshop:submit', $context),
            'canassess'    => has_capability('mod/workshop:peerassess', $context),
            'submission'   => $submissiondata,
            'assessments'  => $assessments,
        ];
        if ($intro !== '') {
            $data['intro'] = $intro;
        }
        return $data;
    }

    /**
     * Format an activity's intro text (returns RAW formatted HTML, no wrapper).
     */
    private static function format_intro_text($record, $context, string $component): string {
        if (empty($record->intro)) {
            return '';
        }
        $intro = file_rewrite_pluginfile_urls(
            $record->intro, 'pluginfile.php', $context->id, 'mod_' . $component, 'intro', null
        );
        return format_text($intro, $record->introformat ?? FORMAT_HTML, ['context' => $context]);
    }

    /**
     * Compose the legacy `html` string from a structured inline payload.
     *
     * Exists ONLY to keep `amd/src/course_page.js` working until the AMD
     * frontend is removed. The Vue frontend ignores `html` entirely and
     * renders chrome from the structured `inline` field instead.
     */
    private static function compose_legacy_html(array $data): string {
        switch ($data['kind']) {
            case 'page':
                return '<div class="smgp-activity-content smgp-activity-content--page">'
                    . ($data['content'] ?? '') . '</div>';

            case 'book':
                if (!empty($data['empty'])) {
                    return '<div class="smgp-activity-content"><p>No chapters available.</p></div>';
                }
                $info = $data['chapter'];
                $nav = '<div class="smgp-activity-content__chapter-info">'
                    . '<strong>' . s($info['title']) . '</strong>'
                    . ' <span class="text-muted">(' . $info['current'] . '/' . $info['total'] . ')</span>'
                    . '</div>';
                return '<div class="smgp-activity-content smgp-activity-content--book">'
                    . $nav . ($data['content'] ?? '') . '</div>';

            case 'resource':
                $intro = '<div class="smgp-activity-content__intro">' . ($data['intro'] ?? '') . '</div>';
                $filehtml = '';
                if (!empty($data['file'])) {
                    $f = $data['file'];
                    $url = $f['url'];
                    $name = s($f['name']);
                    $mt = s($f['mimetype']);
                    switch ($f['kind']) {
                        case 'image':
                            $filehtml = '<div class="smgp-activity-content__preview">'
                                . '<img src="' . $url . '" alt="' . $name
                                . '" style="max-width:100%;height:auto;border-radius:8px;"></div>';
                            break;
                        case 'pdf':
                            // Native browser viewer with toolbar.
                            $filehtml = '<div class="smgp-activity-content__document">'
                                . '<iframe src="' . $url . '#toolbar=1&navpanes=0" '
                                . 'class="smgp-activity-content__document-frame" '
                                . 'title="' . $name . '"></iframe></div>';
                            break;
                        case 'document':
                            // Office docs (doc/ppt/xls/...) via Google Docs Viewer.
                            $viewerurl = 'https://docs.google.com/gview?url=' . urlencode($url) . '&embedded=true';
                            $filehtml = '<div class="smgp-activity-content__document">'
                                . '<iframe src="' . $viewerurl . '" '
                                . 'class="smgp-activity-content__document-frame" '
                                . 'title="' . $name . '"></iframe></div>';
                            break;
                        case 'video':
                            $filehtml = '<div class="smgp-activity-content__video-player">'
                                . '<video controls preload="metadata" class="smgp-video-player">'
                                . '<source src="' . $url . '" type="' . $mt . '">'
                                . get_string('course_page_video_unsupported', 'local_sm_graphics_plugin')
                                . '</video></div>';
                            break;
                        case 'audio':
                            $filehtml = '<div class="smgp-activity-content__audio-player">'
                                . '<audio controls preload="metadata" class="smgp-audio-player">'
                                . '<source src="' . $url . '" type="' . $mt . '">'
                                . '</audio></div>';
                            break;
                    }
                    // Download button: skip for self-contained viewers (video, audio, PDF, document).
                    if (!in_array($f['kind'], ['video', 'audio', 'pdf', 'document'], true)) {
                        $filehtml .= '<div class="smgp-activity-content__file mt-2">'
                            . '<a href="' . $url . '" class="btn btn-primary btn-sm">'
                            . '<i class="icon-download"></i> Download '
                            . $name . ' (' . s($f['size']) . ')</a></div>';
                    }
                }
                return '<div class="smgp-activity-content smgp-activity-content--resource">'
                    . $intro . $filehtml . '</div>';

            case 'label':
                return '<div class="smgp-activity-content smgp-activity-content--label">'
                    . ($data['content'] ?? '') . '</div>';

            case 'unsupported':
            default:
                return '<div class="smgp-activity-content"><p>Content not available inline.</p></div>';
        }
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html'         => new external_value(PARAM_RAW, 'Legacy: pre-composed inline HTML for AMD frontend (deprecated)'),
            'name'         => new external_value(PARAM_TEXT, 'Activity name'),
            'modname'      => new external_value(PARAM_TEXT, 'Module type'),
            'url'          => new external_value(PARAM_URL, 'Activity URL'),
            'rendermode'   => new external_value(PARAM_ALPHA, 'Render mode: inline, iframe, or redirect'),
            'iframeurl'    => new external_value(PARAM_RAW, 'URL for iframe embedding (empty if inline/redirect)'),
            'itemcount'    => new external_value(PARAM_INT, 'Total countable items (0 = no counter)'),
            'currentitem'  => new external_value(PARAM_INT, 'Current item position (1-based, 0 = unknown)'),
            'counterlabel' => new external_value(PARAM_TEXT, 'Counter label (Slide, Page, Question)'),
            'completeditems' => new external_value(PARAM_INT, 'Items completed/viewed by user (for furthest-reached)'),
            'totalpages'   => new external_value(PARAM_INT, 'Total pages in activity (for quiz page navigation)'),
            // Structured inline payload for the Vue frontend. Only present when
            // rendermode === 'inline'. Vue renders all chrome from this data.
            //
            // IMPORTANT: every field used by ANY kind must be declared here
            // (as VALUE_OPTIONAL when not always present). Moodle silently
            // strips fields not declared in execute_returns(), which is why
            // mod_url embeds (genially / youtube / vimeo) and mod_glossary
            // entries previously rendered as empty — `urlkind`, `embedurl`,
            // `url`, `name`, `entries` were all stripped before reaching the
            // SPA.
            'inline'       => new external_single_structure([
                'kind'    => new external_value(PARAM_RAW, 'Activity kind identifier'),
                'content' => new external_value(PARAM_RAW, 'Moodle-formatted user content', VALUE_OPTIONAL),
                'intro'   => new external_value(PARAM_RAW, 'Activity intro text', VALUE_OPTIONAL),
                'empty'   => new external_value(PARAM_BOOL, 'True if no content', VALUE_OPTIONAL),
                'chapter' => new external_single_structure([
                    'title'   => new external_value(PARAM_TEXT, 'Chapter title'),
                    'current' => new external_value(PARAM_INT, '1-based chapter index'),
                    'total'   => new external_value(PARAM_INT, 'Total chapters'),
                ], 'Book chapter info', VALUE_OPTIONAL),
                'allchapters' => new external_multiple_structure(
                    new external_single_structure([
                        'title'   => new external_value(PARAM_TEXT, 'Chapter title'),
                        'content' => new external_value(PARAM_RAW, 'Chapter content HTML'),
                    ]),
                    'All book chapters pre-rendered for instant client-side navigation',
                    VALUE_OPTIONAL
                ),
                'viewedcount' => new external_value(PARAM_INT, 'Number of unique chapters viewed', VALUE_OPTIONAL),
                'file'    => new external_single_structure([
                    'url'      => new external_value(PARAM_URL, 'Pluginfile URL'),
                    'name'     => new external_value(PARAM_TEXT, 'File name'),
                    'size'     => new external_value(PARAM_TEXT, 'Human-readable file size'),
                    'mimetype' => new external_value(PARAM_RAW, 'MIME type'),
                    'kind'     => new external_value(PARAM_ALPHA, 'image | pdf | document | video | audio | other'),
                ], 'Resource file metadata', VALUE_OPTIONAL),
                // mod_url fields.
                'url'      => new external_value(PARAM_RAW, 'External URL', VALUE_OPTIONAL),
                'embedurl' => new external_value(PARAM_RAW, 'Embeddable URL', VALUE_OPTIONAL),
                'urlkind'  => new external_value(PARAM_ALPHA, 'embed | link', VALUE_OPTIONAL),
                'name'     => new external_value(PARAM_TEXT, 'Display name', VALUE_OPTIONAL),
                // mod_glossary entries.
                'entries'  => new external_multiple_structure(
                    new external_single_structure([
                        'id'         => new external_value(PARAM_INT, 'Entry id'),
                        'concept'    => new external_value(PARAM_TEXT, 'Term'),
                        'definition' => new external_value(PARAM_RAW, 'HTML-formatted definition'),
                    ]),
                    'Glossary entries',
                    VALUE_OPTIONAL
                ),
                // mod_folder files.
                'files' => new external_multiple_structure(
                    new external_single_structure([
                        'url'      => new external_value(PARAM_RAW, 'Download URL'),
                        'name'     => new external_value(PARAM_TEXT, 'File name'),
                        'path'     => new external_value(PARAM_TEXT, 'Folder path'),
                        'size'     => new external_value(PARAM_TEXT, 'Human-readable size'),
                        'mimetype' => new external_value(PARAM_RAW, 'MIME type'),
                        'icon'     => new external_value(PARAM_TEXT, 'Bootstrap icon class'),
                    ]),
                    'Folder files',
                    VALUE_OPTIONAL
                ),
                // mod_choice.
                'choiceid'      => new external_value(PARAM_INT, 'Choice instance ID', VALUE_OPTIONAL),
                'text'          => new external_value(PARAM_RAW, 'Choice question text', VALUE_OPTIONAL),
                'allowupdate'   => new external_value(PARAM_BOOL, 'Can update response', VALUE_OPTIONAL),
                'allowmultiple' => new external_value(PARAM_BOOL, 'Multiple answers allowed', VALUE_OPTIONAL),
                'hasanswered'   => new external_value(PARAM_BOOL, 'User has answered', VALUE_OPTIONAL),
                'isclosed'      => new external_value(PARAM_BOOL, 'Choice is closed', VALUE_OPTIONAL),
                'options' => new external_multiple_structure(
                    new external_single_structure([
                        'id'       => new external_value(PARAM_INT, 'Option ID'),
                        'text'     => new external_value(PARAM_RAW, 'Option text'),
                        'selected' => new external_value(PARAM_BOOL, 'Currently selected'),
                    ]),
                    'Choice options',
                    VALUE_OPTIONAL
                ),
                'results' => new external_multiple_structure(
                    new external_single_structure([
                        'optionid' => new external_value(PARAM_INT, 'Option ID'),
                        'text'     => new external_value(PARAM_RAW, 'Option text'),
                        'count'    => new external_value(PARAM_INT, 'Vote count'),
                    ]),
                    'Choice results',
                    VALUE_OPTIONAL
                ),
                // mod_survey.
                'surveyid' => new external_value(PARAM_INT, 'Survey instance ID', VALUE_OPTIONAL),
                'questions' => new external_multiple_structure(
                    new external_single_structure([
                        'id'        => new external_value(PARAM_INT, 'Question ID'),
                        'text'      => new external_value(PARAM_RAW, 'Question text'),
                        'shorttext' => new external_value(PARAM_TEXT, 'Short text', VALUE_OPTIONAL),
                        'type'      => new external_value(PARAM_INT, 'Question type'),
                        'options'   => new external_value(PARAM_RAW, 'Answer options', VALUE_OPTIONAL),
                    ]),
                    'Survey questions',
                    VALUE_OPTIONAL
                ),
                'done' => new external_value(PARAM_BOOL, 'Survey completed', VALUE_OPTIONAL),
                // mod_feedback.
                'feedbackid'  => new external_value(PARAM_INT, 'Feedback instance ID', VALUE_OPTIONAL),
                'anonymous'   => new external_value(PARAM_BOOL, 'Anonymous feedback', VALUE_OPTIONAL),
                'iscomplete'  => new external_value(PARAM_BOOL, 'Feedback completed', VALUE_OPTIONAL),
                'pages' => new external_multiple_structure(
                    new external_multiple_structure(
                        new external_single_structure([
                            'id'          => new external_value(PARAM_INT, 'Item ID'),
                            'typ'         => new external_value(PARAM_TEXT, 'Item type'),
                            'name'        => new external_value(PARAM_RAW, 'Item name/question'),
                            'label'       => new external_value(PARAM_RAW, 'Item label'),
                            'required'    => new external_value(PARAM_BOOL, 'Required'),
                            'options'     => new external_value(PARAM_RAW, 'Presentation/options data'),
                            'dependitem'  => new external_value(PARAM_INT, 'Dependent item ID'),
                            'dependvalue' => new external_value(PARAM_RAW, 'Dependent value'),
                            'position'    => new external_value(PARAM_INT, 'Position'),
                        ])
                    ),
                    'Feedback pages (arrays of items)',
                    VALUE_OPTIONAL
                ),
                'totalpages'  => new external_value(PARAM_INT, 'Total pages', VALUE_OPTIONAL),
                'savedvalues' => new external_single_structure([], 'Saved tmp values', VALUE_OPTIONAL),
                // mod_wiki.
                'wikiid'    => new external_value(PARAM_INT, 'Wiki instance ID', VALUE_OPTIONAL),
                'wikimode'  => new external_value(PARAM_ALPHA, 'collaborative | individual', VALUE_OPTIONAL),
                'firstpage' => new external_value(PARAM_TEXT, 'First page title', VALUE_OPTIONAL),
                'page' => new external_single_structure([
                    'id'           => new external_value(PARAM_INT, 'Page ID'),
                    'title'        => new external_value(PARAM_TEXT, 'Page title'),
                    'content'      => new external_value(PARAM_RAW, 'Page content HTML'),
                    'timemodified' => new external_value(PARAM_INT, 'Last modified timestamp'),
                    'userid'       => new external_value(PARAM_INT, 'Author user ID'),
                ], 'Wiki page data', VALUE_OPTIONAL),
                // mod_data (database).
                'dataid'       => new external_value(PARAM_INT, 'Database instance ID', VALUE_OPTIONAL),
                'fields' => new external_multiple_structure(
                    new external_single_structure([
                        'id'          => new external_value(PARAM_INT, 'Field ID'),
                        'name'        => new external_value(PARAM_TEXT, 'Field name'),
                        'description' => new external_value(PARAM_TEXT, 'Field description'),
                        'type'        => new external_value(PARAM_TEXT, 'Field type'),
                        'required'    => new external_value(PARAM_BOOL, 'Required'),
                        'param1'      => new external_value(PARAM_RAW, 'Param 1'),
                        'param2'      => new external_value(PARAM_RAW, 'Param 2'),
                        'param3'      => new external_value(PARAM_RAW, 'Param 3'),
                    ]),
                    'Database fields',
                    VALUE_OPTIONAL
                ),
                'totalentries' => new external_value(PARAM_INT, 'Total entries', VALUE_OPTIONAL),
                'canaddentry'  => new external_value(PARAM_BOOL, 'Can add entry', VALUE_OPTIONAL),
                // mod_quiz.
                'quizid'          => new external_value(PARAM_INT, 'Quiz instance ID', VALUE_OPTIONAL),
                'attemptsallowed' => new external_value(PARAM_INT, 'Max attempts (0=unlimited)', VALUE_OPTIONAL),
                'attemptsused'    => new external_value(PARAM_INT, 'Attempts used', VALUE_OPTIONAL),
                'timelimit'       => new external_value(PARAM_INT, 'Time limit in seconds', VALUE_OPTIONAL),
                'grademethod'     => new external_value(PARAM_INT, 'Grade method', VALUE_OPTIONAL),
                'state'           => new external_value(PARAM_ALPHA, 'notstarted | inprogress | finished', VALUE_OPTIONAL),
                'attemptid'       => new external_value(PARAM_INT, 'Current attempt ID', VALUE_OPTIONAL),
                'currentpage'     => new external_value(PARAM_INT, 'Current quiz page', VALUE_OPTIONAL),
                'timestarted'     => new external_value(PARAM_INT, 'Attempt start time', VALUE_OPTIONAL),
                'canstartnew'     => new external_value(PARAM_BOOL, 'Can start new attempt', VALUE_OPTIONAL),
                'lastattemptid'   => new external_value(PARAM_INT, 'Last finished attempt ID', VALUE_OPTIONAL),
                'grade'           => new external_value(PARAM_FLOAT, 'Final grade', VALUE_OPTIONAL),
                'reviewavailable' => new external_value(PARAM_BOOL, 'Review available', VALUE_OPTIONAL),
                // mod_assign.
                'assignid'         => new external_value(PARAM_INT, 'Assignment instance ID', VALUE_OPTIONAL),
                'duedate'          => new external_value(PARAM_INT, 'Due date timestamp', VALUE_OPTIONAL),
                'cutoffdate'       => new external_value(PARAM_INT, 'Cutoff date', VALUE_OPTIONAL),
                'submissiontypes'  => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Submission type'),
                    'Enabled submission types',
                    VALUE_OPTIONAL
                ),
                'maxfilesubmissions'     => new external_value(PARAM_INT, 'Max file submissions', VALUE_OPTIONAL),
                'maxsubmissionsizebytes' => new external_value(PARAM_INT, 'Max file size', VALUE_OPTIONAL),
                'attemptreopenmethod'    => new external_value(PARAM_TEXT, 'Reopen method', VALUE_OPTIONAL),
                'maxattempts'            => new external_value(PARAM_INT, 'Max attempts', VALUE_OPTIONAL),
                'submissionstatus'       => new external_value(PARAM_TEXT, 'Submission status', VALUE_OPTIONAL),
                'submissionid'           => new external_value(PARAM_INT, 'Submission ID', VALUE_OPTIONAL),
                'onlinetext'             => new external_value(PARAM_RAW, 'Online text content', VALUE_OPTIONAL),
                'filesubmissions' => new external_multiple_structure(
                    new external_single_structure([
                        'name' => new external_value(PARAM_TEXT, 'File name'),
                        'size' => new external_value(PARAM_TEXT, 'File size'),
                        'url'  => new external_value(PARAM_RAW, 'File URL'),
                    ]),
                    'Submitted files',
                    VALUE_OPTIONAL
                ),
                'gradevalue'       => new external_value(PARAM_FLOAT, 'Grade value', VALUE_OPTIONAL),
                'grademax'         => new external_value(PARAM_FLOAT, 'Grade maximum', VALUE_OPTIONAL),
                'feedbackcomments'  => new external_value(PARAM_RAW, 'Feedback comments HTML', VALUE_OPTIONAL),
                'isgraded'         => new external_value(PARAM_BOOL, 'Is graded', VALUE_OPTIONAL),
                // mod_lesson.
                'lessonid' => new external_value(PARAM_INT, 'Lesson instance ID', VALUE_OPTIONAL),
                'answers' => new external_multiple_structure(
                    new external_single_structure([
                        'id'     => new external_value(PARAM_INT, 'Answer ID'),
                        'text'   => new external_value(PARAM_RAW, 'Answer text'),
                        'jumpto' => new external_value(PARAM_INT, 'Jump to page'),
                    ]),
                    'Lesson page answers',
                    VALUE_OPTIONAL
                ),
                // mod_scorm.
                'scormid' => new external_value(PARAM_INT, 'SCORM instance ID', VALUE_OPTIONAL),
                // mod_workshop.
                'workshopid' => new external_value(PARAM_INT, 'Workshop instance ID', VALUE_OPTIONAL),
                'phase'      => new external_value(PARAM_TEXT, 'Current phase', VALUE_OPTIONAL),
                'phasecode'  => new external_value(PARAM_INT, 'Phase code', VALUE_OPTIONAL),
                'cansubmit'  => new external_value(PARAM_BOOL, 'Can submit', VALUE_OPTIONAL),
                'canassess'  => new external_value(PARAM_BOOL, 'Can peer assess', VALUE_OPTIONAL),
                'submission' => new external_single_structure([
                    'id'          => new external_value(PARAM_INT, 'Submission ID'),
                    'title'       => new external_value(PARAM_TEXT, 'Submission title'),
                    'content'     => new external_value(PARAM_RAW, 'Submission content HTML'),
                    'grade'       => new external_value(PARAM_FLOAT, 'Submission grade', VALUE_OPTIONAL),
                    'timecreated' => new external_value(PARAM_INT, 'Created timestamp'),
                ], 'User submission', VALUE_OPTIONAL),
                'assessments' => new external_multiple_structure(
                    new external_single_structure([
                        'id'              => new external_value(PARAM_INT, 'Assessment ID'),
                        'submissionid'    => new external_value(PARAM_INT, 'Submission ID'),
                        'submissiontitle' => new external_value(PARAM_TEXT, 'Submission title'),
                        'grade'           => new external_value(PARAM_FLOAT, 'Assessment grade', VALUE_OPTIONAL),
                        'feedbackauthor'  => new external_value(PARAM_RAW, 'Feedback text'),
                    ]),
                    'Assigned assessments',
                    VALUE_OPTIONAL
                ),
            ], 'Structured inline content for Vue frontend', VALUE_OPTIONAL),
        ]);
    }
}

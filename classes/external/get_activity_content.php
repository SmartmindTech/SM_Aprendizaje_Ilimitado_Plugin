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
use external_single_structure;
use external_value;

/**
 * External function to get a single activity's rendered content.
 */
class get_activity_content extends external_api {

    /** @var string[] Activity types rendered inline (AJAX HTML). */
    private static $inline_types = ['page', 'book', 'label', 'resource'];

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

        switch ($rendermode) {
            case 'inline':
                $html = self::render_activity_content($cm, $course, $context, $modname, $itemnum);
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

        return [
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
            $modinfo = get_fast_modinfo($course);
            $cminfo = $modinfo->get_cm($cm->id);
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cminfo)) {
                $completion->set_module_viewed($cminfo);
                if ($modname === 'page' || $modname === 'label') {
                    $completion->update_state($cminfo, COMPLETION_COMPLETE);
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

    /**
     * Render the content for a specific activity module (inline types only).
     *
     * @param object $cm Course module record.
     * @param object $course Course record.
     * @param \context_module $context Module context.
     * @param string $modname Module type name.
     * @return string Rendered HTML.
     */
    private static function render_activity_content($cm, $course, $context, string $modname, int $itemnum = 0): string {
        global $DB, $CFG, $OUTPUT;

        $modulecontext = $context;

        switch ($modname) {
            case 'page':
                return self::render_page($cm, $modulecontext);

            case 'book':
                return self::render_book($cm, $modulecontext, $itemnum);

            case 'resource':
                return self::render_resource($cm, $modulecontext);

            case 'label':
                return self::render_label($cm, $modulecontext);

            default:
                return '<div class="smgp-activity-content"><p>Content not available inline.</p></div>';
        }
    }

    /**
     * Render mod_page content (full page body).
     */
    private static function render_page($cm, $context): string {
        global $DB;
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
        $content = file_rewrite_pluginfile_urls(
            $page->content, 'pluginfile.php', $context->id, 'mod_page', 'content', $page->revision
        );
        $content = format_text($content, $page->contentformat, ['context' => $context]);
        return '<div class="smgp-activity-content smgp-activity-content--page">' . $content . '</div>';
    }

    /**
     * Render mod_book — specific chapter (1-based) or first chapter.
     *
     * @param object $cm Course module record.
     * @param \context_module $context Module context.
     * @param int $chapternum Chapter number (1-based, 0 = first chapter).
     * @return string Rendered HTML.
     */
    private static function render_book($cm, $context, int $chapternum = 0): string {
        global $DB;
        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        $chapters = array_values($DB->get_records('book_chapters', ['bookid' => $book->id, 'hidden' => 0], 'pagenum ASC'));

        if (empty($chapters)) {
            return '<div class="smgp-activity-content"><p>No chapters available.</p></div>';
        }

        // Select requested chapter (1-based) or default to first.
        $idx = ($chapternum > 0 && $chapternum <= count($chapters)) ? $chapternum - 1 : 0;
        $chapter = $chapters[$idx];

        $content = file_rewrite_pluginfile_urls(
            $chapter->content, 'pluginfile.php', $context->id, 'mod_book', 'chapter', $chapter->id
        );
        $content = format_text($content, $chapter->contentformat, ['context' => $context]);

        $currentNum = $idx + 1;
        $chapterNav = '<div class="smgp-activity-content__chapter-info">'
            . '<strong>' . format_string($chapter->title) . '</strong>'
            . ' <span class="text-muted">(' . $currentNum . '/' . count($chapters) . ')</span>'
            . '</div>';

        // Trigger chapter_viewed event (books use this instead of course_module_viewed).
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

        return '<div class="smgp-activity-content smgp-activity-content--book">'
            . $chapterNav . $content . '</div>';
    }

    /**
     * Render mod_resource — file preview (images, PDFs, video, audio) + download link.
     */
    private static function render_resource($cm, $context): string {
        global $DB;
        $resource = $DB->get_record('resource', ['id' => $cm->instance], '*', MUST_EXIST);

        $intro = self::get_formatted_intro($resource, $context, 'resource');

        // Get the file.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        $file = reset($files);

        $filehtml = '';
        if ($file) {
            $fileurl = \moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename()
            )->out(false);

            $filesize = display_size($file->get_filesize());
            $mimetype = $file->get_mimetype();
            $filename = $file->get_filename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $isimage = strpos($mimetype, 'image/') === 0;
            $ispdf = ($mimetype === 'application/pdf');
            $isvideo = (strpos($mimetype, 'video/') === 0);
            $isaudio = (strpos($mimetype, 'audio/') === 0);
            $isdocument = in_array($ext, [
                'ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'odp',
            ]);

            if ($isimage) {
                $filehtml = '<div class="smgp-activity-content__preview">'
                    . '<img src="' . $fileurl . '" alt="' . s($filename)
                    . '" style="max-width:100%;height:auto;border-radius:8px;">'
                    . '</div>';
            } else if ($ispdf) {
                // PDF: native browser viewer, fills content area.
                $filehtml = '<div class="smgp-activity-content__document">'
                    . '<iframe src="' . $fileurl . '#toolbar=1&navpanes=0" '
                    . 'class="smgp-activity-content__document-frame" '
                    . 'title="' . s($filename) . '">'
                    . '</iframe></div>';
            } else if ($isdocument) {
                // Office documents: Google Docs Viewer for inline preview.
                $encodedurl = urlencode($fileurl);
                $viewerurl = 'https://docs.google.com/gview?url=' . $encodedurl . '&embedded=true';
                $filehtml = '<div class="smgp-activity-content__document">'
                    . '<iframe src="' . $viewerurl . '" '
                    . 'class="smgp-activity-content__document-frame" '
                    . 'title="' . s($filename) . '">'
                    . '</iframe></div>';
            } else if ($isvideo) {
                $filehtml = '<div class="smgp-activity-content__video-player">'
                    . '<video controls preload="metadata" class="smgp-video-player">'
                    . '<source src="' . $fileurl . '" type="' . s($mimetype) . '">'
                    . get_string('course_page_video_unsupported', 'local_sm_graphics_plugin')
                    . '</video></div>';
            } else if ($isaudio) {
                $filehtml = '<div class="smgp-activity-content__audio-player">'
                    . '<audio controls preload="metadata" class="smgp-audio-player">'
                    . '<source src="' . $fileurl . '" type="' . s($mimetype) . '">'
                    . '</audio></div>';
            }

            // Download button: skip for video, audio, PDF, and embedded documents.
            if (!$isvideo && !$isaudio && !$ispdf && !$isdocument) {
                $filehtml .= '<div class="smgp-activity-content__file mt-2">'
                    . '<a href="' . $fileurl . '" class="btn btn-primary btn-sm">'
                    . '<i class="icon-download"></i> Download '
                    . s($filename) . ' (' . $filesize . ')</a></div>';
            }
        }

        return '<div class="smgp-activity-content smgp-activity-content--resource">'
            . $intro . $filehtml . '</div>';
    }

    /**
     * Render mod_label — inline content.
     */
    private static function render_label($cm, $context): string {
        global $DB;
        $label = $DB->get_record('label', ['id' => $cm->instance], '*', MUST_EXIST);
        $content = format_text($label->intro, $label->introformat, ['context' => $context]);
        return '<div class="smgp-activity-content smgp-activity-content--label">' . $content . '</div>';
    }

    /**
     * Get formatted intro text for a module.
     */
    private static function get_formatted_intro($record, $context, string $component): string {
        if (empty($record->intro)) {
            return '';
        }
        $intro = file_rewrite_pluginfile_urls(
            $record->intro, 'pluginfile.php', $context->id, 'mod_' . $component, 'intro', null
        );
        return '<div class="smgp-activity-content__intro">'
            . format_text($intro, $record->introformat ?? FORMAT_HTML, ['context' => $context])
            . '</div>';
    }

    /**
     * Define return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html'         => new external_value(PARAM_RAW, 'Rendered activity HTML'),
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
        ]);
    }
}

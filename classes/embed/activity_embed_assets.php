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
 * Activity embed mode CSS and JavaScript for the SmartMind Estratoos plugin.
 *
 * When non-SCORM activities (quiz, assignment, lesson, book, etc.) are viewed
 * through the SmartLearning embed endpoint, we need to hide Moodle's Boost theme
 * chrome (navbar, header, footer, side drawers) and make the activity content
 * fill the viewport.
 *
 * Two variants:
 *   - get_css_js(): Hides all Moodle chrome for general activities.
 *   - get_quiz_css_js(): Same but keeps the right drawer for quiz question navigation.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2025 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin\embed;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides CSS and JS for hiding Moodle Boost chrome in embed mode for non-SCORM activities.
 *
 * Called from the before_footer hook in lib.php when embed mode is detected
 * on a non-SCORM /mod/ page.
 */
class activity_embed_assets {

    /**
     * Get the CSS and JS to hide Moodle chrome for general (non-quiz) activities.
     *
     * Hides: navbar, page header, secondary navigation, drawers, footer, toasts.
     * Keeps: #region-main (activity content), prev/next buttons.
     * Applies: SmartLearning theme styling for visual consistency.
     *
     * @return string HTML containing <style> and <script> blocks.
     */
    public static function get_css_js() {
        return self::get_smartlearning_theme_css() . self::get_base_css()
            . self::get_drawer_hide_css() . self::get_base_js(false);
    }

    /**
     * Get the CSS and JS for quiz pages — keeps right drawer for question navigation.
     *
     * Same as get_css_js() but re-shows the right drawer containing the quiz
     * question navigation panel (#mod_quiz_navblock).
     * Applies: SmartLearning theme styling for visual consistency.
     *
     * @return string HTML containing <style> and <script> blocks.
     */
    public static function get_quiz_css_js() {
        return self::get_smartlearning_theme_css() . self::get_base_css()
            . self::get_quiz_drawer_css() . self::get_base_js(true);
    }

    /**
     * SmartLearning theme CSS — applies consistent styling to embedded activities.
     *
     * Defines CSS custom properties matching the SmartLearning design system
     * and overrides Moodle Boost styles for visual consistency.
     *
     * @return string HTML <style> block.
     */
    private static function get_smartlearning_theme_css() {
        return <<<HTML
<style type="text/css">
/* ============================================================
 * SmartLearning Theme Styles
 * Applies SmartLearning design system to embedded Moodle activities.
 * CSS custom properties match inboxfrontend/assets/scss/abstracts/_variables.scss
 * ============================================================ */

/* --- CSS CUSTOM PROPERTIES (Design Tokens) --- */
:root {
    /* Primary Colors — SmartMind Green */
    --sl-primary: #10b981;
    --sl-primary-dark: #059669;
    --sl-primary-light: #34d399;
    --sl-primary-rgb: 16, 185, 129;

    /* Semantic Colors */
    --sl-secondary: #64748b;
    --sl-success: #10b981;
    --sl-danger: #ef4444;
    --sl-warning: #f59e0b;
    --sl-info: #06b6d4;

    /* Background & Text */
    --sl-bg: #ffffff;
    --sl-bg-secondary: #f8fafc;
    --sl-bg-tertiary: #f1f5f9;
    --sl-text: #0f172a;
    --sl-text-secondary: #64748b;
    --sl-text-muted: #94a3b8;
    --sl-border: #e2e8f0;

    /* Gray Scale — Slate */
    --sl-gray-50: #f8fafc;
    --sl-gray-100: #f1f5f9;
    --sl-gray-200: #e2e8f0;
    --sl-gray-300: #cbd5e1;
    --sl-gray-400: #94a3b8;
    --sl-gray-500: #64748b;
    --sl-gray-600: #475569;
    --sl-gray-700: #334155;
    --sl-gray-800: #1e293b;
    --sl-gray-900: #0f172a;

    /* Typography */
    --sl-font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
    --sl-font-size-xs: 0.75rem;
    --sl-font-size-sm: 0.875rem;
    --sl-font-size-base: 1rem;
    --sl-font-size-lg: 1.125rem;
    --sl-font-size-xl: 1.25rem;

    /* Spacing */
    --sl-spacing-xs: 0.25rem;
    --sl-spacing-sm: 0.5rem;
    --sl-spacing-md: 1rem;
    --sl-spacing-lg: 1.5rem;
    --sl-spacing-xl: 2rem;

    /* Border Radius */
    --sl-radius-sm: 0.25rem;
    --sl-radius-md: 0.375rem;
    --sl-radius-lg: 0.5rem;
    --sl-radius-xl: 0.75rem;
    --sl-radius-full: 9999px;

    /* Shadows */
    --sl-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --sl-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --sl-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);

    /* Transitions */
    --sl-transition-fast: 150ms ease-in-out;
    --sl-transition-base: 200ms ease-in-out;
}

/* --- GLOBAL TYPOGRAPHY --- */
body.sm-activity-embed-mode {
    font-family: var(--sl-font-family) !important;
    font-size: var(--sl-font-size-base) !important;
    color: var(--sl-text) !important;
    background-color: var(--sl-bg) !important;
    line-height: 1.5 !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
}

body.sm-activity-embed-mode h1,
body.sm-activity-embed-mode h2,
body.sm-activity-embed-mode h3,
body.sm-activity-embed-mode h4,
body.sm-activity-embed-mode h5,
body.sm-activity-embed-mode h6 {
    font-family: var(--sl-font-family) !important;
    color: var(--sl-text) !important;
    font-weight: 600 !important;
    line-height: 1.25 !important;
}

/* --- BUTTONS --- */
body.sm-activity-embed-mode .btn-primary,
body.sm-activity-embed-mode .btn.btn-primary {
    background-color: var(--sl-primary) !important;
    border-color: var(--sl-primary) !important;
    color: #fff !important;
    font-weight: 500 !important;
    border-radius: var(--sl-radius-md) !important;
    padding: var(--sl-spacing-sm) var(--sl-spacing-lg) !important;
    transition: all var(--sl-transition-fast) !important;
}

body.sm-activity-embed-mode .btn-primary:hover,
body.sm-activity-embed-mode .btn.btn-primary:hover {
    background-color: var(--sl-primary-dark) !important;
    border-color: var(--sl-primary-dark) !important;
    transform: translateY(-1px) !important;
    box-shadow: var(--sl-shadow-md) !important;
}

body.sm-activity-embed-mode .btn-secondary,
body.sm-activity-embed-mode .btn.btn-secondary {
    background-color: var(--sl-bg-secondary) !important;
    border-color: var(--sl-border) !important;
    color: var(--sl-text) !important;
    font-weight: 500 !important;
    border-radius: var(--sl-radius-md) !important;
}

body.sm-activity-embed-mode .btn-secondary:hover,
body.sm-activity-embed-mode .btn.btn-secondary:hover {
    background-color: var(--sl-gray-200) !important;
    border-color: var(--sl-gray-300) !important;
}

/* Outline buttons - make them match the primary style */
body.sm-activity-embed-mode .btn-outline-primary,
body.sm-activity-embed-mode .btn.btn-outline-primary {
    background-color: transparent !important;
    border: 2px solid var(--sl-primary) !important;
    color: var(--sl-primary) !important;
    font-weight: 500 !important;
    border-radius: var(--sl-radius-md) !important;
    padding: var(--sl-spacing-sm) var(--sl-spacing-lg) !important;
}

body.sm-activity-embed-mode .btn-outline-primary:hover,
body.sm-activity-embed-mode .btn.btn-outline-primary:hover {
    background-color: var(--sl-primary) !important;
    color: #fff !important;
}

/* Generic buttons without specific class - style as secondary */
body.sm-activity-embed-mode .btn:not(.btn-primary):not(.btn-secondary):not(.btn-outline-primary):not(.btn-link):not(.btn-icon) {
    background-color: var(--sl-bg) !important;
    border: 2px solid var(--sl-primary) !important;
    color: var(--sl-primary) !important;
    font-weight: 500 !important;
    border-radius: var(--sl-radius-md) !important;
    padding: var(--sl-spacing-sm) var(--sl-spacing-lg) !important;
    transition: all var(--sl-transition-fast) !important;
}

body.sm-activity-embed-mode .btn:not(.btn-primary):not(.btn-secondary):not(.btn-outline-primary):not(.btn-link):not(.btn-icon):hover {
    background-color: var(--sl-primary) !important;
    color: #fff !important;
}

/* ============================================================
 * LESSON BUTTONS - Clean styling with no weird borders/stripes
 * ============================================================ */

/* Reset ALL lesson buttons first - remove any borders */
body.sm-activity-embed-mode .path-mod-lesson button,
body.sm-activity-embed-mode .path-mod-lesson input[type="submit"],
body.sm-activity-embed-mode .path-mod-lesson .btn,
body.sm-activity-embed-mode #page-mod-lesson-view button,
body.sm-activity-embed-mode #page-mod-lesson-view input[type="submit"],
body.sm-activity-embed-mode #page-mod-lesson-view .btn {
    border: 0 !important;
    border-left: 0 !important;
    border-right: 0 !important;
    border-top: 0 !important;
    border-bottom: 0 !important;
    border-style: none !important;
    border-width: 0 !important;
    border-color: transparent !important;
    outline: none !important;
    box-shadow: var(--sl-shadow-sm) !important;
    border-radius: var(--sl-radius-md) !important;
    padding: var(--sl-spacing-sm) var(--sl-spacing-lg) !important;
    min-width: 80px !important;
    text-align: center !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    background-color: var(--sl-primary) !important;
    color: white !important;
}

/* Hover state for all lesson buttons */
body.sm-activity-embed-mode .path-mod-lesson button:hover,
body.sm-activity-embed-mode .path-mod-lesson input[type="submit"]:hover,
body.sm-activity-embed-mode #page-mod-lesson-view button:hover,
body.sm-activity-embed-mode #page-mod-lesson-view input[type="submit"]:hover {
    background-color: var(--sl-primary-dark) !important;
    transform: translateY(-1px) !important;
}

/* Singlebutton form wrapper - remove any borders from the form itself */
body.sm-activity-embed-mode .path-mod-lesson .singlebutton,
body.sm-activity-embed-mode .path-mod-lesson form,
body.sm-activity-embed-mode #page-mod-lesson-view .singlebutton,
body.sm-activity-embed-mode #page-mod-lesson-view form {
    border: 0 !important;
    border-left: 0 !important;
    margin: 0 var(--sl-spacing-sm) 0 0 !important;
    padding: 0 !important;
    display: inline-block !important;
    background: transparent !important;
}

/* --- CARDS --- */
body.sm-activity-embed-mode .card {
    background-color: var(--sl-bg) !important;
    border: 1px solid var(--sl-border) !important;
    border-radius: var(--sl-radius-lg) !important;
    box-shadow: var(--sl-shadow-sm) !important;
}

body.sm-activity-embed-mode .card-header {
    background-color: var(--sl-bg-secondary) !important;
    border-bottom: 1px solid var(--sl-border) !important;
    padding: var(--sl-spacing-md) var(--sl-spacing-lg) !important;
}

body.sm-activity-embed-mode .card-body {
    padding: var(--sl-spacing-lg) !important;
}

/* --- FORMS --- */
body.sm-activity-embed-mode .form-control,
body.sm-activity-embed-mode input[type="text"],
body.sm-activity-embed-mode input[type="number"],
body.sm-activity-embed-mode textarea,
body.sm-activity-embed-mode select {
    border: 1px solid var(--sl-border) !important;
    border-radius: var(--sl-radius-md) !important;
    padding: var(--sl-spacing-sm) var(--sl-spacing-md) !important;
    font-size: var(--sl-font-size-sm) !important;
    transition: border-color var(--sl-transition-fast), box-shadow var(--sl-transition-fast) !important;
}

body.sm-activity-embed-mode .form-control:focus,
body.sm-activity-embed-mode input:focus,
body.sm-activity-embed-mode textarea:focus,
body.sm-activity-embed-mode select:focus {
    border-color: var(--sl-primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--sl-primary-rgb), 0.15) !important;
    outline: none !important;
}

/* ============================================================
 * QUIZ QUESTION STYLES — SmartMind redesign (renderer override)
 * Uses .smgp-que classes from theme_smartmind_core_question_renderer.
 * ============================================================ */

/* --- Response form container — full width inside iframe --- */
#responseform {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 1rem 0 !important;
}

/* --- Question wrapper — reset Moodle/theme defaults on .que --- */
.smgp-que {
    margin-bottom: 1.5rem !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    display: block !important;
    overflow: visible !important;
    border-radius: 0 !important;
    float: none !important;
    clear: none !important;
}

/* Remove default .info/.content layout when custom renderer is active */
.smgp-que .info {
    display: none !important;
}

.smgp-que .content {
    margin: 0 !important;
    margin-left: 0 !important;
    width: 100% !important;
}

/* --- Header bar: back button + number + status + marks --- */
.smgp-que__header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.6rem 1.25rem;
    background: #F9F7F4;
    border: 1px solid #E2DDD6;
    border-radius: 10px;
    margin-bottom: 0.75rem;
    border-left: 3px solid #2e7d32;
}

/* Back button before question number */
.smgp-que__back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    padding: 0;
    border: 1px solid #E2DDD6;
    border-radius: 8px;
    background: #fff;
    color: #6B6660;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
}

.smgp-que__back:hover {
    background: #F0EDE8;
    color: #1A1814;
}

/* Number badge */
.smgp-que__number {
    background: #2e7d32;
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.smgp-que__number--info {
    background: #6B6660;
}

.smgp-que__status,
.smgp-que__marks {
    font-size: 0.8rem;
    color: #6B6660;
}

.smgp-que__status .que_comment,
.smgp-que__status div {
    display: inline;
}

.smgp-que__marks {
    margin-left: auto;
}

.smgp-que__flag {
    flex-shrink: 0;
}

/* --- Content area --- */
.smgp-que__content {
    padding: 0;
}

/* --- Formulation: question text card + answer cards --- */
.smgp-que__formulation {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
}

/* Reset Moodle's alert-style formulation (blue bg/color from question.scss) */
.smgp-que__formulation .formulation,
.smgp-que .formulation {
    background: transparent !important;
    background-image: none !important;
    border: none !important;
    padding: 0 !important;
    color: #1A1814 !important;
}

/* Question text — distinct card */
.smgp-que .qtext {
    color: #1A1814;
    font-size: 1.05rem;
    line-height: 1.7;
    margin-bottom: 0.75rem;
    padding: 1.25rem 1.5rem;
    background: #F9F7F4;
    border: 1px solid #E2DDD6;
    border-radius: 12px;
    border-left: 3px solid #2e7d32;
}

/* --- Answer options --- */
.smgp-que .answer {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin: 0;
    padding: 0;
}

.smgp-que .answer > div {
    margin: 0;
    padding: 0;
}

/* Answer row card — targets Moodle's .r0/.r1 divs (radio + answer-label div) */
.smgp-que .answer > div.r0,
.smgp-que .answer > div.r1 {
    display: flex;
    align-items: center;
    padding: 0.7rem 1rem;
    border: 1px solid #E2DDD6;
    border-radius: 10px;
    cursor: pointer;
    background: #FFFFFF;
    transition: all 0.15s ease;
    font-size: 0.95rem;
    color: #1A1814;
    line-height: 1.5;
}

.smgp-que .answer > div.r0:hover,
.smgp-que .answer > div.r1:hover {
    background: #F9F7F4;
    border-color: #2e7d32;
}

/* Selected answer — row containing a checked input */
.smgp-que .answer > div.r0:has(input:checked),
.smgp-que .answer > div.r1:has(input:checked) {
    background: rgba(46, 125, 50, 0.06);
    border-color: #2e7d32;
    font-weight: 500;
}

/* Fallback: also support label wrapping (some question types) */
.smgp-que .answer label {
    display: flex;
    align-items: center;
    padding: 0.7rem 1rem;
    margin: 0;
    border: 1px solid #E2DDD6;
    border-radius: 10px;
    cursor: pointer;
    background: #FFFFFF;
    transition: all 0.15s ease;
    font-size: 0.95rem;
    color: #1A1814;
    line-height: 1.5;
}

/* Only style label as card when NOT inside r0/r1 (avoid double border) */
.smgp-que .answer > div.r0 label,
.smgp-que .answer > div.r1 label {
    border: none;
    padding: 0;
    background: transparent;
    border-radius: 0;
}

.smgp-que .answer label:hover {
    background: #F9F7F4;
    border-color: #2e7d32;
}

.smgp-que .answer label:has(input:checked) {
    background: rgba(46, 125, 50, 0.06);
    border-color: #2e7d32;
    font-weight: 500;
}

/* Radio / checkbox inputs */
.smgp-que .answer input[type="radio"],
.smgp-que .answer input[type="checkbox"] {
    margin-right: 0.75rem;
    margin-top: 0;
    accent-color: #2e7d32;
    flex-shrink: 0;
    width: 18px;
    height: 18px;
}

/* Answer label text layout */
.smgp-que .answer div[data-region="answer-label"] {
    display: flex;
    align-items: baseline;
}

.smgp-que .answer .answernumber {
    font-weight: 600;
    color: #6B6660;
    margin-right: 0.25rem;
    flex-shrink: 0;
}

.smgp-que .answer .qtype_multichoice_clearchoice {
    margin-top: 0.25rem;
    font-size: 0.8rem;
}

/* --- Feedback states (header bar border color) --- */
.smgp-que.correct .smgp-que__header { border-left-color: #2e7d32; }
.smgp-que.correct .smgp-que__number { background: #2e7d32; }

.smgp-que.incorrect .smgp-que__header { border-left-color: #d32f2f; }
.smgp-que.incorrect .smgp-que__number { background: #d32f2f; }

.smgp-que.partiallycorrect .smgp-que__header { border-left-color: #f59e0b; }
.smgp-que.partiallycorrect .smgp-que__number { background: #f59e0b; }

/* Answer feedback colors — targets both .r0/.r1 rows and label wrapping */
.smgp-que .answer > div.r0.correct,
.smgp-que .answer > div.r1.correct,
.smgp-que .answer label.correct {
    border-color: #2e7d32 !important;
    background: rgba(46, 125, 50, 0.06) !important;
}

.smgp-que .answer > div.r0.incorrect,
.smgp-que .answer > div.r1.incorrect,
.smgp-que .answer label.incorrect {
    border-color: #d32f2f !important;
    background: rgba(211, 47, 47, 0.04) !important;
}

/* --- Outcome / feedback --- */
.smgp-que__outcome {
    margin-top: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    font-size: 0.9rem;
    line-height: 1.5;
    border: 1px solid #E2DDD6;
    background: #F9F7F4;
}

.smgp-que.correct .smgp-que__outcome {
    background: rgba(46, 125, 50, 0.05);
    border-color: rgba(46, 125, 50, 0.2);
    color: #1b5e20;
}

.smgp-que.incorrect .smgp-que__outcome {
    background: rgba(211, 47, 47, 0.04);
    border-color: rgba(211, 47, 47, 0.15);
    color: #b71c1c;
}

.smgp-que.partiallycorrect .smgp-que__outcome {
    background: rgba(245, 158, 11, 0.05);
    border-color: rgba(245, 158, 11, 0.2);
    color: #92400e;
}

/* --- Comment --- */
.smgp-que__comment {
    margin-top: 0.5rem;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    font-size: 0.9rem;
    border: 1px solid #E2DDD6;
    background: #FFFFFF;
}

/* --- Submit / navigation buttons --- */
.submitbtns {
    margin-top: 1rem !important;
    display: flex !important;
    gap: 0.5rem !important;
}

.submitbtns .btn-primary {
    background: #2e7d32 !important;
    border-color: #2e7d32 !important;
    color: #fff !important;
    border-radius: 8px !important;
    padding: 0.5rem 1.5rem !important;
    font-weight: 600 !important;
    font-size: 0.9rem !important;
}

.submitbtns .btn-primary:hover {
    background: #1b5e20 !important;
    border-color: #1b5e20 !important;
}

/* Hide next/prev page buttons — navigation handled by parent player */
.mod_quiz-next-nav,
.mod_quiz-prev-nav {
    display: none !important;
}

/* Hide Back to course button and secondary nav in embed mode */
body.sm-activity-embed-mode .secondary-navigation {
    display: none !important;
}

body.sm-activity-embed-mode .singlebutton .btn-secondary,
body.sm-activity-embed-mode .box.generalbox .singlebutton:last-child .btn-secondary {
    display: none !important;
}

/* Quiz navigation block */
body.sm-activity-embed-mode #mod_quiz_navblock {
    background-color: var(--sl-bg) !important;
}

body.sm-activity-embed-mode #mod_quiz_navblock .qnbutton {
    border-radius: var(--sl-radius-md) !important;
    font-weight: 500 !important;
}

/* Hide quiz timer and toggle-timer in embed mode */
body.sm-activity-embed-mode #quiz-timer-wrapper,
body.sm-activity-embed-mode #quiz-timer,
body.sm-activity-embed-mode .quiz-timer-inner,
body.sm-activity-embed-mode #toggle-timer {
    display: none !important;
}

/* Make quiz questions use full width */
body.sm-activity-embed-mode .que {
    max-width: none !important;
    width: 100% !important;
}

body.sm-activity-embed-mode .que .content {
    max-width: none !important;
    width: auto !important;
    overflow: visible !important;
}

body.sm-activity-embed-mode .que .formulation {
    max-width: none !important;
    width: 100% !important;
}

/* Quiz attempt page - full width content, no side padding */
body.sm-activity-embed-mode #page-mod-quiz-attempt #region-main,
body.sm-activity-embed-mode #page-mod-quiz-review #region-main {
    max-width: none !important;
    width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

/* Remove Bootstrap container constraints */
body.sm-activity-embed-mode .path-mod-quiz #region-main-box,
body.sm-activity-embed-mode .path-mod-quiz .container-fluid {
    max-width: none !important;
    width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

/* Allow question content to expand - remove any explicit widths */
body.sm-activity-embed-mode .path-mod-quiz .que .content,
body.sm-activity-embed-mode .path-mod-quiz .que .formulation,
body.sm-activity-embed-mode .path-mod-quiz .que .ablock,
body.sm-activity-embed-mode .path-mod-quiz .que .answer {
    max-width: none !important;
}

/* Essay/text area should be full width */
body.sm-activity-embed-mode .que .qtype_essay_response,
body.sm-activity-embed-mode .que textarea,
body.sm-activity-embed-mode .que .editor_atto_wrap {
    width: 100% !important;
    max-width: none !important;
}

/* --- BOOK SPECIFIC STYLES --- */
body.sm-activity-embed-mode .book_content {
    padding: var(--sl-spacing-xl) !important;
}

body.sm-activity-embed-mode .book_content h1,
body.sm-activity-embed-mode .book_content h2,
body.sm-activity-embed-mode .book_content h3 {
    margin-top: var(--sl-spacing-xl) !important;
    margin-bottom: var(--sl-spacing-md) !important;
}

body.sm-activity-embed-mode .book_content p {
    margin-bottom: var(--sl-spacing-md) !important;
    line-height: 1.7 !important;
}

/* Book navigation */
body.sm-activity-embed-mode .book_navigation {
    display: flex !important;
    justify-content: space-between !important;
    padding: var(--sl-spacing-lg) 0 !important;
    border-top: 1px solid var(--sl-border) !important;
    margin-top: var(--sl-spacing-xl) !important;
}

/* --- LESSON SPECIFIC STYLES --- */
body.sm-activity-embed-mode .lesson-content {
    padding: var(--sl-spacing-xl) !important;
}

/* Hide database read errors - show clean UI instead of raw errors */
body.sm-activity-embed-mode .alert-danger,
body.sm-activity-embed-mode .notifyproblem,
body.sm-activity-embed-mode .errorbox,
body.sm-activity-embed-mode .error,
body.sm-activity-embed-mode .errormessage,
body.sm-activity-embed-mode div[style*="background-color: #FFCCBB"],
body.sm-activity-embed-mode div[class*="error"]:not(.que):not(.formfielderror):not(.error-feedback),
body.sm-activity-embed-mode .dbreaderror,
body.sm-activity-embed-mode span.error {
    display: none !important;
}

/* Lesson progress container - modern card style */
body.sm-activity-embed-mode .lessonprogress,
body.sm-activity-embed-mode .progress_bar_stage,
body.sm-activity-embed-mode div[class*="progress"]:not(.progress-bar):not(.que .progress),
body.sm-activity-embed-mode .lesson_timer_progress {
    background: linear-gradient(135deg, var(--sl-bg-secondary) 0%, var(--sl-bg) 100%) !important;
    border: 1px solid var(--sl-border) !important;
    border-radius: var(--sl-radius-lg) !important;
    padding: var(--sl-spacing-lg) !important;
    margin: var(--sl-spacing-lg) 0 !important;
    box-shadow: var(--sl-shadow-sm) !important;
}

/* Lesson progress text */
body.sm-activity-embed-mode .lessonprogress span,
body.sm-activity-embed-mode .progress_bar_stage span,
body.sm-activity-embed-mode .lesson_timer_progress span {
    font-size: var(--sl-font-size-sm) !important;
    color: var(--sl-text-secondary) !important;
    font-weight: 500 !important;
    display: block !important;
    margin-bottom: var(--sl-spacing-sm) !important;
}

/* Lesson progress bar wrapper */
body.sm-activity-embed-mode .progress_bar,
body.sm-activity-embed-mode .lesson-progress-bar,
body.sm-activity-embed-mode .lessonprogress .progress {
    height: 10px !important;
    background-color: var(--sl-gray-200) !important;
    border-radius: var(--sl-radius-full) !important;
    overflow: hidden !important;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08) !important;
    margin-top: var(--sl-spacing-sm) !important;
}

/* Lesson progress bar fill - gradient animation */
body.sm-activity-embed-mode .progress_bar_innertube,
body.sm-activity-embed-mode .lesson-progress-fill,
body.sm-activity-embed-mode .lessonprogress .progress-bar,
body.sm-activity-embed-mode .progress_bar .bar,
body.sm-activity-embed-mode table.progress_bar tr td[style*="background"] {
    background: linear-gradient(90deg, var(--sl-primary) 0%, var(--sl-primary-light) 100%) !important;
    border-radius: var(--sl-radius-full) !important;
    height: 100% !important;
    transition: width 0.4s ease-out !important;
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.25) !important;
}

/* Progress bar table cleanup (Moodle uses tables for progress) */
body.sm-activity-embed-mode table.progress_bar {
    display: block !important;
    width: 100% !important;
    height: 10px !important;
    border: none !important;
    background-color: var(--sl-gray-200) !important;
    border-radius: var(--sl-radius-full) !important;
    overflow: hidden !important;
}

body.sm-activity-embed-mode table.progress_bar tbody,
body.sm-activity-embed-mode table.progress_bar tr {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
}

body.sm-activity-embed-mode table.progress_bar td {
    display: inline-block !important;
    height: 100% !important;
    padding: 0 !important;
    border: none !important;
    vertical-align: top !important;
}

body.sm-activity-embed-mode table.progress_bar td:first-child {
    border-radius: var(--sl-radius-full) 0 0 var(--sl-radius-full) !important;
}

/* Hide table borders in progress bar */
body.sm-activity-embed-mode table.progress_bar,
body.sm-activity-embed-mode table.progress_bar td,
body.sm-activity-embed-mode table.progress_bar tr {
    border-collapse: collapse !important;
    border-spacing: 0 !important;
}

/* Lesson question/answer area */
body.sm-activity-embed-mode .contents {
    background-color: var(--sl-bg) !important;
    border: 1px solid var(--sl-border) !important;
    border-radius: var(--sl-radius-lg) !important;
    padding: var(--sl-spacing-xl) !important;
    margin: var(--sl-spacing-lg) 0 !important;
}

/* Hide lesson end/completion links in embed mode */
/* These links navigate away from the activity which is not allowed in embed mode */
body.sm-activity-embed-mode .path-mod-lesson a[href*="view.php?id="][href*="pageid=0"],
body.sm-activity-embed-mode .path-mod-lesson a[href*="course/view.php"],
body.sm-activity-embed-mode .path-mod-lesson a[href*="grade/report"],
body.sm-activity-embed-mode #page-mod-lesson-view a[href*="view.php?id="][href*="pageid=0"],
body.sm-activity-embed-mode #page-mod-lesson-view a[href*="course/view.php"],
body.sm-activity-embed-mode #page-mod-lesson-view a[href*="grade/report"],
body.sm-activity-embed-mode .mod_lesson a[href*="pageid=0"],
body.sm-activity-embed-mode .mod_lesson a[href*="course/view.php"],
body.sm-activity-embed-mode .mod_lesson a[href*="grade/"] {
    display: none !important;
}

/* Also hide by link text content using aria or title */
body.sm-activity-embed-mode .path-mod-lesson .lessonbutton a,
body.sm-activity-embed-mode #page-mod-lesson-view .lessonbutton a {
    display: none !important;
}

/* --- PAGE/RESOURCE SPECIFIC STYLES --- */
body.sm-activity-embed-mode .page-content-container,
body.sm-activity-embed-mode #region-main > .box {
    padding: var(--sl-spacing-xl) !important;
}

/* --- ALERTS --- */
body.sm-activity-embed-mode .alert {
    border-radius: var(--sl-radius-md) !important;
    border: none !important;
    padding: var(--sl-spacing-md) var(--sl-spacing-lg) !important;
}

body.sm-activity-embed-mode .alert-info {
    background-color: rgba(23, 162, 184, 0.1) !important;
    color: var(--sl-info) !important;
}

body.sm-activity-embed-mode .alert-success {
    background-color: rgba(40, 167, 69, 0.1) !important;
    color: var(--sl-success) !important;
}

body.sm-activity-embed-mode .alert-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
    color: #856404 !important;
}

body.sm-activity-embed-mode .alert-danger {
    background-color: rgba(220, 53, 69, 0.1) !important;
    color: var(--sl-danger) !important;
}

/* --- LINKS --- */
body.sm-activity-embed-mode a {
    color: var(--sl-primary) !important;
    text-decoration: none !important;
    transition: color var(--sl-transition-fast) !important;
}

body.sm-activity-embed-mode a:hover {
    color: var(--sl-primary-dark) !important;
    text-decoration: underline !important;
}

/* --- TABLES --- */
body.sm-activity-embed-mode table {
    border-collapse: collapse !important;
    width: 100% !important;
}

body.sm-activity-embed-mode th,
body.sm-activity-embed-mode td {
    padding: var(--sl-spacing-sm) var(--sl-spacing-md) !important;
    border: 1px solid var(--sl-border) !important;
}

body.sm-activity-embed-mode th {
    background-color: var(--sl-bg-secondary) !important;
    font-weight: 600 !important;
}

body.sm-activity-embed-mode tr:nth-child(even) {
    background-color: var(--sl-gray-50) !important;
}

/* --- PROGRESS BAR --- */
body.sm-activity-embed-mode .progress {
    height: 6px !important;
    background-color: var(--sl-gray-200) !important;
    border-radius: var(--sl-radius-full) !important;
    overflow: hidden !important;
}

body.sm-activity-embed-mode .progress-bar {
    background-color: var(--sl-primary) !important;
    border-radius: var(--sl-radius-full) !important;
}

/* --- SCROLLBAR --- */
body.sm-activity-embed-mode ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

body.sm-activity-embed-mode ::-webkit-scrollbar-track {
    background: var(--sl-gray-100);
    border-radius: var(--sl-radius-full);
}

body.sm-activity-embed-mode ::-webkit-scrollbar-thumb {
    background: var(--sl-gray-400);
    border-radius: var(--sl-radius-full);
}

body.sm-activity-embed-mode ::-webkit-scrollbar-thumb:hover {
    background: var(--sl-gray-500);
}
</style>
HTML;
    }

    /**
     * Base CSS shared by all activity types — hides Moodle Boost chrome.
     *
     * @return string HTML <style> block.
     */
    private static function get_base_css() {
        return <<<HTML
<style type="text/css">
/* ============================================================
 * Activity Embed Mode Styles
 * Hide Moodle Boost theme chrome when viewed through SmartLearning.
 * Applied to all /mod/ pages (except SCORM which has its own embed_assets).
 * ============================================================ */

/* --- HIDE NAVIGATION ELEMENTS --- */

/* Top navigation bar */
nav.navbar,
nav.navbar.fixed-top {
    display: none !important;
}

/* Page header (breadcrumbs, context header, course header) */
#page-header {
    display: none !important;
}

/* Secondary navigation tabs */
.secondary-navigation {
    display: none !important;
}

/* Drawer toggle buttons */
.drawer-toggles {
    display: none !important;
}

/* Left drawer (course index) */
#theme_boost-drawers-courseindex,
.drawer.drawer-left {
    display: none !important;
}

/* Footer */
#page-footer,
footer#page-footer {
    display: none !important;
}

/* Toast notifications */
.toast-wrapper {
    display: none !important;
}

/* Back to top button */
.back-to-top {
    display: none !important;
}

/* Mobile primary drawer */
#theme_boost-drawers-primary {
    display: none !important;
}

/* Mobile menu toggle button (hamburger icon) */
button[data-toggler="drawers"],
.btn-icon.drawer-toggle,
button.btn.drawertoggle,
.drawertoggle,
button[aria-controls="theme_boost-drawers-primary"],
[data-action="toggle-drawer"] {
    display: none !important;
}

/* Any button that looks like a drawer toggle */
.btn.icon-no-margin[data-toggler] {
    display: none !important;
}

/* Region main settings menu (gear icon) */
#region-main-settings-menu {
    display: none !important;
}

/* --- HIDE ALL BLOCKS AND BLOCK REGIONS (all Moodle layouts) --- */

/* Navigation block (legacy sidebar shown in standard/incourse layouts) */
.block_navigation,
.block_settings,
.block_site_main_menu,
.block_course_list,
.block_myoverview,
.block_recentlyaccessedcourses,
.block_calendar_month,
.block_calendar_upcoming,
.block_online_users,
.block_activity_modules,
.block_admin_bookmarks,
.block_badges,
.block_blog_menu,
.block_comments,
.block_completionstatus,
.block_course_summary,
.block_globalsearch,
.block_html,
.block_login,
.block_mentees,
.block_mnet_hosts,
.block_news_items,
.block_private_files,
.block_recent_activity,
.block_rss_client,
.block_search_forums,
.block_section_links,
.block_selfcompletion,
.block_social_activities,
.block_tag_flickr,
.block_tag_youtube,
.block_tags {
    display: none !important;
}

/* Hide ALL blocks generically — covers any block not listed above */
aside[data-block],
.block[data-block],
.block.card {
    display: none !important;
}

/* Block regions — left/right sidebars in all Moodle layouts */
#block-region-side-pre,
#block-region-side-post,
.block-region,
[data-region="blocks-column"],
section[data-region="blocks-column"],
aside#block-region-side-pre,
aside#block-region-side-post {
    display: none !important;
}

/* Column containers for block regions (Classic theme and older layouts) */
#region-pre,
#region-post,
.columnleft,
.columnright,
.column-side-pre,
.column-side-post,
#side-pre-only,
#side-post-only {
    display: none !important;
}

/* Block tree navigation (nav tree inside navigation block) */
nav.block_tree,
.block_tree,
.block_tree_box {
    display: none !important;
}

/* Breadcrumbs / navigation bar (different selectors across themes) */
.breadcrumb-nav,
.breadcrumb-item,
nav[aria-label="Navigation bar"],
ol.breadcrumb,
.breadcrumb {
    display: none !important;
}

/* Activity navigation bar (prev/next activity + "Jump to" dropdown at bottom) */
/* Navigation between activities is handled by SmartLearning frontend */
.activity-navigation,
#activity-navigation,
.activity-navigation-container,
.jump-to-activity,
.activity_navigation {
    display: none !important;
}

/* IOMAD specific navigation elements */
.iomad_company_selector,
#iomad_company_selector {
    display: none !important;
}

/* --- MAXIMIZE CONTENT (full width when blocks/sidebars hidden) --- */

/* Force content to use full width — no space reserved for hidden sidebars */
#page.drawers .main-inner,
#page .main-inner,
.pagelayout-standard #page-content,
.pagelayout-incourse #page-content,
.pagelayout-course #page-content,
.pagelayout-admin #page-content,
.pagelayout-report #page-content,
.pagelayout-mydashboard #page-content,
.pagelayout-frontpage #page-content {
    display: block !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}

/* Remove columns layout — force single column */
#page-content .row,
.columns-1 #region-main,
.columns-2 #region-main,
.columns-3 #region-main {
    width: 100% !important;
    max-width: 100% !important;
    flex: 0 0 100% !important;
}

/* --- CONTENT FILLS IFRAME --- */
/* Let content fill the full iframe width with no side padding. */
body.sm-activity-embed-mode #region-main {
    padding: 0 !important;
    margin: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}

/* Remove top offset caused by hidden fixed navbar */
body {
    margin-top: 0 !important;
    padding-top: 0 !important;
    /* Allow body to scroll for page/lesson content */
    overflow-y: auto !important;
    overflow-x: hidden !important;
}

/* Remove drawer margin/padding displacement (both sides) */
#page.drawers,
#page.drawers.show-drawer-left,
#page.drawers.show-drawer-right {
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    transition: none !important;
}

/* topofscroll normally has padding-top for fixed navbar */
#topofscroll {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

/* page-content fills available space */
#page-content {
    padding-bottom: 0 !important;
}

/* Page wrapper - don't force min-height that prevents scrolling */
#page {
    min-height: auto !important;
}

/* region-main - let content flow, don't create scroll context here */
#region-main {
    overflow: visible !important;
}

/* --- BOOK SPECIFIC --- */
/* Prevent horizontal scrollbar in book content */
.book_content,
#book-content,
.book_toc,
.generalbox.book_content {
    overflow-x: hidden !important;
    overflow-y: auto !important;
    max-width: 100% !important;
}

/* Book chapter navigation arrows */
.book_content pre,
.book_content code {
    white-space: pre-wrap !important;
    word-wrap: break-word !important;
}

/* --- PAGE AND LESSON SCROLL FIX --- */
/* Enable proper scrolling for page and lesson content in iframe */
html,
body.sm-activity-embed-mode {
    height: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Allow body to scroll but hide the scrollbar visually.
   Content is embedded in SmartLearning iframe — scrollbar is unnecessary visual noise.
   Users can still scroll with mousewheel/trackpad when content overflows. */
body.sm-activity-embed-mode {
    overflow-y: auto !important;
    overflow-x: hidden !important;
    scrollbar-width: none !important;         /* Firefox */
    -ms-overflow-style: none !important;      /* IE/Edge */
}

body.sm-activity-embed-mode::-webkit-scrollbar {
    display: none !important;                 /* Chrome/Safari/Opera */
}

/* Main page wrapper */
body.sm-activity-embed-mode #page {
    min-height: auto !important;
    height: auto !important;
}

body.sm-activity-embed-mode #page-wrapper {
    display: flex !important;
    flex-direction: column !important;
    min-height: 100vh !important;
}

/* Vertical centering via auto margins on the flex child.
   Short content: auto margins split remaining space equally → centered.
   Long content: no remaining space, margins collapse to 0 → starts at top, scrolls naturally. */
body.sm-activity-embed-mode #page-wrapper > #page {
    margin-top: auto !important;
    margin-bottom: auto !important;
}

/* Page content area */
body.sm-activity-embed-mode #page-content {
    padding-bottom: 0 !important;
}

/* Region main - let content flow naturally */
body.sm-activity-embed-mode #region-main {
    overflow: visible !important;
    height: auto !important;
}

body.sm-activity-embed-mode #region-main-box {
    overflow: visible !important;
    padding: 0 !important;
    margin: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}

/* Page module content - remove any height restrictions */
.page-content-container,
#page-mod-page-content,
.mod_page .generalbox,
body.sm-activity-embed-mode .box.generalbox,
body.sm-activity-embed-mode .generalbox {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
}

/* Lesson content */
body.sm-activity-embed-mode .lesson-content,
body.sm-activity-embed-mode #lesson-content,
body.sm-activity-embed-mode .mod_lesson .generalbox {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
}

/* Page content inside region-main */
#region-main-box .generalbox.box {
    overflow: visible !important;
}

/* --- GENERAL CONTENT OVERFLOW --- */
/* Prevent horizontal overflow in content areas */
.activity-content,
.activityinstance,
#intro,
.box.generalbox {
    max-width: 100% !important;
    overflow-x: hidden !important;
}

/* Images should not overflow */
img {
    max-width: 100% !important;
    height: auto !important;
}

/* Tables should scroll horizontally if needed */
.table-responsive,
table {
    max-width: 100% !important;
    overflow-x: auto !important;
}

/* --- FOLDER SPECIFIC STYLING --- */
/* Clean up folder file tree appearance */
.foldertree,
.filemanager,
.fp-filename-field {
    display: table !important;
    width: 100% !important;
}

/* Folder file listing */
.folder-content,
.box.generalbox.foldertree {
    padding: var(--sl-spacing-md) !important;
}

/* Folder tree table - clean modern look */
.foldertree table,
.fp-folder table {
    display: table !important;
    width: 100% !important;
    border-collapse: collapse !important;
    border: none !important;
}

.foldertree table td,
.foldertree table th,
.fp-folder table td {
    border: none !important;
    padding: 8px 12px !important;
    vertical-align: middle !important;
}

/* File rows */
.foldertree tr,
.fp-folder tr {
    border-bottom: 1px solid var(--sl-gray-200) !important;
    transition: background-color var(--sl-transition-fast) !important;
}

.foldertree tr:hover,
.fp-folder tr:hover {
    background-color: var(--sl-gray-50) !important;
}

.foldertree tr:last-child,
.fp-folder tr:last-child {
    border-bottom: none !important;
}

/* File icons */
.foldertree .fp-icon,
.foldertree .icon,
.fp-folder .fp-icon {
    width: 24px !important;
    height: 24px !important;
    margin-right: 8px !important;
}

/* File names as links */
.foldertree a,
.fp-folder a {
    color: var(--sl-primary) !important;
    text-decoration: none !important;
    font-weight: 500 !important;
}

.foldertree a:hover,
.fp-folder a:hover {
    text-decoration: underline !important;
}

/* Remove tree connector lines */
.foldertree .tree_item::before,
.foldertree .tree_item::after,
.fp-folder .fp-filename::before {
    display: none !important;
}

/* Download folder button */
.folder-download-button,
.singlebutton {
    margin-top: var(--sl-spacing-md) !important;
    display: flex !important;
    justify-content: flex-end !important;
}

/* Quiz/assign action buttons — right-aligned */
.quizstartbuttondiv {
    display: flex !important;
    justify-content: flex-end !important;
    margin-top: var(--sl-spacing-md) !important;
}

/* Assignment/Quiz: move button containers below tables, right-aligned */
body.path-mod-assign [role="main"],
body.path-mod-quiz [role="main"] {
    display: flex !important;
    flex-direction: column !important;
    margin-top: var(--sl-spacing-lg, 1.5rem) !important;
}

body.path-mod-assign [role="main"] > .container-fluid:has(.singlebutton),
body.path-mod-quiz [role="main"] > .tertiary-navigation,
body.path-mod-quiz .quizstartbuttondiv {
    order: 99 !important;
    display: flex !important;
    justify-content: flex-end !important;
}

.quizstartbuttondiv .btn,
.singlebutton .btn {
    width: auto !important;
}

.folder-download-button input[type="submit"],
.folder-download-button button {
    background-color: var(--sl-primary) !important;
    color: white !important;
    border: none !important;
    padding: 10px 20px !important;
    border-radius: var(--sl-radius) !important;
    cursor: pointer !important;
    font-weight: 500 !important;
}

.folder-download-button input[type="submit"]:hover,
.folder-download-button button:hover {
    background-color: var(--sl-primary-dark) !important;
}
</style>
HTML;
    }

    /**
     * CSS to hide BOTH drawers — used for general (non-quiz) activities.
     *
     * @return string HTML <style> block.
     */
    private static function get_drawer_hide_css() {
        return <<<HTML
<style type="text/css">
/* --- HIDE RIGHT DRAWER (general activities) --- */
#theme_boost-drawers-blocks,
.drawer.drawer-right {
    display: none !important;
}

#page.drawers.show-drawer-right {
    margin-right: 0 !important;
}
</style>
HTML;
    }

    /**
     * CSS for quiz pages — hides right drawer in embed mode for more content space.
     *
     * In embed mode, we hide the question navigation drawer to maximize content width.
     * Users can navigate using the SmartLearning position bar instead.
     *
     * @return string HTML <style> block.
     */
    private static function get_quiz_drawer_css() {
        return <<<HTML
<style type="text/css">
/* --- QUIZ EMBED MODE: Hide right drawer for more space --- */

/* Hide right drawer - use SmartLearning position bar for navigation */
#theme_boost-drawers-blocks,
.drawer.drawer-right {
    display: none !important;
}

/* Remove the margin that was reserved for the drawer */
#page.drawers.show-drawer-right,
#page.drawers {
    margin-right: 0 !important;
}

/* Hide quiz timer in embed mode */
#quiz-timer-wrapper,
#quiz-timer,
#toggle-timer {
    display: none !important;
}

/* Prevent horizontal scroll */
html, body {
    overflow-x: hidden !important;
    max-width: 100vw !important;
}

#region-main {
    max-width: 100% !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

/* Question content - FULL WIDTH, no constraints */
.que {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

/* Default layout only when custom renderer is NOT active */
.que:not(.smgp-que) .info {
    float: left !important;
    width: 120px !important;
}

.que:not(.smgp-que) .content {
    margin-left: 130px !important;
    width: auto !important;
    max-width: none !important;
    overflow-x: auto !important;
}

.que .formulation,
.que .ablock,
.que .answer {
    width: 100% !important;
    max-width: none !important;
    box-sizing: border-box !important;
}

/* Essay textarea full width */
.que textarea,
.que .editor_atto_wrap,
.que .qtype_essay_response {
    width: 100% !important;
    max-width: none !important;
}

/* Images and tables inside questions — constrain to container */
.que img {
    max-width: 100% !important;
    height: auto !important;
}

.que table {
    max-width: 100% !important;
    overflow-x: auto !important;
    display: block !important;
}
</style>
HTML;
    }

    /**
     * JavaScript backup — programmatically hides elements for dynamic content.
     *
     * @param bool $isQuiz If true, keeps the right drawer open for quiz navigation.
     * @return string HTML <script> block.
     */
    private static function get_base_js(bool $isQuiz) {
        $quizJs = '';
        if ($isQuiz) {
            $quizJs = <<<'JS'

    // Quiz embed mode: hide right drawer for more content space
    var rightDrawer = document.getElementById('theme_boost-drawers-blocks');
    if (rightDrawer) {
        rightDrawer.style.display = 'none';
    }
    var pageEl = document.getElementById('page');
    if (pageEl) {
        pageEl.classList.remove('show-drawer-right');
        pageEl.style.marginRight = '0';
    }

JS;
        } else {
            $quizJs = <<<'JS'

    // Non-quiz: hide right drawer
    var rightDrawer = document.getElementById('theme_boost-drawers-blocks');
    if (rightDrawer) {
        rightDrawer.style.display = 'none';
    }
    var pageEl = document.getElementById('page');
    if (pageEl) {
        pageEl.classList.remove('show-drawer-right');
    }
JS;
        }

        return <<<HTML
<script>
// ============================================================
// Activity Embed Mode JavaScript (backup)
// Programmatically hides Moodle Boost chrome in case CSS isn't
// sufficient (e.g., elements loaded dynamically after render).
// ============================================================
(function() {
    // Mark body for CSS targeting.
    document.body.classList.add('sm-activity-embed-mode');

    // Remove top padding caused by hidden fixed navbar.
    document.body.style.paddingTop = '0';
    document.body.style.marginTop = '0';

    // Hide navbar.
    var navbar = document.querySelector('nav.navbar.fixed-top') || document.querySelector('nav.navbar');
    if (navbar) navbar.style.display = 'none';

    // Hide page header.
    var pageHeader = document.getElementById('page-header');
    if (pageHeader) pageHeader.style.display = 'none';

    // Hide secondary navigation.
    var secNav = document.querySelector('.secondary-navigation');
    if (secNav) secNav.style.display = 'none';

    // Hide drawer toggles.
    var toggles = document.querySelector('.drawer-toggles');
    if (toggles) toggles.style.display = 'none';

    // Hide left drawer and remove its margin effect.
    var leftDrawer = document.getElementById('theme_boost-drawers-courseindex');
    if (leftDrawer) {
        leftDrawer.classList.remove('show');
        leftDrawer.style.display = 'none';
    }
    var page = document.getElementById('page');
    if (page) {
        page.classList.remove('show-drawer-left');
    }

    // Hide footer.
    var footer = document.getElementById('page-footer');
    if (footer) footer.style.display = 'none';

    // Remove top scroll offset.
    var topScroll = document.getElementById('topofscroll');
    if (topScroll) topScroll.style.paddingTop = '0';

    // Hide ALL blocks and block regions (covers all Moodle layouts/themes).
    var blockSelectors = [
        '.block_navigation', '.block_settings', '.block.card',
        'aside[data-block]', '.block[data-block]',
        '#block-region-side-pre', '#block-region-side-post',
        '.block-region', '[data-region="blocks-column"]',
        '#region-pre', '#region-post',
        '.columnleft', '.columnright',
        '.column-side-pre', '.column-side-post',
        'nav.block_tree', '.block_tree', '.block_tree_box',
        '.breadcrumb-nav', 'ol.breadcrumb', '.breadcrumb',
        '.activity-navigation'
    ];
    blockSelectors.forEach(function(sel) {
        document.querySelectorAll(sel).forEach(function(el) {
            el.style.display = 'none';
        });
    });
{$quizJs}


    // ============================================================
    // FALLBACK PROTECTION
    // Detect when page navigates away from allowed pages.
    // Show blank page instead of Moodle dashboard or other pages.
    // Allows: /mod/, /local/sm_estratoos_plugin/, /grade/, /user/, files
    // ============================================================
    (function() {
        var currentPath = window.location.pathname;

        // Check if current path is allowed
        function isPathAllowed(path) {
            return path.indexOf('/mod/') !== -1 ||
                   path.indexOf('/local/sm_estratoos_plugin/') !== -1 ||
                   path.indexOf('/grade/') !== -1 ||
                   path.indexOf('/user/') !== -1 ||
                   path.indexOf('/pluginfile.php') !== -1 ||
                   path.indexOf('/draftfile.php') !== -1;
        }

        // If we are not on an allowed page, blank it
        if (!isPathAllowed(currentPath)) {
            document.documentElement.innerHTML = '<html><head><style>body{background:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:-apple-system,BlinkMacSystemFont,sans-serif;color:#64748b;}</style></head><body><div style="text-align:center;"><p>Activity session ended.</p><p style="font-size:0.875rem;">Please return to the course to continue.</p></div></body></html>';
            return;
        }

        // Intercept all link clicks to prevent navigation away
        document.addEventListener('click', function(e) {
            var target = e.target.closest('a');
            if (!target) return;

            var href = target.getAttribute('href');
            if (!href) return;

            // Allow same-page anchors
            if (href.startsWith('#')) return;

            // Allow javascript: links
            if (href.startsWith('javascript:')) return;

            // Check if link goes to an allowed path
            try {
                var url = new URL(href, window.location.origin);
                if (!isPathAllowed(url.pathname)) {
                    e.preventDefault();
                    e.stopPropagation();
                    document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,sans-serif;color:#64748b;text-align:center;"><div><p>Activity session ended.</p><p style="font-size:0.875rem;">Please return to the course to continue.</p></div></div>';
                }
            } catch (err) {
                e.preventDefault();
            }
        }, true);

        // Also intercept form submissions
        document.addEventListener('submit', function(e) {
            var form = e.target;
            var action = form.getAttribute('action') || '';

            try {
                var url = new URL(action, window.location.origin);
                if (!isPathAllowed(url.pathname) && action !== '') {
                    e.preventDefault();
                    document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,sans-serif;color:#64748b;text-align:center;"><div><p>Activity session ended.</p><p style="font-size:0.875rem;">Please return to the course to continue.</p></div></div>';
                }
            } catch (err) {
                // Allow form submission if we can't parse the URL
            }
        }, true);
    })();

    // Report content height to parent so the iframe can auto-resize.
    (function() {
        var lastHeight = 0;
        function reportHeight() {
            var h = document.documentElement.scrollHeight || document.body.scrollHeight;
            if (h && h !== lastHeight) {
                lastHeight = h;
                window.parent.postMessage({ type: 'smgp-iframe-resize', height: h }, '*');
            }
        }
        // Report on load, resize, and DOM mutations.
        reportHeight();
        window.addEventListener('load', reportHeight);
        window.addEventListener('resize', reportHeight);
        var mo = new MutationObserver(reportHeight);
        mo.observe(document.body, { childList: true, subtree: true, attributes: true });
        // Periodic fallback for dynamic content.
        setInterval(reportHeight, 500);
    })();
})();
</script>
HTML;
    }
}

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
 * Quiz renderer override for SmartMind theme.
 *
 * - Removes .quizinfo box and "attempts allowed" messages.
 * - Removes "back to course" button (user navigates via the course player).
 * - Styles grade headings with the app's brand color.
 *
 * @package    theme_smartmind
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_smartmind\output\mod_quiz;

use mod_quiz\output\renderer as quiz_renderer;
use mod_quiz\output\view_page;

defined('MOODLE_INTERNAL') || die();

class renderer extends quiz_renderer {

    /**
     * Override quiz view page layout.
     *
     * No quizinfo, no "back to course" button.
     */
    public function view_page($course, $quiz, $cm, $context, $viewobj) {
        $output = '';

        // 1. Attempts list.
        $attemptshtml = $this->render($viewobj->attemptslist);

        // 2. Grade / result info (below attempts).
        $resulthtml = $this->view_result_info($quiz, $context, $cm, $viewobj);

        // 3. Attempts heading.
        $headinghtml = '';
        if ($attemptshtml) {
            $headinghtml = \html_writer::tag('h3',
                get_string('summaryofattempts', 'quiz'),
                ['class' => 'smgp-quiz-heading']
            );
        }

        // 4. Buttons — without "back to course".
        $buttonshtml = $this->smgp_view_page_buttons($viewobj);

        // 5. Tertiary nav (start button).
        $tertiaryhtml = $this->view_page_tertiary_nav($viewobj);

        $output .= $tertiaryhtml;
        $output .= $headinghtml;
        $output .= $attemptshtml;
        $output .= $resulthtml;
        $output .= $buttonshtml;

        return $output;
    }

    /**
     * Buttons section — only access messages, no "back to course".
     */
    protected function smgp_view_page_buttons(view_page $viewobj): string {
        $output = '';

        if (!$viewobj->quizhasquestions) {
            $output .= \html_writer::div(
                $this->notification(get_string('noquestions', 'quiz'), 'warning', false),
                'text-start mb-3'
            );
        }
        $output .= $this->access_messages($viewobj->preventmessages);

        // "Back to course" intentionally omitted — user navigates via the course player.

        if ($output) {
            return $this->box($output, 'quizattempt');
        }
        return '';
    }

    /**
     * Override result info to use brand-colored headings.
     */
    public function view_result_info($quiz, $context, $cm, $viewobj) {
        $output = '';
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return $output;
        }
        $resultinfo = '';

        if ($viewobj->overallstats) {
            if ($viewobj->moreattempts) {
                $a = new \stdClass();
                $a->method = \quiz_get_grading_option_name($quiz->grademethod);
                $a->mygrade = \quiz_format_grade($quiz, $viewobj->mygrade);
                $a->quizgrade = \quiz_format_grade($quiz, $quiz->grade);
                $resultinfo .= \html_writer::tag('h3',
                    get_string('gradesofar', 'quiz', $a),
                    ['class' => 'smgp-quiz-grade-heading']
                );
            } else {
                $a = new \stdClass();
                $a->grade = \quiz_format_grade($quiz, $viewobj->mygrade);
                $a->maxgrade = \quiz_format_grade($quiz, $quiz->grade);
                $a = get_string('outofshort', 'quiz', $a);
                $resultinfo .= \html_writer::tag('h3',
                    get_string('yourfinalgradeis', 'quiz', $a),
                    ['class' => 'smgp-quiz-grade-heading']
                );
            }
        }

        if ($viewobj->mygradeoverridden) {
            $resultinfo .= \html_writer::tag('p', get_string('overriddennotice', 'grades'),
                ['class' => 'overriddennotice']) . "\n";
        }
        if ($viewobj->gradebookfeedback) {
            $resultinfo .= \html_writer::tag('h3', get_string('comment', 'quiz'),
                ['class' => 'smgp-quiz-grade-heading']);
            $resultinfo .= \html_writer::div($viewobj->gradebookfeedback, 'quizteacherfeedback') . "\n";
        }
        if ($viewobj->feedbackcolumn) {
            $resultinfo .= \html_writer::tag('h3', get_string('overallfeedback', 'quiz'),
                ['class' => 'smgp-quiz-grade-heading']);
            $resultinfo .= \html_writer::div(
                \quiz_feedback_for_grade($viewobj->mygrade, $quiz, $context),
                'quizgradefeedback') . "\n";
        }

        if ($resultinfo) {
            $output .= $this->box($resultinfo, 'generalbox', 'feedback');
        }
        return $output;
    }
}

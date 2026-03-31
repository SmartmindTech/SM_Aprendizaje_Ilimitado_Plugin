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
 * Question engine renderer override for SmartMind theme.
 *
 * Redesigns the question layout: info as top header bar,
 * question text in a distinct card, answer options below.
 *
 * @package    theme_smartmind
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_smartmind\output\core;

defined('MOODLE_INTERNAL') || die();

use question_attempt;
use qbehaviour_renderer;
use qtype_renderer;
use question_display_options;
use html_writer;

class question_renderer extends \core_question_renderer {

    /**
     * Override question layout — vertical: header bar → question card → answers → feedback.
     */
    public function question(\question_attempt $qa, \qbehaviour_renderer $behaviouroutput,
            \qtype_renderer $qtoutput, \question_display_options $options, $number) {

        $options = clone($options);
        if (!$options->has_question_identifier()) {
            $options->questionidentifier = $this->question_number_text($number);
        }

        $stateclass = $qa->get_state_class($options->correctness && $qa->has_marks());

        $classes = [
            'que',
            'smgp-que',
            $qa->get_question(false)->get_type_name(),
            $qa->get_behaviour_name(),
            $stateclass,
        ];

        $output = '';
        $output .= \html_writer::start_tag('div', [
            'id' => $qa->get_outer_question_div_unique_id(),
            'class' => implode(' ', $classes),
        ]);

        // --- Header bar: number + status + marks ---
        $output .= $this->smgp_info_bar($qa, $behaviouroutput, $qtoutput, $options, $number);

        // --- Content ---
        $output .= \html_writer::start_tag('div', ['class' => 'smgp-que__content']);

        // Formulation (question text + answer controls).
        $output .= \html_writer::tag('div',
            $this->formulation($qa, $behaviouroutput, $qtoutput, $options),
            ['class' => 'smgp-que__formulation']);

        // Outcome / feedback.
        $outcomehtml = $this->outcome($qa, $behaviouroutput, $qtoutput, $options);
        if ($outcomehtml) {
            $output .= \html_writer::tag('div', $outcomehtml, ['class' => 'smgp-que__outcome']);
        }

        // Manual comment.
        $commenthtml = $this->manual_comment($qa, $behaviouroutput, $qtoutput, $options);
        if ($commenthtml) {
            $output .= \html_writer::tag('div', $commenthtml, ['class' => 'smgp-que__comment']);
        }

        $output .= \html_writer::end_tag('div'); // .smgp-que__content
        $output .= \html_writer::end_tag('div'); // .smgp-que

        return $output;
    }

    /**
     * Render the info bar as a horizontal header with back button.
     */
    protected function smgp_info_bar(\question_attempt $qa, \qbehaviour_renderer $behaviouroutput,
            \qtype_renderer $qtoutput, \question_display_options $options, $number) {

        $parts = [];

        // Back button — navigates to previous page via JS.
        $parts[] = \html_writer::tag('button',
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>',
            [
                'class' => 'smgp-que__back',
                'type' => 'button',
                'title' => get_string('previous'),
                'onclick' => 'window.history.back()',
            ]
        );

        // Question number badge.
        $num = trim($number ?? '');
        if ($num !== '' && $num !== 'i') {
            $parts[] = \html_writer::tag('span', s($num), ['class' => 'smgp-que__number']);
        } else if ($num === 'i') {
            $parts[] = \html_writer::tag('span', 'i', ['class' => 'smgp-que__number smgp-que__number--info']);
        }

        // Status text.
        $statehtml = $this->status($qa, $behaviouroutput, $options);
        if ($statehtml) {
            $parts[] = \html_writer::tag('span', $statehtml, ['class' => 'smgp-que__status']);
        }

        // Marks.
        $markhtml = $this->mark_summary($qa, $behaviouroutput, $options);
        if ($markhtml) {
            $parts[] = \html_writer::tag('span', $markhtml, ['class' => 'smgp-que__marks']);
        }

        // Flag.
        $flaghtml = $this->question_flag($qa, $options->flags);
        if ($flaghtml) {
            $parts[] = \html_writer::tag('span', $flaghtml, ['class' => 'smgp-que__flag']);
        }

        return \html_writer::tag('div', implode('', $parts), ['class' => 'smgp-que__header']);
    }

    /**
     * Override number — return empty since the header bar handles the display.
     */
    protected function number($number) {
        return '';
    }
}

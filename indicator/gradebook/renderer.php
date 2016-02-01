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
 * Output rendering of engagement report
 *
 * @package    mod_engagement
 * @copyright  2015 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class engagementindicator_gradebook_renderer extends engagementindicator_renderer {
    public function user_report($data) {
        $html = '';
        foreach ($data->info as $i) {
            $html .= html_writer::start_tag('strong');
            $html .= html_writer::tag('span', $i->title);
            $html .= html_writer::end_tag('strong');
            $html .= html_writer::empty_tag('br');
            $html .= $this->output->help_icon('weighting', 'engagementindicator_gradebook');
            $html .= html_writer::tag('span', get_string('weighting', 'engagementindicator_gradebook').
                ': ' . $i->weighting);
            $html .= html_writer::empty_tag('br');
            $html .= $this->output->help_icon('localrisk', 'engagementindicator_gradebook');
            $html .= html_writer::tag('span', get_string('localrisk', 'engagementindicator_gradebook').
                ': ' . $i->localrisk);
            $html .= html_writer::empty_tag('br');
            $html .= $this->output->help_icon('riskcontribution', 'engagementindicator_gradebook');
            $html .= html_writer::tag('span', get_string('riskcontribution', 'engagementindicator_gradebook').
                ': ' .  $i->riskcontribution);
            $html .= html_writer::empty_tag('br');
            $html .= $this->output->help_icon('logic', 'engagementindicator_gradebook');
            $html .= html_writer::tag('span', get_string('logic', 'engagementindicator_gradebook').
                ': ' .  $i->logic);
            $html .= html_writer::empty_tag('br');
            $html .= html_writer::empty_tag('br');
        }
        $value = sprintf("%.0f%%", 100 * $data->risk);
        $html .= html_writer::tag('span', get_string('riskscore', 'engagement').": $value");
        return $html;
    }
}

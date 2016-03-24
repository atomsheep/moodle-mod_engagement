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
 * Strings
 *
 * @package    engagementindicator_gradebook
 * @copyright  2015 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Standard plugin strings.
$string['pluginname'] = 'Gradebook';
$string['pluginname_help'] = 'This indicator calculates risk rating based data from the gradebook.';
$string['mailer_column_header_help'] = 'Tick the checkbox(es) in this column to send messages to student(s) based on their gradebook data. The data from gradebook items selected in the settings screen are outlined in a column to the right.';

// Settings.
$string['atriskif'] = 'At risk if the following condition(s) are met:';
$string['group_gradeitem'] = 'Gradebook item';
$string['group_gradeitem_help'] = 'Tick the checkbox to count this gradebook item in the calculations for this indicator. Then, specify the comparison operator, the condition to compare against, and the weighting (number between 0-100) to add to the risk rating if the comparison holds true. For example, if this is set to "less than" and "5", if the record for a student for this grade item is 4, then this grade item will trigger and contribute the specified weighting to the risk rating for this indicator.';
$string['gradeitem_enabled'] = 'Include in calculating risk';

// Errors.
$string['weightingsumonehundred'] = "Weightings for Gradebook indicator must sum to 100";

// Renderer.
$string['localrisk'] = 'Local Risk';
$string['localrisk_help'] = 'The risk percentage of this gradebook item alone, out
of 100. The local risk is multiplied by the weighting for this item to form the
Risk Contribution.';
$string['logic'] = 'Logic';
$string['logic_help'] = 'This field provides some insight into the logic used
to arrive at the Local Risk value.';
$string['riskcontribution'] = 'Risk Contribution';
$string['riskcontribution_help'] = 'The amount of risk this particular gradebook item
contributes to the overall risk returned for the Gradebook indicator.
This is formed by multiplying the Local Risk with the Weighting.
The Risk Contributions of each gradebook item are summed together to form the overall
risk for the indicator.';
$string['weighting'] = 'Weighting';
$string['weighting_help'] = 'This figure shows the amount this item contributes towards 
the overall risk for the Gradebook indicator. The local risk will be multiplied by this to 
form the risk contribution.';
$string['gradeitemvalue'] = 'Grade item value ';
$string['soatrisk'] = ' so at risk';
$string['sonotatrisk'] = ' so not at risk';
$string['gt'] = " greater than ";
$string['notgt'] = " not greater than ";
$string['gte'] = " greater than or equal to ";
$string['notgte'] = " not greater than or equal to ";
$string['lt'] = " less than ";
$string['notlt'] = " not less than ";
$string['lte'] = " less than or equal to ";
$string['notlte'] = " not less than or equal to ";
$string['eq'] = " equal to ";
$string['neq'] = " not equal to ";
$string['neq'] = " not not equal to ";
$string['gradeitem_out_of'] = 'out of {$a}';

// Mailer report.
$string['report_gradebook_risk'] = 'Gradebook risk';
$string['report_gradebook_triggered'] = 'Triggered';
$string['report_gradebook_nottriggered'] = 'Not triggered';
$string['mailer_checkbox_column_header'] = 'Grades'; // Keep this short
$string['mailer_checkbox_column_header_help'] = 'Tick the checkbox(es) in this column to send messages to student(s) based on their gradebook data. The data from gradebook items selected in the settings screen are outlined in columns to the right.';

// Default snippet strings.
$string['defaultsnippet0'] = "Given your grades so far, I am concerned that you have not been keeping up with this unit.";
$string['defaultsnippet1'] = "It is very important that you seek additional assistance to help improve your grade.";
$string['defaultsnippet2'] = "There is some challenging work ahead in this unit, so don’t hesitate to ask questions or seek our help.";
$string['defaultsnippet3'] = "Keeping up with the regular quizzes not only contributes to your final grade, but is a way to test yourself and get feedback on some of the fundamental aspects of the unit.";
$string['defaultsnippet4'] = "In order to improve your understanding of this unit (and your performance on the upcoming assessments), please review the material available on the unit website.";
$string['defaultsnippet5'] = "There is still time to catch up and improve your performance in later assessments.";
$string['defaultsnippet6'] = "With the released results of the assessment I thought I’d get in touch and see how you are going. I know that you may be disappointed with your result but these early tasks are designed to give you feedback on how to improve.";


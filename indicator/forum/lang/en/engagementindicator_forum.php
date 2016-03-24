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
 * @package    engagementindicator_forum
 * @copyright  2012 NetSpot Pty Ltd, 2015-2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['e_newposts'] = 'New posts per week';
$string['e_readposts'] = 'Read posts per week';
$string['e_replies'] = 'Replies per week';
$string['e_totalposts'] = 'Total posts per week';

$string['e_newposts_help'] = 'Numbers of new posts per week if a student is at no risk (first box) and maximum risk (second box) of disengagement. The weighting (number between 0-100) specifies how important this parameter is. The total of the weightings should add to 100.';
$string['e_readposts_help'] = 'Numbers of posts read per week if a student is at no risk (first box) and maximum risk (second box) of disengagement. The weighting (number between 0-100) specifies how important this parameter is. The total of the weightings should add to 100.';
$string['e_replies_help'] = 'Numbers of replies per week if a student is at no risk (first box) and maximum risk (second box) of disengagement. The weighting (number between 0-100) specifies how important this parameter is. The total of the weightings should add to 100.';
$string['e_totalposts_help'] = 'Numbers of posts (new posts and replies) per week if a student is at no risk (first box) and maximum risk (second box) of disengagement. The weighting (number between 0-100) specifies how important this parameter is. The total of the weightings should add to 100.';

$string['forum_read_count_method'] = 'Read count method';
$string['forum_read_count_method_help'] = 'Set how this indicator will count reads/views of forum discussions. If counting only unique, then repeat visits to the same discussion are not re-counted.';
$string['forum_read_count_method_all'] = 'Count any and all reads/views of discussions.';
$string['forum_read_count_method_unique'] = 'Count only unique reads/views of discussions.';

$string['maxrisk'] = 'Max Risk';
$string['norisk'] = 'No Risk';
$string['pluginname'] = 'Forum Activity';
$string['pluginname_help'] = 'This indicator calculates risk rating based on student activity in forums.';
$string['mailer_column_header_help'] = 'Tick the checkbox(es) in this column to send messages to student(s) based on their forum activity. Their forum activity is outlined in a column to the right.';

// Other.
$string['localrisk'] = 'Local Risk';
$string['localrisk_help'] = 'The risk percentage of this alone, out
of 100.  The local risk is multiplied by the login weighting to form the
Risk Contribution.';
$string['logic'] = 'Logic';
$string['logic_help'] = 'This field provides some insight into the logic used to
arrive at the Local Risk value.';
$string['maxrisktitle'] = 'No forums read or contributed to';
$string['riskcontribution'] = 'Risk Contribution';
$string['riskcontribution_help'] = 'The amount of risk this particular
forum consideration contributes to the overall risk returned for the Forum
indicator.  This is formed by multiplying the Local Risk with the
Weighting.  The Risk Contributions of each forum item are summed together to
form the overall risk for the indicator.';
$string['weighting'] = 'Weighting';
$string['weighting_help'] = 'This figure shows the amount this item
contributes towards the overall risk for the Forum indicator.
The local risk will be multiplied by this to form the risk
contribution.';

// Mailer report.
$string['report_forum_risk'] = 'Forum risk';
$string['report_discussionsread'] = 'Discussions read';
$string['report_discussionsreadunique'] = 'Unique discussions read';
$string['report_posted'] = 'Posted';
$string['mailer_checkbox_column_header'] = 'Forum'; // Keep this short
$string['mailer_checkbox_column_header_help'] = 'Tick the checkbox(es) in this column to send messages to student(s) based on their forum activity. Their forum metrics are outlined in columns to the right.';

// Default snippet strings.
$string['defaultsnippet0'] = "The online discussion forum is an important component of this course and your participation is necessary.";
$string['defaultsnippet1'] = "The online discussion forums are a great place to ask questions and get help for this unit.";
$string['defaultsnippet2'] = "Remember that participating in the online discussion forum is an assessable component of this unit.";
$string['defaultsnippet3'] = "Regularly catching up with the posts on the online discussion forum can help you keep an eye on what other students are asking and talking about - you may even see something that you hadn’t thought to ask.";
$string['defaultsnippet4'] = "Consider posting in response to existing threads in the online discussion forum - this can really help you think through the material.";
$string['defaultsnippet5'] = "The online discussion forum is a really active online space where other students are asking questions about this unit. You are likely to see something there that will help you.";

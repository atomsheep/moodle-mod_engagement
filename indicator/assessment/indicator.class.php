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
 * This file defines a class with assessment indicator logic
 *
 * @package    engagementindicator_assessment
 * @author     Ashley Holman <ashley.holman@netspot.com.au>
 * @copyright  2012 NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../indicator.class.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

class indicator_assessment extends indicator {
	
	private $sumgrades = 0;
	
    /**
     * get_risk_for_users_users
     *
     * @param mixed $userid     if userid is null, return risks for all users
     * @param mixed $courseid
     * @param mixed $startdate
     * @param mixed $enddate
     * @access protected
     * @return array            array of risk values, keyed on userid
     */
    protected function get_rawdata($ignored_startdate, $ignored_enddate) {
        global $DB;

        $this->calculator = new assessment_risk_calculator;

        $rawdata = new stdClass();

        $activities = array(); //id, itemtype, itemmodule, iteminstance, grademax
        $grade_items = $DB->get_records_sql("
            SELECT      *
            FROM        {grade_items}
            WHERE       courseid=?
        ", array($this->courseid));
        foreach ($grade_items as $gi) {
            if (in_array($gi->itemtype, array('mod', 'manual'))) {
                // $rawdata->sumgrades += $gi->grademax; // calculate this in the add_* methods
                if ($gi->itemtype == 'mod') {
                    $activities[$gi->itemmodule][] = $gi;
                }
            }
        }

        foreach ($activities as $mod => $items) {
            switch ($mod) {
                case 'assign':
                    $this->add_assignments($items);
                    break;
                case 'assignment':
                    $this->add_assignments_old($items);
                    break;
                case 'quiz':
                    $this->add_quizzes($items);
                    break;
				case 'turnitintool':
                    $this->add_turnitin($items);
                    break;
            }
        }

        $rawdata->assessments = $this->calculator->as_object();
		$rawdata->sumgrades = $this->sumgrades;
        return $rawdata;
    }

    public function calculate_risks(array $userids) {
        // If we've already got a calculator, it means get_rawdata() was called...
        // ...so don't bother reloading the raw data.
        if (!isset($this->calculator)) {
            $this->calculator = new assessment_risk_calculator($this->rawdata->assessments);
        }
        return $this->calculator->get_risks($userids, $this->rawdata->sumgrades, $this->config);
    }

    private function add_turnitin($grade_items) {
        global $DB;
	
		$submissions = array();
		foreach ($grade_items as $gi) {
			$t_assignment_ids[$gi->iteminstance] = $gi;
			$submissions[$gi->iteminstance] = array();
		}
		
		list($insql, $params) = $DB->get_in_or_equal(array_keys($t_assignment_ids));
		
		$t_assignments = $DB->get_records_sql("SELECT b.turnitintoolid, b.dtdue, a.name 
												FROM {turnitintool_parts} b JOIN {turnitintool} a ON (a.id = b.turnitintoolid) 
												WHERE b.turnitintoolid $insql", $params);
		
		// Collect up the turnitin submissions.
		$t_subs = $DB->get_records_sql("SELECT e.id, e.userid, e.turnitintoolid, e.submission_modified, b.dtdue 
										FROM {turnitintool_submissions} e JOIN {turnitintool_parts} b ON (e.turnitintoolid = b.turnitintoolid)
										JOIN {turnitintool} a ON (a.id = e.turnitintoolid) 
										WHERE e.turnitintoolid $insql 
											AND e.submission_status = 'Submission successfully uploaded to Turnitin.'", $params);
		
		foreach ($t_subs as $s) {
			$submissions[$s->turnitintoolid][$s->userid]['submitted'] = $s->submission_modified;
			$submissions[$s->turnitintoolid][$s->userid]['due'] = $s->dtdue;
		}
		// Finally add the assessment details into the calculator.
        foreach ($t_assignments as $a) {
            $grademax = $t_assignment_ids[$a->turnitintoolid]->grademax;
            $this->calculator->add_assessment($grademax, $submissions[$a->turnitintoolid], get_string('modulename', 'turnitintool').": {$a->name}", $a->dtdue);
			// only add grademax for this into sumgrades
			$this->sumgrades += $grademax;
        }
    }
	
    private function add_assignments($grade_items) {
        global $DB;

        $submissions = array();
        foreach ($grade_items as $gi) {
            $assignment_ids[$gi->iteminstance] = $gi;
            $submissions[$gi->iteminstance] = array();
        }
        list($insql, $params) = $DB->get_in_or_equal(array_keys($assignment_ids));
        $sql = "SELECT        id, duedate, name
                FROM          {assign}
                WHERE         id $insql
                    AND       nosubmissions = 0";
        $assignments = $DB->get_records_sql($sql, $params);
        // Collect up the submissions.
        $subs = $DB->get_records_sql("
          SELECT        sub.id, sub.assignment, sub.userid, sub.timemodified, a.duedate
          FROM          {assign_submission} sub
          JOIN          {assign} a ON sub.assignment = a.id
          WHERE         assignment $insql
            AND         sub.status = 'submitted'
        ", $params);
        foreach ($subs as $s) {
            $submissions[$s->assignment][$s->userid]['submitted'] = $s->timemodified;
            $submissions[$s->assignment][$s->userid]['due'] = $s->duedate;
        }
        // Finally add the assessment details into the calculator.
        foreach ($assignments as $a) {
            $grademax = $assignment_ids[$a->id]->grademax;
            $this->calculator->add_assessment($grademax, $submissions[$a->id], get_string('modulename', 'assign').": {$a->name}", $a->duedate);
			// only add grademax for this assignment into sumgrades if submissions are allowed
			$this->sumgrades += $grademax;
        }
    }

    private function add_assignments_old($grade_items) {
        global $DB;

        $submissions = array();
        foreach ($grade_items as $gi) {
            $assignment_ids[$gi->iteminstance] = $gi;
            $submissions[$gi->iteminstance] = array();
        }
        list($insql, $params) = $DB->get_in_or_equal(array_keys($assignment_ids));
        $sql = "SELECT        id, timedue, name
                FROM          {assignment}
                WHERE         id $insql
                    AND       assignmenttype != 'offline'";
        $assignments = $DB->get_records_sql($sql, $params);
        // Collect up the submissions.
        $subs = $DB->get_records_sql("
          SELECT        sub.id, sub.assignment, sub.userid, sub.timemodified, a.timedue
          FROM          {assignment_submissions} sub, {assignment} a
          WHERE         a.id = sub.assignment
            AND         assignment $insql
            AND         (    (assignmenttype = 'upload' AND data2 = 'submitted')
                          OR (assignmenttype IN ('uploadsingle', 'online')))
        ", $params);
        foreach ($subs as $s) {
            $submissions[$s->assignment][$s->userid]['submitted'] = $s->timemodified;
            $submissions[$s->assignment][$s->userid]['due'] = $s->timedue;
        }
        // Finally add the assessment details into the calculator.
        foreach ($assignments as $a) {
            $grademax = $assignment_ids[$a->id]->grademax;
            $this->calculator->add_assessment($grademax, $submissions[$a->id], get_string('modulename', 'assignment').": {$a->name}", $a->timedue);
			// only add grademax for this assignment into sumgrades if submissions are allowed
			$this->sumgrades += $grademax;
        }
    }

    private function add_quizzes($grade_items) {
        global $DB;

        $submissions = array();
        foreach ($grade_items as $gi) {
            $quiz_ids[$gi->iteminstance] = $gi;
            $submissions[$gi->iteminstance] = array();
        }
        list($insql, $params) = $DB->get_in_or_equal(array_keys($quiz_ids));
        $quizzes = $DB->get_records_sql("
            SELECT      id, timeclose, name
            FROM        {quiz}
            WHERE       id $insql
        ", $params);
        // Collect up the attempts.
        $attempts = $DB->get_records_sql("
            SELECT        qa.id, q.id as quiz, q.course, qa.userid, qa.timefinish, q.timeclose
            FROM          {quiz_attempts} qa
            JOIN          {quiz} q ON (q.id = qa.quiz)
            WHERE         q.id $insql
                AND         qa.timefinish > 0
                AND         qa.preview = 0
        ", $params);
        // Get list of user overrides.
        $overrides = $DB->get_records_sql("
            SELECT        userid, groupid, timeclose, quiz
            FROM          {quiz_overrides}
            WHERE         quiz $insql
                AND         timeclose is not null
        ", $params);
        $group_overrides = array();
        foreach ($overrides as $o) {
            if (isset($o->userid)) {
                $submissions[$o->quiz][$o->userid]['due'] = $o->timeclose;
                $submissions[$o->quiz][$o->userid]['override'] = 'user';
            } else if (isset($o->groupid)) {
                $group_overrides[$o->groupid][$o->quiz] = $o->timeclose;
            }
        }
        // Get list of students in overriden groups.
        $groups = array();
        if (!empty($group_overrides)) {
            list ($insql, $params) = $DB->get_in_or_equal(array_keys($group_overrides));
            $group_members = $DB->get_records_sql("
                SELECT        id, groupid, userid
                FROM          {groups_members}
                WHERE         groupid $insql
            ", $params);
        } else {
            $group_members = array();
        }
        foreach ($group_members as $gm) {
            $groups[$gm->groupid][] = $gm->userid;
        }

        // Update submissions based on group overrides.
        foreach ($group_overrides as $gid => $override_quizzes) {
            if (!isset($groups[$gid])) {
                continue;
            }
            foreach ($override_quizzes as $qid => $timeclose) {
                foreach ($groups[$gid] as $uid) {
                    if (!isset($submissions[$qid][$uid])) {
                        // Only set the group override if there wasn't a user-level override.
                        $submissions[$qid][$uid]['due'] = $timeclose;
                        $submissions[$qid][$uid]['override'] = 'group';
                    }
                }
            }
        }
        foreach ($attempts as $a) {
            $submissions[$a->quiz][$a->userid]['submitted'] = $a->timefinish;
            if (!isset($submissions[$a->quiz][$a->userid]['due'])) {
                // Only set timeclose if there wasn't an override in place.
                $submissions[$a->quiz][$a->userid]['due'] = $a->timeclose;
            }
        }
        foreach ($quizzes as $q) {
            $grademax = $quiz_ids[$q->id]->grademax;
			// add grademax to sumgrades
			$this->sumgrades += $grademax;
            // Process user overrides for this quiz.

            $this->calculator->add_assessment($grademax, $submissions[$q->id], get_string('modulename', 'quiz').': ' . $q->name, $q->timeclose);
        }
    }

    protected function load_config() {
        parent::load_config();
        $defaults = $this->get_defaults();
        foreach ($defaults as $setting => $value) {
            if (!isset($this->config[$setting])) {
                $this->config[$setting] = $value;
            } else if (strpos($setting, 'weighting') !== false) {
                $this->config[$setting] = $this->config[$setting] / 100;
            }
        }
    }

    public static function get_defaults() {
        $settings = array();
        $settings['overduegracedays'] = 0;
        $settings['overduemaximumdays'] = 14;

        $settings['overduesubmittedweighting'] = 0.5;
        $settings['overduenotsubmittedweighting'] = 1.0;

        return $settings;
    }
	
	public function get_data_for_mailer() {
		
		$risks = $this->get_course_risks();
		$data = array();
		
		foreach ($this->userarray as $userid) {
			$data[$userid] = array();
		}
		
		// Collect and process data
		foreach ($this->rawdata->assessments as $assessment) {
			foreach ($this->userarray as $userid) {
				$submittime = isset($assessment->submissions[$userid]['submitted']) ? $assessment->submissions[$userid]['submitted'] : PHP_INT_MAX;
				$timedue = isset($assessment->submissions[$userid]['due']) ? $assessment->submissions[$userid]['due'] : 1;
				$interval = $submittime - $timedue;
				if (isset($assessment->submissions[$userid]['submitted'])) {
					$data[$userid]['numbersubmissions'] += 1;
					if ($interval > 0) {
						$data[$userid]['numberoverduesubmitted'] += 1;
						$data[$userid]['totallateinterval'] += $interval;
					}
				} else if ($assessment->due > time()) {
					// Not due yet
				} else {
					$data[$userid]['numberoverduenotsubmitted'] += 1;
					$data[$userid]['overdueassessments'][] = $assessment->description;
				}
			}
		}
		
		// Parse for display
		$return_columns = array();
		// Column for risk
		$return_column = array();
		$return_column['header'] = get_string('report_assessment_risk', 'engagementindicator_assessment');
		$return_column['heatmapdirection'] = 1; // 1 means normal sort i.e. higher numbers are darker
		$return_column['display'] = array();
		foreach ($data as $userid => $record) {
			$return_column['display'][$userid] = '<div><span class="report_engagement_display">'.
				sprintf("%.0f", $risks[$userid]->{'risk'} * 100).
				'</span></div>';
		}
		$return_columns[] = $return_column;
		// Column for number overdue
		$return_column = array();
		$return_column['header'] = get_string('report_assessment_overdue', 'engagementindicator_assessment');
		$return_column['filterable'] = True;
		$return_column['heatmapdirection'] = 1; // 1 means normal sort i.e. higher numbers are darker
		$return_column['display'] = array();
		foreach ($data as $userid => $record) {
			$detail = implode('<br />', $record['overdueassessments']);
			$return_column['display'][$userid] = '<div>'.
				'<span class="report_engagement_display">'.$record['numberoverduenotsubmitted'].'</span>'.
				"<div class='report_engagement_detail'>$detail</div><br />".
				'</div>';
		}
		$return_columns[] = $return_column;
		// Column for number submitted
		$return_column = array();
		$return_column['header'] = get_string('report_assessment_submitted', 'engagementindicator_assessment');
		$return_column['filterable'] = True;
		$return_column['heatmapdirection'] = -1; // -1 means reverse sort, i.e. higher numbers are lighter
		$return_column['display'] = array();
		foreach ($data as $userid => $record) {
			$ov = new stdClass();
			$ov->o = $record['numberoverduesubmitted'] ? $record['numberoverduesubmitted'] : 0;
			$ov->v = sprintf("%0.1f", ($record['totallateinterval'] / 60 / 60 / 24) / $record['numbersubmissions']);
			$return_column['display'][$userid] = '<div>'.
				'<span class="report_engagement_display">'.$record['numbersubmissions'].'</span>'.
				"<div class='report_engagement_detail'>".
				get_string('report_assessment_overduelate', 'engagementindicator_assessment', $ov).
				"</div>".
			'</div>';
		}
		$return_columns[] = $return_column;
		
		// Return
		return $return_columns;
		
	}
	
}

class assessment_risk_calculator {
    // Generic list of assessed activities.
    private $assessments = array();


    public function __construct($from_object = null) {
        $this->assessments = $from_object;
    }

    public function add_assessment($maxscore, $submissions, $description, $due) {
        $a = new stdClass;
        $a->maxscore = $maxscore;
        $a->submissions = $submissions;
        $a->description = $description;
		$a->due = $due;
        $this->assessments[] = $a;
    }

    public function get_risks($uids, $total_assessment_value, $settings) {
        $risks = array();
        if (empty($this->assessments)) {
            // Course doesn't have any assessable material.
            $this->assessments = array();
        }
        foreach ($uids as $uid) {
            $risk = 0;
            $reasons = array();
            $gp = $settings['overduegracedays'];
            $md = $settings['overduemaximumdays'];
            foreach ($this->assessments as $a) {
                $reason = new stdClass();
                $reason->assessment = $a->description;
                $submittime = isset($a->submissions[$uid]['submitted']) ? $a->submissions[$uid]['submitted'] : PHP_INT_MAX;
                $timedue = isset($a->submissions[$uid]['due']) ? $a->submissions[$uid]['due'] : 1;
				$num_days_late = ($submittime - $timedue) / DAYSECS;
                $days_late_weighting = ($num_days_late - $settings['overduegracedays']) /
                                     ($settings['overduemaximumdays'] - $settings['overduegracedays']);
                $days_late_weighting = max(0, min(1, $days_late_weighting));
                $assessment_value_weighting = $a->maxscore / $total_assessment_value;
                $reason->assessmentweighting = number_format($assessment_value_weighting*100, 1) . '%';
                if (isset($a->submissions[$uid]['submitted'])) {
                    // Assessment was submitted.
                    $attime = date("d-m-Y H:i", $submittime);
                    $reason->submitted = "submitted $attime.";
                    if ($num_days_late > 0) {
                        $reason->dayslate = round($num_days_late, 2);
                    }
                    $local_risk = $days_late_weighting * $settings['overduesubmittedweighting'];
                    $risk_contribution = $assessment_value_weighting * $local_risk;
                    $risk += $risk_contribution;
                    $reason->riskcontribution = number_format($risk_contribution*100, 1).'%';
                    $reason->localrisk = number_format($local_risk*100, 1).'%';
                    $mr = intval($settings['overduesubmittedweighting'] * 100);
                    $reason->logic = "0% risk before grace period ($gp days) ... $mr% risk after max days ($md).";
                } else if ($a->due > time()) {
					// Assessment not due yet
					$timedue = date("d-m-Y H:i", $a->due);
					$reason->submitted = "Not due yet; due $timedue";
                    $local_risk = 0;
                    $risk_contribution = 0;
                    $risk += $risk_contribution;
                    $reason->riskcontribution = number_format($risk_contribution*100, 1).'%';
                    $reason->localrisk = number_format($local_risk*100, 1).'%';
                    $mr = intval($settings['overduesubmittedweighting'] * 100);
                    $reason->logic = "0% risk before grace period ($gp days) ... $mr% risk after max days ($md).";
				} else {
                    $reason->submitted = "not submitted.";
                    $local_risk = $days_late_weighting * $settings['overduenotsubmittedweighting'];
                    $risk_contribution = $assessment_value_weighting * $local_risk;
                    $risk += $risk_contribution;
                    $reason->riskcontribution = number_format($risk_contribution*100, 1).'%';
                    $reason->localrisk = number_format($local_risk*100, 1).'%';
                    $mr = intval($settings['overduenotsubmittedweighting'] * 100);
                    $reason->logic = "0% risk before grace period ($gp days) ... $mr% risk after max days ($md).";
                }
                if (isset($a->submissions[$uid]['override'])) {
                    $reason->override = $a->submissions[$uid]['override'];
                }
                $reasons[] = $reason;
            }
            $risks[$uid] = new stdClass();
            $risks[$uid]->risk = $risk;
            $risks[$uid]->info = $reasons;
        }

        return $risks;
    }

    public function as_object() {
        return $this->assessments;
    }
}

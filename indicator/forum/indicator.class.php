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
 * This file defines a class with forum indicator logic
 *
 * @package    engagementindicator_forum
 * @copyright  2012 NetSpot Pty Ltd, 2015-2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../indicator.class.php');

class indicator_forum extends indicator {
    private $currweek;

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
    protected function get_rawdata($startdate, $enddate) {
        global $DB;

        $posts = array();

        // Table: mdl_forum_posts, fields: userid, created, discussion, parent.
        // Table: mdl_forum_discussions, fields: userid, course, id.
        // Table: mdl_forum_read, fields: userid, discussionid, postid, firstread.
        $sql = "SELECT p.id, p.userid, p.created, p.parent
                FROM {forum_posts} p
                JOIN {forum_discussions} d ON (d.id = p.discussion)
                WHERE d.course = :courseid
                    AND p.created > :startdate AND p.created < :enddate";
        $params['courseid'] = $this->courseid;
        $params['startdate'] = $startdate;
        $params['enddate'] = $enddate;
        if ($postrecs = $DB->get_recordset_sql($sql, $params)) {
            foreach ($postrecs as $post) {
                $week = date('W', $post->created);
                if (!isset($posts[$post->userid])) {
                    $posts[$post->userid] = array();
                    $posts[$post->userid]['total'] = 0;
                    $posts[$post->userid]['weeks'] = array();
                    $posts[$post->userid]['new'] = 0;
                    $posts[$post->userid]['replies'] = 0;
                    $posts[$post->userid]['read'] = 0;
                }
                if (!isset($posts[$post->userid]['weeks'][$week])) {
                    $posts[$post->userid]['weeks'][$week] = 0;
                }
                $posts[$post->userid]['total']++;
                $posts[$post->userid]['weeks'][$week]++;

                if ($post->parent == 0) {
                    $posts[$post->userid]['new']++;
                } else {
                    $posts[$post->userid]['replies']++;
                }
            }
            $postrecs->close();
        }

        // Fetch data from forum_read table first.
        $sql = "SELECT *
                  FROM {forum_read} fr
                  JOIN {forum} f ON (f.id = fr.forumid)
                 WHERE f.course = :courseid
                   AND fr.firstread > :startdate 
                   AND fr.firstread < :enddate";
        $params['courseid'] = $this->courseid;
        $params['startdate'] = $startdate;
        $params['enddate'] = $enddate;
        $readposts = $DB->get_recordset_sql($sql, $params);
        // Determine (1) if just counting uniques or all views, and (2) if forum_read data are available.
        $uselogs = false;
        $uselogsunique = false;
        if ($this->config['read_count_method'] == 'all') {
            // Counting all views so ignore forum_read data regardless of whether or not there are any.
            $uselogs = true;
            $uselogsunique = false;
        } else if ($this->config['read_count_method'] == 'unique' && $readposts->valid()) {
            // Just counting uniques, and forum_read has data.
            $uselogs = false;
        } else if ($this->config['read_count_method'] == 'unique' && !$readposts->valid()) {
            // Need to count uniques, but forum_read doesn't have data.
            $uselogs = true;
            $uselogsunique = true;
        }
        // Now that the logic has been decided, fetch data as necessary.
        if ($uselogs) {
            // Set sql for selecting unique views/reads.
            $sqlunique = '';
            if ($uselogsunique) {
                $sqlunique = ' GROUP BY objectid ';
            }
            // Set the sql based on log reader(s) available.
            $sql = array();
            $logmanager = get_log_manager();
            $readers = $logmanager->get_readers(); 
            foreach ($readers as $reader) {
                if ($reader instanceof \logstore_legacy\log\store) {
                    $sql['legacy'] = "SELECT id, userid, time, course, info as objectid
                                        FROM {log}
                                       WHERE module = 'forum'
                                         AND action = 'view discussion'
                                         $sqlunique";
                } else if ($reader instanceof \logstore_standard\log\store) {
                    $sql['standard'] = "SELECT id, userid, timecreated AS time, courseid AS course, objectid
                                          FROM {logstore_standard_log}
                                         WHERE target = 'discussion'
                                           AND action = 'viewed'
                                           $sqlunique";
                }
            }
            $querysql = "SELECT  c.id, c.userid, c.objectid
                           FROM (" . implode(' UNION ', $sql) . ") c
                          WHERE c.course = :courseid
                            AND c.time >= :startdate
                            AND c.time <= :enddate";
            // Read logs.
            $readposts = $DB->get_recordset_sql($querysql, $params);
        } else {
            // Continue - $readposts should already have the required data.
        }
        if ($readposts) {
            foreach ($readposts as $read) {
                if (!isset($posts[$read->userid])) {
                    $posts[$read->userid]['read'] = 0;
                }
                $posts[$read->userid]['read']++;
            }
            $readposts->close();
        }

        $rawdata = new stdClass();
        $rawdata->posts = $posts;
        return $rawdata;
    }

    protected function calculate_risks(array $userids) {
        $risks = array();

        $strtotalposts = get_string('e_totalposts', 'engagementindicator_forum');
        $strreplies = get_string('e_replies', 'engagementindicator_forum');
        $strreadposts = get_string('e_readposts', 'engagementindicator_forum');
        $strnewposts = get_string('e_newposts', 'engagementindicator_forum');
        $strmaxrisktitle = get_string('maxrisktitle', 'engagementindicator_forum');

        $startweek = date('W', $this->startdate);
        $this->currweek = date('W') - $startweek + 1;
        foreach ($userids as $userid) {
            $risk = 0;
            $reasons = array();
            if (!isset($this->rawdata->posts[$userid])) {
                // Max risk.
                $info = new stdClass();
                $info->risk = 1.0 * ($this->config['w_totalposts'] +
                                               $this->config['w_replies'] +
                                               $this->config['w_newposts'] +
                                               $this->config['w_readposts']);
                $reason = new stdClass();
                $reason->weighting = '100%';
                $reason->localrisk = '100%';
                $reason->logic = "This user has never made a post or had tracked read posts in the ".
                                 "course and so is at the maximum 100% risk.";
                $reason->riskcontribution = '100%';
                $reason->title = $strmaxrisktitle;
                $info->info = array($reason);
                $risks[$userid] = $info;
                continue;
            }
            
            // Add missing data if necessary.
            if (empty($this->rawdata->posts[$userid]['total'])) {
                $this->rawdata->posts[$userid]['total'] = 0;
            }   
            if (empty($this->rawdata->posts[$userid]['replies'])) {
                $this->rawdata->posts[$userid]['replies'] = 0;
            }   
            if (empty($this->rawdata->posts[$userid]['new'])) {
                $this->rawdata->posts[$userid]['new'] = 0;
            }   
            if (empty($this->rawdata->posts[$userid]['read'])) {
                $this->rawdata->posts[$userid]['read'] = 0;
            }   
            
            $localrisk = $this->calculate('totalposts', $this->rawdata->posts[$userid]['total']);
            $riskcontribution = $localrisk * $this->config['w_totalposts'];
            $reason = new stdClass();
            $reason->weighting = intval($this->config['w_totalposts'] * 100).'%';
            $reason->localrisk = intval($localrisk * 100).'%';
            $reason->logic = "0% risk for more than {$this->config['no_totalposts']} posts a week. ".
                             "100% for {$this->config['max_totalposts']} posts a week.";
            $reason->riskcontribution = intval($riskcontribution * 100).'%';
            $reason->title = $strtotalposts;
            $reasons[] = $reason;
            $risk += $riskcontribution;

            $localrisk += $this->calculate('replies', $this->rawdata->posts[$userid]['replies']);
            $riskcontribution = $localrisk * $this->config['w_replies'];
            $reason = new stdClass();
            $reason->weighting = intval($this->config['w_replies'] * 100).'%';
            $reason->localrisk = intval($localrisk * 100).'%';
            $reason->logic = "0% risk for more than {$this->config['no_replies']} replies a week. ".
                             "100% for {$this->config['max_replies']} replies a week.";
            $reason->riskcontribution = intval($riskcontribution * 100).'%';
            $reason->title = $strreplies;
            $reasons[] = $reason;
            $risk += $riskcontribution;

            $localrisk += $this->calculate('newposts', $this->rawdata->posts[$userid]['new']);
            $riskcontribution = $localrisk * $this->config['w_newposts'];
            $reason = new stdClass();
            $reason->weighting = intval($this->config['w_newposts'] * 100).'%';
            $reason->localrisk = intval($localrisk * 100).'%';
            $reason->logic = "0% risk for more than {$this->config['no_newposts']} replies a week. ".
                             "100% for {$this->config['max_newposts']} new posts a week.";
            $reason->riskcontribution = intval($riskcontribution * 100).'%';
            $reason->title = $strnewposts;
            $reasons[] = $reason;
            $risk += $riskcontribution;

            $localrisk += $this->calculate('readposts', $this->rawdata->posts[$userid]['read']);
            $riskcontribution = $localrisk * $this->config['w_readposts'];
            $reason = new stdClass();
            $reason->weighting = intval($this->config['w_readposts'] * 100).'%';
            $reason->localrisk = intval($localrisk * 100).'%';
            $reason->logic = "0% risk for more than {$this->config['no_readposts']} read posts a week. ".
                             "100% for {$this->config['max_readposts']} read posts a week.";
            $reason->riskcontribution = intval($riskcontribution * 100).'%';
            $reason->title = $strreadposts;
            $reasons[] = $reason;
            $risk += $riskcontribution;

            $info = new stdClass();
            $info->risk = $risk;
            $info->info = $reasons;
            $risks[$userid] = $info;
        }

        return $risks;
    }

    protected function calculate($type, $num) {
        $risk = 0;
        $maxrisk = $this->config["max_$type"];
        $norisk = $this->config["no_$type"];
        $weight = $this->config["w_$type"];
        if ($num / $this->currweek <= $maxrisk) {
            $risk = $weight;
        } else if ($num / $this->currweek < $norisk) {
            $num = $num / $this->currweek;
            $num -= $maxrisk;
            $num /= $norisk - $maxrisk;
            $risk = $num * $weight;
        }
        return $risk;
    }

    protected function load_config() {
        parent::load_config();
        $defaults = $this->get_defaults();
        foreach ($defaults as $setting => $value) {
            if (!isset($this->config[$setting])) {
                $this->config[$setting] = $value;
            } else if (substr($setting, 0, 2) == 'w_') {
                $this->config[$setting] = $this->config[$setting] / 100;
            } else {
                $this->config[$setting] = $this->config[$setting];
            }
        }
    }

    public static function get_defaults() {
        $settings = array();
        $settings['w_totalposts'] = 0.56;
        $settings['w_replies'] = 0.2;
        $settings['w_newposts'] = 0.12;
        $settings['w_readposts'] = 0.12;

        $settings['no_totalposts'] = 1;
        $settings['no_replies'] = 1;
        $settings['no_newposts'] = 0.5;
        $settings['no_readposts'] = 1; // 100%.

        $settings['max_totalposts'] = 0;
        $settings['max_replies'] = 0;
        $settings['max_newposts'] = 0;
        $settings['max_readposts'] = 0;
        
        $settings['read_count_method'] = 'all';
        
        return $settings;
    }
    
    public function get_data_for_mailer() {
        
        $risks = $this->get_course_risks();
        $data = array();
        
        foreach ($this->userarray as $userid) {
            $data[$userid] = array();
        }
        
        // Collect and process data.
        foreach ($this->rawdata->posts as $userid => $record) {
            if (array_key_exists($userid, $data)) {
                $data[$userid]['total'] = $record['total']; // Total postings (not readings).
                $data[$userid]['new'] = $record['new'];
                $data[$userid]['replies'] = $record['replies'];
                $data[$userid]['read'] = $record['read'];
            }
        }
        
        // Parse for display.
        $returncolumns = array();
        // Column for risk.
        $returncolumn = array();
        $returncolumn['header'] = get_string('report_forum_risk', 'engagementindicator_forum');
        $returncolumn['heatmapdirection'] = 1; // 1 means normal sort i.e. higher numbers are darker.
        $returncolumn['display'] = array();
        foreach ($data as $userid => $record) {
            $returncolumn['display'][$userid] = '<div><span class="report_engagement_display">'.
                sprintf("%.0f", $risks[$userid]->{'risk'} * 100).
                '</span></div>';
        }
        $returncolumns[] = $returncolumn;
        // Column for read posts.
        $returncolumn = array();
        $returncolumn['header'] = get_string('report_readposts', 'engagementindicator_forum');
        $returncolumn['heatmapdirection'] = -1; // -1 means reverse sort, i.e. higher numbers are lighter.
        $returncolumn['display'] = array();
        foreach ($data as $userid => $record) {
            $returncolumn['display'][$userid] = '<div><span class="report_engagement_display">'.
                (isset($record['read']) ? $record['read'] : '').
                '</span></div>';
        }
        $returncolumns[] = $returncolumn;
        // Column for number posted.
        $returncolumn = array();
        $returncolumn['header'] = get_string('report_posted', 'engagementindicator_forum');
        $returncolumn['heatmapdirection'] = -1; // -1 means reverse sort, i.e. higher numbers are lighter.
        $returncolumn['display'] = array();
        foreach ($data as $userid => $record) {
            $returncolumn['display'][$userid] = '<div><span class="report_engagement_display">'.
                (isset($record['total']) ? $record['total'] : '').
                '</span></div>';
        }
        $returncolumns[] = $returncolumn;
        
        // Return
        return $returncolumns;
        
    }
    
}

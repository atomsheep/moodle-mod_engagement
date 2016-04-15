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
 * This file defines a class with login indicator logic
 *
 * @package    engagementindicator_login
 * @copyright  2012 NetSpot Pty Ltd, 2015-2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../indicator.class.php');

class indicator_login extends indicator {

    /**
     * get_rawdata
     *
     * @param int $startdate
     * @param int $enddate
     * @access protected
     * @return array            array of risk values, keyed on userid
     */
    protected function get_rawdata($startdate, $enddate) {
        global $DB;

        $sessions = array();

        // Set the sql based on log reader(s) available.
        $params = array();
        $params['courseid_legacy'] = $params['courseid_standard'] = $this->courseid;
        $params['startdate_legacy'] = $params['startdate_standard'] = $startdate;
        $params['enddate_legacy'] = $params['enddate_standard'] = $enddate;
        $sql = array();
        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers(); 
        foreach ($readers as $reader) {
            if ($reader instanceof \logstore_legacy\log\store) {
                $sql['legacy'] = 'SELECT id, userid, time
                                    FROM {log}
                                    WHERE course = :courseid_legacy AND time >= :startdate_legacy AND time <= :enddate_legacy';
            } else if ($reader instanceof \logstore_standard\log\store) {
                $sql['standard'] = 'SELECT id, userid, timecreated AS time
                                    FROM {logstore_standard_log}
                                    WHERE courseid = :courseid_standard AND timecreated >= :startdate_standard AND timecreated <= :enddate_standard';
            }
        }
        $querysql = 'SELECT c.id, c.userid, c.time FROM (' . implode(' UNION ', $sql) . ') c ORDER BY time ASC';
        // Read logs.
        $logs = $DB->get_recordset_sql($querysql, $params);
                
        if ($logs) {
            // Need to calculate sessions, sessions are defined by time between consecutive logs not exceeding setting.
            foreach ($logs as $log) {
                $increment = false;
                $week = date('W', $log->time);
                if (!isset($sessions[$log->userid])) {
                    $sessions[$log->userid] = array('total' => 0, 'weeks' => array(), 'pastweek' => 0, 'lengths' => array(),
                                                    'start' => 0);
                }
                if (!isset($sessions[$log->userid]['lastlogin'])) {
                    $increment = true;
                } else {
                    if (($log->time - $this->config['session_length']) > $sessions[$log->userid]['lastlogin']) {
                        $increment = true;
                    }
                }

                if ($increment) {
                    if ($sessions[$log->userid]['start'] > 0) {
                        $sessions[$log->userid]['lengths'][] =
                            $sessions[$log->userid]['lastlogin'] - $sessions[$log->userid]['start'];
                    }
                    $sessions[$log->userid]['total']++;
                    $sessions[$log->userid]['start'] = $log->time;
                    if (!isset($sessions[$log->userid]['weeks'][$week])) {
                        $sessions[$log->userid]['weeks'][$week] = 0;
                    }
                    $sessions[$log->userid]['weeks'][$week]++;

                    if ($log->time > ($enddate - WEEKSECS)) { // Session in past week.
                        $sessions[$log->userid]['pastweek']++;
                    }
                }
                $sessions[$log->userid]['lastlogin'] = $log->time;
            }
            $logs->close();
        }

        return $sessions;
    }

    private static function calculate_risk($actual, $expected) {
        $risk = 0;
        if ($actual < $expected) {
            $risk += ($expected - $actual) / $expected;
        }
        return $risk;
    }

    protected function calculate_risks(array $userids) {
        $risks = array();
        $sessions = $this->rawdata;

        $strloginspastweek = get_string('eloginspastweek', 'engagementindicator_login');
        $strloginsperweek = get_string('eloginsperweek', 'engagementindicator_login');
        $stravgsessionlength = get_string('eavgsessionlength', 'engagementindicator_login');
        $strtimesincelast = get_string('etimesincelast', 'engagementindicator_login');
        $strmaxrisktitle = get_string('maxrisktitle', 'engagementindicator_login');

        foreach ($userids as $userid) {
            $risk = 0;
            $reasons = array();

            if (!isset($sessions[$userid])) {
                $info = new stdClass();
                $info->risk = 1.0 * ($this->config['w_loginspastweek'] +
                                         $this->config['w_avgsessionlength'] +
                                         $this->config['w_loginsperweek'] +
                                         $this->config['w_timesincelast']);
                $reason = new stdClass();
                $reason->weighting = '100%';
                $reason->localrisk = '100%';
                $reason->logic = get_string('reasonnologin', 'engagementindicator_login');
                $reason->riskcontribution = '100%';
                $reason->title = $strmaxrisktitle;
                $info->info = array($reason);
                $risks[$userid] = $info;
                continue;
            }

            // Logins past week.
            $localrisk = self::calculate_risk($sessions[$userid]['pastweek'], $this->config['e_loginspastweek']);
            $riskcontribution = $localrisk * $this->config['w_loginspastweek'];
            $reason = new stdClass();
            $reason->weighting = intval($this->config['w_loginspastweek'] * 100).'%';
            $reason->localrisk = intval($localrisk * 100).'%';
            $reason->logic = get_string('reasonloginspastweek', 'engagementindicator_login', $this->config['e_loginspastweek']);
            $reason->riskcontribution = intval($riskcontribution * 100).'%';
            $reason->title = $strloginspastweek;
            $reasons[] = $reason;
            $risk += $riskcontribution;

            // Average session length.
            if (($count = count($sessions[$userid]['lengths'])) > 0) {
                $average = array_sum($sessions[$userid]['lengths']) / $count;
            } else {
                $average = 0;
            }
            $localrisk = self::calculate_risk($average, $this->config['e_avgsessionlength']);
            $riskcontribution = $localrisk * $this->config['w_avgsessionlength'];
            $reason = new stdClass();
            $reason->weighting = intval($this->config['w_avgsessionlength'] * 100).'%';
            $reason->localrisk = intval($localrisk * 100).'%';
            $reason->logic = get_string('reasonavgsessionlen', 'engagementindicator_login', $this->config['e_avgsessionlength']);
            $reason->riskcontribution = intval($riskcontribution * 100).'%';
            $reason->title = $stravgsessionlength;
            $reasons[] = $reason;
            $risk += $riskcontribution;

            // Logins per week.
            if (($count = count($sessions[$userid]['weeks'])) > 0) {
                $average = array_sum($sessions[$userid]['weeks']) / $count;
            } else {
                $average = 0;
            }
            $localrisk = self::calculate_risk($average, $this->config['e_loginsperweek']);
            $riskcontribution = $localrisk * $this->config['w_loginsperweek'];
            $reason = new stdClass();
            $reason->weighting = intval($this->config['w_loginsperweek'] * 100).'%';
            $reason->localrisk = intval($localrisk * 100).'%';
            $reason->logic = get_string('reasonloginsperweek', 'engagementindicator_login', $this->config['e_loginsperweek']);
            $reason->riskcontribution = intval($riskcontribution * 100).'%';
            $reason->title = $strloginsperweek;
            $reasons[] = $reason;
            $risk += $riskcontribution;

            // Time since last login.
            $timediff = time() - $sessions[$userid]['lastlogin'];
            $localrisk = self::calculate_risk($this->config['e_timesincelast'], $timediff);
            $riskcontribution = $localrisk * $this->config['w_timesincelast'];
            $reason = new stdClass();
            $reason->weighting = intval($this->config['w_timesincelast'] * 100).'%';
            $reason->localrisk = intval($localrisk * 100).'%';
            $reason->logic = get_string('reasontimesincelogin', 'engagementindicator_login', $this->config['e_timesincelast'] / DAYSECS);
            $reason->riskcontribution = intval($riskcontribution * 100).'%';
            $reason->title = $strtimesincelast;
            $reasons[] = $reason;
            $risk += $riskcontribution;

            $info = new stdClass();
            $info->risk = $risk;
            $info->info = $reasons;
            $risks[$userid] = $info;
        }
        return $risks;
    }

    protected function load_config() {
        parent::load_config();
        $defaults = $this->get_defaults();
        foreach ($defaults as $setting => $value) {
            if (!isset($this->config[$setting])) {
                $this->config[$setting] = $value;
            } else if (substr($setting, 0, 2) == 'w_') {
                $this->config[$setting] = $this->config[$setting] / 100;
            }
        }
    }

    public static function get_defaults() {
        $settings = array();
        $settings['e_loginspastweek'] = 2;
        $settings['w_loginspastweek'] = 0.2;

        $settings['e_loginsperweek'] = 2;
        $settings['w_loginsperweek'] = 0.3;

        $settings['e_avgsessionlength'] = 10; // 10 minutes
        $settings['w_avgsessionlength'] = 0.1;

        $settings['e_timesincelast'] = 7; // 7 days
        $settings['w_timesincelast'] = 0.4;

        $settings['session_length'] = 60; // 60 minutes
        return $settings;
    }
    
    public function get_helper_initial_settings(){
        $settings = array();
        
        $settings['e_loginspastweek'] = ['start' => 4, 'min' => 0, 'max' => 50];
        $settings['w_loginspastweek'] = ['start' => 20, 'min' => 0, 'max' => 100];

        $settings['e_loginsperweek'] = ['start' => 3, 'min' => 0, 'max' => 50];
        $settings['w_loginsperweek'] = ['start' => 30, 'min' => 0, 'max' => 100];

        $settings['e_avgsessionlength'] = ['start' => 10 * 60, 'min' => 0, 'max' => 12 * 60 * 60]; // In seconds.
        $settings['w_avgsessionlength'] = ['start' => 10, 'min' => 0, 'max' => 100];

        $settings['e_timesincelast'] = ['start' => 7 * 24 * 60 * 60, 'min' => 0, 'max' => 14 * 24 * 60 * 60]; // In seconds.
        $settings['w_timesincelast'] = ['start' => 40, 'min' => 0, 'max' => 100];

        $settings['session_length'] = ['start' => 3600, 'min' => 600, 'max' => 14400];
        
        return $settings;
    }
    
    public function transform_helper_discovered_settings($discoveredsettings) {
        $settings = $this->get_defaults();
        
        $warray = array();
        $earray = array();
        $others = array();
        
        foreach ($settings as $key => $setting) {
            if (substr($key, 0, 2) == 'w_') {
                $warray["login_$key"] = $discoveredsettings[$key];
            } else if (substr($key, 0, 2) == 'e_') {
                $earray["login_$key"] = $discoveredsettings[$key];
            } else if ($key == 'session_length') {
                $others["login_$key"] = $discoveredsettings[$key];
            } else {
                $others["login_$key"] = $setting;
            }
        }
        
        // Normalise weightings.
        $sumweight = array_sum($warray);
        foreach ($warray as $key => $value) {
            $warray[$key] = round(($value / $sumweight) * 100.0, 0);
        }
        
        // Prettify/round numbers.
        foreach ($earray as $key => $value) {
            $earray[$key] = round($value, 2);
        }
        $others["login_session_length"] = round($others["login_session_length"], 2);
        
        return array_merge($earray, $warray, $others);
        
    }
    
    public function get_data_for_mailer() {
        
        $risks = $this->get_course_risks();
        $data = array();
        
        foreach ($this->userarray as $userid) {
            $data[$userid] = array(
                'totaltimes' => null,
                'lastlogin' => null,
                'averagesessionlength' => null,
                'averageperweek' => null
            );
        }
        
        // Collect and process data.
        foreach ($this->rawdata as $userid => $record) {
            if (array_key_exists($userid, $data)) {
                $data[$userid]['totaltimes'] = count($record['lengths']);
                $data[$userid]['lastlogin'] = $record['lastlogin'];
                if ($record['total'] > 0) {
                    $data[$userid]['averagesessionlength'] = array_sum($record['lengths']) / count($record['lengths']);
                    $data[$userid]['averageperweek'] = array_sum($record['weeks']) / count($record['weeks']);
                } else {
                    $data[$userid]['averagesessionlength'] = "";
                    $data[$userid]['averageperweek'] = "";
                }
            }
        }
        
        // Parse for display.
        $returncolumns = array();
        // Column for risk.
        $returncolumn = array();
        $returncolumn['header'] = get_string('report_login_risk', 'engagementindicator_login');
        $returncolumn['heatmapdirection'] = 1; // 1 means normal sort i.e. higher numbers are darker.
        $returncolumn['display'] = array();
        foreach ($data as $userid => $record) {
            $returncolumn['display'][$userid] = '<div><span class="report_engagement_display">'.
                sprintf("%.0f", $risks[$userid]->{'risk'} * 100).
                '</span></div>';
        }
        $returncolumns[] = $returncolumn;
        // Column for days since last login
        $returncolumn = array();
        $returncolumn['header'] = get_string('report_login_dayssince', 'engagementindicator_login');
        $returncolumn['heatmapdirection'] = 1; // 1 means normal sort i.e. higher numbers are darker.
        $returncolumn['display'] = array();
        foreach ($data as $userid => $record) {
            $n = $record['lastlogin'];
            if ($n) {
                $returncolumn['display'][$userid] = '<div><span class="report_engagement_display">'.
                    sprintf("%.1d", (time() - $n) / 60 / 60 / 24.0).
                    '</span></div>';
            } else {
                $returncolumn['display'][$userid] = '';
            }
        }
        $returncolumns[] = $returncolumn;
        // Column for logins per week
        $returncolumn = array();
        $returncolumn['header'] = get_string('report_login_perweek', 'engagementindicator_login');
        $returncolumn['heatmapdirection'] = -1; // -1 means reverse sort, i.e. higher numbers are lighter.
        $returncolumn['display'] = array();
        foreach ($data as $userid => $record) {
            $returncolumn['display'][$userid] = '<div><span class="report_engagement_display">'.
                sprintf("%.1d", $record['averageperweek']).
                '</span></div>';
        }
        $returncolumns[] = $returncolumn;
        
        // Return
        return $returncolumns;
        
    }
    
}
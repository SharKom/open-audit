<?php
#
#  Copyright 2003-2015 Opmantek Limited (www.opmantek.com)
#
#  ALL CODE MODIFICATIONS MUST BE SENT TO CODE@OPMANTEK.COM
#
#  This file is part of Open-AudIT.
#
#  Open-AudIT is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as published
#  by the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  Open-AudIT is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with Open-AudIT (most likely in a file named LICENSE).
#  If not, see <http://www.gnu.org/licenses/>
#
#  For further information on Open-AudIT or for a license other than AGPL please see
#  www.opmantek.com or email contact@opmantek.com
#
# *****************************************************************************
$this->benchmark->mark('code_start');

// load our required helpers
$this->load->helper('log');
$this->load->helper('error');

$log = new stdClass();

if (strtoupper($this->input->server('REQUEST_METHOD')) == 'GET') {
    $get = $this->input->get();
    if (!empty($get)) {
        foreach ($get as $key => $value) {
            $log->$key = $value;
            $input->$key = $value;
        }
    }
} else if (strtoupper($this->input->server('REQUEST_METHOD')) == 'POST') {
    $post = $this->input->post();
    if (!empty($post)) {
        foreach ($post as $key => $value) {
            $log->$key = $value;
        }
    }
}

if (!empty($log->type) and $log->type == 'discovery') {
    $log_id = discovery_log($log);
    if (strpos($log->message, 'Starting discovery for ') !== false) {
        # Set the Status to running
        $sql = '/* input::logs */ ' . "UPDATE `discoveries` SET `status` = 'running', last_run = NOW(), last_log = NOW() WHERE id = ?";
        $data = array($log->discovery_id);
        $query = $this->db->query($sql, $data);
    }
    if (strpos($log->command_status, ' of ') !== false) {
        # Update the Progress
        $progress = str_replace('(', '', $log->command_status);
        $progress = str_replace(')', '', $progress);
        $sql = '/* input::logs */ ' . "UPDATE `discoveries` SET `discovered` = ?, `last_log` = (SELECT `timestamp` FROM discovery_log WHERE `id` = ?), `status` = 'running' WHERE id = ?";
        $data = array($progress, $log_id, $log->discovery_id);
        $query = $this->db->query($sql, $data);
    }
    if (strpos($log->message, 'Completed discovery') !== false) {
        # Update the status
        $sql = '/* input::logs */ ' . "UPDATE `discoveries` SET `status` = 'complete', `duration` = TIMEDIFF(last_log, last_run) WHERE id = ?";
        $data = array($log->discovery_id);
        $query = $this->db->query($sql, $data);
    }
    # Update the duration
    $sql = '/* input::logs */ ' . "UPDATE discoveries SET `duration` = TIMEDIFF(last_log, last_run) WHERE id = ?";
    $data = array($log->discovery_id);
    $query = $this->db->query($sql, $data);
    # Mark any discoveries where status != complete and last_log is greater than 20minutes ago as Zombie
    $sql = '/* input::logs */ ' . "UPDATE `discoveries` SET `status` = 'zombie' WHERE `last_log` < (NOW() - INTERVAL 20 MINUTE) AND `last_log` != '2000-01-01 00:00:00' AND `status` != 'complete'";
    $query = $this->db->query($sql);
}

if ($this->response->meta->format == 'json') {
    print_r(json_encode($input));
}

exit();

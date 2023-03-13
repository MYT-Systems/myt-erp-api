<?php

namespace App\Controllers;
use DateTime;

use App\Models\User;
use App\Models\Branch;
use App\Models\User_branch;
use App\Models\Webapp_response;


class Logout extends MYTController
{

    function __construct()
    {
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];
        
        $this->_load_essentials();
    }
    
    /**
     * login to website
     */
    public function index()
    {
        if (($response = $this->_api_verification('logout', 'index')) !== true)
            return $response;

        if (!$this->_unauthorize_security_keys()) {
            $response = $this->failServerError('Server error. Please try again.');
        } else {
            $response = $this->respond(['response' => 'Logout Successful.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Methods
    // ------------------------------------------------------------------------

    /**
     * Attempt to record attendance
     */
    protected function _attempt_record_attendance($employee_id, $type = false)
    {
        $user = $this->userModel->get_details_by_id($this->requested_by);
        $user = $user ? $user[0] : null;
        $branch_id = $user ? $user['branch_id'] : 0;
        $branch_id = $branch_id ? : 0;

        $attendance = $this->attendanceModel->get_latest_attendance_today($employee_id, $branch_id);
        $attendance = $attendance ? $attendance[0] : null;
        
        if (!$type) {
            
            $value = [
                'branch_id'   => $branch_id,
                'employee_id' => $employee_id,
                'datetime'    => date('Y-m-d H:i:s'),
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];

            if (!$attendance AND !$this->attendanceModel->insert($value)) {
                $this->errorMessage = $this->db->error()['message'];
                $this->db->transRollback();
                $this->db->close();
                return false;
            }
        } else {
            if (!$attendance) {
                $this->errorMessage = "Employee has no attendance";
                $this->db->transRollback();
                $this->db->close();
                return false;
            }

            $attendance_entry = $this->attendanceEntryModel->get_latest_attendance_entry($attendance['id']);
            $attendance_entry = $attendance_entry ? $attendance_entry[0] : null;
            if (!$attendance_entry && $type == 'time_out') {
                $this->errorMessage = "Employee has no attendance entry, time in first.";
                $this->db->transRollback();
                $this->db->close();
                return false;
            }

            switch($type) {
                case 'time_in':
                    // User needs to time_out first before time_in
                    if ($attendance_entry && !$attendance_entry['time_out']) {
                        $this->errorMessage = "User needs to time_out first before time_in";
                        $this->db->transRollback();
                        $this->db->close();
                        return false;
                    }

                    $value = [
                        'attendance_id' => $attendance['id'],
                        'time_in'       => date('Y-m-d H:i:s'),
                        'added_by'      => $this->requested_by,
                        'added_on'      => date('Y-m-d H:i:s')
                    ];

                    if (!$this->attendanceEntryModel->insert($value)) {
                        $this->errorMessage = $this->db->error()['message'];
                        $this->db->transRollback();
                        $this->db->close();
                        return false;
                    }

                    break;
                case 'time_out':                    
                    // If the user time_out already
                    if ($attendance_entry && $attendance_entry['time_out']) {
                        $this->errorMessage = "User has already time_out";
                        $this->db->transRollback();
                        $this->db->close();
                        return false;
                    }
                    
                    $time_in = $attendance_entry['time_in'];
                    $time_out = date('Y-m-d H:i:s');
                    $worked_minutes = $this->_get_worked_minutes($time_in, $time_out);

                    $value = [
                        'time_out'       => date('Y-m-d H:i:s'),
                        'worked_minutes' => $worked_minutes,
                        'updated_by'     => $this->requested_by,
                        'updated_on'     => date('Y-m-d H:i:s')
                    ];

                    if (!$this->attendanceEntryModel->update($attendance_entry['id'], $value)) {
                        $this->errorMessage = $this->db->error()['message'];
                        $this->db->transRollback();
                        $this->db->close();
                        return false;
                    }

                    $value = [
                        'total_minutes' => $attendance['total_minutes'] + $worked_minutes,
                        'updated_by'    => $this->requested_by,
                        'updated_on'    => date('Y-m-d H:i:s')
                    ];

                    if (!$this->attendanceModel->update($attendance['id'], $value)) {
                        $this->errorMessage = $this->db->error()['message'];
                        $this->db->transRollback();
                        $this->db->close();
                        return false;
                    }
                    break;
                default:
                    $this->db->transRollback();
                    $this->db->close();
                    $this->errorMessage = "Invalid type";
                    return false;
            }
        }

        $this->db->transCommit();
        $this->db->close();
        return true;
    }

    /**
     * Get worked minutes
     */
    protected function _get_worked_minutes($time_in, $time_out)
    {
        $time_in = new DateTime($time_in);
        $time_out = new DateTime($time_out);
        $diff = $time_in->diff($time_out);
        
        return $diff->h * 60 + $diff->i;
    }

    /**
     * Unauthorize api key and token
     */
    protected function _unauthorize_security_keys()
    {
        $values = [
            'api_key' => null,
            'token' => null,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        
        if (!$this->userModel->update($this->requested_by, $values))
            return false;
        
        $user = $this->userModel->get_details_by_id($this->requested_by);
        $branch_id = $user[0]['branch_id'];
        $branch_details = $branch_id ? $this->branchModel->get_details_by_id($branch_id) : null;
        
        if ($user[0]['type'] == 'branch' && $branch_details[0]['is_open'] == 1) {
            $values = [
                'is_open' => 0,
                'operation_log_id' => null,
                'closed_on'  => date('Y-m-d H:i:s'),
                'updated_on' => date('Y-m-d H:i:s'),
                'updated_by' => $user[0]['id']
            ];

            $operation_log_data = [
                'time_out' => date("Y-m-d H:i:s"),
                'is_automatic_logout' => 0
            ];

            if (!$this->branchModel->update($branch_id, $values) OR
                !$this->operationLogModel->update($branch_details[0]['operation_log_id'], $operation_log_data)
            ) {
                return false;
            }
        }

        if ($user[0]['type'] == 'supervisor') {
            $this->db = \Config\Database::connect();
            $this->db->transBegin();

            if (!$this->_attempt_record_attendance($user[0]['employee_id'])) {
                $this->db->transRollback();
                $response = $this->fail('Attendance not saved');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            } elseif (!$this->_attempt_record_attendance($user[0]['employee_id'], 'time_out')) {
                $this->db->transRollback();
                $response = $this->fail('Attendance not saved');
                $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                return $response;
            }

            $this->db->transCommit();
        }

        return true;
    }

    /**
     * Load essentials
     */
    protected function _load_essentials()
    {
        $this->attendanceModel        = model('App\Models\Attendance');
        $this->attendanceEntryModel   = model('App\Models\Attendance_entry');
        $this->employeeModel          = model('App\Models\Employee');

        $this->userModel   = new User();
        $this->branchModel = new Branch();
        $this->userBranchModel = new User_branch();
        $this->operationLogModel = model('App\Models\Branch_operation_log');
        $this->webappResponseModel = new Webapp_response();
    }
}

<?php

namespace App\Controllers;

use DateTime;
use App\Models\Attendance;
use App\Models\Attendance_entry;
use App\Models\Employee;
use App\Models\User;
use App\Models\User_branch;
use App\Models\Webapp_response;

class Attendances extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get attendance
     */
    public function get_attendance()
    {
        if (($response = $this->_api_verification('attendances', 'get_attendance')) !== true)
            return $response;

        $attendance_id = $this->request->getVar('attendance_id') ? : null;
        $attendance    = $attendance_id ? $this->attendanceModel->get_details_by_id($attendance_id) : null;

        if (!$attendance) {
            $response = $this->failNotFound('No attendance found');
        } else {
            $attendance[0]['attendance_entries'] = $this->attendanceEntryModel->get_details_by_attendance_id($attendance_id);

            $response = $this->respond([
                'status' => 'success',
                'data'   => $attendance
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all attendances
     */
    public function get_all_attendance()
    {
        if (($response = $this->_api_verification('attendances', 'get_all_attendance')) !== true)
            return $response;

        $branch_id = $this->request->getVar('branch_id') ? : null;

        $attendances = $this->attendanceModel->get_all_attendance($branch_id);

        if (!$attendances) {
            $response = $this->failNotFound('No attendance found');
        } else {
            foreach($attendances as $key => $attendance) {
                $attendances[$key]['attendance_entries'] = $this->attendanceEntryModel->get_details_by_attendance_id($attendance['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $attendances
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete attendances
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('attendances', 'delete')) !== true)
            return $response;

        $attendance_id = $this->request->getVar('attendance_id');

        $where = ['id' => $attendance_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$attendance = $this->attendanceModel->select('', $where, 1)) {
            $response = $this->failNotFound('attendance not found');
        } elseif (!$this->_attempt_delete($attendance_id)) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete attendance', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Attendance deleted successfully', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search attendances based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('attendances', 'search')) !== true)
            return $response;

        $branch_id = $this->request->getVar('branch_id') ? : null;
        $employee_id = $this->request->getVar('employee_id') ? : null;
        $date = $this->request->getVar('date') ? : null;
        $date_from = $this->request->getVar('date_from') ? : null;
        $date_to = $this->request->getVar('date_to') ? : null;
        $group_by_employees = $this->request->getVar('by_employees') ? : false;

        if (!$attendances = $this->attendanceModel->search($group_by_employees, $branch_id, $employee_id, $date, $date_from, $date_to)) {
            $response = $this->failNotFound('No attendance found');
        } else {
            $total_minutes_per_employee = [];
            foreach($attendances as $key => $attendance) {
                if (!isset($total_minutes_per_employee[$attendance['employee_id']])) {
                    $total_minutes_per_employee[$attendance['employee_id']] = 0;
                } 

                $total_minutes_per_employee[$attendance['employee_id']] += (float)$attendance['total_minutes'];

                $attendances[$key]['attendance_entries'] = $this->attendanceEntryModel->get_details_by_attendance_id($attendance['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'total_minutes_per_employee' => $total_minutes_per_employee,
                'data'   => $attendances
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /** 
     * Login Employee
     */
    public function login_employee()
    {
        if (($response = $this->_api_verification('attendances', 'login_employee')) !== true)
            return $response;
            
        $username = $this->request->getVar('username') ? : null;
        $password = $this->request->getVar('password') ? : null;

        $employee = $this->employeeModel->get_details_by_username($username);
        
        if (!$employee)
            $response = $this->failNotFound('No employee found');
        elseif (!password_verify($password, $employee[0]->password))
            $response = $this->failNotFound('Invalid password');
        elseif (!$this->_attempt_record_attendance($employee[0]->id))
            $response = $this->fail($this->errorMessage);
        else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $employee
            ]);
        }
        
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record attendance (time in, time out)
     */
    public function record_attendance() {
        if (($response = $this->_api_verification('attendances', 'record_attendance')) !== true)
            return $response;
        
        $employee_id = $this->request->getVar('employee_id') ? : null;
        $type        = $this->request->getVar('type') ? : null;

        $employee = $this->employeeModel->get_details_by_id($employee_id);

        if (!$employee)
            $response = $this->failNotFound('No employee found');
        elseif (!$this->_attempt_record_attendance($employee_id, $type))
            $response = $this->fail($this->errorMessage);
        else {
            $branch_id = $this->userBranchModel->get_branches_by_user($this->requested_by);
            $branch_id = $branch_id ? $branch_id[0]['id'] : null;
            $user = $this->userModel->get_details_by_id($this->requested_by);
            $user = $user ? $user[0] : null;
            $branch_id = $branch_id ? $branch_id : $user['branch_id'];
            $date = date('Y-m-d');
            $attendance = $this->attendanceModel->search($branch_id, $employee_id, $date);
            if ($attendance) {
                $attendance = $attendance ? $attendance[0] : null;
                $attendance['attendance_entries'] = $this->attendanceEntryModel->get_details_by_attendance_id($attendance['id']);
            }
            $response = $this->respond([
                'status' => 'success',
                'data'   => $employee,
                'attendance' => $attendance,
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt delete
     */
    protected function _attempt_delete($attendance_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->attendanceModel->update($attendance_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt to record attendance
     */
    protected function _attempt_record_attendance($employee_id, $type = false)
    {
        $this->db = \Config\Database::connect();
        $this->db->transBegin();
        $this->db->transCommit();

        $branch_id = $this->userBranchModel->get_branches_by_user($this->requested_by);
        $branch_id = $branch_id ? $branch_id[0]['id'] : null;
        if (!$branch_id) {
            $user = $this->userModel->get_details_by_id($this->requested_by);
            $user = $user ? $user[0] : null;
            $branch_id = $user ? $user['branch_id'] : null;
        }

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
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->attendanceModel      = model('App\Models\Attendance');
        $this->attendanceEntryModel = model('App\Models\Attendance_entry');
        $this->employeeModel        = model('App\Models\Employee');
        $this->userModel            = model('App\Models\User');
        $this->userBranchModel      = model('App\Models\User_branch');
        $this->webappResponseModel  = model('App\Models\Webapp_response');
    }
}

<?php

namespace App\Controllers;
use DateTime;

class Login extends MYTController
{
    private $new_token;
    private $new_api_key;

    public function __construct()
    {
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->_load_essentials();
    }
    
    /**
     * login to website
     */
    public function index()
    {
        if (($response = $this->_api_verification('login', 'index')) !== true)
            return $response;

        return $this->_attempt_login();
    }

    protected function _attempt_login()
    {
        $username = $this->request->getVar('username');
        $password = $this->request->getVar('password');
        $user = $this->userModel->get_details_by_username($username);

        // if user doesn't exist
        if (!$user) {
            $response = $this->failUnauthorized('Unregistered user');
        } elseif ($user[0]->type == 'staff') {
            $response = $this->failUnauthorized('User does not have access to admin portal.');
        } elseif (!password_verify($password, $user[0]->password)) { // if password incorrect
            $response = $this->failUnauthorized('Incorrect Password');
        }  elseif (!$this->_update_security_keys($user[0]->id)) {
            $response = $this->failServerError('Server error. Please try again.');
        } else {
            unset($user->{'password'});
            $user[0]->{'api_key'} = $this->new_api_key;
            $user[0]->{'token'}   = $this->new_token;
            
            // check if user is a supervisor
            $has_daily_sale = false;
            
            if ($user[0]->type == 'supervisor') {
                $this->db = \Config\Database::connect();
                $this->db->transBegin();

                if (!$this->_attempt_record_attendance($user[0]->employee_id)) {
                    $this->db->transRollback();
                    $response = $this->fail($this->errorMessage);
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                } elseif (!$this->_attempt_record_attendance($user[0]->employee_id, 'time_in')) {
                    $this->db->transRollback();
                    $response = $this->fail($this->errorMessage);
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }

                $this->db->transCommit();

            } elseif ($user[0]->type == 'branch') {

                $where = ["branch_id" => $user[0]->branch_id, "date" => date("Y-m-d"), "is_deleted" => 0];
            }

            $response = $this->respond([
                'status' => 200,
                'message' => 'Login successful',
                'user' => $user[0]
            ]);
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
                return false;
            }
        } else {
            if (!$attendance) {
                $this->errorMessage = "Employee has no attendance";
                $this->db->transRollback();
                return false;
            }

            $attendance_entry = $this->attendanceEntryModel->get_latest_attendance_entry($attendance['id']);
            $attendance_entry = $attendance_entry ? $attendance_entry[0] : null;
            if (!$attendance_entry && $type == 'time_out') {
                $this->errorMessage = "Employee has no attendance entry, time in first.";
                $this->db->transRollback();
                return false;
            }

            switch($type) {
                case 'time_in':
                    // User needs to time_out first before time_in
                    if ($attendance_entry && !$attendance_entry['time_out']) {
                        $this->errorMessage = "User needs to time_out first before time_in";
                        $this->db->transRollback();
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
                        return false;
                    }

                    break;
                case 'time_out':                    
                    // If the user time_out already
                    if ($attendance_entry && $attendance_entry['time_out']) {
                        $this->errorMessage = "User has already time_out";
                        $this->db->transRollback();
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
                        return false;
                    }
                    break;
                default:
                    $this->db->transRollback();
                    $this->errorMessage = "Invalid type";
                    return false;
            }
        }

        $this->db->transCommit();
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
     * Update new api key and token
     */
    protected function _update_security_keys($user_id)
    {
        $this->new_token = $this->_generate_token(50);
        $this->new_api_key = $this->_generate_token(50);
        $current_datetime = date('Y-m-d H:i:s');
        $token_expiry = date("Y-m-d H:i:s", strtotime("$current_datetime +12 hours"));

        $values = [
            'token'      => $this->new_token,
            'token_expiry' => $token_expiry,
            'api_key'    => $this->new_api_key,
            'updated_by' => $user_id,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        if (!$this->userModel->update($user_id, $values))
            return false;
        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->attendanceModel        = model('App\Models\Attendance');
        $this->attendanceEntryModel   = model('App\Models\Attendance_entry');
        $this->employeeModel          = model('App\Models\Employee');
        $this->userModel              = model('App\Models\User');
        $this->branchModel            = model('App\Models\Branch');
        $this->discountModel          = model('App\Models\Discount');
        $this->userBranchModel        = model('App\Models\User_branch');
        $this->dailySaleModel         = model('App\Models\Daily_sale');
        $this->webappResponseModel    = model('App\Models\Webapp_response');
    }
}

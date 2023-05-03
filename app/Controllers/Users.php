<?php

namespace App\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Models\User_assignment;
use App\Models\User_branch;
use App\Models\Webapp_response;

class Users extends MYTController
{
    
    public function __construct()
    {
        // Headers for API
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->pin = null;
       
        $this->_load_essentials();
    }

    /**
     * Get users
     */
    public function get_user()
    {
        if (($response = $this->_api_verification('users', 'get_user')) !== true)
            return $response;

        $pin = $this->request->getVar('pin') ? : null;
        $user = $pin ? $this->userModel->get_details_by_pin($pin) : null;

        if (!$user) {
            $response = $this->failNotFound('No user found');
        } else {
            $response = $this->respond([
                'data'   => $user,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get users
     */
    public function get_all_user()
    {
        if (($response = $this->_api_verification('users', 'get_all_user')) !== true)
            return $response;

        $users = $this->userModel->get_all_users();

        if (!$users) {
            $response = $this->failNotFound('No user found');
        } else {
            $response = $this->respond([
                'data'   => $users,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create user
     */
    public function create()
    {
        if ($response = $this->_api_verification('users', 'create') !== true)
            return $response;

        $username = $this->request->getVar('username');
        if ($response = $this->_is_existing($this->userModel, ['username' => $username]))
            return $response;
  
        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$user_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response' => 'User created successfully', 
                'status' => 'success', 
                'user_id' => $user_id,
                'user_pin' => $this->pin
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update user
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('users', 'update')) !== true)
            return $response;

        $pin = $this->request->getVar('pin');
        $where = ['pin' => $pin, 'is_deleted' => 0];
        
        if (!$user = $this->userModel->select('', $where, 1))
            $response = $this->failNotFound('User not found');
        elseif ($this->userModel->select('', ['username' => $this->request->getVar('username'), 'pin !=' => $pin], 1))
            $response = $this->fail('Username already exists');
        elseif (!$this->_attempt_update($user))
            $response = $this->fail($this->errorMessage);
        else
            $response = $this->respond(['response' => 'User updated successfully']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Change password
     */
    public function change_password($id = null)
    {
        if (($response = $this->_api_verification('users', 'change_password')) !== true)
            return $response;

        $user_id = $this->request->getVar('user_id') ? : null;
        $user_id = isset($user_id) ? $user_id : $this->requested_by;

        $where = ['id' => $user_id, 'is_deleted' => 0];
        $values = [
            'password'       => password_hash($this->request->getVar('password'), PASSWORD_BCRYPT),
            'password_reset' => date('Y-m-d H:i:s'),
            'updated_by'     => $this->requested_by,
            'updated_on'     => date('Y-m-d H:i:s')
        ];

        if (!$user = $this->userModel->select('', $where, 1))
            $response = $this->failNotFound('User not found');
        elseif (!$this->userModel->update($user_id, $values))
            $response = $this->fail('Server error');
        else
            $response = $this->respond(['response' => 'Password changed successfully']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete users
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('users', 'delete')) !== true)
            return $response;

        $pin = $this->request->getVar('pin');

        $where = ['pin' => $pin, 'is_deleted' => 0];

        if (!$user = $this->userModel->select('', $where, 1)) {
            $response = $this->failNotFound('User not found');
        } elseif (!$this->_attempt_delete($user)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'User deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search users based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('users', 'search')) !== true)
            return $response;

        $status = $this->request->getVar('status') ? : null;
        $branch_id = $this->request->getVar('branch_id') ? : null;
        $name = $this->request->getVar('name') ? : null;

        if (!$users = $this->userModel->search($name, $status, $branch_id)) {
            $response = $this->failNotFound('No user found');
        } else {
            $response = $this->respond([
                'data'   => $users,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create user
     */
    private function _attempt_create()
    {
        $bytes = random_bytes(5);
        $random_pin = bin2hex($bytes);
        $this->pin = $this->request->getVar('pin') ? : $random_pin;
        $type = $this->request->getVar('type');

        $values = [
            'employee_id' => $this->request->getVar('employee_id'),
            'pin'         => $this->pin,
            'username'    => $this->request->getVar('username'),
            'password'    => password_hash($this->request->getVar('password'), PASSWORD_BCRYPT),
            'last_name'   => $this->request->getVar('last_name'),
            'first_name'  => $this->request->getVar('first_name'),
            'middle_name' => $this->request->getVar('middle_name') ? : null,
            'email'       => $this->request->getVar('email') ? : null,
            'type'        => $this->request->getVar('type'),
            'branch_id'   => $this->request->getVar('branch_id') ? : null,
            'status'      => 'active',
            'added_by'    => $this->requested_by,
            'added_on'    => date('Y-m-d H:i:s'),
            'is_deleted'  => 0
        ];

  
        if (!$user_id = $this->userModel->insert($values) OR
            ($type != 'branch' AND !$this->_insert_user_assignment($user_id))
        ) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $user_id;
    }

    /**
     * User assignment history
     */
    public function _insert_user_assignment($user_id)
    {
        $values = [
            'employee_id' => $this->request->getVar('employee_id'),
            'user_id' => $user_id,
            'assigned_on' => date("Y-m-d H:i:s")
        ];

        return (!$this->userAssignmentModel->insert($values)) ? false : true;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($user)
    {
        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        $where = ['id' => $user['id'], 'is_deleted' => 0];
        $password = $this->request->getVar('password');
        
        $values = [
            'employee_id' => $this->request->getVar('employee_id'),
            'username'    => $this->request->getVar('username') ? : $user['username'],
            'password'    => $password ? password_hash($this->request->getVar('password'), PASSWORD_BCRYPT) : $user['password'],
            'last_name'   => $this->request->getVar('last_name') ? : $user['last_name'],
            'first_name'  => $this->request->getVar('first_name') ? : $user['first_name'],
            'middle_name' => $this->request->getVar('middle_name') ? : $user['middle_name'],
            'email'       => $this->request->getVar('email') ? : $user['email'],
            'type'        => $this->request->getVar('type') ? : $user['type'],
            'status'      => $this->request->getVar('status') ? : $user['status'],
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        if ($password) {
            $values['password_reset'] = date('Y-m-d H:i:s');
        }

        if (!$this->userModel->update($where, $values) OR
            !$this->_update_user_assignment($user['id'])
        ) {
            $this->errorMessage = $this->db->error()['message'];
            $this->db->transRollback();
            return false;
        }

        $this->db->transCommit();
        $this->db->close();
        return true;
    }

    /**
     * User assignment history
     */
    public function _update_user_assignment($user_id)
    {
        $current_datetime = date("Y-m-d H:i:s");
        $previous_user_assignment = $this->userAssignmentModel->select('', ['user_id' => $user_id], 1, 'id DESC');
        $end_values = ['ended_on' => $current_datetime];

        if (!$this->userAssignmentModel->update($previous_user_assignment['id'], $end_values)) return false;

        $values = [
            'employee_id' => $this->request->getVar('employee_id'),
            'user_id' => $user_id,
            'assigned_on' => $current_datetime
        ];

        return (!$this->userAssignmentModel->insert($values)) ? false : true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($user)
    {
        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        $where = ['id' => $user['id'], 'is_deleted' => 0];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->userModel->update($where, $values)) {
            $this->db->transRollback();
            return false;
        }

        $this->db->transCommit();
        $this->db->close();

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->branchModel         = new Branch();
        $this->userModel           = new User();
        $this->userAssignmentModel = new User_assignment();
        $this->webappResponseModel = new Webapp_response();
    }
}

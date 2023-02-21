<?php

namespace App\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Models\User_branch;
use App\Models\Webapp_response;

class Users extends MYTController
{
    
    public function __construct()
    {
        // Headers for API
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];
       
        $this->_load_essentials();
    }

    /**
     * Get users
     */
    public function get_user()
    {
        if (($response = $this->_api_verification('users', 'get_user')) !== true)
            return $response;

        $user_id = $this->request->getVar('user_id') ? : null;
        $user = $user_id ? $this->userModel->get_details_by_id($user_id) : null;

        if (!$user) {
            $response = $this->failNotFound('No user found');
        } else {
            $user[0]['branches'] = $this->userBranchModel->get_branches_by_user($user_id);
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
            $users   = $this->_get_all_branches($users);
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
        } elseif (!$this->_register_user_branches($user_id, true)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response' => 'User created successfully', 
                'status' => 'success', 
                'user_id' => $user_id
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

        $user_id = $this->request->getVar('user_id');
        $where = ['id' => $user_id, 'is_deleted' => 0];
        
        if (!$user = $this->userModel->select('', $where, 1))
            $response = $this->failNotFound('User not found');
        elseif ($this->userModel->select('', ['username' => $this->request->getVar('username'), 'id !=' => $user_id], 1))
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

        $user_id = $this->request->getVar('user_id');

        $where = ['id' => $user_id, 'is_deleted' => 0];

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
            $users  = $this->_get_all_branches($users);
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
        $values = [
            'employee_id' => $this->request->getVar('employee_id'),
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

  
        if (!$user_id = $this->userModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $user_id;
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

        $delete_branches = $this->request->getVar('branch_ids') ? : false;
        if (!$this->userModel->update($where, $values) OR
            !$this->_delete_user_branches($user) OR
            !$this->_register_user_branches($user['id'], $delete_branches)
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
     * Register user to assigned branches
     */
    protected function _register_user_branches($user_id, $delete_branches)
    {
        if (!$delete_branches)
            return true;
        
        $branch_ids = $this->request->getVar('branch_ids');

        if ($branch_ids == 'all') {
            $branches = $this->branchModel->select('', ['is_deleted' => 0]);
            $branch_ids = array_map(function($branch) {
                return $branch['id'];
            }, $branches);
        } else {
            $branch_ids = explode(',', $branch_ids);
        }

        foreach($branch_ids as $branch_id) {
            $values = [
                'user_id'    => $user_id,
                'branch_id'  => $branch_id,
                'added_by'   => $this->requested_by,
                'added_on'   => date('Y-m-d H:i:s')
            ];

            if (!$this->userBranchModel->insert_on_duplicate($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }
        return true;
    }

    /**
     * Delete user branches
     */
    protected function _delete_user_branches($user)
    {
        if (!$this->userBranchModel->_attempt_delete_by_user_id($user['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
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

        if (!$this->userModel->update($where, $values) OR
            !$this->_delete_user_branches($user)
        ) {
            $this->db->transRollback();
            return false;
        }

        $this->db->transCommit();
        $this->db->close();

        return true;
    }

    /**
     * Get all allowed branches per user
     */
    protected function _get_all_branches($users)
    {
        $new_users = [];
        foreach($users as $user) {
            $user['branches'] = $this->userBranchModel->get_branches_by_user($user['id']);
            $new_users[]      = $user;
        }
        return $new_users;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->branchModel         = new Branch();
        $this->userModel           = new User();
        $this->userBranchModel     = new User_branch();
        $this->webappResponseModel = new Webapp_response();
    }
}

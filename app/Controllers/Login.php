<?php

namespace App\Controllers;

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

            // Update the branch that user has logged in to be open
            $branch = $this->userBranchModel->get_branches_by_user($user[0]->id);
            $branch_id = $branch ? $branch[0]['id'] : $user[0]->branch_id;
            $branch_details = $branch_id ? $this->branchModel->get_details_by_id($branch_id) : null;
            
            // check if user is a supervisor
            $branch_groups = null;
            if ($user[0]->type == 'supervisor') {
                $branch_groups = $this->branchGroupModel->search(null, null, $user[0]->id, null, null);
                foreach ($branch_groups as $key => $branch_group) {
                    $branch_group_id = $branch_group['id'];
                    $branch_group_details = $this->branchGroupDetailModel->get_details_by_branch_group_id($branch_group_id);
                    $branch_groups[$key]['branch_group_details'] = $branch_group_details;
                }
            }

            $operation_log_id = null;
            if ($user[0]->type == 'branch') {

                $branch_operation_log_data = [
                    'branch_id' => $branch_id,
                    'user_id' => $user[0]->id,
                    'time_in' => date("Y-m-d H:i:s")
                ];

                if (!$this->operationLogModel->insert($branch_operation_log_data)) {
                    $response = $this->fail('Something went wrong');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }

                $operation_log_id = $this->operationLogModel->insertID();
            }

            $response = $this->respond([
                'status' => 200,
                'message' => 'Login successful',
                'user' => $user[0],
                'branch' => $branch,
                'branch_group' => $branch_groups,
            ]);

            if ($branch_id && $branch_details[0]['is_open'] == 0) {
            
                $values = [
                    'is_open'    => 1,
                    'operation_log_id' => $operation_log_id,
                    'opened_on'  => date('Y-m-d H:i:s'),
                    'updated_on' => date('Y-m-d H:i:s'),
                    'updated_by' => $user[0]->id
                ];

                if (!$this->branchModel->update($branch_id, $values)) {
                    $response = $this->fail('Something went wrong');
                    $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                    return $response;
                }
            }
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Methods
    // ------------------------------------------------------------------------

    /**
     * Update new api key and token
     */
    protected function _update_security_keys($user_id)
    {
        $this->new_token = $this->_generate_token(50);
        $this->new_api_key = $this->_generate_token(50);

        $values = [
            'token'      => $this->new_token,
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
        $this->userModel              = model('App\Models\User');
        $this->branchModel            = model('App\Models\Branch');
        $this->userBranchModel        = model('App\Models\User_branch');
        $this->branchGroupModel       = model('App\Models\Branch_group');
        $this->branchGroupDetailModel = model('App\Models\Branch_group_detail');
        $this->operationLogModel      = model('App\Models\Branch_operation_log');
        $this->webappResponseModel    = model('App\Models\Webapp_response');
    }
}

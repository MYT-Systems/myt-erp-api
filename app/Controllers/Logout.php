<?php

namespace App\Controllers;

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
        
        if ($branch_id && $branch_details[0]['is_open'] == 1) {
            $values = [
                'is_open' => 0,
                'operation_log_id' => null,
                'closed_on'  => date('Y-m-d H:i:s'),
                'updated_on' => date('Y-m-d H:i:s'),
                'updated_by' => $user[0]['id']
            ];

            $operation_log_data = [
                'timeout' => date("Y-m-d H:i:s"),
                'is_automatic_logout' => 0
            ];

            if (!$this->branchModel->update($branch_id, $values) OR
                !$this->operationLogModel->update($branch_details[0]['operation_log_id'], $operation_log_data)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Load essentials
     */
    protected function _load_essentials()
    {
        $this->userModel   = new User();
        $this->branchModel = new Branch();
        $this->userBranchModel = new User_branch();
        $this->operationLogModel = model('App\Models\Branch_operation_log');
        $this->webappResponseModel = new Webapp_response();
    }
}

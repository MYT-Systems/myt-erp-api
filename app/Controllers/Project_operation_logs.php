<?php

namespace App\Controllers;

class Project_operation_logs extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get all project operation logs
     */
    public function get_all()
    {
        if (($response = $this->_api_verification('project_operation_logs', 'get_all')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $project_type = $this->request->getVar('project_type') ? : null;
        $user_id = $this->request->getVar('user_id') ? : null;
        $project_id = $this->request->getVar('project_id') ? : null;
        $project_name = $this->request->getVar('project_name') ? : null;
        $date = $this->request->getVar('date') ? : null;
        $status = $this->request->getVar('status') ? : null;
        $date = $date ? date('Y-m-d', strtotime($date)) : null;

        if (!$operation_logs = $this->projectModel->get_project_operations($project_type, $user_id, $project_id, $project_name, $status, $date)) {
            $response = $this->failNotFound('No project operation logs found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $operation_logs
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->operationLogModel   = model('App\Models\Project_operation_log');
        $this->projectModel         = model('App\Models\Project');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}

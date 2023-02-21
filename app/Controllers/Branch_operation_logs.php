<?php

namespace App\Controllers;

class Branch_operation_logs extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get all branch operation logs
     */
    public function get_all()
    {
        if (($response = $this->_api_verification('branch_operation_logs', 'get_all')) !== true)
            return $response;

        $branch_type = $this->request->getVar('branch_type') ? : null;
        $user_id = $this->request->getVar('user_id') ? : null;
        $branch_id = $this->request->getVar('branch_id') ? : null;
        $date = $this->request->getVar('date') ? : null;
        $date = $date ? date('Y-m-d', strtotime($date)) : null;

        if (!$operation_logs = $this->operationLogModel->get_all($branch_type, $user_id, $branch_id, $date)) {
            $response = $this->failNotFound('No branch operation logs found');
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
        $this->operationLogModel   = model('App\Models\Branch_operation_log');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}

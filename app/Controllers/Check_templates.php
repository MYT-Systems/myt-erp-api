<?php

namespace App\Controllers;

use App\Models\Check_template;
use App\Models\Webapp_response;

class Check_templates extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get check_template
     */
    public function get_check_template()
    {
        if (($response = $this->_api_verification('check_templates', 'get_check_template')) !== true)
            return $response;

        $check_template_id = $this->request->getVar('check_template_id') ? : null;
        $check_template    = $check_template_id ? $this->check_templateModel->get_details_by_id($check_template_id) : null;

        if (!$check_template) {
            $response = $this->failNotFound('No check_template found');
        } else {
            $response = $this->respond([
                'data'   => $check_template,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all check_templates
     */
    public function get_all_check_template()
    {
        if (($response = $this->_api_verification('check_templates', 'get_all_check_template')) !== true)
            return $response;

        $check_templates = $this->check_templateModel->get_all_check_template();

        if (!$check_templates) {
            $response = $this->failNotFound('No check_template found');
        } else {
            $response = $this->respond([
                'data'   => $check_templates,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create check_template
     */
    public function create()
    {
        if (($response = $this->_api_verification('check_templates', 'create')) !== true)
            return $response;

        $where = ['name' => $this->request->getVar('name')];
        if ($this->check_templateModel->select('', $where, 1)) {
            $response = $this->fail('check_template already exists.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }
        
        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$check_template_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create check template.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Check template created successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update check_template
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('check_templates', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('check_template_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$check_template = $this->check_templateModel->select('', $where, 1)) {
            $response = $this->failNotFound('check template not found');
        } elseif (!$this->_attempt_update($check_template['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update check template.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Check template updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete check_templates
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('check_templates', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('check_template_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$check_template = $this->check_templateModel->select('', $where, 1)) {
            $response = $this->failNotFound('check_template not found');
        } elseif (!$this->_attempt_delete($check_template['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete check template.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Check template deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search check_templates based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('check_templates', 'search')) !== true)
            return $response;

        $name      = $this->request->getVar('name');
        $file_name = $this->request->getVar('file_name');

        if (!$check_templates = $this->check_templateModel->search($name, $file_name)) {
            $response = $this->failNotFound('No check_template found');
        } else {
            $response = [];
            $response['data'] = $check_templates;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create check template
     *
     * @return int|bool
     */
    
    protected function _attempt_create()
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'file_name'      => $this->request->getVar('file_name'),
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s')
        ];

        if (!$check_template_id = $this->check_templateModel->insert($values))
            return false;

        return $check_template_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($check_template_id)
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'file_name'  => $this->request->getVar('file_name'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->check_templateModel->update($check_template_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($check_template_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->check_templateModel->update($check_template_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->check_templateModel = new Check_template();
        $this->webappResponseModel = new Webapp_response();
    }
}

<?php

namespace App\Controllers;

use App\Models\Bank;
use App\Models\Check_template;
use App\Models\Webapp_response;

class Banks extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get bank
     */
    public function get_bank()
    {
        if (($response = $this->_api_verification('banks', 'get_bank')) !== true)
            return $response;

        $bank_id = $this->request->getVar('bank_id') ? : null;
        $bank    = $bank_id ? $this->bankModel->get_details_by_id($bank_id) : null;

        if (!$bank) {
            $response = $this->failNotFound('No bank found');
        } else {
            $response = $this->respond([
                'data'   => $bank,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all banks
     */
    public function get_all_bank()
    {
        if (($response = $this->_api_verification('banks', 'get_all_bank')) !== true)
            return $response;

        $banks = $this->bankModel->get_all_bank();

        if (!$banks) {
            $response = $this->failNotFound('No bank found');
        } else {
            $response = $this->respond([
                'data'   => $banks,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create bank
     */
    public function create()
    {
        if (($response = $this->_api_verification('banks', 'create')) !== true)
            return $response;

        $name = $this->request->getVar('name');
        if ($response = $this->_is_existing($this->bankModel, ['name' => $name]))
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create bank.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response' => 'Bank created successfully.',
                'status'   => 'success',
                'bank_id'  => $bank_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update bank
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('banks', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('bank_id'),
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank = $this->bankModel->select('', $where, 1)) {
            $response = $this->failNotFound('bank not found');
        } elseif (!$this->_attempt_update($bank['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update bank.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Bank updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete banks
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('banks', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('bank_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank = $this->bankModel->select('', $where, 1)) {
            $response = $this->failNotFound('bank not found');
        } elseif (!$this->_attempt_delete($bank['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete bank.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Bank deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search banks based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('banks', 'search')) !== true)
            return $response;

        $name          = $this->request->getVar('name');
        $template_name = $this->request->getVar('template_name');

        if (!$banks = $this->bankModel->search($name, $template_name)) {
            $response = $this->failNotFound('No bank found');
        } else {
            $response = [];
            $response['data'] = $banks;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions 
    // ------------------------------------------------------------------------

    /**
     * Attempt create bank
     */
    protected function _attempt_create()
    {
        $values = [
            'name'              => $this->request->getVar('name'),
            'account_name'      => $this->request->getVar('account_name'),
            'account_no'        => $this->request->getVar('account_no'),
            'check_template_id' => $this->request->getVar('check_template_id'),
            'added_by'          => $this->requested_by,
            'added_on'          => date('Y-m-d H:i:s'),
            'is_deleted'        => 0
        ];

        if (!$bank_id = $this->bankModel->insert($values))
            return false;

        return $bank_id;
    }
    /**
     * Attempt update
     */
    protected function _attempt_update($bank_id)
    {
        $values = [
            'name'              => $this->request->getVar('name'),
            'account_name'      => $this->request->getVar('account_name'),
            'account_no'        => $this->request->getVar('account_no'),
            'check_template_id' => $this->request->getVar('check_template_id'),
            'updated_by'        => $this->requested_by,
            'updated_on'        => date('Y-m-d H:i:s')
        ];

        if (!$this->bankModel->update($bank_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($bank_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankModel->update($bank_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->bankModel           = new Bank();
        $this->checkTemplateModel  = new Check_template();
        $this->webappResponseModel = new Webapp_response();
    }
}

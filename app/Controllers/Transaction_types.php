<?php

namespace App\Controllers;

use App\Models\Transaction_type;
use App\Models\Webapp_response;

class Transaction_types extends MYTController
{
    
    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get transaction_type
     */
    public function get_transaction_type()
    {
        if (($response = $this->_api_verification('transaction_types', 'get_transaction_type')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $transaction_type_id = $this->request->getVar('transaction_type_id') ? : null;
        $transaction_type    = $transaction_type_id ? $this->transactionTypeModel->get_details_by_id($transaction_type_id) : null;

        if (!$transaction_type) {
            $response = $this->failNotFound('No transaction_type found');
        } else {
            $response           = [];
            $response['data']   = $transaction_type;
            $response['status'] = 'success';
            $response           = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all transaction_types
     */
    public function get_all_transaction_type()
    {
        if (($response = $this->_api_verification('transaction_types', 'get_all_transaction_type')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $transaction_types = $this->transactionTypeModel->get_all_transaction_type();

        if (!$transaction_types) {
            $response = $this->failNotFound('No transaction_type found');
        } else {
            $response           = [];
            $response['data']   = $transaction_types;
            $response['status'] = 'success';
            $response           = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create transaction_type
     */
    public function create()
    {
        if (($response = $this->_api_verification('transaction_types', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = ['name' => $this->request->getVar('name')];
        if ($this->transactionTypeModel->select('', $where, 1)) {
            $response = $this->fail('transaction_type already exists.');
        } else {
            $values = [
                'name'     => $this->request->getVar('name'),
                'added_by' => $this->requested_by,
                'added_on' => date('Y-m-d H:i:s'),
            ];

            $db = \Config\Database::connect();
            $db->transBegin();

            if (!$this->transactionTypeModel->insert($values)) {
                $db->transRollback();
                $response = $this->fail('Server error');
            } else {
                $db->transCommit();
                $response = $this->respond(['response' => 'transaction_type created successfully']);
            }
            
            $db->close();
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update transaction_type
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('transaction_types', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $transaction_type_id = $this->request->getVar('transaction_type_id');
        $where = ['id' => $transaction_type_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$transaction_type = $this->transactionTypeModel->select('', $where, 1)) {
            $response = $this->failNotFound('transaction_type not found');
        } elseif (!$this->_attempt_update($transaction_type_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Fail to update transaction_type', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'transaction_type updated successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete transaction_types
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('transaction_types', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $transaction_type_id = $this->request->getVar('transaction_type_id');

        $where = ['id' => $transaction_type_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$transaction_type = $this->transactionTypeModel->select('', $where, 1)) {
            $response = $this->failNotFound('transaction_type not found');
        } elseif (!$this->_attempt_delete($transaction_type_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Fail to delete transaction_type', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'transaction_type deleted successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search transaction_types based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('transaction_types', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $name          = $this->request->getVar('name');

        if (!$transaction_types = $this->transactionTypeModel->search($name)) {
            $response = $this->failNotFound('No transaction_type found');
        } else {
            $response = [];
            $response['data'] = $transaction_types;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt update
     */
    protected function _attempt_update($transaction_type_id)
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->transactionTypeModel->update($transaction_type_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($transaction_type_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->transactionTypeModel->update($transaction_type_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->transactionTypeModel = new Transaction_type();
        $this->webappResponseModel  = new Webapp_response();
    }
}

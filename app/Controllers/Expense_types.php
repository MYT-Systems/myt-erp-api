<?php

namespace App\Controllers;

class Expense_types extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get forwarder
     */
    public function get_expense_types()
    {
        if (($response = $this->_api_verification('expense_types', 'get_expense_types')) !== true)
            return $response; 

        $expense_types_id = $this->request->getVar('expense_type_id') ? : null;
        $expense_types    = $expense_types ? $this->expenseTypeModel->get_details_by_id($expense_types_id) : null;

        if (!$expense_types) {
            $response = $this->failNotFound('No forwarder found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $expense_types
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all expense_types
     */
    public function get_all_expense_type()
    {
        if (($response = $this->_api_verification('expense_types', 'get_all_expense_type')) !== true)
            return $response;

        $expense_types = $this->expenseTypeModel->get_all_expense_type();

        if (!$expense_types) {
            $response = $this->failNotFound('No forwarder found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $expense_types
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create expense_type
     */
    public function create()
    {
        if (($response = $this->_api_verification('expense_types', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$expense_types_id= $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create forwarder.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'     => 'Forwarder created successfully.',
                'status'       => 'success',
                'id' => $expense_types_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update expense_types
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('expense_types', 'update')) !== true)
            return $response;

        $expense_type_id = $this->request->getVar('expense_type_id');
        $where = ['id' => $expense_type_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$expense_types = $this->expenseTypeModel->select('', $where, 1)) {
            $response = $this->failNotFound('Expense Type not found');
        } elseif (!$this->_attempt_update($expense_type_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update expense type.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Expense Type updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete expense_types
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('forwarders', 'delete')) !== true)
            return $response;

        $expense_type_id = $this->request->getVar('expense_type_id');

        $where = ['id' => $expense_type_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$expense_types = $this->expenseTypeModel->select('', $where, 1)) {
            $response = $this->failNotFound('Expense Type not found');
        } elseif (!$this->_attempt_delete($expense_type_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Fail to delete expense type.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Expense Type deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search forwarders based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('expense_type', 'search')) !== true)
            return $response;

        $name     = $this->request->getVar('name');
        $description  = $this->request->getVar('description');
        if (!$expense_types = $this->expenseTypeModel->search($name, $description)) {
            $response = $this->failNotFound('No expense found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $expense_types
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create expense_types
     */
    protected function _attempt_create()
    {
        $values = [
            'name'        => $this->request->getVar('name'),
            'description' => $this->request->getVar('description'),            
            'added_by'    => $this->requested_by,
            'added_on'    => date('Y-m-d H:i:s')
        ];

        if (!$expense_types_id = $this->expenseTypeModel->insert($values))
            return false;

        return $expense_types_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($id)
    {
        $values = [
            'name'          => $this->request->getVar('name'),
            'description'   => $this->request->getVar('description'),
            'phone_no'      => $this->request->getVar('phone_no'),
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s')
        ];

        if (!$this->expenseTypeModel->update($id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->expenseTypeModel->update($id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->expenseTypeModel = model('App\Models\Expense_type');
        $this->webappResponseModel  = model('App\Models\Webapp_response');

    }
}

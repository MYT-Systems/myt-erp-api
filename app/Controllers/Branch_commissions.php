<?php

namespace App\Controllers;

use App\Models\Branch_commission;
use App\Models\Webapp_response;

class Branch_commissions extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get branch_commission
     */
    public function get_branch_commission()
    {
        if (($response = $this->_api_verification('branch_commissions', 'get_branch_commission')) !== true)
            return $response;

        $branch_commission_id = $this->request->getVar('branch_commission_id') ? : null;
        $branch_commission    = $branch_commission_id ? $this->branchCommissionModel->get_details_by_id($branch_commission_id) : null;

        if (!$branch_commission) {
            $response = $this->failNotFound('No branch_commission found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $branch_commission
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all branch_commissions
     */
    public function get_all_branch_commission()
    {
        if (($response = $this->_api_verification('branch_commissions', 'get_all_branch_commission')) !== true)
            return $response;

        $branch_commissions = $this->branchCommissionModel->get_all_branch_commission();

        if (!$branch_commissions) {
            $response = $this->failNotFound('No branch_commission found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $branch_commissions
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create branch_commission
     */
    public function create()
    {
        if (($response = $this->_api_verification('branch_commissions', 'create')) !== true)
            return $response;

        $where = [
            'transaction_type_id' => $this->request->getVar('transaction_type_id') ? : null,
            'branch_id'           => $this->request->getVar('branch_id') ? : null,
        ];

        if ($this->branchCommissionModel->select('', $where, 1)) {
            $response = $this->fail('branch commission already exists.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$branch_commission_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['message' => 'Failed to create branch_commission.','status' => 'error',]);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'status'              => 'success',
                'response'            => 'Branch commission created successfully.',
                'brand_commission_id' => $branch_commission_id,
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update branch_commission
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('branch_commissions', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('branch_commission_id'),
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$branch_commission = $this->branchCommissionModel->select('', $where, 1)) {
            $response = $this->failNotFound('branch commission not found');
        } elseif (!$this->_attempt_update($branch_commission['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update branch commission.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Branch commission updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete branch_commissions
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('branch_commissions', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('branch_commission_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$branch_commission = $this->branchCommissionModel->select('', $where, 1)) {
            $response = $this->failNotFound('branch commission not found');
        } elseif (!$this->_attempt_delete($branch_commission['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete branch commission.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Branch commission deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search branch_commissions based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('branch_commissions', 'search')) !== true)
            return $response;

        $transaction_type_id = $this->request->getVar('transaction_type_id') ? : null;
        $branch_id           = $this->request->getVar('branch_id') ? : null;
        $commission          = $this->request->getVar('commission') ? : null;

        if (!$branch_commissions = $this->branchCommissionModel->search($transaction_type_id, $branch_id, $commission)) {
            $response = $this->failNotFound('No branch_commission found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $branch_commissions
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create branch_commission
     */
    protected function _attempt_create()
    {
        $values = [
            'transaction_type_id' => $this->request->getVar('transaction_type_id'),
            'branch_id'           => $this->request->getVar('branch_id'),
            'commission'          => $this->request->getVar('commission'),
            'added_by'            => $this->requested_by,
            'added_on'            => date('Y-m-d H:i:s'),
        ];

        if (!$branch_commission_id = $this->branchCommissionModel->insert($values))
            return false;
        
        return $branch_commission_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($branch_commission_id)
    {
        $values = [
            'transaction_type_id' => $this->request->getVar('transaction_type_id'),
            'branch_id'           => $this->request->getVar('branch_id'),
            'commission'          => $this->request->getVar('commission'),
            'updated_by'          => $this->requested_by,
            'updated_on'          => date('Y-m-d H:i:s')
        ];

        if (!$this->branchCommissionModel->update($branch_commission_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($branch_commission_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->branchCommissionModel->update($branch_commission_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->branchCommissionModel = new Branch_commission();
        $this->webappResponseModel   = new Webapp_response();
    }
}

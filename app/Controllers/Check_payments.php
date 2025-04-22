<?php

namespace App\Controllers;

use App\Models\Check_entry;
use App\Models\Check_slip;
use App\Models\Receive;
use App\Models\Webapp_response;

class Check_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get check_entry
     */
    public function get_entry()
    {
        if (($response = $this->_api_verification('check_payments', 'get_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $check_entry_id = $this->request->getVar('entry_id') ? : null;
        $check_entry    = $check_entry_id ? $this->checkEntryModel->get_details_by_id($check_entry_id) : null;
        $check_slip     = $check_entry ? $this->checkSlipModel->get_details_by_id($check_entry[0]['id']) : null;

        if (!$check_entry) {
            $response = $this->failNotFound('No check invoice found');
        } else {
            $check_entry[0]['check_slip'] = $check_slip ? $check_slip : [];
            $response = $this->respond([
                'data'   => $check_entry,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get check_entry
     */
    public function get_slip()
    {
        if (($response = $this->_api_verification('check_payments', 'get_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $check_slip_id = $this->request->getVar('slip_id') ? : null;
        $check_slip    = $check_slip_id ? $this->checkSlipModel->get_details_by_id($check_slip_id) : null;
        $check_entries = $check_slip ? $this->checkEntryModel->get_details_by_slip_id($check_slip[0]['id']) : null;

        if (!$check_slip) {
            $response = $this->failNotFound('No check invoice found');
        } else {
            $check_slip[0]['check_entries'] = $check_entries ? $check_entries : [];
            $response = $this->respond([
                'data'   => $check_slip,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all check_entry
     */
    public function get_all_entry()
    {
        if (($response = $this->_api_verification('check_payments', 'get_all_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $check_entries = $this->checkEntryModel->get_all_entry();

        if (!$check_entries) {
            $response = $this->failNotFound('No check_entry found');
        } else {
            foreach($check_entries as $key => $check_entry) {
                $check_slip = $this->checkSlipModel->get_details_by_id($check_entry['check_slip_id']);
                $check_entries[$key]['check_slip'] = $check_slip ? $check_slip : [];
            }

            $response = $this->respond([
                'data'   => $check_entries,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all check_entry
     */
    public function get_all_slip()
    {
        if (($response = $this->_api_verification('check_payments', 'get_all_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $check_slips = $this->checkSlipModel->get_all_slip();

        if (!$check_slips) {
            $response = $this->failNotFound('No check_entry found');
        } else {
            foreach($check_slips as $key => $check_slip) {
                $check_entries = $this->checkEntryModel->get_details_by_slip_id($check_slip['id']);
                $check_slips[$key]['check_entries'] = $check_entries ? $check_entries : [];
            }

            $response = $this->respond([
                'data'   => $check_slips,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create check_entry
     */
    public function create()
    {
        if (($response = $this->_api_verification('check_payements', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();
        
        if ($this->checkSlipModel->is_check_no_used($this->request->getVar('check_no'))) {
            $response = $this->fail(['response' => 'Check number is used already.', 'status' => 'error']);
        } elseif (!$check_slip_id = $this->_attempt_create_slip()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create slip.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_entry($check_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate check entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully created slip.', 'status' => 'success', 'slip_id' => $check_slip_id]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update check slip and check_entry
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('check_payements', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('check_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$check_slip = $this->checkSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('check slip not found');
        } elseif (!$this->_attempt_update_slip($check_slip['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update slip.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_entry($check_slip['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update check entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Check slip and check entry successfully updated.', 'status' => 'success']);
        }   

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete check_entry
     */
    public function delete_entry($id = '')
    {
        if (($response = $this->_api_verification('check_payements', 'delete_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $check_entry_id = $this->request->getVar('check_entry_id');

        $where = ['id' => $check_entry_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$check_entry = $this->checkEntryModel->select('', $where, 1)) {
            $response = $this->failNotFound('check_entry not found');
        } elseif (!$this->_attempt_delete_entry($check_entry)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete check_entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted check_entry.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete check slip
     */
    public function delete_slip($id = '')
    {
        if (($response = $this->_api_verification('check_payements', 'delete_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('check_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$check_slip = $this->checkSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('check slip not found');
        } elseif (!$this->_attempt_delete_slip($check_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete check_slip.', 'status' => 'error']);
        } elseif (!$this->checkEntryModel->delete_by_slip_id($check_slip['id'], $this->requested_by)){
            $db->transCommit();
            $response = $this->fail(['response' => 'Successfully deleted check slip.', 'status' => 'success']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted check slip.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search check_entry based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('check_payments', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_id     = $this->request->getVar('bank_id') ?? null;
        $check_no    = $this->request->getVar('check_no') ?? null;
        $check_date  = $this->request->getVar('check_date') ?? null;
        $amount      = $this->request->getVar('amount') ?? null;
        $supplier_id = $this->request->getVar('supplier_id') ?? null;
        $vendor_id   = $this->request->getVar('vendor_id') ?? null;
        $payee       = $this->request->getVar('payee') ?? null;
        $particulars = $this->request->getVar('particulars') ?? null;

        if (!$check_slip = $this->checkSlipModel->search($bank_id, $check_no, $check_date, $amount, $supplier_id, $vendor_id, $payee, $particulars)) {
            $response = $this->failNotFound('No check_entry found');
        } else {
            $check_entries = $this->checkEntryModel->get_details_by_slip_id($check_slip[0]['id']);
            $check_slip[0]['check_entries'] = $check_entries;
            $response = $this->respond([
                'data' => $check_slip, 
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record the action of the user
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('check_payments', 'record_action')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where =  [
            'id' =>$this->request->getVar('check_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$check_slip = $this->checkSlipModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'check_slip not found']);
        } elseif (!$this->_attempt_record_action($check_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to record action.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Check slip action recorded successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Generate check no
     */
    public function generate_check_no()
    {
        if (($response = $this->_api_verification('check_payments', 'generate_check_no')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        if (!$check_no = $this->checkSlipModel->generate_check_no()) {
            $response = $this->fail(['response' => 'Failed to generate check no.', 'status' => 'error']);
        } else {
            $response = $this->respond(['data' => $check_no, 'status' => 'success']);
        }
        
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create check slip
     */
    protected function _attempt_create_slip()
    {
        $data = [
            'bank_id'         => $this->request->getVar('bank_id'),
            'check_no'        => $this->request->getVar('check_no'),
            'check_date'      => $this->request->getVar('check_date'),
            'issued_date'     => $this->request->getVar('issued_date'),
            'supplier_id'     => $this->request->getVar('supplier_id'),
            'vendor_id'       => $this->request->getVar('vendor_id'),
            'payee'           => $this->request->getVar('payee'),
            'particulars'     => $this->request->getVar('particulars'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s')
        ];

        if (!$check_slip_id = $this->checkSlipModel->insert($data)) {
            return false;
        }

        return $check_slip_id;
    }

    /**
     * Attempt generate check entry
     */
    protected function _attempt_generate_entry($check_slip_id)
    {
        $receive_ids = $this->request->getVar('receive_ids');
        $amounts     = $this->request->getVar('amounts');

        $total = 0;
        foreach ($receive_ids as $key => $receive_id) {
            $total += $amounts[$key];
            $data = [
                'check_slip_id' => $check_slip_id,
                'receive_id'    => $receive_id,
                'amount'        => $amounts[$key],
                'added_by'      => $this->requested_by,
                'added_on'      => date('Y-m-d H:i:s')
            ];

            if (!$this->checkEntryModel->insert($data)) {
                return false;
            }

            if ($receive = $this->receiveModel->get_details_by_id($receive_id)) {
                $receive_data = [
                    'paid_amount' => $receive[0]['paid_amount'] + $amounts[$key],
                    'balance'     => $receive[0]['balance'] - $amounts[$key],
                    'updated_on' => date('Y-m-d H:i:s'),
                    'updated_by' => $this->requested_by
                ];

                if (!$this->receiveModel->update($receive_id, $receive_data)) {
                    return false;
                }
            }
        }

        $values = [
            'amount'     => $total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        if (!$this->checkSlipModel->update($check_slip_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt update check slip
     */
    protected function _attempt_update_slip($check_slip_id)
    {
        $value = [
            'bank_id'         => $this->request->getVar('bank_id'),
            'check_no'        => $this->request->getVar('check_no'),
            'check_date'      => $this->request->getVar('check_date'),
            'issued_date'     => $this->request->getVar('issued_date'),
            'supplier_id'     => $this->request->getVar('supplier_id'),
            'vendor_id'       => $this->request->getVar('vendor_id'),
            'payee'           => $this->request->getVar('payee'),
            'particulars'     => $this->request->getVar('particulars'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'updated_by'      => $this->requested_by,
            'updated_on'      => date('Y-m-d H:i:s')
        ];

        return $this->checkSlipModel->update($check_slip_id, $value);
    }

    /**
     * Attempt update check entry
     */
    protected function _attempt_update_entry($check_slip_id)
    {
        // Revert the payments made
        $check_entries = $this->checkEntryModel->get_details_by_slip_id($check_slip_id);
        foreach ($check_entries as $check_entry) {
            if ($receive = $this->receiveModel->get_details_by_id($check_entry['receive_id'])) {
                $receive_data = [
                    'paid_amount' => $receive[0]['paid_amount'] - $check_entry['amount'],
                    'balance'     => $receive[0]['balance'] + $check_entry['amount'],
                    'updated_on'  => date('Y-m-d H:i:s'),
                    'updated_by'  => $this->requested_by
                ];

                if (!$this->receiveModel->update($check_entry['receive_id'], $receive_data)) {
                    return false;
                }
            }
        }

        $this->checkEntryModel->delete_by_slip_id($check_slip_id, $this->requested_by);
        if (!$this->_attempt_generate_entry($check_slip_id)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete_entry($check_entry)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->checkEntryModel->update($check_entry['id'], $values)) {
            return false;
        }

        $check_slip = $this->checkSlipModel->select('', ['id' => $check_entry['check_slip_id']], 1);

        $values = [
            'amount' => $check_slip['amount'] - $check_entry['amount'],
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->checkSlipModel->update($check_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete slip
     */

    protected function _attempt_delete_slip($check_slip)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->checkSlipModel->update($check_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($check_slip)
    {
        $current_status = $check_slip['status'];

        $where = ['id' => $check_slip['id']];

        $values = [
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $action        = $this->request->getVar('action');
        switch ($action) {
            case 'pending':
                $values['status'] = 'pending';
                break;
            case 'approved':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'approved';
                break;
            case 'disapproved':
                $values['disapproved_by'] = $this->requested_by;
                $values['disapproved_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'disapproved'; 
                break;
            case 'print':
                $values['printed_by'] = $this->requested_by;
                $values['printed_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'printed';
                break;
            case 'completed':
                $values['completed_by'] = $this->requested_by;
                $values['completed_on'] = date('Y-m-d H:i:s');
                $values['status']       = 'completed';
                break;
            default:
                return false;
        }

        if (!$this->checkSlipModel->update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->checkEntryModel     = new Check_entry();
        $this->checkSlipModel      = new Check_slip();
        $this->receiveModel        = new Receive();
        $this->webappResponseModel = new Webapp_response();
    }
}

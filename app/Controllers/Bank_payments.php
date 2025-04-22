<?php

namespace App\Controllers;

class Bank_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get bank_entry
     */
    public function get_entry()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_entry_id = $this->request->getVar('entry_id') ? : null;
        $bank_entry    = $bank_entry_id ? $this->bankEntryModel->get_details_by_id($bank_entry_id) : null;
        $bank_slip     = $bank_entry ? $this->bankSlipModel->get_details_by_id($bank_entry[0]['id']) : null;

        if (!$bank_entry) {
            $response = $this->failNotFound('No bank entry found');
        } else {
            $bank_entry[0]['bank_slip'] = $bank_slip ? $bank_slip : null;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $bank_entry
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get bank_entry
     */
    public function get_slip()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_slip_id = $this->request->getVar('slip_id') ? : null;
        $bank_slip    = $bank_slip_id ? $this->bankSlipModel->get_details_by_id($bank_slip_id) : null;
        $bank_entries = $bank_slip ? $this->bankEntryModel->get_details_by_slip_id($bank_slip[0]['id']) : null;

        if (!$bank_slip) {
            $response = $this->failNotFound('No bank invoice found');
        } else {
            $bank_slip[0]['bank_entries'] = $bank_entries ? $bank_entries : [];
            $response = $this->respond([
                'status' => 'success',
                'data'   => $bank_slip
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all bank_entry
     */
    public function get_all_entry()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_all_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_entries = $this->bankEntryModel->get_all_entry();

        if (!$bank_entries) {
            $response = $this->failNotFound('No bank_entry found');
        } else {
            foreach($bank_entries as $key => $bank_entry) {
                $bank_slip = $this->bankSlipModel->get_details_by_id($bank_entry['bank_slip_id']);
                $bank_entries[$key]['bank_slip'] = $bank_slip ? $bank_slip : [];
            }

            $response = $this->respond([
                'data'   => $bank_entries,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all bank_entry
     */
    public function get_all_slip()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_all_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_slips = $this->bankSlipModel->get_all_slip();

        if (!$bank_slips) {
            $response = $this->failNotFound('No bank_entry found');
        } else {
            foreach($bank_slips as $key => $bank_slip) {
                $bank_entries = $this->bankEntryModel->get_details_by_slip_id($bank_slip['id']);
                $bank_slips[$key]['bank_entries'] = $bank_entries ? $bank_entries : [];
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $bank_slips
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create bank_entry
     */
    public function create()
    {
        if (($response = $this->_api_verification('bank_payements', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank_slip_id = $this->_attempt_create_slip()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create slip.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_entry($bank_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate bank entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully created slip.', 'status' => 'success', 'slip_id' => $bank_slip_id]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update bank slip and Bank slip
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('bank_payements', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('bank_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank_slip = $this->bankSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('bank slip not found');
        } elseif (!$this->_attempt_update_slip($bank_slip['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Bank slip updated unsuccessfully.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_entry($bank_slip['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Bank entry updated unsuccessfully.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Bank payment updated successfully.', 'status' => 'success']);
        }   

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete bank_entry
     */
    public function delete_entry($id = '')
    {
        if (($response = $this->_api_verification('bank_payements', 'delete_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('bank_entry_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank_entry = $this->bankEntryModel->select('', $where, 1)) {
            $response = $this->failNotFound('bank entry not found');
        } elseif (!$this->_attempt_delete_entry($bank_entry)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete bank_entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted bank_entry.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete bank slip
     */
    public function delete_slip($id = '')
    {
        if (($response = $this->_api_verification('bank_payements', 'delete_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('bank_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank_slip = $this->bankSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('bank slip not found');
        } elseif (!$this->_attempt_delete_slip($bank_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete bank slip.', 'status' => 'error']);
        } elseif (!$this->bankEntryModel->delete_by_slip_id($bank_slip['id'], $this->requested_by)){
            $db->transCommit();
            $response = $this->fail(['response' => 'Successfully deleted bank slip.', 'status' => 'success']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted bank slip.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search bank_entry based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('bank_payments', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $payment_date = $this->request->getVar('payment_date') ?? null;
        $amount       = $this->request->getVar('amount') ?? null;
        $supplier_id  = $this->request->getVar('supplier_id') ?? null;
        $vendor_id    = $this->request->getVar('vendor_id') ?? null;
        $payee        = $this->request->getVar('payee') ?? null;
        $particulars  = $this->request->getVar('particulars') ?? null;

        if (!$bank_slip = $this->bankSlipModel->search($payment_date, $amount, $supplier_id, $vendor_id, $payee, $particulars)) {
            $response = $this->failNotFound('No bank_entry found');
        } else {
            $bank_entries = $this->bankEntryModel->get_details_by_slip_id($bank_slip[0]['id']);
            $bank_slip[0]['bank_entries'] = $bank_entries;
            $response = $this->respond(['data' => $bank_slip, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record the action of the user
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('bank_payments', 'record_action')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('bank_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank_slip = $this->bankSlipModel->select('', $where, 1)) {
            $response = $this->fail(['response' => 'bank slip not found']);
        } elseif (!$this->_attempt_record_action($bank_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to record action.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Bank slip action recorded successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create bank slip
     */
    protected function _attempt_create_slip()
    {
        $data = [
            'payment_date'      => $this->request->getVar('payment_date'),
            'bank_from'         => $this->request->getVar('bank_from'),
            'from_account_no'   => $this->request->getVar('from_account_no'),   
            'from_account_name' => $this->request->getVar('from_account_name'),
            'bank_to'           => $this->request->getVar('bank_to'),
            'to_account_no'     => $this->request->getVar('to_account_no'),
            'to_account_name'   => $this->request->getVar('to_account_name'),
            'transaction_fee'   => $this->request->getVar('transaction_fee'),
            'reference_no'      => $this->request->getVar('reference_no'),
            'supplier_id'       => $this->request->getVar('supplier_id'),
            'vendor_id'         => $this->request->getVar('vendor_id'),
            'payee'             => $this->request->getVar('payee'),
            'particulars'       => $this->request->getVar('particulars'),
            'acknowledged_by'   => $this->request->getVar('acknowledged_by'),
            'added_by'          => $this->requested_by,
            'added_on'          => date('Y-m-d H:i:s')
        ];

        if (!$bank_slip_id = $this->bankSlipModel->insert($data)) {
            return false;
        }

        return $bank_slip_id;
    }

    /**
     * Attempt generate bank entry
     */
    protected function _attempt_generate_entry($bank_slip_id)
    {
        $receive_ids = $this->request->getVar('receive_ids');
        $amounts     = $this->request->getVar('amounts');

        $total = 0;
        foreach ($receive_ids as $key => $receive_id) {
            $total += $amounts[$key];
            $data = [
                'bank_slip_id' => $bank_slip_id,
                'receive_id'   => $receive_id,
                'amount'       => $amounts[$key],
                'added_by'     => $this->requested_by,
                'added_on'     => date('Y-m-d H:i:s')
            ];

            if (!$this->bankEntryModel->insert($data)) {
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
        if (!$this->bankSlipModel->update($bank_slip_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt update bank slip
     */
    protected function _attempt_update_slip($bank_slip_id)
    {
        $data = [
            'payment_date'      => $this->request->getVar('payment_date'),
            'bank_from'         => $this->request->getVar('bank_from'),
            'from_account_no'   => $this->request->getVar('from_account_no'),   
            'from_account_name' => $this->request->getVar('from_account_name'),
            'bank_to'           => $this->request->getVar('bank_to'),
            'to_account_no'     => $this->request->getVar('to_account_no'),
            'to_account_name'   => $this->request->getVar('to_account_name'),
            'transaction_fee'   => $this->request->getVar('transaction_fee'),
            'reference_no'      => $this->request->getVar('reference_no'),
            'supplier_id'       => $this->request->getVar('supplier_id'),
            'vendor_id'         => $this->request->getVar('vendor_id'),
            'payee'             => $this->request->getVar('payee'),
            'particulars'       => $this->request->getVar('particulars'),
            'acknowledged_by'   => $this->request->getVar('acknowledged_by'),
            'updated_by'        => $this->requested_by,
            'updated_on'        => date('Y-m-d H:i:s')
        ];

        return $this->bankSlipModel->update($bank_slip_id, $data);
    }

    /**
     * Attempt update bank entry
     */
    protected function _attempt_update_entry($bank_slip_id)
    {
        // Revert the payments made
        $bank_entries = $this->bankEntryModel->get_details_by_slip_id($bank_slip_id);
        foreach ($bank_entries as $bank_entry) {
            $receive = $this->receiveModel->get_details_by_id($bank_entry['receive_id']);
            $receive_data = [
                'paid_amount' => $receive[0]['paid_amount'] - $bank_entry['amount'],
                'balance'     => $receive[0]['balance'] + $bank_entry['amount'],
                'updated_on'  => date('Y-m-d H:i:s'),
                'updated_by'  => $this->requested_by
            ];

            if (!$this->receiveModel->update($bank_entry['receive_id'], $receive_data)) {
                return false;
            }
        }
        
        $this->bankEntryModel->delete_by_slip_id($bank_slip_id, $this->requested_by);
        if (!$this->_attempt_generate_entry($bank_slip_id)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete_entry($bank_entry)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        
        if (!$this->bankEntryModel->update($bank_entry['id'], $values)) {
            return false;
        }

        $bank_slip = $this->bankSlipModel->select('', ['id' => $bank_entry['bank_slip_id']], 1);

        $values = [
            'amount' => $bank_slip['amount'] - $bank_entry['amount'],
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankSlipModel->update($bank_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete slip
     */

    protected function _attempt_delete_slip($bank_slip)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankSlipModel->update($bank_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($bank_slip)
    {
        $current_status = $bank_slip['status'];

        $where = ['id' => $bank_slip['id']];

        $values = [
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        
        $action = $this->request->getVar('action');
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

        if (!$this->bankSlipModel->update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->bankEntryModel      = model('App\Models\Bank_entry');
        $this->bankSlipModel       = model('App\Models\Bank_slip');
        $this->receiveModel        = model('App\Models\Receive');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}

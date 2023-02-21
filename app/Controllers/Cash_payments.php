<?php

namespace App\Controllers;

use App\Models\Cash_entry;
use App\Models\Cash_slip;
use App\Models\Receive;
use App\Models\Webapp_response;

class Cash_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get cash_entry
     */
    public function get_entry()
    {
        if (($response = $this->_api_verification('cash_payments', 'get_entry')) !== true)
            return $response;

        $cash_entry_id = $this->request->getVar('entry_id') ? : null;
        $cash_entry    = $cash_entry_id ? $this->cashEntryModel->get_details_by_id($cash_entry_id) : null;
        $cash_slip     = $cash_entry ? $this->cashSlipModel->get_details_by_id($cash_entry[0]['id']) : null;

        if (!$cash_entry) {
            $response = $this->failNotFound('No cash entry found');
        } else {
            $cash_entry[0]['cash_slip'] = $cash_slip ? $cash_slip : [];
            $response = $this->respond([
                'data'   => $cash_entry,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get cash_entry
     */
    public function get_slip()
    {
        if (($response = $this->_api_verification('cash_payments', 'get_slip')) !== true)
            return $response;

        $cash_slip_id = $this->request->getVar('slip_id') ? : null;
        $cash_slip    = $cash_slip_id ? $this->cashSlipModel->get_details_by_id($cash_slip_id) : null;
        $cash_entries = $cash_slip ? $this->cashEntryModel->get_details_by_slip_id($cash_slip[0]['id']) : null;

        if (!$cash_slip) {
            $response = $this->failNotFound('No cash invoice found');
        } else {
            $cash_slip[0]['cash_entries'] = $cash_entries ? $cash_entries : [];
            $response = $this->respond([
                'data'   => $cash_slip,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all cash_entry
     */
    public function get_all_entry()
    {
        if (($response = $this->_api_verification('cash_payments', 'get_all_entry')) !== true)
            return $response;

        $cash_entries = $this->cashEntryModel->get_all_entry();

        if (!$cash_entries) {
            $response = $this->failNotFound('No cash_entry found');
        } else {
            foreach($cash_entries as $key => $cash_entry) {
                $cash_slip = $this->cashSlipModel->get_details_by_id($cash_entry['cash_slip_id']);
                $cash_entries[$key]['cash_slip'] = $cash_slip ? $cash_slip : [];
            }

            $response = $this->respond([
                'data'   => $cash_entries,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all cash_entry
     */
    public function get_all_slip()
    {
        if (($response = $this->_api_verification('cash_payments', 'get_all_slip')) !== true)
            return $response;

        $cash_slips = $this->cashSlipModel->get_all_slip();

        if (!$cash_slips) {
            $response = $this->failNotFound('No cash_entry found');
        } else {
            foreach($cash_slips as $key => $cash_slip) {
                $cash_entries = $this->cashEntryModel->get_details_by_slip_id($cash_slip['id']);
                $cash_slips[$key]['cash_entries'] = $cash_entries ? $cash_entries : [];
            }

            $response = $this->respond([
                'data'   => $cash_slips,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create cash_entry
     */
    public function create()
    {
        if (($response = $this->_api_verification('cash_payements', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$cash_slip_id = $this->_attempt_create_slip()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create slip.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_entry($cash_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate cash entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response' => 'Slip and entry created successfully.',
                'status'   => 'success', 
                'slip_id'  => $cash_slip_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update cash slip and cash_entry
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('cash_payements', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('cash_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$cash_slip = $this->cashSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('cash slip not found');
        } elseif (!$this->_attempt_update_slip($cash_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update slip.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_entry($cash_slip['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update cash entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Cash slip and entry successfully updated.', 'status' => 'success']);
        }   

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete cash_entry
     */
    public function delete_entry($id = '')
    {
        if (($response = $this->_api_verification('cash_payements', 'delete_entry')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('cash_entry_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$cash_entry = $this->cashEntryModel->select('', $where, 1)) {
            $response = $this->failNotFound('cash entry not found');
        } elseif (!$this->_attempt_delete_entry($cash_entry)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete cash entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Cash entry successfully deleted.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete cash slip
     */
    public function delete_slip($id = '')
    {
        if (($response = $this->_api_verification('cash_payements', 'delete_slip')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('cash_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$cash_slip = $this->cashSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('cash slip not found');
        } elseif (!$this->_attempt_delete_slip($cash_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete cash slip.', 'status' => 'error']);
        } elseif (!$this->cashEntryModel->delete_by_slip_id($cash_slip['id'], $this->requested_by)){
            $db->transCommit();
            $response = $this->fail(['response' => 'Failed to delete cash entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Cash slip and entry successfully deleted.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search cash_entry based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('cash_payments', 'search')) !== true)
            return $response;

        $payment_date = $this->request->getVar('payment_date') ?? null;
        $amount       = $this->request->getVar('amount') ?? null;
        $supplier_id  = $this->request->getVar('supplier_id') ?? null;
        $vendor_id    = $this->request->getVar('vendor_id') ?? null;
        $payee        = $this->request->getVar('payee') ?? null;
        $particulars  = $this->request->getVar('particulars') ?? null;

        if (!$cash_slip = $this->cashSlipModel->search($payment_date, $amount, $supplier_id, $vendor_id, $payee, $particulars)) {
            $response = $this->failNotFound('No cash_entry found');
        } else {
            $cash_entries = $this->cashEntryModel->get_details_by_slip_id($cash_slip[0]['id']);
            $cash_slip[0]['cash_entries'] = $cash_entries;
            $response = $this->respond(['data' => $cash_slip, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record the action of the user
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('cash_payments', 'record_action')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('cash_slip_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$cash_slip = $this->cashSlipModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'cash slip not found']);
        } elseif (!$this->_attempt_record_action($cash_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to record action.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Action successfully recorded.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create cash slip
     */
    protected function _attempt_create_slip()
    {
        $data = [
            'payment_date'    => $this->request->getVar('payment_date'),
            'supplier_id'     => $this->request->getVar('supplier_id'),
            'vendor_id'       => $this->request->getVar('vendor_id'),
            'payee'           => $this->request->getVar('payee'),
            'particulars'     => $this->request->getVar('particulars'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s')
        ];

        if (!$cash_slip_id = $this->cashSlipModel->insert($data)) {
            return false;
        }

        return $cash_slip_id;
    }

    /**
     * Attempt generate cash entry
     */
    protected function _attempt_generate_entry($cash_slip_id)
    {
        $receive_ids = $this->request->getVar('receive_ids');
        $amounts     = $this->request->getVar('amounts');

        $total = 0;
        foreach ($receive_ids as $key => $receive_id) {
            $total += $amounts[$key];
            $data = [
                'cash_slip_id' => $cash_slip_id,
                'receive_id'   => $receive_id,
                'amount'       => $amounts[$key],
                'added_by'     => $this->requested_by,
                'added_on'     => date('Y-m-d H:i:s')
            ];

            if (!$this->cashEntryModel->insert($data)) {
                return false;
            }

            $receive = $this->receiveModel->get_details_by_id($receive_id);
            $receive_data = [
                'paid_amount' => $receive[0]['paid_amount'] + $amounts[$key],
                'balance'     => $receive[0]['balance'] - $amounts[$key],
                'updated_on'  => date('Y-m-d H:i:s'),
                'updated_by'  => $this->requested_by
            ];

            if (!$this->receiveModel->update($receive_id, $receive_data)) {
                return false;
            }
        }

        $values = [
            'amount'     => $total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        if (!$this->cashSlipModel->update($cash_slip_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt update cash slip
     */
    protected function _attempt_update_slip($cash_slip_id)
    {
        $data = [
            'payment_date'    => $this->request->getVar('payment_date'),
            'supplier_id'     => $this->request->getVar('supplier_id'),
            'vendor_id'       => $this->request->getVar('vendor_id'),
            'payee'           => $this->request->getVar('payee'),
            'particulars'     => $this->request->getVar('particulars'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'updated_by'      => $this->requested_by,
            'updated_on'      => date('Y-m-d H:i:s')
        ];

        return $this->cashSlipModel->update($cash_slip_id, $data);
    }

    /**
     * Attempt update cash entry
     */
    protected function _attempt_update_entry($cash_slip_id)
    {   
        // Revert the payments made
        $cash_entries = $this->cashEntryModel->get_details_by_slip_id($cash_slip_id);
        foreach ($cash_entries as $cash_entry) {
            $receive = $this->receiveModel->get_details_by_id($cash_entry['receive_id']);
            $receive_data = [
                'paid_amount' => $receive[0]['paid_amount'] - $cash_entry['amount'],
                'balance'     => $receive[0]['balance'] + $cash_entry['amount'],
                'updated_on'  => date('Y-m-d H:i:s'),
                'updated_by'  => $this->requested_by
            ];

            if (!$this->receiveModel->update($cash_entry['receive_id'], $receive_data)) {
                return false;
            }
        }

        $this->cashEntryModel->delete_by_slip_id($cash_slip_id, $this->requested_by);
        if (!$this->_attempt_generate_entry($cash_slip_id)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete_entry($cash_entry)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashEntryModel->update($cash_entry['id'], $values)) {
            return false;
        }

        $cash_slip = $this->cashSlipModel->select('', ['id' => $cash_entry['cash_slip_id']], 1);

        $values = [
            'amount' => $cash_slip['amount'] - $cash_entry['amount'],
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashSlipModel->update($cash_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete slip
     */

    protected function _attempt_delete_slip($cash_slip)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashSlipModel->update($cash_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($cash_slip)
    {
        $current_status = $cash_slip['status'];

        $where = ['id' => $cash_slip['id']];

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

        if (!$this->cashSlipModel->update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->cashEntryModel      = new Cash_entry();
        $this->cashSlipModel       = new Cash_slip();
        $this->receiveModel        = new Receive();
        $this->webappResponseModel = new Webapp_response();
    }
}

<?php

namespace App\Controllers;

use App\Models\SE_bank_entry;
use App\Models\SE_bank_slip;
use App\Models\Supplies_receive;
use App\Models\Webapp_response; 

class Se_bank_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get se_bank_entry
     */
    public function get_entry()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_entry')) !== true)
            return $response;

        $se_bank_entry_id = $this->request->getVar('entry_id') ? : null;
        $se_bank_entry    = $se_bank_entry_id ? $this->bankEntryModel->get_details_by_id($se_bank_entry_id) : null;
        $se_bank_slip     = $se_bank_entry ? $this->bankSlipModel->get_details_by_id($se_bank_entry[0]['id']) : null;

        if (!$se_bank_entry) {
            $response = $this->failNotFound('No bank invoice found');
        } else {
            $se_bank_entry[0]['se_bank_slip'] = $se_bank_slip;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_bank_entry
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get se_bank_entry
     */
    public function get_slip()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_slip')) !== true)
            return $response;

        $se_bank_slip_id = $this->request->getVar('slip_id') ? : null;
        $se_bank_slip    = $se_bank_slip_id ? $this->bankSlipModel->get_details_by_id($se_bank_slip_id) : null;
        $bank_entries = $se_bank_slip ? $this->bankEntryModel->get_details_by_slip_id($se_bank_slip[0]['id']) : null;

        if (!$se_bank_slip) {
            $response = $this->failNotFound('No bank invoice found');
        } else {
            $se_bank_slip[0]['bank_entries'] = $bank_entries;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_bank_slip
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all se_bank_entry
     */
    public function get_all_entry()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_all_entry')) !== true)
            return $response;

        $bank_entries = $this->bankEntryModel->get_all_entry();

        if (!$bank_entries) {
            $response = $this->failNotFound('No se_bank_entry found');
        } else {
            foreach($bank_entries as $key => $se_bank_entry) {
                $se_bank_slip = $this->bankSlipModel->get_details_by_id($se_bank_entry['se_bank_slip_id']);
                $bank_entries[$key]['se_bank_slip'] = $se_bank_slip;
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
     * Get all se_bank_entry
     */
    public function get_all_slip()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_all_slip')) !== true)
            return $response;

        $se_bank_slips = $this->bankSlipModel->get_all_slip();

        if (!$se_bank_slips) {
            $response = $this->failNotFound('No se_bank_entry found');
        } else {
            foreach($se_bank_slips as $key => $se_bank_slip) {
                $bank_entries = $this->bankEntryModel->get_details_by_slip_id($se_bank_slip['id']);
                $se_bank_slips[$key]['bank_entries'] = $bank_entries;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_bank_slips
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create se_bank_entry
     */
    public function create()
    {
        if (($response = $this->_api_verification('bank_payements', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_bank_slip_id = $this->_attempt_create_slip()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create slip.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_entry($se_bank_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate bank entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully created slip.', 'status' => 'success', 'slip_id' => $se_bank_slip_id]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update bank slip and se_bank_entry
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('bank_payements', 'update')) !== true)
            return $response;

        $se_bank_slip_id = $this->request->getVar('se_bank_slip_id');
        $where         = ['id' => $se_bank_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_bank_slip = $this->bankSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense bank slip not found');
        } elseif (!$this->_attempt_update_slip($se_bank_slip_id)) {
            $db->transRollback();
            $response = $this->respond(['response' => 'Supplies expense bankentry updated unsuccessfully']);
        } elseif (!$this->_attempt_update_entry($se_bank_slip_id)) {
            $db->transRollback();
            $response = $this->respond(['response' => 'Supplies expense bank entry updated unsuccessfully']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Supplies expense bank entry updated successfully']);
        }   

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete se_bank_entry
     */
    public function delete_entry($id = '')
    {
        if (($response = $this->_api_verification('bank_payements', 'delete_entry')) !== true)
            return $response;

        $se_bank_entry_id = $this->request->getVar('se_bank_entry_id');

        $where = ['id' => $se_bank_entry_id, 'is_deleted' => 0];


        if (!$se_bank_entry = $this->bankEntryModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense bank entry not found');
        } elseif (!$this->_attempt_delete_entry($se_bank_entry)) {
            $response = $this->fail(['response' => 'Failed to delete Supplies expense bank entry.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Successfully deleted Supplies expense bank entry.', 'status' => 'success']);
        }

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

        $se_bank_slip_id = $this->request->getVar('se_bank_slip_id');

        $where = ['id' => $se_bank_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_bank_slip = $this->bankSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense bank slip not found');
        } elseif (!$this->_attempt_delete_slip($se_bank_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete Supplies expense bank slip.', 'status' => 'error']);
        } elseif (!$this->bankEntryModel->delete_by_slip_id($se_bank_slip_id, $this->requested_by)){
            $db->transCommit();
            $response = $this->fail(['response' => 'Successfully deleted Supplies expense bank slip.', 'status' => 'success']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted Supplies expense bank slip.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search se_bank_entry based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('bank_payments', 'search')) !== true)
            return $response;

        $bank_id     = $this->request->getVar('bank_id') ?? null;
        $bank_no    = $this->request->getVar('bank_no') ?? null;
        $bank_date  = $this->request->getVar('bank_date') ?? null;
        $amount      = $this->request->getVar('amount') ?? null;
        $supplier_id = $this->request->getVar('supplier_id') ?? null;
        $payee       = $this->request->getVar('payee') ?? null;
        $particulars = $this->request->getVar('particulars') ?? null;

        if (!$se_bank_slip = $this->bankSlipModel->search($bank_id, $bank_no, $bank_date, $amount, $supplier_id, $payee, $particulars)) {
            $response = $this->failNotFound('No se_bank_entry found');
        } else {
            $bank_entries = $this->bankEntryModel->get_details_by_slip_id($se_bank_slip[0]['id']);
            $se_bank_slip[0]['bank_entries'] = $bank_entries;
            $response = $this->respond(['data' => $se_bank_slip, 'status' => 'success']);
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

        $se_bank_slip_id = $this->request->getVar('se_bank_slip_id');
        $action        = $this->request->getVar('action');

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_bank_slip = $this->bankSlipModel->select('', ['id' => $se_bank_slip_id, 'is_deleted' => 0], 1)) {
            $response = $this->respond(['response' => 'Supplies expense bank slip not found']);
        } elseif (!$this->_attempt_record_action($se_bank_slip, $action)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'se_bank_slip status changed successfully']);
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
            'amount'            => $this->request->getVar('amount'),
            'supplier_id'       => $this->request->getVar('supplier_id'),
            'vendor_id'         => $this->request->getVar('vendor_id'),
            'payee'             => $this->request->getVar('payee'),
            'particulars'       => $this->request->getVar('particulars'),
            'acknowleded_by'    => $this->request->getVar('acknowleded_by'),
            'added_by'          => $this->requested_by,
            'added_on'          => date('Y-m-d H:i:s')
        ];

        if (!$se_bank_slip_id = $this->bankSlipModel->insert($data)) {
            return false;
        }

        return $se_bank_slip_id;
    }

    /**
     * Attempt generate bank entry
     */
    protected function _attempt_generate_entry($se_bank_slip_id)
    {
        $se_ids = $this->request->getVar('se_ids');
        $amounts = $this->request->getVar('amounts');

        $total = 0;
        foreach ($se_ids as $key => $se_id) {
            $total += $amounts[$key];
            $data = [
                'se_bank_slip_id' => $se_bank_slip_id,
                'se_id'            => $se_id,
                'amount'           => $amounts[$key],
                'added_by'         => $this->requested_by,
                'added_on'         => date('Y-m-d H:i:s')
            ];

            if (!$this->bankEntryModel->insert($data)) {
                return false;
            }

            if ($receive = $this->seReceiveModel->get_details_by_id($se_id)) {
                $receive_data = [
                    'paid_amount' => $receive[0]['paid_amount'] + $amounts[$key],
                    'balance'     => $receive[0]['balance'] - $amounts[$key],
                    'updated_on' => date('Y-m-d H:i:s'),
                    'updated_by' => $this->requested_by
                ];

                if (!$this->seReceiveModel->update($se_id, $receive_data)) {
                    return false;
                }
            } else {
                var_dump("Supplies Expense Receive id {$se_id} not found");
            }
        }

        $values = [
            'amount'     => $total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        if (!$this->bankSlipModel->update($se_bank_slip_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt update bank slip
     */
    protected function _attempt_update_slip($se_bank_slip_id)
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
            'amount'            => $this->request->getVar('amount'),
            'supplier_id'       => $this->request->getVar('supplier_id'),
            'vendor_id'         => $this->request->getVar('vendor_id'),
            'payee'             => $this->request->getVar('payee'),
            'particulars'       => $this->request->getVar('particulars'),
            'acknowleded_by'    => $this->request->getVar('acknowleded_by'),
            'updated_by'        => $this->requested_by,
            'updated_on'        => date('Y-m-d H:i:s')
        ];

        return $this->bankSlipModel->update($se_bank_slip_id, $data);
    }

    /**
     * Attempt update bank entry
     */
    protected function _attempt_update_entry($se_bank_slip_id)
    {
        $this->bankEntryModel->delete_by_slip_id($se_bank_slip_id, $this->requested_by);
        if (!$this->_attempt_generate_entry($se_bank_slip_id)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete entry
     */
    protected function _attempt_delete_entry($se_bank_entry)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankEntryModel->update($se_bank_entry['id'], $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        if (!$se_bank_slip = $this->bankSlipModel->select('', ['id' => $se_bank_entry['se_bank_slip_id']], 1)) {
            $db->close();
            return false;
        }

        $values = [
            'amount' => $se_bank_slip['amount'] - $se_bank_entry['amount'],
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankSlipModel->update($se_bank_slip['id'], $values)) {
            $db->transRollback();   
            $db->close();
            return false;
        }

        $db->transCommit();
        $db->close();
        
        return true;
    }

    /**
     * Attempt delete slip
     */

    protected function _attempt_delete_slip($se_bank_slip)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankSlipModel->update($se_bank_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($se_bank_slip, $action)
    {
        $current_status = $se_bank_slip['status'];

        $where = ['id' => $se_bank_slip['id']];

        $values = [
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

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
        $this->bankEntryModel      = new SE_bank_entry();
        $this->bankSlipModel       = new SE_bank_slip();
        $this->seReceiveModel      = new Supplies_receive();
        $this->webappResponseModel = new Webapp_response();
    }
}

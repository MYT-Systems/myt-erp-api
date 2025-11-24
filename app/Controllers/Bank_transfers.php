<?php

namespace App\Controllers;

use App\Models\Bank_transfer;
use App\Models\Webapp_response;
use App\Models\Bank;

class Bank_transfers extends MYTController {
    protected $bank_transferModel;
    protected $webappResponseModel;
    protected $bankModel;

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
    * Get bank transfer
    */
    public function get_bank_transfer()
    {
        if (($response = $this->_api_verification('bank_transfer', 'get_bank_transfer')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_transfer_id = $this->request->getVar('bank_transfer_id') ?: null;
        $bank_transfer    = $bank_transfer_id ? $this->bankTransferModel->get_details_by_id($bank_transfer_id) : null;

        if (!$bank_transfer) {
            $response = $this->failNotFound('No bank transfer found');
        } else {
            $response = $this->respond([
                'data'   => $bank_transfer,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

     /**
     * Get all bank transfers
     */
    public function get_all_bank_transfer()
    {
        if (($response = $this->_api_verification('bank_transfer', 'get_all_bank_transfer')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_transfers = $this->bankTransferModel->get_all_bank_transfer();

        if (!$bank_transfers) {
            $response = $this->failNotFound('No bank transfers found');
        } else {
            $response = $this->respond([
                'data'   => $bank_transfers,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

     /**
     * Create a bank transfer
     */
     public function create()
    {
        if (($response = $this->_api_verification('bank_transfers', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$bank_transfer_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to create bank transfer.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response'    => 'Bank transfer created successfully.',
                'status'      => 'success',
                'transfer_id' => $bank_transfer_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search bank_transfer 
     */
     public function search()
    {
        if (($response = $this->_api_verification('bank_transfers', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $transaction_date = $this->request->getVar('transaction_date') ?? null;
        $reference_no          = $this->request->getVar('reference_no') ?? null;
        $bank_from_id = $this->request->getVar('bank_from_id') ? : null;
        $bank_to_id = $this->request->getVar('bank_to_id') ? : null;
        $amount = $this->request->getVar('amount') ? : null;
        $remarks = $this->request->getVar('remarks') ? : null;
        
        if (!$data = $this->bankTransferModel->search($transaction_date, $reference_no, $bank_from_id, $bank_to_id, $amount, $remarks)) {
            $response = $this->failNotFound('No bank found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $data
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete bank_transfers
     */
    public function delete_transfer($id = null)
    {
        if (($response = $this->_api_verification('delete_transfer', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_transfer_id = $this->request->getVar('bank_transfer_id');

        $where = ['id' => $bank_transfer_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$bank_transfer = $this->bankTransferModel->select('', $where, 1)) {
            $response = $this->failNotFound('Bank transfer not found');
        } elseif (!$this->_attempt_delete($bank_transfer)) {
            $db->transRollback();
            $response = $this->fail(['response' => $this->errorMessage ?: 'Fail to delete bank transfer.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Bank Transfer deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    /**
     * Update bank_transfers
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('bank_transfers', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_transfer_id = $this->request->getVar('bank_transfer_id');
        $where = ['id' => $bank_transfer_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$bank_transfer = $this->bankTransferModel->select('', $where, 1)) {
            $response = $this->failNotFound('Bank transfer not found');
        } elseif (!$this->_attempt_update($bank_transfer)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Bank transfer updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


     /**
     * Attempt create bank transfer
     */
    protected function _attempt_create()
    {
        $bank_from_id = $this->request->getVar('bank_from_id');
        $bank_to_id   = $this->request->getVar('bank_to_id');
        $amount       = (float)$this->request->getVar('amount');

        $values = [
            'transaction_date' => $this->request->getVar('transaction_date'),
            'reference_no'     => $this->request->getVar('reference_no'),
            'bank_from_id'     => $bank_from_id,
            'bank_to_id'       => $bank_to_id,
            'amount'           => $amount,
            'remarks'          => $this->request->getVar('remarks'),
            'added_by'         => $this->requested_by,
            'added_on'         => date('Y-m-d H:i:s'),
            'is_deleted'       => 0
        ];

        if (!$bank_transfer_id = $this->bankTransferModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Only update balances if bank_from and bank_to are different
        if ($bank_from_id !== $bank_to_id && $amount > 0) {
            if (
                !$this->_attempt_decrement_balance($bank_from_id, $amount) ||
                !$this->_attempt_increment_balance($bank_to_id, $amount)
            ) {
                return false;
            }
        }

        return $bank_transfer_id;
    }

    
    /**
     * Attempt delete 
     */
    protected function _attempt_delete($bank_transfer)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankTransferModel->update($bank_transfer['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
            
        // Reverse the original transfer 
        if ($bank_transfer['bank_from_id'] !== $bank_transfer['bank_to_id'] && $bank_transfer['amount'] > 0) {
            if (
                !$this->_attempt_increment_balance($bank_transfer['bank_from_id'], (float)$bank_transfer['amount']) ||
                !$this->_attempt_decrement_balance($bank_transfer['bank_to_id'], (float)$bank_transfer['amount'])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt update bank transfer
     */
    private function _attempt_update($bank_transfer)
    {
        $old_from = $bank_transfer['bank_from_id'];
        $old_to   = $bank_transfer['bank_to_id'];
        $old_amt  = (float)$bank_transfer['amount'];

        $new_from = $this->request->getVar('bank_from_id');
        $new_to   = $this->request->getVar('bank_to_id');
        $new_amt  = (float)$this->request->getVar('amount');

        if ($old_from !== $new_from || $old_to !== $new_to || $old_amt !== $new_amt) {
            if ($old_from !== $old_to && $old_amt > 0) {
                $this->_attempt_increment_balance($old_from, $old_amt);
                $this->_attempt_decrement_balance($old_to, $old_amt);
            }
        }

        $new_values = [
            'transaction_date' => $this->request->getVar('transaction_date'),
            'reference_no'     => $this->request->getVar('reference_no'),
            'bank_from_id'     => $new_from,
            'bank_to_id'       => $new_to,
            'amount'           => $new_amt,
            'remarks'          => $this->request->getVar('remarks'),
            'updated_by'       => $this->requested_by,
            'updated_on'       => date('Y-m-d H:i:s')
        ];

        if (!$this->bankTransferModel->update($bank_transfer['id'], $new_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if ($new_from !== $new_to && $new_amt > 0) {
            if (
                !$this->_attempt_decrement_balance($new_from, $new_amt) || !$this->_attempt_increment_balance($new_to, $new_amt)
            ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Increment bank balance
    */
    protected function _attempt_increment_balance($bank_id, $amount)
    {
        $bank = $this->bankModel->get_details_by_id($bank_id)[0];

        $new_values = [
            'current_bal' => $bank['current_bal'] + $amount,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$this->bankModel->update($bank_id, $new_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Decrement bank balance
     */
    protected function _attempt_decrement_balance($bank_id, $amount)
    {
        $bank = $this->bankModel->get_details_by_id($bank_id)[0];

        $new_values = [
            'current_bal' => $bank['current_bal'] - $amount,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$this->bankModel->update($bank_id, $new_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }


    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->bankTransferModel = new Bank_transfer();
        $this->webappResponseModel = new Webapp_response();
        $this->bankModel = new Bank();
    }

}


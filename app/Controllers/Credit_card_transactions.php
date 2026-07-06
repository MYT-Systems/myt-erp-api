<?php

namespace App\Controllers;

use App\Models\Credit_card_transaction;
use App\Models\Credit_card_transaction_attachment;
use App\Models\Credit_card;
use App\Models\Webapp_response;

class Credit_card_transactions extends MYTController
{
    protected $creditCardTransactionModel;
    protected $creditCardTransactionAttachmentModel;
    protected $creditCardModel;
    protected $webappResponseModel;

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Record a payment or expense against a credit card and adjust its balance.
     * 'expense' increases current_bal (amount owed), 'payment' decreases it.
     */
    public function add()
    {
        if (($response = $this->_api_verification('credit_card_transactions', 'add')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $credit_card_id = $this->request->getVar('credit_card_id');
        $type            = $this->request->getVar('type');
        $txn_date        = $this->request->getVar('txn_date');
        $amount          = $this->request->getVar('amount');
        $remarks         = $this->request->getVar('remarks') ?? null;

        if (!$credit_card_id || !$type || !$txn_date || !$amount || (float) $amount <= 0) {
            $response = $this->fail('credit_card_id, type, txn_date, and a positive amount are required.', 400);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        if (!in_array($type, ['expense', 'payment'])) {
            $response = $this->fail('type must be either expense or payment.', 400);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$credit_card = $this->creditCardModel->select('', ['id' => $credit_card_id, 'is_deleted' => 0], 1)) {
            $db->transRollback();
            $response = $this->failNotFound('credit card not found');
        } elseif (!$credit_card_transaction_id = $this->_attempt_add_transaction($credit_card, $type, $txn_date, $amount, $remarks)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to save transaction.', 'status' => 'error']);
        } elseif (($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$this->_attempt_upload_file_base64($this->creditCardTransactionAttachmentModel, ['credit_card_transaction_id' => $credit_card_transaction_id])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to upload payment receipt.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Transaction recorded successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all transactions for a credit card
     */
    public function get_all_transaction()
    {
        if (($response = $this->_api_verification('credit_card_transactions', 'get_all_transaction')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $credit_card_id = $this->request->getVar('credit_card_id') ? : null;
        $transactions   = $credit_card_id ? $this->creditCardTransactionModel->get_all_by_credit_card_id($credit_card_id) : null;

        if (!$transactions) {
            $response = $this->failNotFound('No transactions found');
        } else {
            foreach ($transactions as $key => $transaction) {
                $transactions[$key]['attachments'] = $this->creditCardTransactionAttachmentModel->get_details_by_credit_card_transaction_id($transaction['id']) ?: [];
            }

            $response = $this->respond([
                'data'   => $transactions,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete a transaction and reverse its effect on the credit card balance
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('credit_card_transactions', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('credit_card_transaction_id'),
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$transaction = $this->creditCardTransactionModel->select('', $where, 1)) {
            $response = $this->failNotFound('transaction not found');
        } elseif (!$credit_card = $this->creditCardModel->select('', ['id' => $transaction['credit_card_id'], 'is_deleted' => 0], 1)) {
            $db->transRollback();
            $response = $this->failNotFound('credit card not found');
        } elseif (!$this->_attempt_delete_transaction($transaction, $credit_card)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete transaction.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Transaction deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Insert a payment/expense transaction and adjust the credit card balance.
     */
    protected function _attempt_add_transaction($credit_card, $type, $txn_date, $amount, $remarks)
    {
        $values = [
            'credit_card_id' => $credit_card['id'],
            'type'           => $type,
            'txn_date'       => $txn_date,
            'amount'         => (float) $amount,
            'remarks'        => $remarks,
            'added_by'       => $this->requested_by,
            'added_on'       => date('Y-m-d H:i:s'),
            'is_deleted'     => 0,
        ];

        if (!$credit_card_transaction_id = $this->creditCardTransactionModel->insert($values))
            return false;

        $new_bal = $type === 'expense'
            ? (float) $credit_card['current_bal'] + (float) $amount
            : (float) $credit_card['current_bal'] - (float) $amount;

        if (!$this->creditCardModel->update($credit_card['id'], [
            'current_bal' => $new_bal,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ])) {
            return false;
        }

        return $credit_card_transaction_id;
    }

    /**
     * Soft-delete a transaction and reverse its effect on the credit card balance.
     */
    protected function _attempt_delete_transaction($transaction, $credit_card)
    {
        if (!$this->creditCardTransactionModel->update($transaction['id'], [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ])) {
            return false;
        }

        $this->creditCardTransactionAttachmentModel->delete_attachments_by_credit_card_transaction_id($transaction['id'], $this->requested_by);

        $new_bal = $transaction['type'] === 'expense'
            ? (float) $credit_card['current_bal'] - (float) $transaction['amount']
            : (float) $credit_card['current_bal'] + (float) $transaction['amount'];

        if (!$this->creditCardModel->update($credit_card['id'], [
            'current_bal' => $new_bal,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ])) {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->creditCardTransactionModel           = new Credit_card_transaction();
        $this->creditCardTransactionAttachmentModel = new Credit_card_transaction_attachment();
        $this->creditCardModel                      = new Credit_card();
        $this->webappResponseModel                  = new Webapp_response();
    }
}

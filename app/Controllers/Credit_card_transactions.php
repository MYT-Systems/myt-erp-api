<?php

namespace App\Controllers;

use App\Models\Credit_card_transaction;
use App\Models\Credit_card_transaction_attachment;
use App\Models\Credit_card_slip;
use App\Models\Credit_card_slip_attachment;
use App\Models\Credit_card_entry;
use App\Models\Credit_card;
use App\Models\Supplies_expense;
use App\Models\Project_expense;
use App\Models\Webapp_response;

class Credit_card_transactions extends MYTController
{
    protected $creditCardTransactionModel;
    protected $creditCardTransactionAttachmentModel;
    protected $creditCardSlipModel;
    protected $creditCardSlipAttachmentModel;
    protected $creditCardEntryModel;
    protected $creditCardModel;
    protected $suppliesExpenseModel;
    protected $projectExpenseModel;
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
        } elseif ($type === 'expense' && (float) $credit_card['credit_limit'] > 0 && ((float) $credit_card['current_bal'] + (float) $amount) > (float) $credit_card['credit_limit']) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Credit limit exceeded.', 'status' => 'error']);
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
     * Get a single credit card payment slip, with its entries and attachments.
     */
    public function get_slip()
    {
        if (($response = $this->_api_verification('credit_card_transactions', 'get_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $credit_card_slip_id = $this->request->getVar('slip_id') ? : null;
        $credit_card_slip    = $credit_card_slip_id ? $this->creditCardSlipModel->get_details_by_id($credit_card_slip_id) : null;

        if (!$credit_card_slip) {
            $response = $this->failNotFound('No credit card payment found');
        } else {
            $credit_card_slip[0]['credit_card_entries']            = $this->creditCardEntryModel->get_details_by_credit_card_slip_id($credit_card_slip[0]['id']) ?: [];
            $credit_card_slip[0]['credit_card_slip_attachments']   = $this->creditCardSlipAttachmentModel->get_details_by_credit_card_slip_id($credit_card_slip[0]['id']) ?: [];
            $response = $this->respond([
                'status' => 'success',
                'data'   => $credit_card_slip
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Pay off one or more supplies/project expenses using a credit card.
     * Creates a credit_card_slip plus one credit_card_entry per se_id, marks
     * each supplies/project expense as paid down, and charges the total to
     * the credit card's current_bal (rejected if it would exceed credit_limit).
     */
    public function create()
    {
        if (($response = $this->_api_verification('credit_card_transactions', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $credit_card_id = $this->request->getVar('credit_card_id');
        $payment_date   = $this->request->getVar('payment_date');

        if (!$credit_card_id || !$payment_date) {
            $response = $this->fail('credit_card_id and payment_date are required.', 400);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$credit_card = $this->creditCardModel->select('', ['id' => $credit_card_id, 'is_deleted' => 0], 1)) {
            $db->transRollback();
            $response = $this->failNotFound('credit card not found');
        } elseif (!$credit_card_slip_id = $this->_attempt_create_slip()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create slip.', 'status' => 'error']);
        } elseif (($error_message = $this->_attempt_generate_entry($credit_card_slip_id, $credit_card)) !== true) {
            $db->transRollback();
            $response = $this->respond([
                'response' => $error_message,
                'status'   => 'error'
            ]);
        } elseif (($this->request->getFileMultiple('attachments') ? true : false) && !$this->_upload_attachments($credit_card_slip_id, 'assets/credit_card_payments/')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to upload attachments. Make sure you have the correct file type, and file does not exceed 5 megabytes.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully created slip.', 'status' => 'success', 'slip_id' => $credit_card_slip_id]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update a credit card payment slip and its entries.
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('credit_card_transactions', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $credit_card_slip_id = $this->request->getVar('credit_card_slip_id');
        $where               = ['id' => $credit_card_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$credit_card_slip = $this->creditCardSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Credit card payment slip not found');
        } elseif (!$this->_attempt_update_slip($credit_card_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Credit card payment slip updated unsuccessfully', 'status' => 'error']);
        } elseif (($error_message = $this->_attempt_update_entry($credit_card_slip_id, $credit_card_slip)) !== true) {
            $db->transRollback();
            $response = $this->respond(['response' => $error_message, 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Credit card payment updated successfully', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete a credit card payment slip and its entries.
     */
    public function delete_slip()
    {
        if (($response = $this->_api_verification('credit_card_transactions', 'delete_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $credit_card_slip_id = $this->request->getVar('credit_card_slip_id');
        $where                = ['id' => $credit_card_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$credit_card_slip = $this->creditCardSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Credit card payment slip not found');
        } elseif (!$this->_attempt_delete_slip($credit_card_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete credit card payment slip.', 'status' => 'error']);
        } else {
            $this->creditCardEntryModel->delete_by_credit_card_slip_id($credit_card_slip_id, $this->requested_by);
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted credit card payment slip.', 'status' => 'success']);
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
     * Insert the credit_card_slip shell for a create() payment.
     * Amount starts at 0 and is filled in once entries are generated.
     */
    protected function _attempt_create_slip()
    {
        $data = [
            'payment_date'    => $this->request->getVar('payment_date'),
            'reference_no'    => $this->request->getVar('reference_no'),
            'credit_card_id'  => $this->request->getVar('credit_card_id'),
            'supplier_id'     => $this->request->getVar('supplier_id') ?: null,
            'vendor_id'       => $this->request->getVar('vendor_id') ?: null,
            'payee'           => $this->request->getVar('payee'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'particulars'     => $this->request->getVar('particulars'),
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s'),
        ];

        if (!$credit_card_slip_id = $this->creditCardSlipModel->insert($data)) {
            return false;
        }

        return $credit_card_slip_id;
    }

    /**
     * Generate credit_card_entry rows for each se_id/amount/type, mark the
     * corresponding supplies/project expense as paid down, and charge the
     * total to the credit card's current_bal (checked against credit_limit).
     */
    protected function _attempt_generate_entry($credit_card_slip_id, $credit_card)
    {
        $se_ids  = $this->request->getVar('se_ids');
        $amounts = $this->request->getVar('amounts');
        $types   = $this->request->getVar('types');

        if (empty($se_ids) || empty($amounts) || empty($types)) {
            return "se_ids, amounts, and types are required.";
        }

        $total = array_sum($amounts);

        $new_bal = (float) $credit_card['current_bal'] + (float) $total;
        if ((float) $credit_card['credit_limit'] > 0 && $new_bal > (float) $credit_card['credit_limit']) {
            return "Credit limit exceeded.";
        }

        foreach ($se_ids as $key => $se_id) {
            $amount = $amounts[$key];
            $type   = $types[$key];

            $data = [
                'credit_card_slip_id' => $credit_card_slip_id,
                'se_id'               => $se_id,
                'type'                => $type,
                'amount'              => $amount,
                'added_by'            => $this->requested_by,
                'added_on'            => date('Y-m-d H:i:s'),
            ];

            if (!$this->creditCardEntryModel->insert($data)) {
                return "Failed to insert credit card entry for se_id: {$se_id}";
            }

            if ($type == 'supplies_expense') {
                if ($supplies_expense = $this->suppliesExpenseModel->get_details_by_id($se_id)) {
                    $new_balance = $supplies_expense[0]['balance'] - $amounts[$key];

                    $order_status = ($new_balance <= 0) ? 'complete' : 'incomplete';

                    $supplies_expense_data = [
                        'paid_amount'  => $supplies_expense[0]['paid_amount'] + $amounts[$key],
                        'balance'      => $new_balance,
                        'order_status' => $order_status,
                        'updated_on'   => date('Y-m-d H:i:s'),
                        'updated_by'   => $this->requested_by,
                    ];

                    if (!$this->suppliesExpenseModel->update($se_id, $supplies_expense_data)) {
                        return "Failed to update supplies expense for se_id: {$se_id}";
                    }
                } else {
                    return "Supplies expense id {$se_id} not found";
                }
            } elseif ($type == 'project_expense') {
                if ($project_expense = $this->projectExpenseModel->get_details_by_id($se_id)) {
                    $paid_amount_total = $project_expense[0]['paid_amount'] + $amounts[$key];
                    $new_status = ($paid_amount_total >= $project_expense[0]['grand_total']) ? 'paid' : 'approved';

                    $project_expense_data = [
                        'paid_amount' => $paid_amount_total,
                        'status'      => $new_status,
                        'updated_on'  => date('Y-m-d H:i:s'),
                        'updated_by'  => $this->requested_by,
                    ];

                    if (!$this->projectExpenseModel->update($se_id, $project_expense_data)) {
                        return "Failed to update project expense for se_id: {$se_id}";
                    }
                } else {
                    return "Project expense id {$se_id} not found";
                }
            }
        }

        if (!$this->creditCardSlipModel->update($credit_card_slip_id, [
            'amount'     => $total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ])) {
            return "Failed to update credit card slip amount.";
        }

        if (!$this->creditCardModel->update($credit_card['id'], [
            'current_bal' => $new_bal,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ])) {
            return "Failed to update credit card balance.";
        }

        return true;
    }

    /**
     * Upload attachments for a credit card payment slip.
     */
    public function _upload_attachments($credit_card_slip_id, $path)
    {
        $files = $this->request->getFileMultiple('attachments');
        $file_path = $path . $credit_card_slip_id;

        if (empty($files)) {
            return false;
        }

        $path = $file_path . '/';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            write_file($path . 'index.html', 'Directory access is forbidden.');
        }

        foreach ($files as $file) {
            if ($file->isValid() && !in_array($file->getExtension(), ['png', 'jpeg', 'jpg'])) {
                return false;
            }

            $max_file_size = 5 * 1024 * 1024; // 5 MB in bytes
            if ($file->getSize() > $max_file_size) {
                return false;
            }

            if ($file->isValid() && !$file->hasMoved()) {
                $extension = $file->getExtension();
                $random_str = bin2hex(random_bytes(4));
                $file_name = pathinfo($file->getName(), PATHINFO_FILENAME) . '_' . $random_str . '.' . $extension;
                $mime_type = $file->getMimeType();

                $file->move($path, $file_name);

                $data = [
                    'credit_card_slip_id' => $credit_card_slip_id,
                    'file_name'           => $file_name,
                    'file_path'           => $file_path,
                    'file_url'            => base_url($file_path . '/' . $file_name),
                    'mime'                => $mime_type,
                    'added_by'            => $this->requested_by,
                    'added_on'            => date('Y-m-d H:i:s'),
                ];

                if (!$this->creditCardSlipAttachmentModel->insert($data)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Update slip fields and attachments.
     */
    protected function _attempt_update_slip($credit_card_slip_id)
    {
        $data = [
            'payment_date'    => $this->request->getVar('payment_date'),
            'reference_no'    => $this->request->getVar('reference_no'),
            'credit_card_id'  => $this->request->getVar('credit_card_id'),
            'supplier_id'     => $this->request->getVar('supplier_id') ?: null,
            'vendor_id'       => $this->request->getVar('vendor_id') ?: null,
            'payee'           => $this->request->getVar('payee'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'particulars'     => $this->request->getVar('particulars'),
            'updated_by'      => $this->requested_by,
            'updated_on'      => date('Y-m-d H:i:s'),
        ];

        if (!$this->creditCardSlipModel->update($credit_card_slip_id, $data)) {
            return false;
        }

        if (!$this->creditCardSlipAttachmentModel->delete_attachment_by_credit_card_slip_id($credit_card_slip_id, $this->requested_by)) {
            return false;
        } elseif (($this->request->getFileMultiple('attachments') ? true : false) && !$this->_upload_attachments($credit_card_slip_id, 'assets/credit_card_payments/')) {
            return false;
        }

        return true;
    }

    /**
     * Delete and regenerate entries for an update() call. Reverses the old
     * slip amount off its original card first, so the credit_limit check in
     * _attempt_generate_entry() runs against the pre-slip balance.
     */
    protected function _attempt_update_entry($credit_card_slip_id, $old_slip)
    {
        $this->creditCardEntryModel->delete_by_credit_card_slip_id($credit_card_slip_id, $this->requested_by);

        if (!$old_credit_card = $this->creditCardModel->select('', ['id' => $old_slip['credit_card_id'], 'is_deleted' => 0], 1)) {
            return "Original credit card not found.";
        }

        if (!$this->creditCardModel->update($old_credit_card['id'], [
            'current_bal' => (float) $old_credit_card['current_bal'] - (float) $old_slip['amount'],
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ])) {
            return "Failed to reverse previous credit card balance.";
        }

        $new_credit_card_id = $this->request->getVar('credit_card_id') ?: $old_slip['credit_card_id'];

        if (!$credit_card = $this->creditCardModel->select('', ['id' => $new_credit_card_id, 'is_deleted' => 0], 1)) {
            return "Credit card not found.";
        }

        return $this->_attempt_generate_entry($credit_card_slip_id, $credit_card);
    }

    /**
     * Soft-delete a credit card payment slip and reverse its effect on the
     * credit card's current_bal.
     */
    protected function _attempt_delete_slip($credit_card_slip)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        if (!$this->creditCardSlipModel->update($credit_card_slip['id'], $values)) {
            return false;
        }

        if (!$credit_card = $this->creditCardModel->select('', ['id' => $credit_card_slip['credit_card_id'], 'is_deleted' => 0], 1)) {
            return false;
        }

        if (!$this->creditCardModel->update($credit_card['id'], [
            'current_bal' => (float) $credit_card['current_bal'] - (float) $credit_card_slip['amount'],
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ])) {
            return false;
        }

        return true;
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
        $this->creditCardSlipModel                  = new Credit_card_slip();
        $this->creditCardSlipAttachmentModel         = new Credit_card_slip_attachment();
        $this->creditCardEntryModel                 = new Credit_card_entry();
        $this->creditCardModel                      = new Credit_card();
        $this->suppliesExpenseModel                  = model('App\Models\Supplies_expense');
        $this->projectExpenseModel                   = new Project_expense();
        $this->webappResponseModel                  = new Webapp_response();
    }
}

<?php

namespace App\Controllers;

class Supplies_invoice_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get supplies_invoice_payment
     */
    public function get_supplies_invoice_payment()
    {
        if (($response = $this->_api_verification('supplies_invoice_payments', 'get_supplies_invoice_payment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplies_invoice_payment_id = $this->request->getVar('supplies_invoice_payment_id') ? : null;
        $supplies_invoice_payment    = $supplies_invoice_payment_id ? $this->suppliesInvoicePaymentModel->get_details_by_id($supplies_invoice_payment_id) : null;
        $supplies_receive            = $supplies_invoice_payment ? $this->suppliesReceiveModel->get_details_by_id($supplies_invoice_payment[0]['supplies_receive_id']) : null;

        if (!$supplies_invoice_payment) {
            $response = $this->failNotFound('No supplies invoice sale payment found');
        } else {
            $supplies_invoice_payment[0]['supplies_receive'] = $supplies_receive;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $supplies_invoice_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all supplies_invoice_payments
     */
    public function get_all_supplies_invoice_payment()
    {
        if (($response = $this->_api_verification('supplies_invoice_payments', 'get_all_supplies_invoice_payment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplies_invoice_payments = $this->suppliesInvoicePaymentModel->get_all();

        if (!$supplies_invoice_payments) {
            $response = $this->failNotFound('No supplies invoice sale payment found');
        } else {
            foreach ($supplies_invoice_payments as $key => $supplies_invoice_payment) {
                $supplies_receive = $this->suppliesReceiveModel->get_details_by_id($supplies_invoice_payment['supplies_receive_id']);
                $supplies_invoice_payments[$key]['supplies_receive'] = $supplies_receive;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $supplies_invoice_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create supplies_invoice_payment
     */
    public function create()
    {
        if (($response = $this->_api_verification('supplies_invoice_payments', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$supplies_invoice_payment_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$response = $this->_attempt_upload_file_base64($this->suppliesInvoicePaymentAttachmentModel, ['supplies_invoice_payment_id' => $supplies_invoice_payment_id]) AND
            $response === false) {
            $db->transRollback();
            $response = $this->respond(['response' => 'supplies_invoice_payment_attachment file upload failed']);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'                     => 'success',
                'supplies_invoice_payment_id' => $supplies_invoice_payment_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update supplies_invoice_payment
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('supplies_invoice_payments', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id'         => $this->request->getVar('supplies_invoice_payment_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$supplies_invoice_payment = $this->suppliesInvoicePaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('supplies_invoice_payment not found');
        } elseif (!$this->_attempt_update($supplies_invoice_payment['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'supplies_invoice_payment updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete supplies_invoice_payments
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('supplies_invoice_payments', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('supplies_invoice_payment_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$supplies_invoice_payment = $this->suppliesInvoicePaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('supplies_invoice_payment not found');
        } elseif (!$this->_attempt_delete($supplies_invoice_payment)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'supplies_invoice_payment deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search supplies_invoice_payments based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('supplies_invoice_payments', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
    
        $supplies_expense_id         = $this->request->getVar('supplies_expense_id') ?? null;
        $supplies_receive_id = $this->request->getVar('supplies_receive_id') ?? null;
        $payment_method     = $this->request->getVar('payment_method') ?? null;
        $payment_date_from  = $this->request->getVar('payment_date_from') ?? null;
        $payment_date_to    = $this->request->getVar('payment_date_to') ?? null;
        $from_bank_id       = $this->request->getVar('from_bank_id') ?? null;
        $cheque_number      = $this->request->getVar('cheque_number') ?? null;
        $cheque_date_from   = $this->request->getVar('cheque_date_from') ?? null;
        $cheque_date_to     = $this->request->getVar('cheque_date_to') ?? null;
        $reference_number   = $this->request->getVar('reference_number') ?? null;
        $transaction_number = $this->request->getVar('transaction_number') ?? null;
        $branch_name        = $this->request->getVar('branch_name') ?? null;
        $date_from          = $this->request->getVar('date_from') ?? null;
        $date_to            = $this->request->getVar('date_to') ?? null;
    
        if (!$supplies_invoice_payments = $this->suppliesInvoicePaymentModel->search($supplies_expense_id, $supplies_receive_id, $payment_method, $payment_date_from, $payment_date_to, $from_bank_id, $cheque_number, $cheque_date_from, $cheque_date_to, $reference_number, $transaction_number, $branch_name, $date_from, $date_to)) {
            $response = $this->failNotFound('No supplies invoice sale payment found');
        } else {
            $total_paid_amount = 0;  // Initialize the total paid amount variable
            foreach ($supplies_invoice_payments as $key => $supplies_invoice_payment) {
                $supplies_invoice_payments[$key]['supplies_receive'] = $this->suppliesReceiveModel->get_details_by_id($supplies_invoice_payment['supplies_receive_id']);
                $total_paid_amount += (float)$supplies_invoice_payment['paid_amount'];  // Add paid amount to the total
            }
    
            $response = $this->respond([
                'status' => 'success',
                'data'   => $supplies_invoice_payments,
                'paid_amount' => $total_paid_amount  // Include the total paid amount in the response
            ]);
        }
    
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // Private methods
    // --------------------------------------------------------------------

    /**
     * Create supplies_invoice_payments
     */
    private function _attempt_create()
    {        
        if ($supplies_receive = $this->suppliesReceiveModel->get_details_by_id($this->request->getVar('supplies_receive_id'))) {
            $supplies_receive = $supplies_receive[0];
        } else {
            $this->errorMessage = 'Invalid supplies receive id';
            return false;
        }

        $values = [
            'supplies_expense_id' => $this->request->getVar('supplies_expense_id'),
            'supplies_receive_id' => $supplies_receive['id'],
            'payment_type'       => $this->request->getVar('payment_type'),
            'payment_date'       => $this->request->getVar('payment_date'),
            'remarks'            => $this->request->getVar('remarks'),
            'from_bank_id'       => $this->request->getVar('from_bank_id'),
            'to_bank_name'       => $this->request->getVar('to_bank_name'),
            'to_bank_id'         => $this->request->getVar('to_bank_id'),
            'cheque_number'      => $this->request->getVar('cheque_number'),
            'cheque_date'        => $this->request->getVar('cheque_date'),
            'reference_number'   => $this->request->getVar('reference_number'),
            'transaction_number' => $this->request->getVar('transaction_number'),
            'payment_description'=> $this->request->getVar('payment_description'),
            'invoice_no'         => $this->request->getVar('invoice_no'),
            'term_day'           => $this->request->getVar('term_day'),
            'delivery_address'   => $this->request->getVar('delivery_address'),
            'paid_amount'        => $this->request->getVar('paid_amount'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'subtotal'           => $this->request->getVar('subtotal'),
            'service_fee'        => $this->request->getVar('service_fee'),
            'delivery_fee'       => $this->request->getVar('delivery_fee'),
            'withholding_tax'    => $this->request->getVar('withholding_tax'),
            'deposit_date'       => $this->request->getVar('deposit_date'),
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        if (!$supplies_invoice_payment_id = $this->suppliesInvoicePaymentModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
           return false;
        }
    
        if (!$this->_record_sale_payment($supplies_receive, $values)) {
            return false;
        }
        
        return $supplies_invoice_payment_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($supplies_invoice_payment_id)
    {
        // revert previous payment
        $supplies_invoice_payment = $this->suppliesInvoicePaymentModel->get_details_by_id($supplies_invoice_payment_id);
        $supplies_invoice_payment = $supplies_invoice_payment[0];
        $supplies_receive_id = $supplies_invoice_payment['supplies_receive_id'];
        $supplies_receive = $this->suppliesReceiveModel->get_details_by_id($supplies_receive_id);
        $supplies_receive = $supplies_receive[0];

        $new_values = [
            'balance'     => $supplies_receive['balance'] + $supplies_invoice_payment['paid_amount'],
            'paid_amount' => $supplies_receive['paid_amount'] - $supplies_invoice_payment['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($new_values['balance'] > 0) {
            $new_values['payment_status'] = 'open_bill';
            $new_values['fully_paid_on']  = null;
        }

        if (!$this->suppliesReceiveModel->update($supplies_receive_id, $new_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        $values = [
            'supplies_expense_id'      => $this->request->getVar('supplies_expense_id'),
            'supplies_invoice_payment_id' => $supplies_invoice_payment_id,
            'payment_type'       => $this->request->getVar('payment_type'),
            'payment_date'       => $this->request->getVar('payment_date'),
            'remarks'            => $this->request->getVar('remarks'),
            'from_bank_id'       => $this->request->getVar('from_bank_id'),
            'to_bank_id'         => $this->request->getVar('to_bank_id'),
            'to_bank_name'       => $this->request->getVar('to_bank_name'),
            'cheque_number'      => $this->request->getVar('cheque_number'),
            'cheque_date'        => $this->request->getVar('cheque_date'),
            'reference_number'   => $this->request->getVar('reference_number'),
            'transaction_number' => $this->request->getVar('transaction_number'),
            'payment_description'=> $this->request->getVar('payment_description'),
            'invoice_no'         => $this->request->getVar('invoice_no'),
            'term_day'           => $this->request->getVar('term_day'),
            'delivery_address'   => $this->request->getVar('delivery_address'),
            'paid_amount'        => $this->request->getVar('paid_amount'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'subtotal'           => $this->request->getVar('subtotal'),
            'service_fee'        => $this->request->getVar('service_fee'),
            'delivery_fee'       => $this->request->getVar('delivery_fee'),
            'withholding_tax'    => $this->request->getVar('withholding_tax'),
            'deposit_date'       => $this->request->getVar('deposit_date'),
            'updated_by'         => $this->requested_by,
            'updated_on'         => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesInvoicePaymentModel->update($supplies_invoice_payment_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$supplies_receive = $this->suppliesReceiveModel->get_details_by_id($values['supplies_receive_id'])) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_record_sale_payment($supplies_receive[0], $values))
            return false;

        return true;
    }

    /**
     * Record Supplies Sale Payment
     */
    protected function _record_sale_payment($supplies_receive, $values) {
        $update_values = [
            'balance'     => (float)$supplies_receive['balance'] - (float)$values['paid_amount'],
            'paid_amount' => (float)$supplies_receive['paid_amount'] + (float)$values['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($update_values['balance'] == 0) {
            $update_values['payment_status'] = 'closed_bill';
            $update_values['fully_paid_on']  = date('Y-m-d H:i:s');
        }

        if (!$this->suppliesReceiveModel->update($supplies_receive['id'], $update_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($supplies_invoice_payment)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesInvoicePaymentModel->update($supplies_invoice_payment['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        // Restore the credit limit back
        $supplies_receive = $this->suppliesReceiveModel->get_details_by_id($supplies_invoice_payment['supplies_receive_id'])[0];
        // Update the paid amount
        $new_values = [
            'balance'     => $supplies_receive['balance'] + $supplies_invoice_payment['paid_amount'],
            'paid_amount' => $supplies_receive['paid_amount'] - $supplies_invoice_payment['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($new_values['balance'] > 0) {
            $new_values['payment_status'] = 'open_bill';
            $new_values['fully_paid_on']  = null;
        }

        if (!$this->suppliesReceiveModel->update($supplies_receive['id'], $new_values)) {
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
        $this->suppliesInvoicePaymentModel = model('App\Models\Supplies_invoice_payment');
        $this->suppliesExpenseModel   = model('App\Models\Supplies_expense');
        $this->suppliesReceiveModel        = model('App\Models\Supplies_receive');
        $this->suppliesInvoicePaymentAttachmentModel = model('App\Models\Supplies_invoice_payment_attachment');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}

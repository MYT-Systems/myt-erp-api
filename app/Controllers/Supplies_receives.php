<?php

namespace App\Controllers;

class Supplies_receives extends MYTController
{
    
    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get receive
     */
    public function get_receive()
    {
        if (($response = $this->_api_verification('receive', 'get_receive')) !== true)
            return $response;

        $supplies_receive_id    = $this->request->getVar('supplies_receive_id') ? : null;
        $receive                = $supplies_receive_id ? $this->suppliesReceiveModel->get_details_by_id($supplies_receive_id) : null;
        $supplies_receive_items = $supplies_receive_id ? $this->suppliesReceiveItemModel->get_details_by_receive_id($supplies_receive_id) : null;


        if (!$receive) {
            $response = $this->failNotFound('No receive found');
        } else {
            $receive[0]['supplies_receive_items']  = $supplies_receive_items;

            $response = $this->respond([
                'status' => 'success',
                'data' => $receive
            ]);
        }


        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all receive
     */
    public function get_all_receive()
    {
        if (($response = $this->_api_verification('receive', 'get_all_receive')) !== true)
            return $response;

        $supplier_id = $this->request->getVar('supplier_id') ? : null;
        $vendor_id   = $this->request->getVar('vendor_id') ? : null;
        $bill_type   = $this->request->getVar('bill_type') ? : null;

        $supplies_receives = $this->suppliesReceiveModel->get_all_receive($supplier_id, $vendor_id, $bill_type);

        if (!$supplies_receives) {
            $response = $this->failNotFound('No receive found');
        } else {
            foreach ($supplies_receives as $key => $receive) {
                $supplies_receives[$key]['supplies_receive_items'] = $this->suppliesReceiveItemModel->get_details_by_receive_id($receive['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $supplies_receives
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create receive
     */
    public function create()
    {
        if (($response = $this->_api_verification('supplies_receives', 'create')) !== true)
            return $response;

        if ($this->_has_duplicate_invoice()) {
            $response = $this->fail(['response' => 'Either waybill, invoice, or DR number is duplicate.', 'status' => 'error']);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        $se_id             = $this->request->getVar('se_id');
        $branch_id         = $this->request->getVar('branch_id');
        $supplies_expense  = $this->suppliesExpenseModel->get_details_by_id($se_id);
        if ($supplies_expense && $supplies_expense[0]['order_status'] == 'complete') {
            $response = $this->fail(['response' => 'Supplies Expense is already complete.', 'status' => 'error']);
        } elseif (!$supplies_receive_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else if (!$this->_attempt_generate_supplies_receive_items($supplies_receive_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'supplies_receive_id' => $supplies_receive_id,
                'branch_id'           => $branch_id,
                'response'            => 'Receive created successfully.',
                'status'              => 'success'
            ]);
        }

        // Check if supplies_expense is with payment and if receive was created
        if ($supplies_expense && isset($supplies_receive_id) && $supplies_expense[0]['with_payment']) {
            $this->_attempt_create_payment($supplies_receive_id, $supplies_expense[0]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
    * Check duplicate invoice, waybill, or DR numbers
    */
    protected function _has_duplicate_invoice()
    {
        $waybill_no = $this->request->getVar('waybill_no') ? : NULL;
        $invoice_no = $this->request->getVar('invoice_no') ? : NULL;
        $dr_no = $this->request->getVar('dr_no') ? : NULL;

        return (($this->suppliesReceiveModel->check_duplicate_invoice($waybill_no, $invoice_no, $dr_no)) ? true : false);
    }

    /**
     * Update Supplies_receive
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('supplies_receives', 'receive_receive_orders')) !== true)
            return $response;

        $supplies_receive_id = $this->request->getVar('supplies_receive_id');
        $branch_id = $this->request->getVar('branch_id');
        $where      = ['id' => $supplies_receive_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();
        if (!$receive = $this->suppliesReceiveModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies_receive not found');
        } elseif (!$this->_attempt_update_receive($supplies_receive_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_update_supplies_receive_items($supplies_receive_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Supplies_receive updated successfully.', 'branch_id' => $branch_id,]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    /**
     * Delete receive
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('supplies_receives', 'delete')) !== true)
            return $response;

        $supplies_receive_id = $this->request->getVar('supplies_receive_id');
        $where = ['id' => $supplies_receive_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();
        
        if (!$receive = $this->suppliesReceiveModel->select('', $where, 1)) {
            $response = $this->failNotFound('receive not found');
        } elseif (!$this->_attempt_delete($supplies_receive_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->suppliesReceiveItemModel->delete_by_receive_id($supplies_receive_id, $this->requested_by, $this->db)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Receive deleted successfully.']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search receive based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('receive', 'search')) !== true)
            return $response;
        
        $se_id                 = $this->request->getVar('se_id') ? : null;
        $branch_id             = $this->request->getVar('branch_id') ? : null;
        $supplier_id           = $this->request->getVar('supplier_id') ? : null;
        $vendor_id             = $this->request->getVar('vendor_id') ? : null;
        $supplies_receive_date = $this->request->getVar('supplies_receive_date') ? : null;
        $waybill_no            = $this->request->getVar('waybill_no') ? : null;
        $invoice_no            = $this->request->getVar('invoice_no') ? : null;
        $dr_no                 = $this->request->getVar('dr_no') ? : null;
        $remarks               = $this->request->getVar('remarks') ? : null;
        $purchase_date_from    = $this->request->getVar('purchase_date_from') ? : null;
        $purchase_date_to      = $this->request->getVar('purchase_date_to') ? : null;
        $se_receive_date_from  = $this->request->getVar('se_receive_date_from') ? : null;
        $se_receive_date_to    = $this->request->getVar('se_receive_date_to') ? : null;
        $bill_type   = $this->request->getVar('bill_type') ? : null;

        if (!$receives = $this->suppliesReceiveModel->search($branch_id, $se_id, $supplier_id, $vendor_id, $supplies_receive_date, $waybill_no, $invoice_no, $dr_no, $remarks, $purchase_date_from, $purchase_date_to, $se_receive_date_from, $se_receive_date_to, $bill_type)) {
            $response = $this->failNotFound('No receive found');
        } else {
            $summary = [
                'total' => 0,
                'total_paid' => 0,
                'total_balance' => 0
            ];

            foreach ($receives as $key => $receive) {
                // get the payment made on the receive using receive id
                $receives[$key]['payments'] = $this->suppliesPaymentModel->get_all_payment_by_se($receive['id']);

                $summary['total'] += $receive['grand_total'];
                $summary['total_paid'] += $receive['paid_amount'];
                $summary['total_balance'] += $receive['balance'];
            }

            $response = $this->respond([
                'summary'  => $summary,
                'response' => $receives,
                'status'   => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_all_invoice_payments()
    {
        if (($response = $this->_api_verification('supplies_receive', 'get_all_invoice_payments')) !== true)
            return $response;
    
        $se_id            = $this->request->getVar('se_id') ? : null;
        $invoice_payments = $se_id ? $this->suppliesPaymentModel->get_all_payment_by_se($se_id) : null;

        if (!$invoice_payments) {
            $response = $this->failNotFound('No invoice payments found');
        } else {
            $response = $this->respond([
                'se_id'  => $se_id,
                'data'   => $invoice_payments,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get Bills
     */
    public function get_bills()
    {
        if (($response = $this->_api_verification('receive', 'get_bills')) !== true)
            return $response;

        $type = $this->request->getVar('type');
        if (!$supplies_receives = $this->suppliesReceiveModel->get_bills($type)) {
            $response = $this->failNotFound('No receive found');
        } else {
            $response = [];
            $response['data'] = $supplies_receives;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create
     */
    private function _attempt_create()
    {
        $values = [
            'branch_id'              => $this->request->getVar('branch_id') ? : null,
            'se_id'                  => $this->request->getVar('se_id'),
            'supplier_id'            => $this->request->getVar('supplier_id'),
            'vendor_id'              => $this->request->getVar('vendor_id'),
            'purchase_date'          => $this->request->getVar('purchase_date'),
            'supplies_receive_date'  => $this->request->getVar('supplies_receive_date'),
            'type'                   => $this->request->getVar('type'),
            'purpose'                => $this->request->getVar('purpose'),
            'forwarder_id'           => $this->request->getVar('forwarder_id'),
            'expense_type_id'        => $this->request->getVar('expense_type_id'),
            'waybill_no'             => $this->request->getVar('waybill_no'),
            'invoice_no'             => $this->request->getVar('invoice_no'),
            'dr_no'                  => $this->request->getVar('dr_no'),
            'freight_cost'           => $this->request->getVar('freight_cost'),
            'discount'               => $this->request->getVar('discount'),
            'paid_amount'            => $this->request->getVar('paid_amount'),
            'remarks'                => $this->request->getVar('remarks'),
            'added_by'               => $this->requested_by,
            'added_on'               => date('Y-m-d H:i:s'),
        ];

        if (!$supplies_receive_id = $this->suppliesReceiveModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $supplies_receive_id;
    }

    /**
     * Attempt to generate receive items
     */
    private function _attempt_generate_supplies_receive_items($supplies_receive_id)
    {
        $names       = $this->request->getVar('names');
        $quantities  = $this->request->getVar('quantities');
        $units       = $this->request->getVar('units');
        $types       = $this->request->getVar('types');
        $prices      = $this->request->getVar('prices');
        $se_item_ids = $this->request->getVar('se_item_ids');

        $total = 0;
        foreach ($names as $key => $name) {
            $total += $quantities[$key] * $prices[$key];

            // check if the key exists in the se_item_ids array
            if (!isset($se_item_ids[$key])) {
                $se_item_id = null;
            } else {
                $se_item_id = $se_item_ids[$key];
            }

            $values = [
                'se_receive_id' => $supplies_receive_id,
                'se_item_id'    => $se_item_id,
                'name'          => $name,
                'qty'           => $quantities[$key],
                'unit'          => $units[$key],
                'price'         => $prices[$key],
                'total'         => $quantities[$key] * $prices[$key],
                'added_by'      => $this->requested_by,
                'added_on'      => date('Y-m-d H:i:s'),
            ];

            if (!$this->suppliesReceiveItemModel->insert($values)) {
                return false;
            }

            // Update the po item receive quanity
            if ($se_item_id && !$this->seItemModel->update_receive_qty_by_id($se_item_id, $quantities[$key], $this->requested_by)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        // Update if supplies expense is complete
        $se_id = $this->request->getVar('se_id');
        $is_complete = $this->seItemModel->is_all_received_by_se_id($se_id);

        if ($is_complete) {
            $values = [
                'order_status' => 'complete',
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ];
        } else {
            $values = [
                'order_status' => 'incomplete',
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ];
        }

        if (!$this->suppliesExpenseModel->update($se_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        $discount     = $this->request->getVar('discount');
        $freight_cost = $this->request->getVar('freight_cost');

        // Get supplies receive 
        $paid_amount = 0;
        if ($supplies_receive = $this->suppliesReceiveModel->get_details_by_id($supplies_receive_id)) {
            $paid_amount = (float)$supplies_receive[0]['paid_amount'] ?? 0;
        }
        
        $values = [
            'subtotal'      => (float)$total,
            'grand_total'   => (float)$total - (float)$discount + (float)$freight_cost,
            'paid_amount'   => (float)$paid_amount,
            'balance'       => ((float)$total - (float)$discount + (float)$freight_cost) - (float)$paid_amount,
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesReceiveModel->update($supplies_receive_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt update
     */
    function _attempt_update_receive($supplies_receive_id)
    {
        $values = [
            'branch_id'              => $this->request->getVar('branch_id'),
            'se_id'                  => $this->request->getVar('se_id'),
            'supplier_id'            => $this->request->getVar('supplier_id'),
            'vendor_id'              => $this->request->getVar('vendor_id'),
            'purchase_date'          => $this->request->getVar('purchase_date'),
            'supplies_receive_date'  => $this->request->getVar('supplies_receive_date'),
            'type'                   => $this->request->getVar('type'),
            'purpose'                => $this->request->getVar('purpose'),
            'forwarder_id'           => $this->request->getVar('forwarder_id'),
            'expense_type_id'        => $this->request->getVar('expense_type_id'),
            'waybill_no'             => $this->request->getVar('waybill_no'),
            'invoice_no'             => $this->request->getVar('invoice_no'),
            'dr_no'                  => $this->request->getVar('dr_no'),
            'freight_cost'           => $this->request->getVar('freight_cost'),
            'discount'               => $this->request->getVar('discount'),
            'remarks'                => $this->request->getVar('remarks'),
            'updated_by'             => $this->requested_by,
            'updated_on'             => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesReceiveModel->update($supplies_receive_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    function _attempt_update_supplies_receive_items($supplies_receive_id)
    {
        $supplies_receive_items = $this->suppliesReceiveItemModel->get_by_receive_id($supplies_receive_id);

        // Decrease the receive_qty based on the receive items
        foreach ($supplies_receive_items as $supplies_receive_item) {
            if ($supplies_receive_item['se_item_id'] && !$this->seItemModel->update_receive_qty_by_id($supplies_receive_item['se_item_id'], $supplies_receive_item['qty'] * -1, $this->requested_by)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        if (!$this->suppliesReceiveItemModel->delete_by_receive_id($supplies_receive_id, $this->requested_by, $this->db)) { 
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        return $this->_attempt_generate_supplies_receive_items($supplies_receive_id);
    }


    /**
     * Attempt delete
     */
    protected function _attempt_delete($supplies_receive_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesReceiveModel->update($supplies_receive_id, $values)) {
            return false;
        } 
        
        return true;
    }

    /**
     * Attempt add payment
     */
    protected function _attempt_create_payment($supplies_receive_id, $supplies_expense) {
        // Get the supplies_expense payment
        if (!$supplies_expense_payment = $this->suppliesExpensePaymentModel->get_details_by_supplies_expense_id($supplies_expense['id'])) {
            $this->db->close();
            var_dump("Error getting supplies_expense payment");
            return false;
        }

        // Check if there is balance that can be use for payment
        $supplies_expense_payment = $supplies_expense_payment[0];
        if ($supplies_expense_payment['balance'] <= 0) {
            $this->db->close();
            var_dump("supplies_expense payment balance is 0");
            return false;
        }

        // Get the supplies_receive
        if (!$supplies_receive = $this->suppliesReceiveModel->get_details_by_id($supplies_receive_id)) {
            $this->db->close();
            var_dump("Error getting supplies_receive");
            return false;
        }
        $supplies_receive = $supplies_receive[0];

        // Get the balance of the supplies_receive
        $balance = $supplies_receive['balance'];
        // Compute what is the amount that can be paid
        $paid_amount = ($supplies_expense_payment['balance'] - $balance) >= 0 ? $balance : $supplies_expense_payment['balance'];

        // Update the supplies_expense payment
        $new_supplies_expense_payment_balance = $supplies_expense_payment['balance'] - $paid_amount;
        $supplies_expense_payment_values = [
            'balance' => $new_supplies_expense_payment_balance,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesExpensePaymentModel->update($supplies_expense_payment['id'], $supplies_expense_payment_values)) {
            $this->db->transRollback();
            $this->db->close();
            var_dump("Error updating supplies_expense payment");
            return false;
        }

        // Update the supplies_receive
        $supplies_receive_values = [
            'paid_amount' => $supplies_receive['paid_amount'] + $paid_amount,
            'balance' => $supplies_receive['balance'] - $paid_amount,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesReceiveModel->update($supplies_receive['id'], $supplies_receive_values)) {
            $this->db->transRollback();
            $this->db->close();
            var_dump("Error updating supplies_receive");
            return false;
        }


        // ----------------- Create the payment -----------------
        // Check the supplies_expense payment details
        if (!$supplies_expense_payment_details = $this->suppliesExpensePaymentDetailModel->get_details_by_supplies_expense_payment_id($supplies_expense_payment['id'])) {
            $this->db->close();
            var_dump("Error getting supplies_expense payment details");
            return false;
        }

        // Create the commmon values for slips
        $slip_details = [
            'status'              => 'pending',
            'supplies_expense_payment_id' => $supplies_expense_payment['id'],
            'added_by'            => $this->requested_by,
            'added_on'            => date('Y-m-d H:i:s'),
        ];

        // Create the common values for entries
        $entry_details = [
            'se_id' => $supplies_receive['id'],
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
        ];

        // Loop through the supplies_expense payment details and create the payment
        foreach ($supplies_expense_payment_details as $supplies_expense_payment_detail) {
            // Check if the payment detail balance is 0
            if ($supplies_expense_payment_detail['balance'] <= 0) {
                // Skip since the payment detail is used up
                continue;
            }

            // Compute the amount that can be paid using the payment detail
            $payment_amount = ($supplies_expense_payment_detail['balance'] - $paid_amount) >= 0 ? $paid_amount : $supplies_expense_payment_detail['balance'];
            $paid_amount -= $payment_amount;

            // This stops the loop from adding more payment details
            if ($payment_amount <= 0) {
                // Break the loop since the payment amount is 0
                break;
            }

            // Update the balance of supplies_expense_payment_detail
            $new_supplies_expense_payment_detail_balance = $supplies_expense_payment_detail['balance'] - $payment_amount;
            $supplies_expense_payment_detail_values = [
                'balance' => $new_supplies_expense_payment_detail_balance,
                'updated_by' => $this->requested_by,
                'updated_on' => date('Y-m-d H:i:s')
            ];

            if (!$this->suppliesExpensePaymentDetailModel->update($supplies_expense_payment_detail['id'], $supplies_expense_payment_detail_values)) {
                $this->db->transRollback();
                $this->db->close();
                var_dump("Error updating supplies_expense payment detail");
                return false;
            }

            // Assigning the unique values for slips
            $slip_details['payee']       = $supplies_expense_payment_detail['payee'];
            $slip_details['particulars'] = $supplies_expense_payment_detail['particulars'];
            $slip_details['vendor_id']   = $supplies_expense_payment_detail['vendor_id'];
            $slip_details['supplier_id'] = $supplies_expense_payment_detail['supplier_id'];
            $slip_details['amount']      = $payment_amount;

            // Assigning the unique values for entry
            $entry_details['amount'] = $payment_amount;

            // Declaring global variables
            $slip_model = $entry_model = $merged_slip_details = $merged_entry_details = null;

            $payment_type = $supplies_expense_payment_detail['payment_type'];
            switch ($payment_type) {
                case 'cash':
                    // Create the details for the cash slip
                    $cash_slip['payment_date'] = $supplies_expense_payment_detail['payment_date'];
                    $merged_slip_details = array_merge($slip_details, $cash_slip);

                    // Declaring the models
                    $slip_model  = $this->SECashSlipModel;
                    $entry_model = $this->SECashEntryModel;
                    break;
                case 'check':
                    // Create the details for the check slip
                    $check_slip['bank_id']     = $supplies_expense_payment_detail['from_bank_id'];
                    $check_slip['check_no']    = $supplies_expense_payment_detail['check_no'];
                    $check_slip['check_date']  = $supplies_expense_payment_detail['check_date'];
                    $check_slip['issued_date'] = $supplies_expense_payment_detail['issued_date'];
                    $merged_slip_details       = array_merge($slip_details, $check_slip);

                    // declaring the models
                    $slip_model  = $this->SECheckSlipModel;
                    $entry_model = $this->SECheckEntryModel;
                    break;
                case 'bank':
                    // Create the details for the bank slip
                    $bank_slip['bank_from']         = $supplies_expense_payment_detail['from_bank_id'];
                    $bank_slip['from_account_no']   = $supplies_expense_payment_detail['from_account_no'];
                    $bank_slip['from_account_name'] = $supplies_expense_payment_detail['from_account_name'];
                    $bank_slip['bank_to']           = $supplies_expense_payment_detail['to_bank_name'];
                    $bank_slip['to_account_no']     = $supplies_expense_payment_detail['to_account_no'];
                    $bank_slip['to_account_name']   = $supplies_expense_payment_detail['to_account_name'];
                    $bank_slip['transaction_fee']   = $supplies_expense_payment_detail['transaction_fee'];
                    $bank_slip['reference_no']      = $supplies_expense_payment_detail['reference_no'];
                    $bank_slip['payment_date']      = $supplies_expense_payment_detail['payment_date'];
                    $merged_slip_details            = array_merge($slip_details, $bank_slip);

                    // declaring the models
                    $slip_model  = $this->SEBankSlipModel;
                    $entry_model = $this->SEBankEntryModel;
                    break;
            }

            // Insert the entry
            if (!$this->_insert_data($payment_type, $slip_model, $entry_model, $merged_slip_details, $entry_details, $this->db)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Insert Entry and slip
     */
    private function _insert_data($payment_type, $slip_model, $entry_model, $merged_slip_details, $entry_details) {
        if (!$slip_id = $slip_model->insert($merged_slip_details)) {
            var_dump($this->db->error()['message']);
            $this->db->transRollback();
            $this->db->close();
          
            return false;
        }
        
        $entry_slip_id = null;
        switch ($payment_type) {
            case 'cash':
                $entry_slip_id['se_cash_slip_id'] = $slip_id;
                break;
            case 'check':
                $entry_slip_id['se_check_slip_id'] = $slip_id;
                break;
            case 'bank':
                $entry_slip_id['se_bank_slip_id'] = $slip_id;
                break;
        }

        $merged_entry_details = array_merge($entry_details, $entry_slip_id);
        if (!$entry_model->insert($merged_entry_details)) {
            $this->db->transRollback();
            $this->db->close();
            var_dump("Error inserting entry");
            return false;
        }

        return $slip_id;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->suppliesReceiveModel              = model('App\Models\Supplies_receive');
        $this->suppliesReceiveItemModel          = model('App\Models\Supplies_receive_item');
        $this->suppliesExpenseModel              = model('App\Models\Supplies_expense');
        $this->suppliesPaymentModel              = model('App\Models\SE_payment');
        $this->seItemModel                       = model('App\Models\SE_item');
        $this->SECashSlipModel                   = model('App\Models\SE_cash_slip');
        $this->SECashEntryModel                  = model('App\Models\SE_cash_entry');
        $this->SECheckSlipModel                  = model('App\Models\SE_check_slip');
        $this->SECheckEntryModel                 = model('App\Models\SE_check_entry');
        $this->SEBankSlipModel                   = model('App\Models\SE_bank_slip');
        $this->SEBankEntryModel                  = model('App\Models\SE_bank_entry');
        $this->suppliesExpensePaymentModel       = model('App\Models\Supplies_expense_payment');
        $this->suppliesExpensePaymentDetailModel = model('App\Models\Supplies_expense_payment_detail');
        $this->webappResponseModel               = model('App\Models\Webapp_response');
    }
}

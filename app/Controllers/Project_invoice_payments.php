<?php

namespace App\Controllers;

class Project_invoice_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get project_invoice_payment
     */
    public function get_project_invoice_payment()
    {
        if (($response = $this->_api_verification('project_invoice_payments', 'get_project_invoice_payment')) !== true)
            return $response;

        $project_invoice_payment_id = $this->request->getVar('project_invoice_payment_id') ? : null;
        $project_invoice_payment    = $project_invoice_payment_id ? $this->projectInvoicePaymentModel->get_details_by_id($project_invoice_payment_id) : null;
        $project_invoice            = $project_invoice_payment ? $this->projectInvoiceModel->get_details_by_id($project_invoice_payment[0]['project_invoice_id']) : null;

        if (!$project_invoice_payment) {
            $response = $this->failNotFound('No project invoice sale payment found');
        } else {
            $project_invoice_payment[0]['project_invoice'] = $project_invoice;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $project_invoice_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all project_invoice_payments
     */
    public function get_all_project_invoice_payment()
    {
        if (($response = $this->_api_verification('project_invoice_payments', 'get_all_project_invoice_payment')) !== true)
            return $response;

        $project_invoice_payments = $this->projectInvoicePaymentModel->get_all();

        if (!$project_invoice_payments) {
            $response = $this->failNotFound('No project invoice sale payment found');
        } else {
            foreach ($project_invoice_payments as $key => $project_invoice_payment) {
                $project_invoice = $this->projectInvoiceModel->get_details_by_id($project_invoice_payment['project_invoice_id']);
                $project_invoice_payments[$key]['project_invoice'] = $project_invoice;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $project_invoice_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create project_invoice_payment
     */
    public function create()
    {
        if (($response = $this->_api_verification('project_invoice_payments', 'create')) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$project_invoice_payment_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$response = $this->_attempt_upload_file_base64($this->projectInvoicePaymentAttachmentModel, ['project_invoice_payment_id' => $project_invoice_payment_id]) AND
            $response === false) {
            $db->transRollback();
            $response = $this->respond(['response' => 'project_invoice_payment_attachment file upload failed']);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'                     => 'success',
                'project_invoice_payment_id' => $project_invoice_payment_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update project_invoice_payment
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('project_invoice_payments', 'update')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('project_invoice_payment_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$project_invoice_payment = $this->projectInvoicePaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_invoice_payment not found');
        } elseif (!$this->_attempt_update($project_invoice_payment['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'project_invoice_payment updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete project_invoice_payments
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('project_invoice_payments', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('project_invoice_payment_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$project_invoice_payment = $this->projectInvoicePaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_invoice_payment not found');
        } elseif (!$this->_attempt_delete($project_invoice_payment)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'project_invoice_payment deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search project_invoice_payments based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('project_invoice_payments', 'search')) !== true)
            return $response;
    
        $project_id         = $this->request->getVar('project_id') ?? null;
        $customer_id        = $this->request->getVar('customer_id') ?? null;
        $project_invoice_id = $this->request->getVar('project_invoice_id') ?? null;
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
    
        if (!$project_invoice_payments = $this->projectInvoicePaymentModel->search($project_id, $customer_id, $project_invoice_id, $payment_method, $payment_date_from, $payment_date_to, $from_bank_id, $cheque_number, $cheque_date_from, $cheque_date_to, $reference_number, $transaction_number, $branch_name, $date_from, $date_to)) {
            $response = $this->failNotFound('No project invoice sale payment found');
        } else {
            $total_paid_amount = 0;  // Initialize the total paid amount variable
            foreach ($project_invoice_payments as $key => $project_invoice_payment) {
                $project_invoice_payments[$key]['project_invoice'] = $this->projectInvoiceModel->get_details_by_id($project_invoice_payment['project_invoice_id']);
                $total_paid_amount += (float)$project_invoice_payment['paid_amount'];  // Add paid amount to the total
            }
    
            $response = $this->respond([
                'status' => 'success',
                'data'   => $project_invoice_payments,
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
     * record inventory 
     */
    private function _record_inventory($project_invoice)
    {
        $project_invoice_items = $this->projectInvoiceItemModel->get_details_by_project_invoices_id($project_invoice['id']);
        foreach ($project_invoice_items as $project_invoice_item) {
            if ($item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($project_invoice['seller_project_id'], $project_invoice_item['item_id'], $project_invoice_item['unit'])) {
                if ($seller_inventory = $this->inventoryModel->get_inventory_detail($project_invoice_item['item_id'], $project_invoice['seller_project_id'], $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $seller_inventory[0]['current_qty'] - $project_invoice_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->update($seller_inventory[0]['id'], $new_values)) {
                        $this->errorMessage = $this->db->error()['message'];
                        return false;
                    }
                }
    
                if ($buyer_inventory = $this->inventoryModel->get_inventory_detail($project_invoice_item['item_id'], $project_invoice['buyer_project_id'], $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $buyer_inventory[0]['current_qty'] + $project_invoice_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->update($buyer_inventory[0]['id'], $new_values)) {
                        $this->errorMessage = $this->db->error()['message'];
                        return false;
                    }
                } else {
                    $new_values = [
                        'item_id'       => $project_invoice_item['item_id'],
                        'project_id'     => $project_invoice['buyer_project_id'],
                        'item_unit_id'  => $item_unit[0]['id'],
                        'beginning_qty' => 0,
                        'current_qty'   => $project_invoice_item['qty'],
                        'added_by'      => $this->requested_by,
                        'added_on'      => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->insert($new_values)) {
                        $this->errorMessage = $this->db->error()['message'];
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Create project_invoice_payments
     */
    private function _attempt_create()
    {        
        if ($project_invoice = $this->projectInvoiceModel->get_details_by_id($this->request->getVar('project_invoice_id'))) {
            $project_invoice = $project_invoice[0];
        } else {
            $this->errorMessage = 'Invalid project invoice id';
            return false;
        }

        $values = [
            'project_id'      => $this->request->getVar('project_id'),
            'project_invoice_id' => $project_invoice['id'],
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

        if (!$project_invoice_payment_id = $this->projectInvoicePaymentModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
           return false;
        }
    
        if (!$this->_record_sale_payment($project_invoice, $values)) {
            return false;
        }
        
        return $project_invoice_payment_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($project_invoice_payment_id)
    {
        // revert previous payment
        $project_invoice_payment = $this->projectInvoicePaymentModel->get_details_by_id($project_invoice_payment_id);
        $project_invoice_payment = $project_invoice_payment[0];
        $project_invoice_id = $project_invoice_payment['project_invoice_id'];
        $project_invoice = $this->projectInvoiceModel->get_details_by_id($project_invoice_id);
        $project_invoice = $project_invoice[0];

        $new_values = [
            'balance'     => $project_invoice['balance'] + $project_invoice_payment['paid_amount'],
            'paid_amount' => $project_invoice['paid_amount'] - $project_invoice_payment['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($new_values['balance'] > 0) {
            $new_values['payment_status'] = 'open_bill';
            $new_values['fully_paid_on']  = null;
        }

        if (!$this->projectInvoiceModel->update($project_invoice_id, $new_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        // Restore the credit limit back
        // if (!$this->_update_credit_limit($project_invoice, (float)$project_invoice_payment['paid_amount'] * -1)) {
        //     return false;
        // }

        $values = [
            'project_id'      => $this->request->getVar('project_id'),
            'project_invoice_id' => $project_invoice_id,
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

        if (!$this->projectInvoicePaymentModel->update($project_invoice_payment_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$project_invoice = $this->projectInvoiceModel->get_details_by_id($values['project_invoice_id'])) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_record_sale_payment($project_invoice[0], $values))
            return false;

        return true;
    }

    /**
     * Record Project Sale Payment
     */
    protected function _record_sale_payment($project_invoice, $values) {
        $update_values = [
            'balance'     => (float)$project_invoice['balance'] - (float)$values['paid_amount'],
            'paid_amount' => (float)$project_invoice['paid_amount'] + (float)$values['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($update_values['balance'] == 0) {
            $update_values['payment_status'] = 'closed_bill';
            $update_values['fully_paid_on']  = date('Y-m-d H:i:s');
        }

        if (!$this->projectInvoiceModel->update($project_invoice['id'], $update_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        //handle update balance
        // $where = [
        //     'project_id'    =>  $project_id,
        //     'is_deleted'    =>  0,
        //     'is_occupied'   =>  0
        // ];
        // // Fetch all one-time fees and recurring costs where `is_occupied` is 0
        // $one_time_fees = $this->projectOneTimeFeeModel->select('',$where);
        // $recurring_costs = $this->projectRecurringCostModel->select('',$where);

        // if (!$this->projectInvoiceModel->update($project_invoice['id'], $update_values)) {
        //     $this->errorMessage = $this->db->error()['message'];
        //     return false;
        // }

        // if (!$this->projectInvoiceModel->update($project_invoice['id'], $update_values)) {
        //     $this->errorMessage = $this->db->error()['message'];
        //     return false;
        // }

        // if (!$this->_update_credit_limit($project_invoice, $values['paid_amount'])) {
        //     return false;
        // }
        
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($project_invoice_payment)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectInvoicePaymentModel->update($project_invoice_payment['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        // Restore the credit limit back
        $project_invoice = $this->projectInvoiceModel->get_details_by_id($project_invoice_payment['project_invoice_id'])[0];
        // Update the paid amount
        $new_values = [
            'balance'     => $project_invoice['balance'] + $project_invoice_payment['paid_amount'],
            'paid_amount' => $project_invoice['paid_amount'] - $project_invoice_payment['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($new_values['balance'] > 0) {
            $new_values['payment_status'] = 'open_bill';
            $new_values['fully_paid_on']  = null;
        }

        if (!$this->projectInvoiceModel->update($project_invoice['id'], $new_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // if (!$this->_update_credit_limit($project_invoice, (float)$project_invoice_payment['paid_amount'] * -1)) {
        //     return false;
        // }

        return true;
    }

    /**
     * Increase credit limit
     */
    private function _update_credit_limit($project_invoice, $amount) {
        $project = $this->projectModel->get_details_by_id($project_invoice['project_id'])[0];
        
        $new_values = [
            // 'current_credit_limit' => (float)$project['current_credit_limit'] + (float)$amount,
            'updated_by'           => $this->requested_by,
            'updated_on'           => date('Y-m-d H:i:s'),
        ];

        if (!$this->projectModel->update($project['id'], $new_values)) {
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
        $this->projectInvoicePaymentModel = model('App\Models\Project_invoice_payment');
        $this->projectInvoiceModel        = model('App\Models\Project_invoice');
        $this->projectInvoiceItemModel    = model('App\Models\Project_invoice_item');
        $this->projectModel               = model('App\Models\Project');
        $this->projectOneTimeFeeModel     = model('App\Models\Project_one_time_fee');
        $this->projectRecurringCostModel  = model('App\Models\Project_recurring_cost');
        $this->projectInvoicePaymentAttachmentModel = model('App\Models\Project_invoice_payment_attachment');
        $this->itemUnitModel              = model('App\Models\Item_unit');
        $this->inventoryModel             = model('App\Models\Inventory');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}

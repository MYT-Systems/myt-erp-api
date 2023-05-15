<?php

namespace App\Controllers;

class Franchisee_sale_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get franchisee_sale_payment
     */
    public function get_franchisee_sale_payment()
    {
        if (($response = $this->_api_verification('franchisee_sale_payments', 'get_franchisee_sale_payment')) !== true)
            return $response;

        $franchisee_sale_payment_id = $this->request->getVar('franchisee_sale_payment_id') ? : null;
        $franchisee_sale_payment    = $franchisee_sale_payment_id ? $this->franchiseeSalePaymentModel->get_details_by_id($franchisee_sale_payment_id) : null;
        $franchisee_sale            = $franchisee_sale_payment ? $this->franchiseeSaleModel->get_details_by_id($franchisee_sale_payment[0]['franchisee_sale_id']) : null;

        if (!$franchisee_sale_payment) {
            $response = $this->failNotFound('No franchisee sale payment found');
        } else {
            $franchisee_sale_payment[0]['franchisee_sale'] = $franchisee_sale;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_sale_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all franchisee_sale_payments
     */
    public function get_all_franchisee_sale_payment()
    {
        if (($response = $this->_api_verification('franchisee_sale_payments', 'get_all_franchisee_sale_payment')) !== true)
            return $response;

        $franchisee_sale_payments = $this->franchiseeSalePaymentModel->get_all();

        if (!$franchisee_sale_payments) {
            $response = $this->failNotFound('No franchisee sale payment found');
        } else {
            foreach ($franchisee_sale_payments as $key => $franchisee_sale_payment) {
                $franchisee_sale = $this->franchiseeSaleModel->get_details_by_id($franchisee_sale_payment['franchisee_sale_id']);
                $franchisee_sale_payments[$key]['franchisee_sale'] = $franchisee_sale;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_sale_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create franchisee_sale_payment
     */
    public function create()
    {
        if (($response = $this->_api_verification('franchisee_sale_payments', 'create')) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchisee_sale_payment_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'                     => 'success',
                'franchisee_sale_payment_id' => $franchisee_sale_payment_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update franchisee_sale_payment
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('franchisee_sale_payments', 'update')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('franchisee_sale_payment_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchisee_sale_payment = $this->franchiseeSalePaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_sale_payment not found');
        } elseif (!$this->_attempt_update($franchisee_sale_payment['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'franchisee_sale_payment updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete franchisee_sale_payments
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('franchisee_sale_payments', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('franchisee_sale_payment_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchisee_sale_payment = $this->franchiseeSalePaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_sale_payment not found');
        } elseif (!$this->_attempt_delete($franchisee_sale_payment)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'franchisee_sale_payment deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search franchisee_sale_payments based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('franchisee_sale_payments', 'search')) !== true)
            return $response;

        $franchisee_id      = $this->request->getVar('franchisee_id') ?? null;
        $franchisee_sale_id = $this->request->getVar('franchisee_sale_id') ?? null;
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

        if (!$franchisee_sale_payments = $this->franchiseeSalePaymentModel->search($franchisee_id, $franchisee_sale_id, $payment_method, $payment_date_from, $payment_date_to, $from_bank_id, $cheque_number, $cheque_date_from, $cheque_date_to, $reference_number, $transaction_number, $branch_name)) {
            $response = $this->failNotFound('No franchisee sale payment found');
        } else {
            foreach ($franchisee_sale_payments as $key => $franchisee_sale_payment) {
                $franchisee_sale_payments[$key]['franchisee_sale'] = $this->franchiseeSaleModel->get_details_by_id($franchisee_sale_payment['franchisee_sale_id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_sale_payments
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
    private function _record_inventory($franchisee_sale)
    {
        $franchisee_sale_items = $this->franchiseeSaleItemModel->get_details_by_franchisee_sales_id($franchisee_sale['id']);
        foreach ($franchisee_sale_items as $franchisee_sale_item) {
            if ($item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($franchisee_sale['seller_project_id'], $franchisee_sale_item['item_id'], $franchisee_sale_item['unit'])) {
                if ($seller_inventory = $this->inventoryModel->get_inventory_detail($franchisee_sale_item['item_id'], $franchisee_sale['seller_project_id'], $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $seller_inventory[0]['current_qty'] - $franchisee_sale_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->update($seller_inventory[0]['id'], $new_values)) {
                        $this->errorMessage = $this->db->error()['message'];
                        return false;
                    }
                }
    
                if ($buyer_inventory = $this->inventoryModel->get_inventory_detail($franchisee_sale_item['item_id'], $franchisee_sale['buyer_project_id'], $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $buyer_inventory[0]['current_qty'] + $franchisee_sale_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->update($buyer_inventory[0]['id'], $new_values)) {
                        $this->errorMessage = $this->db->error()['message'];
                        return false;
                    }
                } else {
                    $new_values = [
                        'item_id'       => $franchisee_sale_item['item_id'],
                        'project_id'     => $franchisee_sale['buyer_project_id'],
                        'item_unit_id'  => $item_unit[0]['id'],
                        'beginning_qty' => 0,
                        'current_qty'   => $franchisee_sale_item['qty'],
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
     * Create franchisee_sale_payments
     */
    private function _attempt_create()
    {        
        if ($franchisee_sale = $this->franchiseeSaleModel->get_details_by_id($this->request->getVar('franchisee_sale_id'))) {
            $franchisee_sale = $franchisee_sale[0];
        } else {
            $this->errorMessage = 'Invalid franchisee sale id';
            return false;
        }

        $values = [
            'franchisee_id'      => $this->request->getVar('franchisee_id'),
            'franchisee_sale_id' => $franchisee_sale['id'],
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

        if (!$franchisee_sale_payment_id = $this->franchiseeSalePaymentModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
           return false;
        }
    
        if (!$this->_record_sale_payment($franchisee_sale, $values)) {
            return false;
        }
        
        return $franchisee_sale_payment_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($franchisee_sale_payment_id)
    {
        // revert previous payment
        $franchisee_sale_payment = $this->franchiseeSalePaymentModel->get_details_by_id($franchisee_sale_payment_id);
        $franchisee_sale_payment = $franchisee_sale_payment[0];
        $franchisee_sale_id = $franchisee_sale_payment['franchisee_sale_id'];
        $franchisee_sale = $this->franchiseeSaleModel->get_details_by_id($franchisee_sale_id);
        $franchisee_sale = $franchisee_sale[0];

        $new_values = [
            'balance'     => $franchisee_sale['balance'] + $franchisee_sale_payment['paid_amount'],
            'paid_amount' => $franchisee_sale['paid_amount'] - $franchisee_sale_payment['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($new_values['balance'] > 0) {
            $new_values['payment_status'] = 'open_bill';
            $new_values['fully_paid_on']  = null;
        }

        if (!$this->franchiseeSaleModel->update($franchisee_sale_id, $new_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        // Restore the credit limit back
        if (!$this->_update_credit_limit($franchisee_sale, (float)$franchisee_sale_payment['paid_amount'] * -1)) {
            return false;
        }

        $values = [
            'franchisee_id'      => $this->request->getVar('franchisee_id'),
            'franchisee_sale_id' => $franchisee_sale_id,
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

        if (!$this->franchiseeSalePaymentModel->update($franchisee_sale_payment_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$franchisee_sale = $this->franchiseeSaleModel->get_details_by_id($values['franchisee_sale_id'])) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_record_sale_payment($franchisee_sale[0], $values))
            return false;

        return true;
    }

    /**
     * Record Frachisee Sale Payment
     */
    protected function _record_sale_payment($franchisee_sale, $values) {
        $update_values = [
            'balance'     => $franchisee_sale['balance'] - $values['paid_amount'],
            'paid_amount' => $franchisee_sale['paid_amount'] + $values['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($update_values['balance'] == 0) {
            $update_values['payment_status'] = 'closed_bill';
            $update_values['fully_paid_on']  = date('Y-m-d H:i:s');
        }

        if (!$this->franchiseeSaleModel->update($franchisee_sale['id'], $update_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_update_credit_limit($franchisee_sale, $values['paid_amount'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($franchisee_sale_payment)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeSalePaymentModel->update($franchisee_sale_payment['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        // Restore the credit limit back
        $franchisee_sale = $this->franchiseeSaleModel->get_details_by_id($franchisee_sale_payment['franchisee_sale_id'])[0];
        // Update the paid amount
        $new_values = [
            'balance'     => $franchisee_sale['balance'] + $franchisee_sale_payment['paid_amount'],
            'paid_amount' => $franchisee_sale['paid_amount'] - $franchisee_sale_payment['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($new_values['balance'] > 0) {
            $new_values['payment_status'] = 'open_bill';
            $new_values['fully_paid_on']  = null;
        }

        if (!$this->franchiseeSaleModel->update($franchisee_sale['id'], $new_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_update_credit_limit($franchisee_sale, (float)$franchisee_sale_payment['paid_amount'] * -1)) {
            return false;
        }

        return true;
    }

    /**
     * Increase credit limit
     */
    private function _update_credit_limit($franchisee_sale, $amount) {
        $franchisee = $this->franchiseeModel->get_details_by_id($franchisee_sale['franchisee_id'])[0];
        
        $new_values = [
            'current_credit_limit' => (float)$franchisee['current_credit_limit'] + (float)$amount,
            'updated_by'           => $this->requested_by,
            'updated_on'           => date('Y-m-d H:i:s'),
        ];

        if (!$this->franchiseeModel->update($franchisee['id'], $new_values)) {
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
        $this->franchiseeSalePaymentModel = model('App\Models\Franchisee_sale_payment');
        $this->franchiseeSaleModel        = model('App\Models\Franchisee_sale');
        $this->franchiseeSaleItemModel    = model('App\Models\Franchisee_sale_item');
        $this->franchiseeModel            = model('App\Models\Franchisee');
        $this->itemUnitModel              = model('App\Models\Item_unit');
        $this->inventoryModel             = model('App\Models\Inventory');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}

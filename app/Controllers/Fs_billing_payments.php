<?php

namespace App\Controllers;

class Fs_billing_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get fs_billing_payment
     */
    public function get_fs_billing_payment()
    {
        if (($response = $this->_api_verification('fs_billing_payments', 'get_fs_billing_payment')) !== true)
            return $response;

        $fs_billing_payment_id = $this->request->getVar('fs_billing_payment_id') ? : null;
        $fs_billing_payment    = $fs_billing_payment_id ? $this->FsBillingPaymentModel->get_details_by_id($fs_billing_payment_id) : null;
        $fs_billing            = $fs_billing_payment ? $this->franchiseeSaleBillingModel->get_details_by_id($fs_billing_payment[0]['fs_billing_id']) : null;

        if (!$fs_billing_payment) {
            $response = $this->failNotFound('No franchisee sale billing payment found');
        } else {
            $fs_billing_payment[0]['fs_billing'] = $fs_billing;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $fs_billing_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all fs_billing_payments
     */
    public function get_all_fs_billing_payment()
    {
        if (($response = $this->_api_verification('fs_billing_payments', 'get_all_fs_billing_payment')) !== true)
            return $response;

        $fs_billing_payments = $this->FsBillingPaymentModel->get_all();

        if (!$fs_billing_payments) {
            $response = $this->failNotFound('No franchisee sale billing payment found');
        } else {
            foreach ($fs_billing_payments as $key => $fs_billing_payment) {
                $fs_billing = $this->franchiseeSaleBillingModel->get_details_by_id($fs_billing_payment['fs_billing_id']);
                $fs_billing_payments[$key]['fs_billing'] = $fs_billing;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $fs_billing_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create fs_billing_payment
     */
    public function create()
    {
        if (($response = $this->_api_verification('fs_billing_payments', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$fs_billing_payment_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Failed to create franchisee sale payment.');
        } else {
            $db->transCommit();
            $response = $this->respond([
                'status'                     => 'success',
                'fs_billing_payment_id' => $fs_billing_payment_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update fs_billing_payment
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('fs_billing_payments', 'update')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('fs_billing_payment_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$fs_billing_payment = $this->FsBillingPaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('fs_billing_payment not found');
        } elseif (!$this->_attempt_update($fs_billing_payment['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update franchisee sale payment.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'fs_billing_payment updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete fs_billing_payments
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('fs_billing_payments', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('fs_billing_payment_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();

        $db->transBegin();

        if (!$fs_billing_payment = $this->FsBillingPaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('fs_billing_payment not found');
        } elseif (!$this->_attempt_delete($fs_billing_payment)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete franchisee sale payment.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'fs_billing_payment deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search fs_billing_payments based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('fs_billing_payments', 'search')) !== true)
            return $response;

        $franchisee_id      = $this->request->getVar('franchisee_id') ?? null;
        $fs_billing_id      = $this->request->getVar('fs_billing_id') ?? null;
        $payment_method     = $this->request->getVar('payment_method') ?? null;
        $payment_date_from  = $this->request->getVar('payment_date_from') ?? null;
        $payment_date_to    = $this->request->getVar('payment_date_to') ?? null;
        $bank_id            = $this->request->getVar('bank_id') ?? null;
        $cheque_number      = $this->request->getVar('cheque_number') ?? null;
        $cheque_date_from   = $this->request->getVar('cheque_date_from') ?? null;
        $cheque_date_to     = $this->request->getVar('cheque_date_to') ?? null;
        $reference_number   = $this->request->getVar('reference_number') ?? null;
        $transaction_number = $this->request->getVar('transaction_number') ?? null;
        $branch_name        = $this->request->getVar('branch_name') ?? null;

        if (!$fs_billing_payments = $this->FsBillingPaymentModel->search($franchisee_id, $fs_billing_id, $payment_method, $payment_date_from, $payment_date_to, $bank_id, $cheque_number, $cheque_date_from, $cheque_date_to, $reference_number, $transaction_number, $branch_name)) {
            $response = $this->failNotFound('No franchisee sale billing payment found');
        } else {
            $summary = [
                'total_paid_amount' => 0
            ];

            foreach ($fs_billing_payments as $key => $fs_billing_payment) {
                $summary['total_paid_amount'] += $fs_billing_payment['paid_amount'];
                $fs_billing_payments[$key]['fs_billing'] = $this->franchiseeSaleBillingModel->get_details_by_id($fs_billing_payment['fs_billing_id']);
            }

            $response = $this->respond([
                'summary' => $summary,
                'data'    => $fs_billing_payments,
                'status'  => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // Private methods
    // --------------------------------------------------------------------

    /**
     * Create fs_billing_payments
     */
    private function _attempt_create()
    {
        $fs_billing_id = $this->request->getVar('fs_billing_id');
        $values = [
            'franchisee_id'      => $this->request->getVar('franchisee_id'),
            'fs_billing_id'      => $fs_billing_id,
            'payment_type'       => $this->request->getVar('payment_type'),
            'payment_date'       => $this->request->getVar('payment_date'),
            'remarks'            => $this->request->getVar('remarks'),
            'from_bank_id'       => $this->request->getVar('from_bank_id'),
            'from_bank_name'     => $this->request->getVar('from_bank_name'),
            'to_bank_id'         => $this->request->getVar('to_bank_id'),
            'to_bank_name'       => $this->request->getVar('to_bank_name'),
            'cheque_number'      => $this->request->getVar('cheque_number'),
            'cheque_date'        => $this->request->getVar('cheque_date'),
            'reference_number'   => $this->request->getVar('reference_number'),
            'transaction_number' => $this->request->getVar('transaction_number'),
            'payment_description'=> $this->request->getVar('payment_description'),
            'term_day'           => $this->request->getVar('term_day'),
            'delivery_address'   => $this->request->getVar('delivery_address'),
            'paid_amount'        => $this->request->getVar('paid_amount'),
            'discount'           => $this->request->getVar('discount'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'subtotal'           => $this->request->getVar('subtotal'),
            'service_fee'        => $this->request->getVar('service_fee'),
            'delivery_fee'       => $this->request->getVar('delivery_fee'),
            'withholding_tax'    => $this->request->getVar('withholding_tax'),
            'deposit_date'       => $this->request->getVar('deposit_date'),
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        if (!$fs_billing_payment_id = $this->FsBillingPaymentModel->insert($values))
           return false;

        if (!$fs_billing = $this->franchiseeSaleBillingModel->get_details_by_id($fs_billing_id)) {
            var_dump("Franchisee billing not found");
            return false;
        }
            
        $fs_billing = $fs_billing[0];
        
        if (!$this->_record_sale_payment($fs_billing, $values))
            return false;
        
        return $fs_billing_payment_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($fs_billing_payment_id)
    {
        // revert previous payment
        if (!$this->_revert_sale_payment($fs_billing_payment_id)) {
            var_dump("Failed to revert previous payment");
            return false;
        }

        $values = [
            'franchisee_id'      => $this->request->getVar('franchisee_id'),
            'fs_billing_id'      => $this->request->getVar('fs_billing_id'),
            'payment_type'       => $this->request->getVar('payment_type'),
            'payment_date'       => $this->request->getVar('payment_date'),
            'remarks'            => $this->request->getVar('remarks'),
            'from_bank_id'       => $this->request->getVar('from_bank_id'),
            'from_bank_name'     => $this->request->getVar('from_bank_name'),
            'to_bank_id'         => $this->request->getVar('to_bank_id'),
            'to_bank_name'       => $this->request->getVar('to_bank_name'),
            'cheque_number'      => $this->request->getVar('cheque_number'),
            'cheque_date'        => $this->request->getVar('cheque_date'),
            'reference_number'   => $this->request->getVar('reference_number'),
            'transaction_number' => $this->request->getVar('transaction_number'),
            'payment_description'=> $this->request->getVar('payment_description'),
            'term_day'           => $this->request->getVar('term_day'),
            'delivery_address'   => $this->request->getVar('delivery_address'),
            'paid_amount'        => $this->request->getVar('paid_amount'),
            'discount'           => $this->request->getVar('discount'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'subtotal'           => $this->request->getVar('subtotal'),
            'service_fee'        => $this->request->getVar('service_fee'),
            'withholding_tax'    => $this->request->getVar('withholding_tax'),
            'deposit_date'       => $this->request->getVar('deposit_date'),
            'updated_by'         => $this->requested_by,
            'updated_on'         => date('Y-m-d H:i:s')
        ];

        if (!$this->FsBillingPaymentModel->update($fs_billing_payment_id, $values))
            return false;

        if (!$fs_billing = $this->franchiseeSaleBillingModel->get_details_by_id($values['fs_billing_id']))
            return false;

        if (!$this->_record_sale_payment($fs_billing[0], $values))
            return false;

        return true;
    }

    /**
     * Revert Frachisee Sale Payment
     */
    protected function _record_sale_payment($fs_billing, $values) {
        if (!$this->_update_credit_limit($fs_billing['franchisee_id'], $values['paid_amount']))  {
            var_dump("Failed to update credit limit");
            return false;
        }

        $update_values = [
            'balance'     => floatval($fs_billing['balance']) - floatval($values['paid_amount']) - floatval($values['discount']),
            'paid_amount' => floatval($fs_billing['paid_amount']) + floatval($values['paid_amount']),
            'discount'    => floatval($fs_billing['discount']) + floatval($values['discount']),
            'status'      => 'done',
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($update_values['balance'] <= 0) {
            $update_values['payment_status'] = 'closed_bill';
            $update_values['fully_paid_on']  = date('Y-m-d H:i:s');
        }

        if (!$this->franchiseeSaleBillingModel->update($fs_billing['id'], $update_values))
            return false;

        return true;
    }

    /**
     * Revert Frachisee Sale Payment
     */
    protected function _revert_sale_payment($fs_billing_payment_id) {
        if (!$fs_billing_payment = $this->FsBillingPaymentModel->get_details_by_id($fs_billing_payment_id))
            return false;

        $fs_billing_payment = $fs_billing_payment[0];

        if (!$this->_update_credit_limit($fs_billing_payment['franchisee_id'], (float)$fs_billing_payment['paid_amount'] * -1))  {
            var_dump("Failed to update credit limit");
            return false;
        }

        if (!$fs_billing = $this->franchiseeSaleBillingModel->get_details_by_id($fs_billing_payment['fs_billing_id']))
            return false;

        $fs_billing = $fs_billing[0];

        $update_values = [
            'balance'     => floatval($fs_billing['balance']) + floatval($fs_billing_payment['paid_amount']) + floatval($fs_billing_payment['discount']),
            'paid_amount' => floatval($fs_billing['paid_amount']) - floatval($fs_billing_payment['paid_amount']),
            'discount'    => floatval($fs_billing['discount']) - floatval($fs_billing_payment['discount']),
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($update_values['balance'] > 0) {
            $update_values['payment_status'] = 'open_bill';
            $update_values['fully_paid_on']  = null;
        } else {
            $update_values['payment_status'] = 'closed_bill';
            $update_values['fully_paid_on']  = date("Y-m-d H:i:s");
        }

        if (!$this->franchiseeSaleBillingModel->update($fs_billing['id'], $update_values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($fs_billing_payment)
    {
        // Restore the balance and discount
        if (!$this->_revert_sale_payment($fs_billing_payment['id'])) {
            var_dump("Failed to revert previous payment");
            return false;
        }

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->FsBillingPaymentModel->update($fs_billing_payment['id'], $values))
            return false;

        return true;
    }

    /**
     * Update credit limit
     */
    private function _update_credit_limit($franchisee_id, $amount) {
        if ($franchisee = $this->franchiseeModel->get_details_by_id($franchisee_id))
            $franchisee = $franchisee[0];
        else 
            return false;
        
        $new_values = [
            'current_credit_limit' => $franchisee['current_credit_limit'] + (float)$amount,
            'updated_by'           => $this->requested_by,
            'updated_on'           => date('Y-m-d H:i:s'),
        ];

        if (!$this->franchiseeModel->update($franchisee['id'], $new_values)) {
            var_dump($this->franchiseeModel->errors());
            return false;
        }

        return true;
    }


    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->FsBillingPaymentModel      = model('App\Models\Fs_billing_payment');
        $this->franchiseeSaleBillingModel = model('App\Models\Franchisee_sale_billing');
        $this->franchiseeModel            = model('App\Models\Franchisee');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}

<?php

namespace App\Controllers;

use App\Models\Franchisee_payment;
use App\Models\Franchisee;
use App\Models\Webapp_response;

class Franchisee_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get franchisee_payments
     */
    public function get_franchisee_payment()
    {
        if (($response = $this->_api_verification('franchisee_payments', 'get_franchisee_payments')) !== true)
            return $response;

        $franchisee_payment_id = $this->request->getVar('franchisee_payment_id') ? : null;
        $franchisee_payment   = $franchisee_payment_id ? $this->franchiseePaymentModel->get_details_by_id($franchisee_payment_id) : null;
        $franchisee            = $franchisee_payment ? $this->franchiseeModel->get_details_by_id($franchisee_payment[0]['franchisee_id']) : null;

        if (!$franchisee_payment) {
            $response = $this->failNotFound('No franchisee_payments found');
        } else {
            $franchisee_payment[0]['franchisee'] = $franchisee_payment;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all franchisee_payments
     */
    public function get_all_franchisee_payments()
    {
        if (($response = $this->_api_verification('franchisee_payments', 'get_all_franchisee_payments')) !== true)
            return $response;

        $franchisee_payments = $this->franchiseePaymentModel->get_all();

        if (!$franchisee_payments) {
            $response = $this->failNotFound('No franchisee_payments found');
        } else {
            foreach ($franchisee_payments as $key => $franchisee_payment) {
                $franchisee = $this->franchiseeModel->get_details_by_id($franchisee_payment['franchisee_id']);
                $franchisee_payments[$key]['franchisee'] = $franchisee;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create franchisee_payments
     */
    public function create()
    {
        if (($response = $this->_api_verification('franchisee_payments', 'create')) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchisee_payment_id = $this->_create_franchisee_payments()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'                => 'success',
                'franchisee_payment_id' => $franchisee_payment_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update franchisee_payments
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('franchisee_payments', 'update')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('franchisee_payment_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchisee_payments = $this->franchiseePaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_payments not found');
        } elseif (!$this->_attempt_update($franchisee_payments['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to update franchisee_payments.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'franchisee_payments updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete franchisee_payments
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('franchisee_payments', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('franchisee_payment_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchisee_payments = $this->franchiseePaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_payments not found');
        } elseif (!$this->_attempt_delete($franchisee_payments['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'franchisee_payments deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search franchisee_payments based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('franchisee_payments', 'search')) !== true)
            return $response;

        $branch_id          = $this->request->getVar('branch_id') ?? null;
        $franchisee_id      = $this->request->getVar('franchisee_id') ?? null;
        $payment_method     = $this->request->getVar('payment_method') ?? null;
        $payment_date       = $this->request->getVar('payment_date') ?? null;
        $amount             = $this->request->getVar('amount') ?? null;
        $remarks            = $this->request->getVar('remarks') ?? null;
        $from_bank_id       = $this->request->getVar('from_bank_id') ?? null;
        $cheque_number      = $this->request->getVar('cheque_number') ?? null;
        $cheque_date        = $this->request->getVar('cheque_date') ?? null;
        $reference_number   = $this->request->getVar('reference_number') ?? null;
        $transaction_number = $this->request->getVar('transaction_number') ?? null;

        if (!$franchisee_payments = $this->franchiseePaymentModel->search($branch_id, $franchisee_id, $payment_method, $payment_date, $amount, $remarks, $from_bank_id, $cheque_number, $cheque_date, $reference_number, $transaction_number)) {
            $response = $this->failNotFound('No franchisee_payments found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // Private methods
    // --------------------------------------------------------------------

    /**
     * Create franchisee_payments
     */

    private function _create_franchisee_payments()
    {
        $franchisee_id = $this->request->getVar('franchisee_id');
        $values = [
            'branch_id'          => $this->request->getVar('branch_id'),
            'franchisee_id'      => $franchisee_id,
            'payment_method'     => $this->request->getVar('payment_method'),
            'payment_date'       => $this->request->getVar('payment_date'),
            'amount'             => $this->request->getVar('amount'),
            'remarks'            => $this->request->getVar('remarks'),
            'from_bank_id'       => $this->request->getVar('from_bank_id'),
            'to_bank_id'         => $this->request->getVar('to_bank_id'),
            'to_bank_name'       => $this->request->getVar('to_bank_name'),
            'invoice_no'         => $this->request->getVar('invoice_no'),
            'cheque_number'      => $this->request->getVar('cheque_number'),
            'cheque_date'        => $this->request->getVar('cheque_date'),
            'reference_number'   => $this->request->getVar('reference_number'),
            'transaction_number' => $this->request->getVar('transaction_number'),
            'deposit_date'       => $this->request->getVar('deposit_date'),
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        if (!$franchisee_payment_id = $this->franchiseePaymentModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Update franchisee payments balance
        if ($franchisee = $this->franchiseeModel->get_details_by_id($franchisee_id)) {
            $franchisee = $franchisee[0];

            $values = [
                'paid_amount' => $franchisee['paid_amount'] + $this->request->getVar('amount'),
                'balance'     => $franchisee['balance'] - $this->request->getVar('amount'),
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ];

            if ($values['balance'] <= 0)
                $values['payment_status'] = 'closed_bill';

            if (!$this->franchiseeModel->update($franchisee_id, $values)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        return $franchisee_payment_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($franchisee_payment_id)
    {
        $franchisee_id = $this->request->getVar('franchisee_id');
        $values = [
            'branch_id'          => $this->request->getVar('branch_id'),
            'franchisee_id'      => $franchisee_id,
            'payment_method'     => $this->request->getVar('payment_method'),
            'payment_date'       => $this->request->getVar('payment_date'),
            'amount'             => $this->request->getVar('amount'),
            'remarks'            => $this->request->getVar('remarks'),
            'from_bank_id'       => $this->request->getVar('from_bank_id'),
            'to_bank_id'         => $this->request->getVar('to_bank_id'),
            'to_bank_name'       => $this->request->getVar('to_bank_name'),
            'invoice_no'         => $this->request->getVar('invoice_no'),
            'cheque_number'      => $this->request->getVar('cheque_number'),
            'cheque_date'        => $this->request->getVar('cheque_date'),
            'reference_number'   => $this->request->getVar('reference_number'),
            'transaction_number' => $this->request->getVar('transaction_number'),
            'deposit_date'       => $this->request->getVar('deposit_date'),
            'updated_by'         => $this->requested_by,
            'updated_on'         => date('Y-m-d H:i:s')
        ];

        // Revert the old transactions on the old franchisee
        if (!$this->_attempt_revert_payments($franchisee_payment_id)) {
            return false;
        }

        // Update the new franchisee
        if (!$this->franchiseePaymentModel->update($franchisee_payment_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Update the new franchisee payments balance
        $franchisee = $this->franchiseeModel->get_details_by_id($franchisee_id);
        $franchisee = $franchisee[0];

        $values = [
            'paid_amount' => $franchisee['paid_amount'] + $this->request->getVar('amount'),
            'balance'     => $franchisee['balance'] - $this->request->getVar('amount'),
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($values['balance'] <= 0)
            $values['payment_status'] = 'closed_bill';

        if (!$this->franchiseeModel->update($franchisee_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($franchisee_payment_id)
    {
        // Revert the old transactions on the old franchisee
            if (!$this->_attempt_revert_payments($franchisee_payment_id)) {
            return false;
        }

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseePaymentModel->update($franchisee_payment_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt revert payments
     */
    protected function _attempt_revert_payments($franchisee_payment_id)
    {
        $old_franchise_payement = $this->franchiseePaymentModel->get_details_by_id($franchisee_payment_id);
        $old_franchise_payement = $old_franchise_payement[0];
        $franchisee = $this->franchiseeModel->get_details_by_id($old_franchise_payement['franchisee_id']);
        $franchisee = $franchisee[0];

        $franchisee_value = [
            'paid_amount' => $franchisee['paid_amount'] - $old_franchise_payement['amount'],
            'balance'     => $franchisee['balance'] + $old_franchise_payement['amount'],
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($franchisee_value['balance'] > 0)
            $franchisee_value['payment_status'] = 'open_bill';

        // Update the old franchisee payments balance
        if (!$this->franchiseeModel->update($old_franchise_payement['franchisee_id'], $franchisee_value)) {
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
        $this->franchiseePaymentModel = model('App\Models\Franchisee_payment');
        $this->franchiseeModel        = model('App\Models\Franchisee');
        $this->webappResponseModel    = model('App\Models\Webapp_response');
    }
}

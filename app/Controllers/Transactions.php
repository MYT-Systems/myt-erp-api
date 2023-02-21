<?php

namespace App\Controllers;

use App\Models\Transaction;
use App\Models\Branch;
use App\Models\Webapp_response;

class Transactions extends MYTController
{
    
    public function __construct()
    {
        // Headers for API
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];
       
        $this->_load_essentials();
    }

    /**
     * Get receivables
     */
    public function get_all_receivables()
    {
        if (($response = $this->_api_verification('receivables', 'get_receivable')) !== true)
            return $response;

        $branch_id  = $this->request->getVar('branch_id') ? : null;
        $date_from  = $this->request->getVar('date_from') ? : null;
        $date_to    = $this->request->getVar('date_to') ? : null;
        $type       = $this->request->getVar('type') ? : null;
        
        $receivables = $this->franchiseTransactionModel->get_all_receivables($branch_id, $date_from, $date_to);

        if (!$receivables) {
            $response = $this->failNotFound('No transaction Found');
        } else {
            $summary = [
                'contract' => 0,
                'sales' => 0,
                'monthly_billables' => 0
            ];

            foreach ($receivables as $receivable) {
                if ($receivable['type'] == 'franchisee') {
                    $summary['contract'] += $receivable['balance'];
                } else if ($receivable['type'] == 'franchisee_sale') {
                    $summary['sales'] += $receivable['balance'];
                } else if ($receivable['type'] == 'franchisee_sale_billing') {
                    $summary['monthly_billables'] += $receivable['balance'];
                }
            }

            $summary['total'] = $summary['contract'] + $summary['sales'] + $summary['monthly_billables'];
            
            $response = $this->respond([
                'summary'     => $summary,
                'receivables' => $receivables,
                'status'      => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get receivables
     */
    public function get_all_specific_receivables_by_branch_id()
    {
        if (($response = $this->_api_verification('receivables', 'get_receivable')) !== true)
            return $response;

        $branch_id  = $this->request->getVar('branch_id') ? : null;
        $date_from  = $this->request->getVar('date_from') ? : null;
        $date_to    = $this->request->getVar('date_to') ? : null;
        $type       = $this->request->getVar('type') ? : null;

        $receivables = $this->franchiseTransactionModel->get_all_receivables_by_branch_id($branch_id, $date_from, $date_to, $type);

        if (!$receivables) {
            $response = $this->failNotFound('No transaction Found');
        } else {
            $summary = [
                'contract' => 0,
                'sales' => 0,
                'monthly_billables' => 0,
            ];

            foreach ($receivables as $receivable) {
                if ($receivable['type'] == 'franchisee') {
                    $summary['contract'] += $receivable['balance'];
                } else if ($receivable['type'] == 'franchisee_sale') {
                    $summary['sales'] += $receivable['balance'];
                } else if ($receivable['type'] == 'franchisee_sale_billing') {
                    $summary['monthly_billables'] += $receivable['balance'];
                }
            }
            
            $summary['total'] = $summary['contract'] + $summary['sales'] + $summary['monthly_billables'];

            $response = $this->respond([
                'summary'     => $summary,
                'receivables' => $receivables,
                'status'      => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get payments in franchisee
     */
    public function get_all_payments()
    {
        if (($response = $this->_api_verification('payments', 'get_payment')) !== true)
            return $response;

        $branch_id       = $this->request->getVar('branch_id') ? : null;
        $date_from       = $this->request->getVar('date_from') ? : null;
        $date_to         = $this->request->getVar('date_to') ? : null;
        $franchisee_id   = $this->request->getVar('franchisee_id') ? : null;
        $franchisee_name = $this->request->getVar('franchisee_name') ? : null;
        $payment_status  = $this->request->getVar('payment_status') ? : null;
        $payment_mode    = $this->request->getVar('payment_mode') ? : null;
        $type            = $this->request->getVar('type') ? : null;
        $is_done         = $this->request->getVar('is_done');

        if (!$payments = $this->franchiseTransactionModel->get_all_filtered_payments($branch_id, $date_from, $date_to, $franchisee_id, $franchisee_name, $payment_status, $payment_mode, $type, $is_done)) {
            $response = $this->failNotFound('No transaction Found');
        } else {
            $summary = [
                'franchisee_payment_grand_total' => 0,
                'franchisee_payment_total' => 0,
                'franchisee_sale_payment_grand_total' => 0,
                'franchisee_sale_payment_total' => 0,
                'fs_billing_payment_grand_total' => 0,
                'fs_billing_payment_total' => 0,
            ];

            foreach ($payments as $payment) {
                if ($payment['type'] == 'franchisee_payment') {
                    $summary['franchisee_payment_grand_total'] += $payment['grand_total'];
                    $summary['franchisee_payment_total'] += $payment['paid_amount'];
                } else if ($payment['type'] == 'franchisee_sale_payment') {
                    $summary['franchisee_sale_payment_grand_total'] += $payment['grand_total'];
                    $summary['franchisee_sale_payment_total'] += $payment['paid_amount'];
                } else if ($payment['type'] == 'fs_billing_payment') {
                    $summary['fs_billing_payment_grand_total'] += $payment['grand_total'];
                    $summary['fs_billing_payment_total'] += $payment['paid_amount'];
                }
            }

            $response = $this->respond([
                'summary' => $summary,
                'payments' => $payments,
                'status'   => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /*
    * Mark as Done
    */
    public function mark_as_done()
    {
        if (($response = $this->_api_verification('transactions', 'mark_as_done')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if ($this->_attempt_mark_as_done()) {
            $db->transCommit();
            $response = $this->respond([
                'status' => 'success',
                'message' => 'Transaction successfully marked as done'
            ]);
        } else {
            $db->transRollback();
            $response = $this->failServerError('Failed to mark transaction as done');
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to mark as done
     */
    private function _attempt_mark_as_done()
    {
        $ids = $this->request->getVar('ids') ? : [];
        $types = $this->request->getVar('types') ? : [];

        $values = [
            'is_done' => '1',
            'updated_on' => date('Y-m-d H:i:s'),
            'updated_by' => $this->requested_by
        ];

        foreach ($ids as $key => $id) {
            $type = $types[$key];

            switch ($type) {
                case 'franchisee':
                    $this->franchiseePaymentModel->update($id, $values);
                    break;
                case 'franchisee_sale':
                    $this->franchiseeSalePaymentModel->update($id, $values);
                    break;
                case 'franchisee_sale_billing':
                    $this->fsBillingPaymentModel->update($id, $values);
                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->franchiseTransactionModel  = model('App\Models\Transaction');
        $this->branchModel                = model('App\Models\Branch');
        $this->franchiseePaymentModel     = model('App\Models\Franchisee_payment');
        $this->franchiseeSalePaymentModel = model('App\Models\Franchisee_sale_payment');
        $this->fsBillingPaymentModel      = model('App\Models\Fs_billing_payment');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}
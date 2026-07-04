<?php

namespace App\Controllers;

class Statement_of_account extends MYTController
{
    protected $statementOfAccountModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get list of all projects that have a statement of account
     */
    public function get_all_statement_of_account()
    {
        if (($response = $this->_api_verification('statement_of_account', 'get_all_statement_of_account')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        if (!$statements = $this->statementOfAccountModel->get_all()) {
            $response = $this->failNotFound('No statement of account found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $statements,
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get a single statement of account for a project, with optional date range filter
     * Params: id (project id), date_from (optional), date_to (optional)
     */
    public function get_statement_of_account()
    {
        if (($response = $this->_api_verification('statement_of_account', 'get_statement_of_account')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $project_id = $this->request->getVar('id');
        $date_from  = $this->request->getVar('date_from') ?: null;
        $date_to    = $this->request->getVar('date_to')   ?: null;

        if (!$project = $this->statementOfAccountModel->get_project_details($project_id)) {
            $response = $this->failNotFound('No statement of account found');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $ledger            = $this->statementOfAccountModel->get_ledger($project_id);
        $running_balance   = 0.0;
        $beginning_balance = 0.0;
        $total_billings    = 0.0;
        $total_payment     = 0.0;
        $items             = [];

        foreach ($ledger as $txn) {
            $debit  = (float) str_replace(',', '', $txn['debit']);
            $credit = (float) str_replace(',', '', $txn['credit']);
            $running_balance += $debit - $credit;

            $txn_date = substr($txn['txn_date'], 0, 10);

            // Transactions before the period contribute to beginning balance only
            if ($date_from && $txn_date < $date_from) {
                $beginning_balance = $running_balance;
                continue;
            }

            // Transactions after the period are excluded entirely
            if ($date_to && $txn_date > $date_to) {
                continue;
            }

            $items[] = [
                'id'          => (int) $txn['item_sort_id'],
                'type'        => ((int) $txn['sort_order'] === 1) ? 'invoice' : 'payment',
                'date'        => $txn['txn_date'],
                'billing_no'  => $txn['billing_no'],
                'description' => $txn['description'],
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => $running_balance,
            ];

            $total_billings += $debit;
            $total_payment  += $credit;
        }

        $data = [
            'id'                      => (int) $project['id'],
            'client_name'             => $project['client_name'],
            'project_name'            => $project['project_name'],
            'address'                 => $project['project_address'] ?: $project['customer_address'],
            'beginning_balance'       => $beginning_balance,
            'total_billings'          => $total_billings,
            'total_payment'           => $total_payment,
            'ending_balance'          => $beginning_balance + $total_billings - $total_payment,
            'account_current_balance' => $this->statementOfAccountModel->get_current_balance($project_id),
            'notes'                   => '',
            'items'                   => $items,
        ];

        $response = $this->respond([
            'status' => 'success',
            'data'   => $data,
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Save an SOA-only description override for an invoice line
     * Params: project_invoice_id, description
     */
    public function update_invoice_description()
    {
        if (($response = $this->_api_verification('statement_of_account', 'update_invoice_description')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $project_invoice_id = $this->request->getVar('project_invoice_id');
        $description        = $this->request->getVar('description');

        if (!$this->statementOfAccountModel->update_invoice_description($project_invoice_id, $description)) {
            $response = $this->fail('Failed to update description');
        } else {
            $response = $this->respond([
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Save an SOA-only description override for a payment line
     * Params: project_invoice_payment_id, description
     */
    public function update_payment_description()
    {
        if (($response = $this->_api_verification('statement_of_account', 'update_payment_description')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $project_invoice_payment_id = $this->request->getVar('project_invoice_payment_id');
        $description                = $this->request->getVar('description');

        if (!$this->statementOfAccountModel->update_payment_description($project_invoice_payment_id, $description)) {
            $response = $this->fail('Failed to update description');
        } else {
            $response = $this->respond([
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->statementOfAccountModel = model('App\Models\Statement_of_account');
        $this->webappResponseModel     = model('App\Models\Webapp_response');
    }
}

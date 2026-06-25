<?php

namespace App\Controllers;

use App\Models\Bank_monthly_adjustment;
use App\Models\Webapp_response;

class Bank_monthly_adjustments extends MYTController
{
    protected $bankMonthlyAdjustmentModel;
    protected $webappResponseModel;

    public function __construct()
    {
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    public function add()
    {
        if (($response = $this->_api_verification('bank_monthly_adjustments', 'add')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true)
            return $response;

        $bank_id  = $this->request->getVar('bank_id');
        $type     = $this->request->getVar('type');
        $txn_date = $this->request->getVar('txn_date');
        $amount   = $this->request->getVar('amount');
        $remarks  = $this->request->getVar('remarks') ?? null;

        if (!$bank_id || !$type || !$txn_date || !$amount || (float) $amount <= 0) {
            $response = $this->fail('bank_id, type, txn_date, and a positive amount are required.', 400);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $year  = date('Y', strtotime($txn_date));
        $month = date('n', strtotime($txn_date));

        $existing = $this->bankMonthlyAdjustmentModel->get_by_bank_and_month($bank_id, $type, $year, $month);
        if ($existing) {
            $label = $type === 'interest' ? 'Interest' : 'Withholding tax';
            $response = $this->fail([
                'error' => "$label already exists for this bank this month.",
            ], 400);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $values = [
            'bank_id'    => $bank_id,
            'type'       => $type,
            'txn_date'   => $txn_date,
            'amount'     => (float) $amount,
            'remarks'    => $remarks,
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
            'is_deleted' => 0,
        ];

        if (!$this->bankMonthlyAdjustmentModel->insert($values)) {
            $response = $this->failServerError('Failed to save adjustment.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $response = $this->respond(['status' => 'success']);
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _load_essentials()
    {
        $this->bankMonthlyAdjustmentModel = new Bank_monthly_adjustment();
        $this->webappResponseModel        = new Webapp_response();
    }
}

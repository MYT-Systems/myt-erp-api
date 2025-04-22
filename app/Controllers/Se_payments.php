<?php

namespace App\Controllers;

use App\Models\SE_payment;
use App\Models\Webapp_response;

class Se_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get all payments
     */
    public function get_all_payment()
    {
        if (($response = $this->_api_verification('se_payments', 'get_all_payment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $start_date   = $this->request->getVar('start_date') ? : null;
        $end_date     = $this->request->getVar('end_date') ? : null;
        $status       = $this->request->getVar('status') ? : null;
        $supplier_id  = $this->request->getVar('supplier_id') ? : null;
        $vendor_id    = $this->request->getVar('vendor_id') ? : null;
        $payment_mode = $this->request->getVar('payment_mode') ? : null;
        $doc_no       = $this->request->getVar('doc_no') ? : null;

        $se_payment = $this->suppliesPaymentModel->get_all_payment($start_date, $end_date, $status, $supplier_id, $vendor_id, $payment_mode, $doc_no);

        if (!$se_payment) {
            $response = $this->failNotFound('No payments found');
        } else {
            $response = $this->respond([
                'data'   => $se_payment,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->suppliesPaymentModel = new SE_payment();
        $this->webappResponseModel  = new Webapp_response();
    }
}

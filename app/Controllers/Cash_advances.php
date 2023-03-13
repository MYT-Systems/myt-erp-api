<?php

namespace App\Controllers;

class Cash_advances extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get cash advances
     */
    public function get()
    {
        if (($response = $this->_api_verification('cash_advances', 'get')) !== true)
            return $response;

        $cash_advance_id = $this->request->getVar('cash_advance_id') ? : null;
        $cash_advances = $this->cashAdvanceModel->get($cash_advance_id);

        if (!$cash_advances) {
            $response = $this->failNotFound('No cash advances found');
        } else {
            $response = $this->respond([
                'data'   => $cash_advances,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search cash advances
     */
    public function search()
    {
        if (($response = $this->_api_verification('cash_advances', 'search')) !== true)
            return $response;

        $employee_name = $this->request->getVar('employee_name') ? : null;
        $status = $this->request->getVar('status') ? : null;
        $date_from = $this->request->getVar('date_from') ? : null;
        $date_to = $this->request->getVar('date_to') ? : null;

        if (!$cash_advances = $this->cashAdvanceModel->search($employee_name, $status, $date_from, $date_to)) {
            $response = $this->failNotFound('No bank found');
        } else {
            $summary = [
                "total_cash_advance" => 0,
                "total_paid_amount" => 0
            ];

            foreach ($cash_advances as $cash_advance) {
                $summary["total_cash_advance"] += $cash_advance['amount'];
                $summary["total_paid_amount"] += $cash_advance['paid_amount'];
            }

            $response = [];
            $response['data'] = $cash_advances;
            $response['summary'] = $summary;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }   

    /**
     * Create cash advance
     */
    public function create()
    {
        if (($response = $this->_api_verification('cash_advances', 'create')) !== true)
            return $response;

        $values = [];
        $employee_ids = $this->request->getVar('employee_ids');
        $amounts = $this->request->getVar('amounts');
        $employee_ids = $this->request->getVar('employee_ids');

        foreach ($employee_ids as $index => $employee_id) {
            $values[] = [
                "employee_id" => $employee_id,
                "status" => "pending",
                "date" => $this->request->getVar('date'),
                "billing_start_month" => $this->request->getVar('billing_start_month'),
                "amount" => $this->request->getVar('amount'),
                "disbursement_type" => $this->request->getVar('disbursement_type'),
                "terms" => $this->request->getVar('terms'),
                "purpose" => $this->request->getVar('purpose'),
                "status" => $this->request->getVar('status'),
                "remarks" => $this->request->getVar('remarks') ? : null,
                "other_fees" => $this->request->getVar('other_fees'),
                'added_by' => $this->requested_by,
                'added_on' => date('Y-m-d H:i:s')
            ];
        }

        if (!$this->cashAdvanceModel->insert($values)) {
            $response = $this->fail(['response' => 'Failed to create cash advance.', 'status' => 'error']);
        } else {
            $response = $this->respond([
                'response' => 'Cash advance created successfully.',
                'status'   => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update cash advance
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('cash_advances', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('cash_advance_id'),
            'is_deleted' => 0
        ];

        $values = [
            "employee_id" => $this->request->getVar('employee_id'),
            "date" => $this->request->getVar('date'),
            "billing_start_month" => $this->request->getVar('billing_start_month'),
            "amount" => $this->request->getVar('amount'),
            "disbursement_type" => $this->request->getVar('disbursement_type'),
            "terms" => $this->request->getVar('terms'),
            "purpose" => $this->request->getVar('purpose'),
            "status" => $this->request->getVar('status'),
            "remarks" => $this->request->getVar('remarks') ? : null,
            "other_fees" => $this->request->getVar('other_fees'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        
        if (!$cash_advance = $this->cashAdvanceModel->select('', $where, 1)) {
            $response = $this->failNotFound('Cash advance not found');
        } elseif (!$this->cashAdvanceModel->update($cash_advance['id'], $values)) {
            $response = $this->fail(['response' => 'Failed to update cash advance.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Cash advance updated successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update status of cash advance
     */
    public function update_status()
    {
        if (($response = $this->_api_verification('cash_advances', 'update_status')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('cash_advance_id'),
            'is_deleted' => 0
        ];

        $status = $this->request->getVar('status');
        $values = [
            "status" => $this->request->getVar('status'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if ($status == 'approved') {
            $values['approved_by'] = $this->requested_by;
            $values['approved_on'] = date('Y-m-d H:i:s');
        } elseif ($status == 'printed') {
            $values['printed_by'] = $this->requested_by;
            $values['printed_on'] = date('Y-m-d H:i:s');
        }
        
        if (!$cash_advance = $this->cashAdvanceModel->select('', $where, 1)) {
            $response = $this->failNotFound('Cash advance not found');
        } else {
            $where = [
                'status' => 'approved',
                'is_deleted' => 0
            ];
            if (!$this->cashAdvanceModel->select('', $where, 1)) {
                $response = $this->failNotFound('This employee currently has unsettled cash advance. Please settle first before approving another cash advance.');
            } elseif (!$this->cashAdvanceModel->update($cash_advance['id'], $values)) {
                $response = $this->fail(['response' => 'Failed to update cash advance status.', 'status' => 'error']);
            } else {
                $response = $this->respond(['response' => 'Cash advance status updated successfully.', 'status' => 'success']);
            }
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete cash advance
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('cash_advances', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('cash_advance_id'),
            'is_deleted' => 0
        ];

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$cash_advance = $this->cashAdvanceModel->select('', $where, 1)) {
            $response = $this->failNotFound('Cash advance not found');
        } elseif (!$this->cashAdvanceModel->update($cash_advance['id'], $values)) {
            $response = $this->fail(['response' => 'Failed to delete cash advance.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Cash advance deleted successfully.', 'status' => 'success']);
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
        $this->cashAdvanceModel    = model("App\Models\Cash_advance");
        $this->webappResponseModel = model("App\Models\Webapp_response");
    }
}

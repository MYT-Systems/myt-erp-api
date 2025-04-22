<?php

namespace App\Controllers;

use App\Models\Discount;
use App\Models\Check_template;
use App\Models\Webapp_response;

class Discounts extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get discount
     */
    public function get_discount()
    {
        if (($response = $this->_api_verification('discounts', 'get_discount')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $discount_id = $this->request->getVar('discount_id') ? : null;
        $discount    = $discount_id ? $this->discountModel->get_details_by_id($discount_id) : null;

        if (!$discount) {
            $response = $this->failNotFound('No discount found');
        } else {
            foreach ($discount as $index => $discount_entry)
                $discount[$index]['discount_branches'] = $this->discountBranchModel->get_by_discount_id($discount_entry['id']);

            $response = $this->respond([
                'data'   => $discount,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all discounts
     */
    public function get_all_discount()
    {
        if (($response = $this->_api_verification('discounts', 'get_all_discount')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $discounts = $this->discountModel->get_all_discount();

        if (!$discounts) {
            $response = $this->failNotFound('No discount found');
        } else {
            foreach ($discounts as $index => $discount)
                $discounts[$index]['discount_branches'] = $this->discountBranchModel->get_by_discount_id($discount['id']);
            
            $response = $this->respond([
                'data'   => $discounts,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create discount
     */
    public function create()
    {
        if (($response = $this->_api_verification('discounts', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        $discount_amount = $this->request->getVar('discount_amount') ? : 0.00;
        $mm_discount_share = $this->request->getVar('mm_discount_share');
        $delivery_discount_share = $this->request->getVar('delivery_discount_share');

        if ($discount_amount != $mm_discount_share + $delivery_discount_share) {
            $response = $this->fail(['response' => 'Total of discount shares is not equal to discount amount.', 'status' => 'error']);
        } elseif (!$this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create discount.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Discount created successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update discount
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('discounts', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('discount_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        $discount_amount = $this->request->getVar('discount_amount') ? : 0.00;
        $mm_discount_share = $this->request->getVar('mm_discount_share');
        $delivery_discount_share = $this->request->getVar('delivery_discount_share');

        if ($discount_amount != $mm_discount_share + $delivery_discount_share) {
            $response = $this->fail(['response' => 'Total of discount shares is not equal to discount amount.', 'status' => 'error']);
        } elseif (!$discount = $this->discountModel->select('', $where, 1)) {
            $response = $this->failNotFound('discount not found');
        } elseif (!$this->_attempt_update($discount['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update discount.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Discount updated successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete discounts
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('discounts', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('discount_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$discount = $this->discountModel->select('', $where, 1)) {
            $response = $this->failNotFound('discount not found');
        } elseif (!$this->_attempt_delete($discount['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete discount.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Discount deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search discounts based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('discounts', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id') ? : null;
        $date_from = $this->request->getVar('date_from') ? : null;
        $date_to = $this->request->getVar('date_to') ? : null;
        $validity = $this->request->getVar('validity') ? : null;
        $merchant = $this->request->getVar('merchant') ? : null;
        $commission_base = $this->request->getVar('commission_base') ? : null;

        if (!$discounts = $this->discountModel->search($branch_id, $date_from, $date_to, $validity, $merchant, $commission_base, null)) {
            $response = $this->failNotFound('No discount found');
        } else {
            foreach ($discounts as $index => $discount)
                $discounts[$index]['discount_branches'] = $this->discountBranchModel->get_by_discount_id($discount['id']);

            $response = $this->respond([
                'data'     => $discounts,
                'status'   => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Discount report
     */
    public function reports()
    {
        if (($response = $this->_api_verification('discounts', 'reports')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id');
        $date = $this->request->getVar('date') ? : null;
        $transaction_type = $this->request->getVar('transaction_type') ? : null;

        if (!$discount_reports = $this->paymentModel->get_discount_reports($branch_id, $date, $transaction_type)) {
            $response = $this->failNotFound('No discount report found');
        } else {
            $summary = [
                'total_gross' => 0,
                'total_discount' => 0,
                'total_revenue' => 0
            ];

            foreach ($discount_reports as $index => $discount_report) {
                $summary += $discount_report['gross_value'];
                $summary += $discount_report['partner_funded_discount'];
                $summary += $discount['sales_revenue'];
            }

            $response = $this->respond([
                'data'    => $discounts,
                'summary' => $summary,
                'status'  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Discount report
     */
    public function invoice()
    {
        if (($response = $this->_api_verification('discounts', 'invoice')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id');
        $date = $this->request->getVar('date') ? : null;
        $transaction_type = $this->request->getVar('transaction_type') ? : null;

        if (!$discount_reports = $this->paymentModel->get_discount_invoice($branch_id, $date, $transaction_type)) {
            $response = $this->failNotFound('No discount invoice found');
        } else {
            $response = $this->respond([
                'data'    => $discounts,
                'status'  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create discount
     */
    protected function _attempt_create()
    {
        $values = [
            'description' => $this->request->getVar('description'),
            'valid_from' => $this->request->getVar('valid_from') ? : null,
            'valid_to' => $this->request->getVar('valid_to') ? : null,
            'sundays' => $this->request->getVar('sundays') ? : 0,
            'mondays' => $this->request->getVar('mondays') ? : 0,
            'tuesdays' => $this->request->getVar('tuesdays') ? : 0,
            'wednesdays' => $this->request->getVar('wednesdays') ? : 0,
            'thursdays' => $this->request->getVar('thursdays') ? : 0,
            'fridays' => $this->request->getVar('fridays') ? : 0,
            'saturdays' => $this->request->getVar('saturdays') ? : 0,
            'commission_rate' => $this->request->getVar('commission_rate'),
            'vat_rate' => $this->request->getVar('vat_rate'),
            'other_fee' => $this->request->getVar('other_fee'),
            'discount_amount' => $this->request->getVar('discount_amount') ? : 0.00,
            'type' => $this->request->getVar('type'),
            'mm_discount_share' => $this->request->getVar('mm_discount_share'),
            'delivery_discount_share' => $this->request->getVar('delivery_discount_share'),
            'commission_base' => $this->request->getVar('commission_base'),
            'remarks' => $this->request->getVar('remarks') ? : null,
            'merchant' => $this->request->getVar('discount_source'),
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s')
        ];

        if (!$discount_id = $this->discountModel->insert($values) OR !$this->_attempt_register_discount_branches($discount_id))
            return false;

        return $discount_id;
    }

    protected function _attempt_register_discount_branches($discount_id)
    {
        $data = [];
        $branches = $this->request->getVar('branches') ? : [];
        foreach ($branches as $branch) {
            $data[] = [
                'branch_id' => $branch,
                'discount_id' => $discount_id,
                'added_by' => $this->requested_by,
                'added_on' => date('Y-m-d H:i:s')
            ];
        }

        if ($data AND !$this->discountBranchModel->insertBatch($data))
            return false;
        return true;
    }

    protected function _attempt_update_discount_branches($discount_id)
    {
        $discount_branch_ids = [];
        $branches = $this->request->getVar('branches') ? : [];
        foreach ($branches as $branch) {
            $data = [
                'branch_id' => $branch,
                'discount_id' => $discount_id
            ];

            if (!$discount_branch_id = $this->discountBranchModel->insert_on_duplicate_key_update($data, $this->requested_by))
                return false;

            if ($discount_branch_id === true) {
                $discount_branch_details = $this->discountBranchModel->select('', ['branch_id' => $branch, 'discount_id' => $discount_id, 'is_deleted' => 0], 1);
                $discount_branch_ids[] = $discount_branch_details['id'];
            } else {
                $discount_branch_ids[] = $discount_branch_id;
            }
        }
        
        if (!$this->discountBranchModel->delete_multiple_branches($discount_branch_ids, $discount_id, $this->requested_by))
            return false;

        return true;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($discount_id)
    {
        $values = [
            'description' => $this->request->getVar('description'),
            'valid_from' => $this->request->getVar('valid_from') ? : null,
            'valid_to' => $this->request->getVar('valid_to') ? : null,
            'sundays' => $this->request->getVar('sundays') ? : 0,
            'mondays' => $this->request->getVar('mondays') ? : 0,
            'tuesdays' => $this->request->getVar('tuesdays') ? : 0,
            'wednesdays' => $this->request->getVar('wednesdays') ? : 0,
            'thursdays' => $this->request->getVar('thursdays') ? : 0,
            'fridays' => $this->request->getVar('fridays') ? : 0,
            'saturdays' => $this->request->getVar('saturdays') ? : 0,
            'commission_rate' => $this->request->getVar('commission_rate'),
            'vat_rate' => $this->request->getVar('vat_rate'),
            'other_fee' => $this->request->getVar('other_fee'),
            'discount_amount' => $this->request->getVar('discount_amount') ? : 0.00,
            'type' => $this->request->getVar('type'),
            'mm_discount_share' => $this->request->getVar('mm_discount_share'),
            'delivery_discount_share' => $this->request->getVar('delivery_discount_share'),
            'commission_base' => $this->request->getVar('commission_base'),
            'remarks' => $this->request->getVar('remarks') ? : null,
            'merchant' => $this->request->getVar('discount_source'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->discountModel->update($discount_id, $values) OR !$this->_attempt_update_discount_branches($discount_id))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($discount_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->discountModel->update($discount_id, $values) OR
            !$this->discountBranchModel->custom_update(['discount_id' => $discount_id, 'is_deleted' => 0], $values))
        {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->discountModel = new Discount();
        $this->paymentModel = model('App\Models\Payment');
        $this->discountBranchModel = model('App\Models\Discount_branch');
        $this->webappResponseModel = new Webapp_response();
    }
}

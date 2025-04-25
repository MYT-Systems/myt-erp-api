<?php

namespace App\Controllers;

class Franchisee_sale_billings extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get franchisee_sale
     */
    public function get_franchisee_billing_sale()
    {
        if (($response = $this->_api_verification('franchisee_sale_billings', 'get_franchisee_sale')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $fs_billing_id = $this->request->getVar('fs_billing_id') ? : null;
        $franchisee_sale_billing    = $fs_billing_id ? $this->franchiseeSaleBillingModel->get_details_by_id($fs_billing_id) : null;
        $fs_billing_items           = $franchisee_sale_billing ? $this->fsSaleBillingItemModel->get_fs_billing_item_by_fs_billing_id($fs_billing_id) : null;
        
        if (!$franchisee_sale_billing) {
            $response = $this->failNotFound('No franchisee_sale found');
        } else {
            $franchisee_sale_billing[0]['fs_billing_items'] = $fs_billing_items;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_sale_billing
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all franchisee_sale_billings
     */
    public function get_all_franchisee_billing_sale()
    {
        if (($response = $this->_api_verification('franchisee_sale_billings', 'get_all_franchisee_sale')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchisee_sale_billings = $this->franchiseeSaleBillingModel->get_all();

        if (!$franchisee_sale_billings) {
            $response = $this->failNotFound('No franchisee_sale found');
        } else {
            foreach ($franchisee_sale_billings as $key => $franchisee_sale) {
                $franchisee_sale_billings[$key]['fs_billing_items'] = $this->fsSaleBillingItemModel->get_fs_billing_item_by_fs_billing_id($franchisee_sale['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_sale_billings
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create franchisee_sale
     */
    public function create()
    {
        if (($response = $this->_api_verification('franchisee_sale_billings', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$fs_billing_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_fs_billing_items($fs_billing_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'        => 'success',
                'fs_billing_id' => $fs_billing_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update franchisee_sale
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('franchisee_sale_billings', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id'         => $this->request->getVar('fs_billing_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchisee_sale_billing = $this->franchiseeSaleBillingModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_sale not found');
        } elseif (!$this->_attempt_update($franchisee_sale_billing)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif ($this->request->getVar('dates') && !$this->_attempt_update_fs_billing_items($franchisee_sale_billing)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'franchisee_sale updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete franchisee_sale_billings
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('franchisee_sale_billings', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('fs_billing_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchisee_sale_billing = $this->franchiseeSaleBillingModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_sale not found');
        } elseif (!$this->_attempt_delete($franchisee_sale_billing, $this->db)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'franchisee_sale deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function search_missing()
    {
        if (($response = $this->_api_verification('franchisee_sale_billings', 'search_missing')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id         = $this->request->getVar('branch_id') ? : null;
        $franchisee_id     = $this->request->getVar('franchisee_id') ? : null;
        $month             = $this->request->getVar('month') ? : null;
        $year              = $this->request->getVar('year') ? : null;
        $payment_status    = $this->request->getVar('payment_status') ? : null;
        $branch_name       = $this->request->getVar('branch_name') ? : null;
        $type              = $this->request->getVar('type') ? : null;
        $status            = $this->request->getVar('status') ? : null;
        $franchisee_name   = $this->request->getVar('franchisee_name') ? : null;

        $franchisee_sale_billings = [];
        $final_data = [];

        if ($year OR (!$year AND !$month)) {
            if (!$year) {
                $current_year = date("Y");
                for ($year = 2022; $year <= $current_year; $year++) {
                    $final_data = $this->_get_missing_data($branch_id, $franchisee_id, $year, $payment_status, $branch_name, $type, $status, $final_data, $franchisee_name);
                }
            } else {
                $final_data = $this->_get_missing_data($branch_id, $franchisee_id, $year, $payment_status, $branch_name, $type, $status, $final_data, $franchisee_name);
            }
            
            if ($final_data) {
                $response = $this->respond([
                    'status' => 'success',
                    'data'   => $final_data
                ]);
            } else {
                $response = $this->failNotFound('No franchisee found');
            }
        } else {
            if (!$franchisee_sale_billings = $this->franchiseeSaleBillingModel->search_missing($branch_id, $franchisee_id, $month, $year, $payment_status, $branch_name, $type, $status, $franchisee_name)) {
                $response = $this->failNotFound('No franchisee found');
            } else {
                $response = $this->respond([
                    'status' => 'success',
                    'data'   => $franchisee_sale_billings
                ]);
            }
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _get_missing_data($branch_id, $franchisee_id, $year, $payment_status, $branch_name, $type, $status, $final_data, $franchisee_name)
    {
        $months_in_words = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        $months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
        foreach ($months as $current_month) {
            $franchisee = $this->franchiseeSaleBillingModel->search_missing($branch_id, $franchisee_id, $current_month, $year, $payment_status, $branch_name, $type, $status, $franchisee_name);

            if ($franchisee) {
                $month_year = $months_in_words[$current_month - 1] . " " . $year;
                $final_data[$month_year] = $franchisee;
            }
        }

        return $final_data;
    }

    /**
     * Search franchisee_sale_billings based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('franchisee_sale_billings', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id         = $this->request->getVar('branch_id');
        $franchisee_id     = $this->request->getVar('franchisee_id');
        $month             = $this->request->getVar('month');
        $payment_status    = $this->request->getVar('payment_status');
        $branch_name       = $this->request->getVar('branch_name');
        $status            = $this->request->getVar('status');
        $month_from        = $this->request->getVar('month_from');
        $month_to          = $this->request->getVar('month_to');
        $franchisee_name   = $this->request->getVar('franchisee_name');

        if (!$franchisee_sale_billings = $this->franchiseeSaleBillingModel->search($branch_id, $franchisee_id, $month, $payment_status, $branch_name, $status, $month_from, $month_to, $franchisee_name)) {
            $response = $this->failNotFound('No franchisee_sale found');
        } else {
            $summary = [
                'total_amount_due' => 0,
                'total_balance' => 0,
                'total_discount' => 0,
                'total_paid_amount' => 0,
                'total_sales' => 0,
                'total_net' => 0,
                'total_royalty_fees' => 0,
                'total_marketing_fees' => 0,
            ];
            
            foreach ($franchisee_sale_billings as $key => $franchisee_sale_billing) {
                $franchisee_sale_billings[$key]['fs_billing_payment'] = $this->fsBillingPaymentModel->get_payment_by_franchisee_sale_billing_id($franchisee_sale_billing['id']);
                $franchisee_sale_billings[$key]['grand_total'] = strval(number_format((float)$franchisee_sale_billing['total_amount_due'] - $franchisee_sale_billing['discount'], 2, '.', ''));
                $summary['total_amount_due'] += $franchisee_sale_billing['total_amount_due'];
                $summary['total_balance'] += $franchisee_sale_billing['balance'];
                $summary['total_discount'] += $franchisee_sale_billing['discount'];
                $summary['total_paid_amount'] += $franchisee_sale_billing['paid_amount'];
                $summary['total_sales'] += $franchisee_sale_billing['total_sale'];
                $summary['total_net'] += $franchisee_sale_billing['total_net'];
                $royalty_fee =  $franchisee_sale_billing['royalty_fee'] + $franchisee_sale_billing['twelve_vat_from_royalty_fee'];
                $summary['total_royalty_fees'] += $royalty_fee;
                $marketing_fee =  $franchisee_sale_billing['s_marketing_fee'] + $franchisee_sale_billing['s_marketing_fee_net_of_vat'];
                $summary['total_marketing_fees'] += $marketing_fee;
            }

            $response = $this->respond([
                'summary' => $summary,
                'data'   => $franchisee_sale_billings,
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get franchisee_sale_billings
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('franchisee_sale_billings', 'record_action')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('fs_billing_id'), 
            'is_deleted' => 0
        ];

        if (!$franchisee_sale = $this->franchiseeSaleBillingModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_sale not found');
        } elseif (!$this->_attempt_record_action($franchisee_sale['id'])) {
            $response = $this->fail($this->errorMessage);
        } else {
            $response = $this->respond(['response' => 'Action recorded successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // Private methods
    // --------------------------------------------------------------------

    /**
     * Create franchisee_sale_billings
     */
    private function _attempt_create()
    {
        $values = [
            'branch_id'        => $this->request->getVar('branch_id'),
            'franchisee_id'    => $this->request->getVar('franchisee_id'),
            'month'            => $this->request->getVar('month'),
            'discount'         => $this->request->getVar('discount'),
            'discount_remarks' => $this->request->getVar('discount_remarks'),
            'added_by'         => $this->requested_by,
            'added_on'         => date('Y-m-d H:i:s'),
        ];

        if (!$fs_billing_id = $this->franchiseeSaleBillingModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $fs_billing_id;
    }

    /**
     * Update franchisee_sale_billings
     */
    protected function _attempt_generate_fs_billing_items($fs_billing_id)
    {
        $dates     = $this->request->getVar('dates');
        $sales     = $this->request->getVar('sales');
        $is_closed = $this->request->getVar('is_closed');

        $values = [
            'fs_billing_id' => $fs_billing_id,
            'added_by'      => $this->requested_by,
            'added_on'      => date('Y-m-d H:i:s'),
        ];

        $grand_total = 0;
        $is_done = true;
        foreach ($sales as $key => $sale) {
            $grand_total += $sale;
            
            $values['date']      = $dates[$key];
            $values['sale']      = $sale;
            $values['is_closed'] = $is_closed[$key];

            $is_done = $is_done && ($sale > 0 || $is_closed[$key]);

            if (!$this->fsSaleBillingItemModel->insert_on_duplicate_key_update($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }
        
        $franchisee_id = $this->request->getVar('franchisee_id');

        // Get franchisee
        if (!$franchisee = $this->franchiseeModel->get_details_by_id($franchisee_id)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        $paid_amount = 0;
        if ($franchisee_sale_billing = $this->franchiseeSaleBillingModel->get_details_by_id($fs_billing_id)) {
            $paid_amount = $franchisee_sale_billing[0]['paid_amount'];
        }

        $franchisee = $franchisee[0];
        $total_net = $grand_total / 1.12;
        $royalty_fee = $total_net * ROUND($franchisee['royalty_fee']/100, 2);
        $s_marketing_fee = $total_net * ROUND($franchisee['marketing_fee']/100, 2);
        $royalty_vat = $royalty_fee * 0.12;
        $marketing_vat = $s_marketing_fee * 0.12;
        $discount = $this->request->getVar('discount') ?? 0;

        $balance = ((float)$royalty_fee + (float)$royalty_vat + (float)$s_marketing_fee + (float)$marketing_vat - (float)$discount);

        $updated_values = [
            'total_sale' => $grand_total,
            'total_net' => $total_net,
            'royalty_fee' => $royalty_fee,
            'twelve_vat_from_royalty_fee' => $royalty_vat,
            'royalty_fee_net_of_vat' => $royalty_fee + $royalty_vat,
            's_marketing_fee' => $s_marketing_fee,
            'twelve_vat_from_s_marketing_fee' => $marketing_vat,
            's_marketing_fee_net_of_vat' => $s_marketing_fee + $marketing_vat,
            'total_royalty_fee_and_s_marketing_fee' => $royalty_fee + $royalty_vat + $s_marketing_fee + $marketing_vat,
            'balance' => $balance,
            'discount' => $discount,
            'payment_status' => $balance > 0 ? 'open_bill' : 'closed_bill',
            'status'  => $is_done ? 'done' : 'undone',
            'paid_amount' => 0,
            'total_amount_due' => $royalty_fee + $royalty_vat + $s_marketing_fee + $marketing_vat,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$this->_update_credit_limit($franchisee_id, (float)$updated_values['total_amount_due'] * -1)) {
            var_dump('Failed to update credit limit');
            return false;
        }

        if (!$this->franchiseeSaleBillingModel->update($fs_billing_id, $updated_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($franchisee_sale_billing)
    {
        $values = [
            'branch_id'        => $this->request->getVar('branch_id') ?? $franchisee_sale_billing['branch_id'],
            'franchisee_id'    => $this->request->getVar('franchisee_id') ?? $franchisee_sale_billing['franchisee_id'],
            'month'            => $this->request->getVar('month') ?? $franchisee_sale_billing['month'],
            'discount'         => $this->request->getVar('discount') ?? $franchisee_sale_billing['discount'],
            'discount_remarks' => $this->request->getVar('discount_remarks') ?? $franchisee_sale_billing['discount_remarks'],
            'updated_by'       => $this->requested_by,
            'updated_on'       => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeSaleBillingModel->update($franchisee_sale_billing['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }
    
    /**
     * Attempt generate franchisee sales items
     */
    protected function _attempt_update_fs_billing_items($franchisee_sale_billing)
    {
        // Restore credit limit
        $franchisee_id = $this->request->getVar('franchisee_id');
        if (!$this->_update_credit_limit($franchisee_id, (float)$franchisee_sale_billing['total_amount_due'])) {
            var_dump('Failed to update credit limit');
            return false;
        }

        // Delete the previous items
        if (!$this->fsSaleBillingItemModel->delete_fs_billing_item_by_fs_billing_id($franchisee_sale_billing['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        if (!$this->_attempt_generate_fs_billing_items($franchisee_sale_billing['id'])) {
            var_dump("Failed to generate franchisee_sale_billing_item");
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($franchisee_sale_billing)
    {
        if (!$this->_update_credit_limit($franchisee_sale_billing['franchisee_id'], $franchisee_sale_billing['total_amount_due'])) {
            var_dump('Failed to update credit limit');
            return false;
        }
        
        // Delete the previous items
        if (!$this->fsSaleBillingItemModel->delete_fs_billing_item_by_fs_billing_id($franchisee_sale_billing['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeSaleBillingModel->update($franchisee_sale_billing['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($fs_billing_id)
    {
        $status = $this->request->getVar('status');
        $values = [
            'status' => $status,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        switch($status) {
            case 'approved':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                break;
            case 'printed':
                $values['printed_by'] = $this->requested_by;
                $values['printed_on'] = date('Y-m-d H:i:s');
                break;
        }

        if (!$this->franchiseeSaleBillingModel->update($fs_billing_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Update credit limit
     */
    private function _update_credit_limit($franchisee_id, $amount) {
        $franchisee = $this->franchiseeModel->get_details_by_id($franchisee_id)[0];
        $remaining_credit = $this->franchiseeModel->get_remaining_credit_by_franchisee_name($franchisee['name']);

        // if ((float)$remaining_credit[0]['remaining_credit'] + (float)$amount < 0 && (float)$amount < 0) {
        //     $this->errorMessage = 'Insufficient credit limit';
        //     return false;
        // }
        
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
        $this->franchiseeSaleBillingModel = model('App\Models\Franchisee_sale_billing');
        $this->fsSaleBillingItemModel     = model('App\Models\Fs_billing_item');
        $this->fsBillingPaymentModel      = model('App\Models\Fs_billing_payment');
        $this->franchiseeModel            = model('App\Models\Franchisee');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}

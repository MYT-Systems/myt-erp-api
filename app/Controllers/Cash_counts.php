<?php

namespace App\Controllers;

class Cash_counts extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get cash_count
     */
    public function get_cash_count()
    {
        if (($response = $this->_api_verification('cash_counts', 'get_cash_count')) !== true)
            return $response;

        $cash_count_id = $this->request->getVar('cash_count_id') ? : null;
        $cash_count    = $cash_count_id ? $this->cashCountModel->get_details_by_id($cash_count_id) : null;
        $daily_sale   = $cash_count ? $this->dailySaleModel->get_details_by_id($cash_count[0]['daily_sale_id']) : null;

        if (!$cash_count) {
            $response = $this->failNotFound('No cash_count found');
        } else {
            $response = $this->respond([
                'daily_sale' => $daily_sale ? $daily_sale[0] : null,
                'status' => 'success',
                'data'   => $cash_count
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all cash_counts
     */
    public function get_all_cash_count()
    {
        if (($response = $this->_api_verification('cash_counts', 'get_all_cash_count')) !== true)
            return $response;

        $cash_counts = $this->cashCountModel->get_all_cash_count();

        if (!$cash_counts) {
            $response = $this->failNotFound('No cash count found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $cash_counts
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create cash_count
     */
    public function create()
    {
        if (($response = $this->_api_verification('cash_counts', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        /* 
        ** DEFINITIONS:
        ** dep_ = deposit
        ** chf_ = change funds
        **/ 
        if (!$cash_count_deposit_id = $this->_attempt_create_cash_breakdown('dep_')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create cash count.', 'status' => 'error']);
        } elseif (!$cash_count_change_funds_id = $this->_attempt_create_cash_breakdown('chf_')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create cash count.', 'status' => 'error']);
        } elseif (!$daily_sale_id = $this->_attempt_create_sales($cash_count_deposit_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create cash count.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'                   => 'Cash count created successfully.',
                'status'                     => 'success',
                'daily_sale_id'              => $daily_sale_id,
                'cash_count_deposit_id'      => $cash_count_deposit_id,
                'cash_count_change_funds_id' => $cash_count_change_funds_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update cash_count
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('cash_counts', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('cash_count_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$cash_count = $this->cashCountModel->select('', $where, 1)) {
            $response = $this->failNotFound('cash count not found');
        } elseif (!$this->_attempt_update($cash_count['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update cash count.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Cash count updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete cash_counts
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('cash_counts', 'delete')) !== true)
            return $response;

        $cash_count_id = $this->request->getVar('cash_count_id');

        $where = ['id' => $cash_count_id, 'is_deleted' => 0];

        if (!$cash_count = $this->cashCountModel->select('', $where, 1)) {
            $response = $this->failNotFound('cash_count not found');
        } elseif (!$this->_attempt_delete($cash_count_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'cash_count deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search cash_counts based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('cash_counts', 'search')) !== true)
            return $response;

        $branch_id       = $this->request->getVar('branch_id');
        $branch_name     = $this->request->getVar('branch_name');
        $sales_report_id = $this->request->getVar('sales_report_id');
        $is_reviewed     = $this->request->getVar('is_reviewed');
        $prepared_by     = $this->request->getVar('prepared_by');
        $approved_by     = $this->request->getVar('approved_by');
        $count_date_from = $this->request->getVar('count_date_from');
        $count_date_to   = $this->request->getVar('count_date_to');
        $type            = $this->request->getVar('type');


        if (!$cash_counts = $this->cashCountModel->search($branch_id, $branch_name, $sales_report_id, $is_reviewed, $prepared_by, $approved_by, $count_date_from, $count_date_to, $type)) {
            $response = $this->failNotFound('No cash_count found');
        } else {
            $response = [];
            $response['data'] = $cash_counts;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get current sales report
     */
    public function get_current_sales_report()
    {
        if (($response = $this->_api_verification('cash_counts', 'get_current_sales_report')) !== true)
            return $response;

        $branch_id = $this->request->getVar('branch_id');

        if (!$sales_report = $this->paymentModel->get_current_sales_report($branch_id)) {
            $response = $this->failNotFound('No sales report found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $sales_report
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // PRIVATE METHODS
    // --------------------------------------------------------------------

    /**
     * Attempt create the daily sale
     */
    protected function _attempt_create_sales($cash_count_deposit_id)
    {
        $branch_id = $this->request->getVar('branch_id');
        $current_date = date('Y-m-d H:i:s');
        $cash_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'cash') ?? 0;
        $gcash_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'gcash') ?? 0;
        $food_panda_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'credit', 'food_panda') ?? 0;
        $grab_food_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'credit', 'grab_food') ?? 0;
        $total_sales = $cash_sales + $gcash_sales + $food_panda_sales + $grab_food_sales;

        $total_expense = $this->expenseModel->get_total_expense($branch_id, $current_date);

        $actual_inventory_sales = $this->request->getVar('actual_inventory_sales');
        $net_actual_sales = $actual_inventory_sales - $total_expense;
        
        $system_inventory_sales = $this->orderDetailModel->get_system_inventory_sales(true);
        $net_system_sales = $system_inventory_sales['grand_total'] - $total_expense;

        $values = [
            'branch_id'         => $branch_id,
            'cash_count_id'     => $cash_count_deposit_id,
            'date'              => date('Y-m-d'),
            'actual_cash_sales' => $this->total_cash,
            'system_cash_sales' => $cash_sales,
            'cash_sales_overage' => $this->total_cash - $cash_sales, // actual - system
            'gcash_sales'       => $gcash_sales,
            'food_panda_sales'  => $food_panda_sales,
            'grab_food_sales'   => $grab_food_sales,
            'total_sales'       => $total_sales,
            'total_expense'     => $total_expense,
            'actual_inventory_sales' => $actual_inventory_sales,
            'net_actual_sales' => $net_actual_sales,
            'system_inventory_sales' => $system_inventory_sales['grand_total'],
            'net_system_sales' => $net_system_sales,
            'overage_inventory_sales' => $net_actual_sales - $net_system_sales, // actual - system
            'cashier_id'        => $this->request->getVar('cashier_id'),
            'prepared_by'       => $this->request->getVar('prepared_by'),
            'prepared_on'       => date('Y-m-d H:i:s'),
            'added_by'          => $this->requested_by,
            'added_on'          => date('Y-m-d H:i:s')
        ];

        if (!$daily_sale_id = $this->dailySaleModel->insert($values)) {
            var_dump("Failed to create daily sale");
            return false;
        }

        return $daily_sale_id;
    }

    /**
     * Attempt create cash count
     */
    protected function _attempt_create_cash_breakdown($type)
    {
        $bill_1000     = $this->request->getVar($type.'bill_1000') ? : 0;
        $bill_500      = $this->request->getVar($type.'bill_500') ? : 0;
        $bill_200      = $this->request->getVar($type.'bill_200') ? : 0;
        $bill_100      = $this->request->getVar($type.'bill_100') ? : 0;
        $bill_50       = $this->request->getVar($type.'bill_50') ? : 0;
        $bill_20       = $this->request->getVar($type.'bill_20') ? : 0;
        $coin_20       = $this->request->getVar($type.'coin_20') ? : 0;
        $coin_10       = $this->request->getVar($type.'coin_10') ? : 0;
        $coin_5        = $this->request->getVar($type.'coin_5') ? : 0;
        $coin_1        = $this->request->getVar($type.'coin_1') ? : 0;
        $cent_25       = $this->request->getVar($type.'cent_25') ? : 0;
        $cent_10       = $this->request->getVar($type.'cent_10') ? : 0;
        $cent_5        = $this->request->getVar($type.'cent_5') ? : 0;
        $cent_1        = $this->request->getVar($type.'cent_1') ? : 0;
        $total_cash    = ($bill_1000 * 1000 + $bill_500 * 500 + $bill_200 * 200 + $bill_100 * 100 + 
                          $bill_50 * 50 + $bill_20 * 20 + $coin_20 * 20 + $coin_10 * 10 + $coin_5 * 5 + $coin_1 * 1 + 
                          $cent_25 * 0.25 + $cent_10 * 0.10 + $cent_5 * 0.05 + $cent_1 * 0.01);

        $values = [
            'branch_id'     => $this->request->getVar('branch_id'),
            'count_date'    => date('Y-m-d'),
            'bill_1000'     => $bill_1000,
            'bill_500'      => $bill_500,
            'bill_200'      => $bill_200,
            'bill_100'      => $bill_100,
            'bill_50'       => $bill_50,
            'bill_20'       => $bill_20,
            'coin_20'       => $coin_20,
            'coin_10'       => $coin_10,
            'coin_5'        => $coin_5,
            'coin_1'        => $coin_1,
            'cent_25'       => $cent_25,
            'cent_10'       => $cent_10,
            'cent_5'        => $cent_5,
            'cent_1'        => $cent_1,
            'total_count'   => $total_cash,
            'type'          => $type == 'dep_' ? 'deposit' : 'change_funds',
            'added_by'      => $this->requested_by,
            'added_on'      => date('Y-m-d H:i:s'),
        ];
        
        if (!$cash_count_id = $this->cashCountModel->insert($values))
            return false;

        if ($type == 'dep_')
            $this->total_cash = $total_cash;
        
        return $cash_count_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($cash_count_id)
    {
        $values = [
            'transaction_type_id' => $this->request->getVar('transaction_type_id'),
            'branch_id'           => $this->request->getVar('branch_id'),
            'commission'          => $this->request->getVar('commission'),
            'updated_by'          => $this->requested_by,
            'updated_on'          => date('Y-m-d H:i:s')
        ];

        if (!$this->cashCountModel->update($cash_count_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($cash_count_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $cash_count_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashCountModel->update($where, $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        $db->transCommit();
        $db->close();

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->cashCountModel       = model('App\Models\Cash_count');
        $this->paymentModel         = model('App\Models\Payment');
        $this->expenseModel         = model('App\Models\Expense');
        $this->dailySaleModel       = model('App\Models\Daily_sale');
        $this->webappResponseModel  = model('App\Models\Webapp_response');

        // TO BE USED FOR attempt create sale function
        $this->total_cash = 0;
    }
}

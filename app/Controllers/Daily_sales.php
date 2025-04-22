<?php

namespace App\Controllers;

use App\Models\Daily_sale;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Ending_inventory;
use App\Models\Cash_count;
use App\Models\Order_detail_ingredient;
use App\Models\Store_deposit;
use App\Models\Store_deposit_attachment;
use App\Models\Daily_sale_employee_deduction;
use App\Models\Webapp_response;

class Daily_sales extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get daily_sale
     */
    public function get_daily_sale()
    {
        if (($response = $this->_api_verification('daily_sales', 'get_daily_sale')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $daily_sale_id = $this->request->getVar('daily_sale_id') ? : null;
        $daily_sale    = $daily_sale_id ? $this->cashCountModel->get_details_by_id($daily_sale_id) : null;

        if (!$daily_sale) {
            $response = $this->failNotFound('No daily_sale found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $daily_sale
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all daily_sales
     */
    public function get_all_daily_sale()
    {
        if (($response = $this->_api_verification('daily_sales', 'get_all_daily_sale')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $daily_sales = $this->dailySaleModel->get_all_daily_sale();

        if (!$daily_sales) {
            $response = $this->failNotFound('No daily sale found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $daily_sales
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
    
    /**
     * Add employee deduction for daily sale
     */
    public function employee_deduction()
    {
        if (($response = $this->_api_verification('daily_sales', 'employee_deduction')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $daily_sale_id = $this->request->getVar('daily_sale_id');
        $employee_ids = $this->request->getVar('employee_ids');
        $amounts = $this->request->getVar('amounts');

        $data = [];

        foreach ($employee_ids as $index => $employee_id) {
            $data[] = [
                "daily_sale_id" => $daily_sale_id,
                "employee_id" => $employee_id,
                "amount" => $amounts[$index],
                'added_on' => date("Y-m-d H:i:s"),
                'added_by' => $this->requested_by
            ];
        }

        if ($data AND !$this->dsDeductionModel->insertBatch($data)) {
            $response = $this->fail('Unable to add employee deductions');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'message' => 'Employee deductions added.'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get inventory sales (actual inventory sales and system inventory sales)
     */
    public function get_inventory_sales()
    {
        if (($response = $this->_api_verification('daily_sales', 'get_inventory_sales')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id');
        $date = $this->request->getVar('date') ? : date("Y-m-d");
        $system_sales = $this->orderDetailIngredModel->get_system_inventory_sales_by_item($branch_id, $date);
        $total_system_sales = $this->orderDetailIngredModel->get_system_inventory_sales($branch_id, $date);
        $actual_sales = $this->orderDetailIngredModel->get_actual_inventory_sales_by_item($branch_id, $date);
        $total_actual_sales = $this->orderDetailIngredModel->get_actual_inventory_sales($branch_id, $date);

        $response = $this->respond([
            'status' => 'success',
            'system_sales' => $system_sales,
            'total_system_sales' => $total_system_sales,
            'actual_sales' => $actual_sales,
            'total_actual_sales' => $total_actual_sales
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Submit daily sale
     */
    public function submit()
    {
        if (($response = $this->_api_verification('daily_sales', 'submit')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $this->db = db_connect();
        $this->db->transBegin();

        $sales_date_from = $this->request->getVar('sales_date_from');
        $sales_date_from = date("Y-m-d", strtotime($sales_date_from));
        $sales_date_to = $this->request->getVar('sales_date_to');
        $sales_date_to = date("Y-m-d", strtotime($sales_date_to));
        $branch_id = $this->request->getVar('branch_id');
        
        if (!$this->dailySaleModel->search($branch_id, null, null, null, $sales_date_from, $sales_date_to)) {
            $response = $this->fail('No daily sales within chosen date range.');
        } elseif ($this->storeDepositModel->get_store_deposit_within_date_range($branch_id, $sales_date_from, $sales_date_to)) {
            $response = $this->fail('Already submitted daily sales within chosen date range.');
        } elseif (!$store_deposit_id = $this->_deposit_sales()) {
            $this->db->transRollback();
            $response = $this->fail('Store deposit saving failed.');
        } elseif (!$this->dailySaleModel->update_store_deposit($store_deposit_id, $this->request->getVar('deposited_by'), $sales_date_from, $sales_date_to, $branch_id)) {
            $this->db->transRollback();
            $response = $this->fail('Unable to mark daily sales as submitted.');
        } elseif (!$this->_save_file_to_database($store_deposit_id)) {
            $this->db->transRollback();
            $response = $this->fail('Files not saved in the database.');
        } else {
            $response = $this->respond(['response' => 'Sales deposited successfully']);
        }

        $this->db->transCommit();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _deposit_sales()
    {
        $deposited_on = $this->request->getVar('deposited_on');
        $deposited_on = date("Y-m-d", strtotime($deposited_on));
        $sales_date_from = $this->request->getVar('sales_date_from');
        $sales_date_from = date("Y-m-d", strtotime($sales_date_from));
        $sales_date_to = $this->request->getVar('sales_date_to');
        $sales_date_to = date("Y-m-d", strtotime($sales_date_to));

        $sales = $this->dailySaleModel->compute_total_sales_per_transaction_type('cash', $sales_date_from, $sales_date_to);

        $values = [
            'branch_id' => $this->request->getVar('branch_id'),
            'amount' => $sales['total_sales'],
            'transaction_type' => 'cash',
            'sales_date_from' => $sales_date_from,
            'sales_date_to' => $sales_date_to,
            'reference_no' => $this->request->getVar('reference_no'),
            'deposited_to' => $this->request->getVar('deposited_to'),
            'deposited_on' => $deposited_on,
            'deposited_by' => $this->request->getVar('deposited_by'),
            'added_on'   => date("Y-m-d H:i:s"),
            'added_by'   => $this->requested_by
        ];

        if (!$this->storeDepositModel->insert($values)) return false;
        return $this->storeDepositModel->insertID();
    }

    protected function _save_file_to_database($store_deposit_id)
    {
        $deposit_attachments = $this->request->getVar('deposit_attachments') ? : [];
        $data = [];
        foreach ($deposit_attachments as $attachment) {
            $data[] = [
                'store_deposit_id' => $store_deposit_id,
                'base64' => $attachment,
                'added_on'   => date("Y-m-d H:i:s"),
                'added_by'   => $this->requested_by
            ];
        }

        if (count($data) > 0 AND !$this->storeDepositAttachmentModel->insertBatch($data))
            return false;
        return true;
    }

    protected function _save_store_deposit($daily_sale_id)
    {
        $values = [
            'date' => $this->request->getVar('date'),
            'daily_sale_id' => $daily_sale_id,
            'reference_no' => $this->request->getVar('reference_no'),
            'deposited_to' => $this->request->getVar('deposited_to'),
            'deposited_on' => $this->request->getVar('deposited_on'),
            'deposited_by' => $this->request->getVar('deposited_by'),
            'added_on' => $this->requested_by,
            'added_by' => date("Y-m-d H:i:s")
        ];

        if (!$this->storeDepositModel->insert($values))
            return false;
        return $this->storeDepositModel->insertID();
    }

    /**
     * Create daily_sale
     */
    public function create()
    {
        if (($response = $this->_api_verification('daily_sales', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
        
        $response = $this->_attempt_create_sales();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update daily_sale
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('daily_sales', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('daily_sale_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$daily_sale = $this->cashCountModel->select('', $where, 1)) {
            $response = $this->failNotFound('cash count not found');
        } elseif (!$this->_attempt_update($daily_sale['id'])) {
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
     * Attempt create the daily sale
     */
    protected function _attempt_create_sales()
    {
        $branch_id = $this->request->getVar('branch_id');
        $current_date = date('Y-m-d');

        if (($total_cash = $this->_compute_total_cash($branch_id, $current_date)) === false) {
            return $this->fail('Cash count not yet created.');
        }

        $cash_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'cash') ?? 0;
        $gcash_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'gcash') ?? 0;
        $food_panda_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'credit', 'foodpanda') ?? 0;
        $grab_food_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'credit', 'grabfood') ?? 0;
        $total_sales = $cash_sales + $gcash_sales + $food_panda_sales + $grab_food_sales;

        $total_expense = $this->expenseModel->get_total_expense($branch_id, $current_date);

        $actual_inventory_sales = $this->orderDetailIngredModel->get_actual_inventory_sales($branch_id, date("Y-m-d"));
        $net_actual_sales = $actual_inventory_sales['grand_total'] - $total_expense;
        
        $system_inventory_sales = $this->orderDetailIngredModel->get_system_inventory_sales($branch_id, date("Y-m-d"));
        $net_system_sales = $system_inventory_sales['grand_total'] - $total_expense;

        $ending_inventory = $this->endingInventoryModel->get_branch_inventory_variance($branch_id, $current_date);

        $values = [
            'branch_id'               => $branch_id,
            'date'                    => $current_date,
            'actual_cash_sales'       => $total_cash,
            'system_cash_sales'       => $cash_sales,
            'cash_sales_overage'      => $total_cash - $cash_sales, // actual - system
            'gcash_sales'             => $gcash_sales,
            'food_panda_sales'        => $food_panda_sales,
            'grab_food_sales'         => $grab_food_sales,
            'total_sales'             => $total_sales,
            'total_expense'           => $total_expense,
            'actual_inventory_sales'  => $actual_inventory_sales['grand_total'],
            'net_actual_sales'        => $net_actual_sales,
            'system_inventory_sales'  => $system_inventory_sales['grand_total'],
            'net_system_sales'        => $net_system_sales,
            'overage_inventory_sales' => $net_actual_sales - $net_system_sales, // actual - system
            'inventory_variance'      => $ending_inventory['inventory_variance'],
            'cashier_id'              => $this->request->getVar('cashier_id'),
            'prepared_by'             => $this->request->getVar('prepared_by'),
            'prepared_on'             => date('Y-m-d H:i:s'),
            'added_by'                => $this->requested_by,
            'added_on'                => date('Y-m-d H:i:s')
        ];

        if (!$daily_sale_id = $this->dailySaleModel->insert($values)) {
            return $this->fail('Failed to create daily sale.');
        }

        return $this->respond([
            'status' => 'success',
            'daily_sale_id' => $daily_sale_id,
            'data' => $values
        ]);
    }

    /**
     * Compute total cash from cash count table
     */
    protected function _compute_total_cash($branch_id, $current_date)
    {
        $where = [
            'branch_id' => $branch_id,
            'count_date' => $current_date,
            'type' => 'deposit',
            'is_deleted' => 0
        ];

        if (!$cash_counts = $this->cashCountModel->select('', $where))
            return false;

        $total_cash = 0;

        foreach ($cash_counts as $cash_count) {
            $total_cash += $cash_count['total_count'];
        }

        return $total_cash;
    }

    /**
     * Delete daily_sales
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('daily_sales', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $daily_sale_id = $this->request->getVar('daily_sale_id');

        $where = ['id' => $daily_sale_id, 'is_deleted' => 0];

        if (!$daily_sale = $this->dailySaleModel->select('', $where, 1)) {
            $response = $this->failNotFound('Daily sale not found');
        } elseif (!$this->_attempt_delete($daily_sale_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'Daily sale deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search daily_sales based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('daily_sales', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id') ? : null;
        $date      = $this->request->getVar('date') ? : null;
        $date      = $date ? date('Y-m-d', strtotime($date)) : null;

        $date_from = $this->request->getVar('date_from') ? : null;
        $date_from = $date_from ? date('Y-m-d', strtotime($date_from)) : null;
        $date_to   = $this->request->getVar('date_to') ? : null;
        $date_to   = $date_to ? date('Y-m-d', strtotime($date_to)) : null;

        $inventory_variance_discrepancy = $this->request->getVar('inventory_variance_discrepancy');
        $inventory_variance_discrepancy = ($inventory_variance_discrepancy == "" OR $inventory_variance_discrepancy == null) ? null : $inventory_variance_discrepancy;
        $cash_variance_discrepancy = $this->request->getVar('cash_variance_discrepancy');
        $cash_variance_discrepancy = ($cash_variance_discrepancy == "" OR $cash_variance_discrepancy == null) ? null : $cash_variance_discrepancy;

        if (!$daily_sales = $this->dailySaleModel->search($branch_id, $date, $inventory_variance_discrepancy, $cash_variance_discrepancy, $date_from, $date_to)) {
            $response = $this->failNotFound('No daily sale found');
        } else {
            $response = [];
            $response['data'] = $daily_sales;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Attempt create cash count
     */
    protected function _attempt_create()
    {
        $branch_id       = $this->request->getVar('branch_id') ? : null;
        $sales_for_today = $this->paymentModel->get_sales(true, $branch_id) ? : 0;
        $bill_1000       = $this->request->getVar('bill_1000') ? : 0;
        $bill_500        = $this->request->getVar('bill_500') ? : 0;
        $bill_200        = $this->request->getVar('bill_200') ? : 0;
        $bill_100        = $this->request->getVar('bill_100') ? : 0;
        $bill_50         = $this->request->getVar('bill_50') ? : 0;
        $bill_20         = $this->request->getVar('bill_20') ? : 0;
        $coin_10         = $this->request->getVar('coin_10') ? : 0;
        $coin_5          = $this->request->getVar('coin_5') ? : 0;
        $coin_1          = $this->request->getVar('coin_1') ? : 0;
        $cent_25         = $this->request->getVar('cent_25') ? : 0;
        $cent_10         = $this->request->getVar('cent_10') ? : 0;
        $cent_5          = $this->request->getVar('cent_5') ? : 0;
        $cent_1          = $this->request->getVar('cent_1') ? : 0;
        $total_cash      = ($bill_1000 * 1000 + $bill_500 * 500 + $bill_200 * 200 + $bill_100 * 100 + 
                            $bill_50 * 50 + $bill_20 * 20 + $coin_10 * 10 + $coin_5 * 5 + $coin_1 * 1 + 
                            $cent_25 * 0.25 + $cent_10 * 0.10 + $cent_5 * 0.05 + $cent_1 * 0.01);


        $values = [
            'branch_id'       => $branch_id,
            'sales_report_id' => $this->request->getVar('sales_report_id'),
            'count_date'      => date('Y-m-d H:i:s'),
            'bill_1000'       => $billd_1000,
            'bill_500'        => $bill_500,
            'bill_200'        => $bill_200,
            'bill_100'        => $bill_100,
            'bill_50'         => $bill_50,
            'bill_20'         => $bill_20,
            'coin_10'         => $coin_10,
            'coin_5'          => $coin_5,
            'coin_1'          => $coin_1,
            'cent_25'         => $cent_25,
            'cent_10'         => $cent_10,
            'cent_5'          => $cent_5,
            'cent_1'          => $cent_1,
            'physical_count'  => $total_cash,
            'cash_sales'      => $sales_for_today,
            'overage'         => $total_cash - $sales_for_today ,
            'is_reviewed'     => $this->request->getVar('is_reviewed'),
            'prepared_by'     => $this->request->getVar('prepared_by'),
            'approved_by'     => $this->request->getVar('approved_by'),
            'added_on'        => date('Y-m-d H:i:s'),
        ];

        if (!$daily_sale_id = $this->cashCountModel->insert($values))
            return false;
        
        return $daily_sale_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($daily_sale_id)
    {
        $values = [
            'transaction_type_id' => $this->request->getVar('transaction_type_id'),
            'branch_id'           => $this->request->getVar('branch_id'),
            'commission'          => $this->request->getVar('commission'),
            'updated_by'          => $this->requested_by,
            'updated_on'          => date('Y-m-d H:i:s')
        ];

        if (!$this->cashCountModel->update($daily_sale_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($daily_sale_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $daily_sale_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->dailySaleModel->update($where, $values)) {
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
        $this->dailySaleModel = new Daily_sale();
        $this->dsDeductionModel = new Daily_sale_employee_deduction();
        $this->cashCountModel = new Cash_count();
        $this->expenseModel = new Expense();
        $this->paymentModel = new Payment();
        $this->endingInventoryModel = new Ending_inventory();
        $this->orderDetailIngredModel = new Order_detail_ingredient();
        $this->storeDepositModel = new Store_deposit();
        $this->storeDepositAttachmentModel = new Store_deposit_attachment();
        $this->webappResponseModel  = new Webapp_response();
    }
}

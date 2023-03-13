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

        if (!$cash_count) {
            $response = $this->failNotFound('No cash_count found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $cash_count
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get cash count reports
     */
    public function get_cash_count_reports()
    {
        if (($response = $this->_api_verification('cash_counts', 'get_cash_count_reports')) !== true)
            return $response;

        $branch_id = $this->request->getVar('branch_id') ? : null;
        $date = $this->request->getVar('date') ? : null;
        $date_from = $this->request->getVar('date_from') ? : null;
        $date_to = $this->request->getVar('date_to') ? : null;

        $total_expense = $this->expenseModel->get_total_expense($branch_id, $date, $date_from, $date_to);
        $cash_sales = $this->paymentModel->get_sales($date, $branch_id, $date_from, $date_to, null, 'cash') ?? 0;

        $daily_sales = $this->dailySaleModel->search($branch_id, $date, null, null, $date_from, $date_to);

        $where = [
            'branch_id' => $branch_id,
            'count_date' => $date,
            'type' => 'deposit',
            'is_deleted' => 0
        ];
        $date_from = $date ? $date : $date_from;
        $date_to = $date ? $date : $date_to;
        $deposit_cash_counts = $this->cashCountModel->search($branch_id, null, null, null, null, null, $date_from, $date_to, 'deposit', null);

        $where['type'] = 'change_funds';
        $change_funds_cash_counts = $this->cashCountModel->search($branch_id, null, null, null, null, null, $date_from, $date_to, 'change_funds', null);

        if (!$deposit_cash_counts OR !$change_funds_cash_counts) {
            $response = $this->failNotFound('No cash count reports found');
        } else {
            if ($daily_sales) {
                $daily_sales[0]['actual_total_sales'] = ($daily_sales[0]['total_sales'] - $daily_sales[0]['system_cash_sales']) + $daily_sales[0]['actual_cash_sales'];
                $daily_sales[0]['system_total_sales'] = $daily_sales[0]['total_sales'];
            }

            $deposit_quantities = [];
            $change_funds_quantities = [];

            foreach ($deposit_cash_counts as $index => $deposit_cash_count) {
                $deposit_quantities[] = [
                    $deposit_cash_count['bill_1000'], $deposit_cash_count['bill_500'], $deposit_cash_count['bill_200'], $deposit_cash_count['bill_100'], $deposit_cash_count['bill_50'], $deposit_cash_count['bill_20'], $deposit_cash_count['coin_10'], $deposit_cash_count['coin_5'], $deposit_cash_count['coin_1'], $deposit_cash_count['cent_25']
                ];

                $change_funds_quantities[] = [
                    $change_funds_cash_counts[$index]['bill_1000'], $change_funds_cash_counts[$index]['bill_500'], $change_funds_cash_counts[$index]['bill_200'], $change_funds_cash_counts[$index]['bill_100'], $change_funds_cash_counts[$index]['bill_50'], $change_funds_cash_counts[$index]['bill_20'], $change_funds_cash_counts[$index]['coin_10'], $change_funds_cash_counts[$index]['coin_5'], $change_funds_cash_counts[$index]['coin_1'], $change_funds_cash_counts[$index]['cent_25']
                ];
            }

            $cash_variance = 0;
            if ($cash_sales !== false AND $total_expense !== false) {
                foreach ($deposit_cash_counts as $deposit_cash_count) {
                    $current_cash_variance = ($deposit_cash_count['total_count']) - ($cash_sales - $total_expense);
                    $cash_variance += $current_cash_variance;
                }
            }

            $response = $this->respond([
                'status' => 'success',
                'daily_sales' => $daily_sales,
                'deposit' => $deposit_cash_counts,
                'change_funds' => $change_funds_cash_counts,
                'system_cash_sales' => $cash_sales,
                'total_expenses' => $total_expense,
                'cash_variance' => $cash_variance,
                'deposit_quantities' => $deposit_quantities,
                'change_funds_quantities' => $change_funds_quantities
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

        $where = [
            'count_date' => date("Y-m-d"),
            'branch_id' => $this->request->getVar('branch_id'),
            'is_deleted' => 0
        ];

        /* 
        ** DEFINITIONS:
        ** dep_ = deposit
        ** chf_ = change funds
        **/ 
        if ($this->cashCountModel->select('', $where)) {
            $response = $this->fail(['response' => 'Cash count already exist.']);
        } elseif (!$cash_count_deposit_id = $this->_attempt_create_cash_breakdown('dep_')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create cash count.', 'status' => 'error']);
        } elseif (!$cash_count_change_funds_id = $this->_attempt_create_cash_breakdown('chf_')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create cash count.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'                   => 'Cash count created successfully.',
                'status'                     => 'success',
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

        $branch_id         = $this->request->getVar('branch_id');
        $branch_name       = $this->request->getVar('branch_name');
        $sales_report_id   = $this->request->getVar('sales_report_id');
        $is_reviewed       = $this->request->getVar('is_reviewed');
        $prepared_by       = $this->request->getVar('prepared_by');
        $approved_by       = $this->request->getVar('approved_by');
        $count_date_from   = $this->request->getVar('count_date_from');
        $count_date_to     = $this->request->getVar('count_date_to');
        $type              = $this->request->getVar('type');
        $group_cash_counts = $this->request->getVar('group_cash_counts') ? : false;


        if (!$cash_counts = $this->cashCountModel->search($branch_id, $branch_name, $sales_report_id, $is_reviewed, $prepared_by, $approved_by, $count_date_from, $count_date_to, $type, $group_cash_counts)) {
            $response = $this->failNotFound('No cash_count found');
        } else {
            foreach ($cash_counts as $index => $cash_count) {
                $quantities = [$cash_count['bill_1000'], $cash_count['bill_500'], $cash_count['bill_200'], $cash_count['bill_100'], $cash_count['bill_50'], $cash_count['bill_20'], $cash_count['coin_10'], $cash_count['coin_5'], $cash_count['coin_1'], $cash_count['cent_25']];
                $cash_counts[$index]['quantities'] = $quantities;
            }

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
        $cash_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'cash') ?? 0;
        $gcash_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'gcash') ?? 0;
        $food_panda_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'credit', 'foodpanda') ?? 0;
        $grab_food_sales = $this->paymentModel->get_sales(true, $branch_id, null, null, null, 'credit', 'grabfood') ?? 0;
        $total_sales = $cash_sales + $gcash_sales + $food_panda_sales + $grab_food_sales;

        if (!$sales_report = $this->paymentModel->get_current_sales_report($branch_id)) {
            $response = $this->failNotFound('No sales report found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'cash_sales' => $cash_sales,
                'gcash_sales' => $gcash_sales,
                'food_panda_sales' => $food_panda_sales,
                'grab_food_sales' => $grab_food_sales,
                'total_sales' => $total_sales
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // PRIVATE METHODS
    // --------------------------------------------------------------------

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
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->cashCountModel         = model('App\Models\Cash_count');
        $this->paymentModel           = model('App\Models\Payment');
        $this->expenseModel           = model('App\Models\Expense');
        $this->dailySaleModel         = model('App\Models\Daily_sale');
        $this->orderDetailIngredModel = model('App\Models\Order_detail_ingredient');
        $this->webappResponseModel    = model('App\Models\Webapp_response');

        // TO BE USED FOR attempt create sale function
        $this->total_cash = 0;
    }
}

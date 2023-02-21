<?php

namespace App\Controllers;

use App\Models\Daily_sale;
use App\Models\Cash_count;
use App\Models\Inventory_report;
use App\Models\Inventory_report_item;
use App\Models\Initial_inventory;
use App\Models\initial_inventory_item;
use App\Models\Ending_inventory;
use App\Models\Ending_inventory_item;
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

        $daily_sales = $this->dailySaleModel->get_all_daily_sale();

        if (!$daily_sales) {
            $response = $this->failNotFound('No cash count found');
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
     * Mark as posted or checked
     */
    public function change_status()
    {
        if (($response = $this->_api_verification('daily_sales', 'mark_as_posted')) !== true)
            return $response;

        $daily_sale_id = $this->request->getVar('daily_sale_id');
        $status = $this->request->getVar('status');

        if ($status == 'posted') {
            $values = [
                'status' => 'posted',
                'posted_on' => date("Y-m-d H:i:s"),
                'posted_by' => $this->requested_by,
            ];
        } elseif ($status == 'checked') {
            $values = [
                'status' => 'checked',
                'checked_on' => date("Y-m-d H:i:s"),
                'checked_by' => $this->requested_by,
            ];
        }

        $values['updated_on'] = date("Y-m-d H:i:s");
        $values['updated_by'] = $this->requested_by;
        
        if (!$this->dailySaleModel->select('', ['id' => $daily_sale_id, 'is_deleted' => 0], 1)) {
            $response = $this->fail('Daily sale not found.');
        } elseif (!$this->dailySaleModel->update($daily_sale_id, $values)) {
            $response = $this->fail('Something went wrong.');
        } else {
            $response = $this->respond(['response' => 'Daily sale status changed']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create daily_sale
     */
    public function create()
    {
        if (($response = $this->_api_verification('daily_sales', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$daily_sale_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'      => 'Cash count created successfully.',
                'status'        => 'success',
                'daily_sale_id' => $daily_sale_id
            ]);
        }

        $db->close();
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
     * Delete daily_sales
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('daily_sales', 'delete')) !== true)
            return $response;

        $daily_sale_id = $this->request->getVar('daily_sale_id');

        $where = ['id' => $daily_sale_id, 'is_deleted' => 0];

        if (!$daily_sale = $this->cashCountModel->select('', $where, 1)) {
            $response = $this->failNotFound('daily_sale not found');
        } elseif (!$this->_attempt_delete($daily_sale_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'daily_sale deleted successfully']);
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

        $branch_id       = $this->request->getVar('branch_id');
        $sales_report_id = $this->request->getVar('sales_report_id');
        $is_reviewed     = $this->request->getVar('is_reviewed');
        $prepared_by     = $this->request->getVar('prepared_by');
        $approved_by     = $this->request->getVar('approved_by');


        if (!$daily_sales = $this->cashCountModel->search($branch_id, $sales_report_id, $is_reviewed, $prepared_by, $approved_by)) {
            $response = $this->failNotFound('No daily_sale found');
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
        $this->dailySaleModel   = new Daily_sale();
        $this->cashCountModel   = new Cash_count();
        $this->inventoryReportModel = new Inventory_report();
        $this->inventoryReportItemModel = new Inventory_report_item();
        $this->initialInventoryModel = new Initial_inventory();
        $this->initialInventoryModelItem = new Initial_inventory_item();
        $this->endingInventoryModel = new Ending_inventory();
        $this->endingInventoryModelItem = new Ending_inventory_item();
        $this->webappResponseModel  = new Webapp_response();
    }
}

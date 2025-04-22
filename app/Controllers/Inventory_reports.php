<?php

namespace App\Controllers;

class Inventory_reports extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get inventory_report
     */
    public function get_inventory_report()
    {
        if (($response = $this->_api_verification('inventory_reports', 'get_inventory_report')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $inventory_report_id = $this->request->getVar('inventory_report_id') ? : null;
        $inventory_report    = $inventory_report_id ? $this->inventory_reportModel->get_details_by_id($inventory_report_id) : null;

        if (!$inventory_report) {
            $response = $this->failNotFound('No inventory_report found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $inventory_report
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get initial inventory
     */
    public function get_initial_inventory()
    {
        if (($response = $this->_api_verification('inventory_reports', 'get_initial_inventory')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        if (!$initial_inventories = $this->initialInventoryModel->select('', ['date' => date("Y-m-d"), 'is_deleted' => 0])) {
            $response = $this->failNotFound('No initial inventories found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $initial_inventories
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all inventory_reports
     */
    public function get_all_inventory_report()
    {
        if (($response = $this->_api_verification('inventory_reports', 'get_all_inventory_report')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id') ? : null;

        $inventory_reports = $this->inventory_reportModel->get_all_inventory_report($branch_id);

        if (!$inventory_reports) {
            $response = $this->failNotFound('No inventory_report found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $inventory_reports
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create inventory_report
     * Used for creating ending inventory and showing the initial and ending inventory
     * Used by mobile app for creating and reporting inventory after the day
     */
    public function create_ending_inventory()
    {
        if (($response = $this->_api_verification('inventory_reports', 'create_ending_inventory')) !== true) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        $has_error = false;

        $branch_id = $this->request->getVar('branch_id');

        if (!$ending_inventory_id = $this->_attempt_create_ending_inventory()) {
            $this->db->transRollback(); 
            $response = $this->fail($this->errorMessage);
            $has_error = true;
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'inventory_report created successfully']);
        }

        $this->db->close();

        if ($has_error) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        //return the ending inventory and initial inventory to reduce api call from the mobile app
        $response = $this->_get_ending_and_initial($branch_id, date("Y-m-d"));

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get initial and ending inventories
     */
    public function get_daily_inventories()
    {
        if (($response = $this->_api_verification('inventory_reports', 'get_daily_inventories')) !== true) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id');
        $date = $this->request->getVar('date');
        $response = $this->_get_ending_and_initial($branch_id, $date);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _get_ending_and_initial($branch_id, $date)
    {
        if (!$inventories = $this->endingInventoryModel->get_ending_and_initial($branch_id, $date)) {
            $response = $this->failNotFound('No ending inventory and initial inventory found');
        } else {
            $initial_inventories = [];
            $usage_inventories = [];
            $ending_inventories = [];

            foreach ($inventories as $inventory) {
                $initial_inventories[] = [
                    "item" => $inventory["name"],
                    "item_unit_name" => $inventory["item_unit_name"],
                    "beginning" => $inventory["beginning"],
                    "delivered" => $inventory["delivered"],
                    "initial_total" => $inventory["initial_total"]
                ];

                $usage_inventories[] = [
                    "item" => $inventory["name"],
                    "item_unit_name" => $inventory["item_unit_name"],
                    "actual_usage" => $inventory["actual_usage"],
                    "system_usage" => $inventory["system_usage"],
                    "usage_variance" => $inventory["usage_variance"]
                ];

                $ending_inventories[] = [
                    "item" => $inventory["name"],
                    "item_unit_name" => $inventory["item_unit_name"],
                    "actual_end" => $inventory["actual_end"],
                    "system_end" => $inventory["system_end"],
                    "ending_variance" => $inventory["ending_variance"],
                ];
            }

            $response = $this->respond([
                'status' => 'success',
                'initial_inventories' => $initial_inventories,
                'usage_inventories' => $usage_inventories,
                'ending_inventories' => $ending_inventories
            ]);
        }
        return $response;
    }

    /**
     * Search inventory_reports based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('inventory_reports', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id     = $this->request->getVar('branch_id');
        $item_id       = $this->request->getVar('item_id');
        $beginning_qty = $this->request->getVar('beginning_qty');
        $current_qty   = $this->request->getVar('current_qty');
        $unit          = $this->request->getVar('unit');
        $status        = $this->request->getVar('status');

        if (!$inventory_reports = $this->inventory_reportModel->search($branch_id, $item_id, $beginning_qty, $current_qty, $unit, $status)) {
            $response = $this->failNotFound('No inventory_report found');
        } else {
            $response = $this->respond([
                'data' => $inventory_reports,
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get Item's inventory_report history
     */
    public function get_item_inventory_report_history()
    {
        if (($response = $this->_api_verification('inventory_reports', 'get_item_inventory_report_history')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id');
        if (!$response = $this->_is_existing($this->branchModel, ['id' => $branch_id]))
            return $this->fail(['status' => 'error', 'message' => 'Branch not found']);

        $item_id = $this->request->getVar('item_id');
        if (!$response = $this->_is_existing($this->itemModel, ['id' => $item_id]))
            return $this->fail(['status' => 'error', 'message' => 'Item not found']);

        $encoded_on_to  = $this->request->getVar('encoded_on_to');
        $encded_on_from = $this->request->getVar('encoded_on_from');
        $doc_type       = $this->request->getVar('doc_type');
        

        if (!$result = $this->inventory_reportModel->get_item_history($item_id, $branch_id, $encoded_on_to, $encded_on_from, $doc_type)) {
            $response = $this->fail(['message' => 'No inventory_report history found.', 'status' => 'error']);
        } else {
            $response = $this->respond([
                'data' => $result,
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create ending inventory
     * used in creating daily sales
     */   
    private function _attempt_create_ending_inventory()
    {
        $branch_id            = $this->request->getVar('branch_id');
        $item_ids             = $this->request->getVar('item_ids');
        $item_unit_ids        = $this->request->getVar('item_unit_ids');
        $inventory_quantities = $this->request->getVar('inventory_quantities');
        $breakdown_quantities = $this->request->getVar('breakdown_quantities');
        $total_quantities     = $this->request->getVar('total_quantities');

        foreach ($item_ids as $key => $item_id) {
            $item_unit = $this->itemUnitModel->get_details_by_id($item_unit_ids[$key]) ?? [];
            $item_unit = $item_unit[0] ?? [];
            $inventory = $this->inventoryModel->get_inventory_by_details($item_id, $branch_id, $item_unit_ids[$key]);
            $inventory = $inventory[0] ?? [];
            $system_inventory_qty = $inventory['current_qty'];

            $branch_id = $this->request->getVar('branch_id');
            $variance_inventory_qty = $total_quantities[$key] - $system_inventory_qty;
            $is_inventory_variance = abs($variance_inventory_qty) > $inventory['acceptable_variance'] ? 1 : 0; // 1 means with discrepancy

            $values = [
                'branch_id'              => $branch_id,
                'date'                   => date("Y-m-d"),
                'inventory_id'           => $inventory['id'],
                'item_id'                => $item_id,
                'item_unit_id'           => $item_unit_ids[$key],
                'breakdown_unit'         => $item_unit['breakdown_unit'] ?? '',
                'breakdown_qty'          => $breakdown_quantities[$key],
                'inventory_unit'         => $item_unit['inventory_unit'] ?? '',
                'inventory_qty'          => $inventory_quantities[$key],
                'actual_inventory_quantity' => $total_quantities[$key],
                'system_inventory_quantity' => $system_inventory_qty,
                'variance_inventory_quantity' => $variance_inventory_qty,
                'is_inventory_variance'  => $is_inventory_variance,
                'added_by'               => $this->requested_by,
                'added_on'               => date('Y-m-d H:i:s'),
            ];

            if (!$ending_inventory_item_id = $this->endingInventoryModel->insert($values) OR
                !$this->_generate_adjustments($branch_id, $inventory, $item_id, $total_quantities[$key], $item_unit['inventory_unit'], $system_inventory_qty, $is_inventory_variance)
            ) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        return true;
    }

    protected function _generate_adjustments($branch_id, $inventory, $item_id, $physical_count, $unit, $computer_count, $is_inventory_variance)
    {
        $where = ['name' => 'Discrepancy', 'is_deleted' => 0];
        $discrepancy_adjustment_type = $this->adjustmentTypeModel->select('', $where, 1);
        $where = ['name' => 'System Adjustment', 'is_deleted' => 0];
        $system_adjustment_type = $this->adjustmentTypeModel->select('', $where, 1);

        $remarks_condition = $is_inventory_variance ? " not" : "";
        $remarks = "Item discrepancy is$remarks_condition within acceptable variance";

        $values = [
            'branch_id' => $branch_id,
            'inventory_id' => $inventory['id'],
            'item_id' => $item_id,
            'type_id' => $is_inventory_variance ? $system_adjustment_type['id'] : $discrepancy_adjustment_type['id'],
            'counted_by' => SYSTEM_ID,
            'physical_count' => $physical_count,
            'unit' => $unit,
            'cost' => 0.00,
            'approved_on' => date("Y-m-d H:i:s"),
            'approved_by' => SYSTEM_ID,
            'difference' => $physical_count - $computer_count,
            'computer_count' => $computer_count,
            'difference_cost' => 0.00,
            'status' => $is_inventory_variance ? 'pending' : 'system_adjustment',
            'remarks' => $remarks,
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s')
        ];
        
        if ($is_inventory_variance == 0 AND !$this->_adjust_inventory($inventory, $physical_count)) false;

        return ($this->adjustmentModel->insert($values)) ? true : false;
    }

    protected function _adjust_inventory($inventory, $physical_count)
    {
        $values = [
            'current_qty' => $physical_count,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        return ($this->inventoryModel->update($inventory['id'], $values)) ? true : false;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->inventoryReportModel      = model('App\Models\Inventory_report');
        $this->inventoryReportItemModel  = model('App\Models\Inventory_report_item');
        $this->adjustmentModel           = model('App\Models\Adjustment');
        $this->adjustmentTypeModel       = model('App\Models\Adjustment_type');
        $this->initialInventoryModel     = model('App\Models\Initial_inventory');
        $this->endingInventoryModel      = model('App\Models\Ending_inventory');
        $this->endingInventoryItemModel  = model('App\Models\Ending_inventory_item');
        $this->itemUnitModel             = model('App\Models\Item_unit');
        $this->inventoryModel            = model('App\Models\Inventory');
        $this->transferReceiveItemModel  = model('App\Models\Transfer_receive_item');
        $this->orderModel                = model('App\Models\Order');
        $this->orderDetailIngredModel    = model('App\Models\Order_detail_ingredient');
        $this->webappResponseModel       = model('App\Models\Webapp_response');
        $this->webappResponseItemModel   = model('App\Models\Webapp_response_item');
    }
}

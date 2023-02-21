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
     * Get all inventory_reports
     */
    public function get_all_inventory_report()
    {
        if (($response = $this->_api_verification('inventory_reports', 'get_all_inventory_report')) !== true)
            return $response;

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
        if (($response = $this->_api_verification('inventory_reports', 'create')) !== true) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$ending_inventory_id = $this->_attempt_create_ending_inventory()) {
            $this->db->transRollback(); 
            $response = $this->fail($this->errorMessage);
        } else if (!$this->_attempt_create_ending_inventory_item($ending_inventory_id)) {
            $this->db->transRollback();
            $response = $this->respond($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'inventory_report created successfully']);
        }

        $this->db->close();

        //return the ending inventory and initial inventory to reduce api call from the mobile app
        if (!$ending_inventory = $this->endingInventoryModel->get_details_by_id($ending_inventory_id)) {
            $response = $this->failNotFound('No ending inventory found');
        } else {
            // get the last ending inventory made by the branch
            if ($initial_inventory = $this->endingInventoryModel->get_details_from_yesterday_ending($this->request->getVar('branch_id'), $ending_inventory_id)) {
                $initial_inventory_items = $this->endingInventoryItemModel->get_details_by_ending_inventory_id($initial_inventory[0]['id']);

                $transferred_items_today = $this->transferReceiveItemModel->get_all_items_received_today($this->request->getVar('branch_id')) ? : [];
                
                // loop through the transferred items today and add it to the initial inventory
                foreach ($transferred_items_today as $transferred_item) {
                    $item_found = false;
                    foreach ($initial_inventory_items as $key => $initial_inventory_item) {
                        if ($initial_inventory_item['item_id'] == $transferred_item['item_id']) {
                            $initial_inventory_items[$key]['actual_inventory_qty'] += $transferred_item['qty'];
                            $initial_inventory_items[$key]['system_inventory_qty'] += $transferred_item['qty'];
                            $item_found = true;
                            break;
                        }
                    }

                    if (!$item_found) {
                        $initial_inventory_items[] = [
                            'item_id' => $transferred_item['item_id'],
                            'actual_inventory_qty' => $transferred_item['qty'],
                            'system_inventory_qty' => $transferred_item['qty'],
                        ];
                    }
                }

                $initial_inventory[0]['items'] = $initial_inventory_items;
            }

            $ending_inventory[0]['items'] = $this->endingInventoryItemModel->get_details_by_ending_inventory_id($ending_inventory_id);

            $response = $this->respond([
                'status' => 'success',
                'ending_inventory' => $ending_inventory,
                'initial_inventory' => $initial_inventory
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search inventory_reports based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('inventory_reports', 'search')) !== true)
            return $response;

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
     * Attempt to create inventory_report
     */
    private function _attempt_create_ending_inventory()
    {
        $values = [
            'branch_id'              => $this->request->getVar('branch_id'),
            'date'                   => $this->request->getVar('date'),
            'added_by'               => $this->requested_by,
            'added_on'               => date('Y-m-d H:i:s'),
        ];

        if (!$ending_inventory_id = $this->endingInventoryModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $ending_inventory_id;
    }

    /**
     * Attempt to create ending inventory item
     * used in creating daily sales
     */   
    private function _attempt_create_ending_inventory_item($ending_inventory_id)
    {
        $item_ids             = $this->request->getVar('item_ids');
        $item_unit_ids        = $this->request->getVar('item_unit_ids');
        $inventory_quantities = $this->request->getVar('inventory_quantities');
        $breakdown_quantities = $this->request->getVar('breakdown_quantities');

        foreach ($item_ids as $key => $item_id) {
            $item_unit = $this->itemUnitModel->get_details_by_id($item_unit_ids[$key]) ?? [];
            $item_unit = $item_unit[0] ?? [];
            $system_breakdown_qty = $this->orderModel->get_item_breakdown_quantity($item_id, $item_unit_ids[$key], $this->request->getVar('branch_id'));
            $system_inventory_qty = 0;
            $inventory = $this->inventoryModel->get_inventory_by_details($item_id, $this->request->getVar('branch_id'), $item_unit_ids[$key]);
            $inventory = $inventory[0] ?? [];

            $values = [
                'ending_inventory_id'    => $ending_inventory_id,
                'inventory_id'           => $inventory['id'] ?? 0,
                'item_id'                => $item_id,
                'item_unit_id'           => $item_unit_ids[$key],
                'breakdown_unit'         => $item_unit['breakdown_unit'] ?? '',
                'actual_breakdown_qty'   => $breakdown_quantities[$key],
                'system_breakdown_qty'   => $system_breakdown_qty ?? 0,
                'variance_breakdown_qty' => $breakdown_quantities[$key] - $system_breakdown_qty,
                'inventory_unit'         => $item_unit['unit'] ?? '',
                'actual_inventory_qty'   => $inventory_quantities[$key],
                'system_inventory_qty'   => $system_inventory_qty,
                'variance_inventory_qty' => $inventory_quantities[$key] - $system_inventory_qty,
                'total_inventory'        => $inventory_quantities[$key] + ($breakdown_quantities[$key] / $item_unit['breakdown_value']),
                'total_breakdown'        => ($inventory_quantities[$key] * $item_unit['breakdown_value']) + $breakdown_quantities[$key],
                'added_by'               => $this->requested_by,
                'added_on'               => date('Y-m-d H:i:s'),
            ];

            if (!$ending_inventory_item_id = $this->endingInventoryItemModel->insert($values)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->inventoryReportModel      = model('App\Models\Inventory_report');
        $this->inventoryReportItemModel  = model('App\Models\Inventory_report_item');
        $this->initialInventoryModel     = model('App\Models\Initial_inventory');
        $this->initialInventoryItemModel = model('App\Models\Initial_inventory_item');
        $this->endingInventoryModel      = model('App\Models\Ending_inventory');
        $this->endingInventoryItemModel  = model('App\Models\Ending_inventory_item');
        $this->itemUnitModel             = model('App\Models\Item_unit');
        $this->inventoryModel            = model('App\Models\Inventory');
        $this->transferReceiveItemModel  = model('App\Models\Transfer_receive_item');
        $this->orderModel                = model('App\Models\Order');
        $this->webappResponseModel       = model('App\Models\Webapp_response');
        $this->webappResponseItemModel   = model('App\Models\Webapp_response_item');
    }
}

<?php

namespace App\Controllers;

class Inventories extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get inventory
     */
    public function get_inventory()
    {
        if (($response = $this->_api_verification('inventories', 'get_inventory')) !== true)
            return $response;

        $inventory_ids = $this->request->getVar('inventory_ids') ? : [];
        $inventory_id  = $this->request->getVar('inventory_id') ? : null;
        if ($inventory_id)
            $inventory_ids[] = $inventory_id;
        $inventory     = $inventory_ids ? $this->inventoryModel->get_details_by_ids($inventory_ids) : null;

        if (!$inventory) {
            $response = $this->failNotFound('No inventory found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $inventory
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all inventories
     */
    public function get_all_inventory()
    {
        if (($response = $this->_api_verification('inventories', 'get_all_inventory')) !== true)
            return $response;

        $branch_id     = $this->request->getVar('branch_id') ? : null;
        $is_low_level  = $this->request->getVar('is_low_level') ? : null;
        $is_high_level = $this->request->getVar('is_high_level') ? : null;
        $inventories   = $this->inventoryModel->get_all_inventory($branch_id, $is_low_level, $is_high_level);

        if (!$inventories) {
            $response = $this->failNotFound('No inventory found');
        } else {
            foreach ($inventories as $key => $inventory) {
                $inventories[$key]['ongoing_po'] = $this->purchaseItemModel->get_all_ongoing_by_inventory($inventory['item_id'], $inventory['branch_id']);
            }
            $response = $this->respond([
                'status' => 'success',
                'data' => $inventories
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create inventory
     */
    public function create()
    {
        if (($response = $this->_api_verification('inventories', 'create')) !== true) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'inventory created successfully']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update inventory
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('inventories', 'update')) !== true)
            return $response;

        $inventory_id = $this->request->getVar('inventory_id');
        $where = ['id' => $inventory_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$inventory = $this->inventoryModel->select('', $where, 1)) {
            $this->db->transRollback();
            $response = $this->failNotFound('inventory not found');
        } elseif (!$this->_attempt_update($inventory_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'inventory updated successfully']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete inventories
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('inventories', 'delete')) !== true)
            return $response;

        $inventory_id = $this->request->getVar('inventory_id');

        $where = ['id' => $inventory_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$inventory = $this->inventoryModel->select('', $where, 1)) {
            $response = $this->failNotFound('inventory not found');
        } elseif (!$this->_attempt_delete($inventory_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'inventory deleted successfully']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search inventories based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('inventories', 'search')) !== true)
            return $response;

        $branch_id     = $this->request->getVar('branch_id');
        $item_id       = $this->request->getVar('item_id');
        $beginning_qty = $this->request->getVar('beginning_qty');
        $current_qty   = $this->request->getVar('current_qty');
        $unit          = $this->request->getVar('unit');
        $status        = $this->request->getVar('status');
        $name          = $this->request->getVar('name');
        $item_type     = $this->request->getVar('item_type');
        $limit_by      = $this->request->getVar('limit_by');
        $low_stock     = $this->request->getVar('low_stock');
        $high_stock    = $this->request->getVar('high_stock');
        $normal_stock  = $this->request->getVar('normal_stock');
        $with_po       = $this->request->getVar('with_po');
        
        if (!$inventories = $this->inventoryModel->search($branch_id, $item_id, $beginning_qty, $current_qty, $unit, $status, $name, $item_type, $limit_by, $low_stock, $high_stock, $normal_stock)) {
            $response = $this->failNotFound('No inventory found');
        } else {
            $filtered_inventories = [];
            foreach ($inventories as $key => $inventory) {
                $pos = $this->purchaseItemModel->get_all_ongoing_by_inventory($inventory['item_id'], $inventory['branch_id']);

                if ($with_po == 1 && count($pos) > 0) {
                    $inventories[$key]['ongoing_po'] = $pos;
                    $filtered_inventories[] = $inventories[$key];
                } elseif ($with_po == 0 && $with_po != '') {
                    $filtered_inventories[] = $inventories[$key];
                } elseif ($with_po == '') {
                    $inventories[$key]['ongoing_po'] = $pos;
                    $filtered_inventories[] = $inventories[$key];
                }
            }

            $item_classifications = $this->itemModel->get_item_classification_by_branch($branch_id);
            $item_classifications = array_column($item_classifications, 'type');
            $response = $this->respond([
                'item_classifications' => $item_classifications,
                'data'                 => $filtered_inventories,
                'status'               => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all items with negative
     * quantity per branch
     */
    public function get_negative_inventory()
    {
        if (($response = $this->_api_verification('inventories', 'get_negative_inventory')) !== true)
            return $response;

        if (!$negative_items = $this->inventoryModel->get_negative_qty()) {
            $response = $this->fail('No negative items found');
        } else {
            $response = $this->respond(['negative_items' => $negative_items]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get Item's inventory history
     */
    public function get_item_inventory_history()
    {
        if (($response = $this->_api_verification('inventories', 'get_item_inventory_history')) !== true)
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
        $item_unit_id   = $this->request->getVar('item_unit_id');
        $branch_name    = $this->request->getVar('branch_name');
        $doc_no         = $this->request->getVar('doc_no');

        $temporary_storage_encoded_on_to = $encoded_on_to;
        $temporary_storage_encoded_on_from = $encded_on_from;
        $encoded_on_to = null;
        $encded_on_from = null;

        if (!$inventory = $this->inventoryModel->get_inventory_detail($item_id, $branch_id, $item_unit_id)) {
            $response = $this->fail(['message' => 'No inventory found.', 'status' => 'error']);
        } else if (!$history = $this->inventoryModel->get_item_history($item_id, $branch_id, $encoded_on_to, $encded_on_from, $doc_type, $item_unit_id, $branch_name, $doc_no)) {
            $response = $this->fail(['message' => 'No inventory history found.', 'status' => 'error']);
        } else {

            $current_qty = $inventory[0]['beginning_qty'];
            $final_history = [];

            foreach ($history as $key => $value) {
                $qty_out = (float)$value['qty_out'];
                $qty_in = (float)$value['qty_in'];
                $qty_out = $qty_out < 0 ? $qty_out * -1 : $qty_out; // this solves the problem with adjustments
                $current_qty += $qty_in - $qty_out;
                $history[$key]['current_qty'] = $current_qty;

                if ($history[$key]['encoded_on'] >= $temporary_storage_encoded_on_from AND
                    $history[$key]['encoded_on'] <= $temporary_storage_encoded_on_to
                ) {
                    $final_history[$key] = $history[$key];
                }
            }

            // insert a row in the beginning of the history
            $final_history = array_merge([[
                'id'            => 0,
                'item_id'       => $item_id,
                'branch_id'     => $branch_id,
                'item_unit_id'  => $item_unit_id,
                'qty_in'        => 0,
                'qty_out'       => 0,
                'current_qty'   => $inventory[0]['beginning_qty'],
                'encoded_on'    => '',
                'doc_type'      => 'Beginning Qty',
                'doc_date'      => $inventory[0]['added_on']
            ]], $final_history);

            // insert a row in the end of the history
            $final_history[] = [
                'id'            => 0,
                'item_id'       => $item_id,
                'branch_id'     => $branch_id,
                'item_unit_id'  => $item_unit_id,
                'qty_in'        => 0,
                'qty_out'       => 0,
                'current_qty'   => $inventory[0]['current_qty'],
                'encoded_on'    => date('Y-m-d'),
                'doc_type'      => 'Current Qty'
            ];

            $response = $this->respond([
                'inventory' => $inventory,
                'history'   => $final_history,
                'status'    => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * DANGER: Matches all currenty quantities to the sum of all transactions
     */
    function match_item_history() {
        if (($response = $this->_api_verification('inventories', 'match_item_history')) !== true)
            return $response;

        if (!$this->inventoryModel->match_qty_base_computer_count()) {
            $response = $this->fail(['message' => 'No inventory found.', 'status' => 'error']);
        } else {
            $response = $this->respond(['message' => 'Inventory matched successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get low stock inventories
     */
    public function get_low_stock()
    {
        if (($response = $this->_api_verification('inventories', 'get_low_stock')) !== true)
            return $response;

        if (!$inventory = $this->inventoryModel->get_low_stock_items()) {
            $response = $this->fail(['message' => 'No inventory found.', 'status' => 'error']);
        } else {
            $response = $this->respond(['inventory' => $inventory, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update Warehouse Inventory
     */
    public function update_warehouse_inventory()
    {
        if (($response = $this->_api_verification('inventories', 'update_warehouse_inventory')) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$this->_attempt_update_warehouse_inventory()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['message' => 'Inventory updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get warehouse inventory ids base on item id
     */
    public function get_warehouse_inventory_details()
    {
        if (($response = $this->_api_verification('inventories', 'get_warehouse_inventory_ids')) !== true)
            return $response;

        $item_id = $this->request->getVar('item_id');
        if (!$response = $this->_is_existing($this->itemModel, ['id' => $item_id]))
            return $this->fail(['status' => 'error', 'message' => 'Item not found']);

        $warehouse_inventories = $this->inventoryModel->get_warehouse_inventory_details($item_id);
        $inventory_group_details = $this->inventoryModel->get_inventory_group_inventory_details($item_id);

        $data = [
            'warehouse_inventories' => $warehouse_inventories,
            'inventory_group_details' => $inventory_group_details,
        ];

        $response = $this->respond([
            'data' => $data, 
            'status' => 'success'
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get transferrable items base on requesting branch and requested branch
     */
    public function get_transferrable_items()
    {
        if (($response = $this->_api_verification('inventories', 'get_transferrable_items')) !== true)
            return $response;

        $requesting_branch_id = $this->request->getVar('requesting_branch_id');
        $requested_branch_id = $this->request->getVar('requested_branch_id');

        if (!$response = $this->_is_existing($this->branchModel, ['id' => $requesting_branch_id]))
            return $this->fail(['status' => 'error', 'message' => 'Requesting branch not found']);

        if (!$response = $this->_is_existing($this->branchModel, ['id' => $requested_branch_id]))
            return $this->fail(['status' => 'error', 'message' => 'Requested branch not found']);

        if ($transferrable_items = $this->inventoryModel->get_transferrable_items($requesting_branch_id, $requested_branch_id)) {
            $response = $this->respond([
                'data' => $transferrable_items, 
                'status' => 'success'
            ]);
        } else {
            $response = $this->fail(['status' => 'error', 'message' => 'No transferrable items found']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create inventory
     */
    private function _attempt_create()
    {
        $values = [
            'branch_id'              => $this->request->getVar('branch_id'),
            'item_id'                => $this->request->getVar('item_id'),
            'beginning_qty'          => $this->request->getVar('beginning_qty'),
            'current_qty'            => $this->request->getVar('current_qty'),
            'min'                    => $this->request->getVar('min'),
            'max'                    => $this->request->getVar('max'),
            'acceptable_variance'    => $this->request->getVar('acceptable_variance'),
            'unit'                   => $this->request->getVar('unit'),
            'status'                 => $this->request->getVar('status'),
            'added_by'               => $this->requested_by,
            'added_on'               => date('Y-m-d H:i:s'),
        ];

        if (!$inventory_id = $this->inventoryModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $inventory_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($inventory_id)
    {
        $values = [
            'branch_id'              => $this->request->getVar('branch_id'),
            'item_id'                => $this->request->getVar('item_id'),
            'beginning_qty'          => $this->request->getVar('beginning_qty'),
            'current_qty'            => $this->request->getVar('current_qty'),
            'min'                    => $this->request->getVar('min'),
            'max'                    => $this->request->getVar('max'),
            'acceptable_variance'    => $this->request->getVar('acceptable_variance'),
            'unit'                   => $this->request->getVar('unit'),
            'status'                 => $this->request->getVar('status'),
            'updated_by'             => $this->requested_by,
            'updated_on'             => date('Y-m-d H:i:s')
        ];

        if (!$this->inventoryModel->update($inventory_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($inventory_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->inventoryModel->update($inventory_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Update warehouse inventory
     */
    private function _attempt_update_warehouse_inventory()
    {
        $item_id               = $this->request->getVar('item_id');
        $item_unit_id          = $this->request->getVar('item_unit_id');
        $branch_ids            = $this->request->getVar('branch_ids');
        $inventory_group_ids   = $this->request->getVar('inventory_group_ids');
        $default_item_unit_ids = $this->request->getVar('default_item_unit_ids');
        $minimum_levels        = $this->request->getVar('minimum_levels');
        $maximum_levels        = $this->request->getVar('maximum_levels');
        $acceptable_variances  = $this->request->getVar('acceptable_variances');
        $beginning_quantities  = $this->request->getVar('beginning_quantities');
        $units                 = $this->request->getVar('units');
        $critical_levels       = $this->request->getVar('critical_levels');        

        foreach ($branch_ids as $key => $branch_id) {
            $inventory_group_id   = $inventory_group_ids[$key];
            
            $values = [
                'beginning_qty'          => $beginning_quantities[$key],
                'min'                    => $minimum_levels[$key],
                'max'                    => $maximum_levels[$key],
                'critical_level'         => $critical_levels[$key],
                'acceptable_variance'    => $acceptable_variances[$key],
                'item_unit_id'           => $default_item_unit_ids[$key],
                'unit'                   => $units[$key],
                'added_by'               => $this->requested_by,
                'added_on'               => date('Y-m-d H:i:s'),
            ];

            if ($inventory_group_id) {
                $branches_under_inventory_group = $this->inventoryGroupDetailModel->get_branches_by_inventory_group_id($inventory_group_id);
                $branches_under_inventory_group = array_column($branches_under_inventory_group, 'branch_id');
                foreach ($branches_under_inventory_group as $key2 => $inventory_group_branch) {
                    $warehouse_ids = $this->inventoryModel->get_warehouse_inventory_ids($item_id, $item_unit_id, $inventory_group_branch);
                    $warehouse_ids = array_column($warehouse_ids, 'id');
                   // Update all the warehouses with the current data
                    if (!$this->_update_inventory_warehouses($warehouse_ids, $values)) {
                        return false;
                    }
                }
            } elseif ($branch_id) {
                $warehouse_ids = $this->inventoryModel->get_warehouse_inventory_ids($item_id, $item_unit_id, $branch_id);
                $warehouse_ids = array_column($warehouse_ids, 'id');
                // Update all the warehouses with the current data
                if (!$this->_update_inventory_warehouses($warehouse_ids, $values)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Update inventory warehouses
     */
    private function _update_inventory_warehouses($warehouse_ids, $values)
    {
        foreach ($warehouse_ids as $key2 => $warehouse_id) {
            if (!$inventory = $this->inventoryModel->get_details_by_id($warehouse_id)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
 
            $inventory = $inventory[0];
            $values['current_qty'] = ($inventory['current_qty'] + ($values['beginning_qty'] - $inventory['beginning_qty']));
            
            if (!$this->inventoryModel->update($inventory['id'], $values)) {
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
        $this->inventoryModel            = model('App\Models\Inventory');
        $this->branchModel               = model('App\Models\Branch');
        $this->itemModel                 = model('App\Models\Item');
        $this->purchaseItemModel         = model('App\Models\Purchase_item');
        $this->inventoryGroupModel       = model('App\Models\Inventory_group');
        $this->inventoryGroupDetailModel = model('App\Models\Inventory_group_detail');
        $this->webappResponseModel       = model('App\Models\Webapp_response');
    }
}

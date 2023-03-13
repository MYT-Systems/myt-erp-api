<?php

namespace App\Controllers;

class Items extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get item
     */
    public function get_item()
    {
        if (($response = $this->_api_verification('items', 'get_item')) !== true )
            return $response;

        $item_id    = $this->request->getVar('item_id') ? : null;
        $item       = $item_id ? $this->itemModel->get_details_by_id($item_id) : null;
        $item_units = $item_id ? $this->itemUnitModel->get_details_by_item_id($item_id) : null;
        
        if (!$item) {
            $response = $this->failNotFound('No item found');
        } else {
            $item[0]['item_units'] = $item_units;
            $response = $this->respond([
                'data'   => $item,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all items
     */
    public function get_all_item()
    {
        if (($response = $this->_api_verification('items', 'get_all_item')) !== true) {
            return $response;
        }

        $branch_id = $this->request->getVar('branch_id') ? : null;
        $all       = $this->request->getVar('all') ? : false;
        $items     = $this->itemModel->get_all_item($branch_id, $all);
        
        if (!$items) {
            $response = $this->failNotFound('No item found');
        } else {
            foreach ($items as $key => $item) {
                $items[$key]['item_units'] = $this->itemUnitModel->get_details_by_item_id($item['id'], $branch_id);
            }

            $classifications = $this->itemModel->get_all_classification();
            $classifications = array_column($classifications, 'type');
            $response = $this->respond([
                'classifications' => $classifications,
                'data' => $items,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create item
     */
    public function create()
    {
        if (($response = $this->_api_verification('items', 'create')) !== true || 
        ($response = $this->_validation_check(['item'])) !== true) {
            return $response;
        }

        $name = $this->request->getVar('name');
        if ($response = $this->_is_existing($this->itemModel, ['name' => $name]))
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$item_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_units($item_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'data' => $item_id,
                'status' => 'success'
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update item
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('items', 'update')) !== true)
            return $response;

        $item_id = $this->request->getVar('item_id');
        $where   = ['id' => $item_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$item = $this->itemModel->select('', $where, 1)) {
            $response = $this->failNotFound('item not found');
        } elseif (!$this->_attempt_update($item)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Item updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete items
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('items', 'delete')) !== true)
            return $response;

        $item_id = $this->request->getVar('item_id');
        $where   = ['id' => $item_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$item = $this->itemModel->select('', $where, 1)) {
            $response = $this->failNotFound('item not found');
        } elseif (!$this->_attempt_delete($item_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Item deleted successfully', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search items based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('items', 'search')) !== true)
            return $response;

        $name           = $this->request->getVar('name');
        $breakdown_unit = $this->request->getVar('breakdown_unit');
        $inventory_unit = $this->request->getVar('inventory_unit');
        $detail         = $this->request->getVar('detail');
        $price          = $this->request->getVar('price');
        $type           = $this->request->getVar('type');
        $is_dsr         = $this->request->getVar('is_dsr');
        $is_active      = $this->request->getVar('is_active');
        $is_for_sale    = $this->request->getVar('is_for_sale');

        if (!$items = $this->itemModel->search($name, $breakdown_unit, $inventory_unit, $detail, $price, $type, $is_dsr, $is_active, $is_for_sale)) {
            $response = $this->failNotFound('No item found');
        } else {
            $classifications = $this->itemModel->get_all_classification();
            $classifications = array_column($classifications, 'type');
            foreach ($items as $key => $item) {
                $items[$key]['item_units'] = $this->itemUnitModel->get_details_by_item_id($item['id'], 1);
            }
            $response = $this->respond([
                // 'classifications' => $classifications,
                'classifications' => ['ingredient','supplies','cleaning_supplies','office_supplies','equipment','uniform','beverage','raw_material','store_supplies','commissary_supplies','commissary_equipment','store_equipment','carpentry','electrical','painting','smallwares'],
                'data'   => $items,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to generate units
     */
    protected function _attempt_generate_units($item_id)
    {
        $breakdown_units      = $this->request->getVar('breakdown_units') ?? [];
        $breakdown_values     = $this->request->getVar('breakdown_values') ?? [];
        $inventory_units      = $this->request->getVar('inventory_units') ?? [];
        $inventory_values     = $this->request->getVar('inventory_values') ?? [];
        $minimums             = $this->request->getVar('minimums') ?? [];
        $maximums             = $this->request->getVar('maximums') ?? [];
        $acceptable_variances = $this->request->getVar('acceptable_variances') ?? [];
        $prices               = $this->request->getVar('prices') ?? [];

        $values = [
            'item_id'   => $item_id,
            'added_by'  => $this->requested_by,
            'added_on'  => date('Y-m-d H:i:s'),
        ];

        foreach ($inventory_units as $key => $inventory_unit) {
            $values['breakdown_unit']      = $breakdown_units[$key];
            $values['breakdown_value']     = $breakdown_values[$key];
            $values['inventory_unit']      = $inventory_unit;
            $values['inventory_value']     = $inventory_values[$key];
            $values['min']                 = $minimums[$key];
            $values['max']                 = $maximums[$key];
            $values['acceptable_variance'] = $acceptable_variances[$key];

            // Check if there is a price for this unit
            if (isset($prices[$key]))
                $values['price'] = $prices[$key];

            if (!$item_unit_id = $this->itemUnitModel->insert_on_duplicate_key_update($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
            
            if ($this->request->getVar('is_for_sale')) {
                $franchisee_sale_item_price_values = [
                    'item_id'      => $item_id,
                    'item_unit_id' => $item_unit_id,
                    'unit'         => $inventory_unit,
                    'type'         => $this->request->getVar('type') ?? 'item',
                    'price_1'      => 0,
                    'price_2'      => 0,
                    'price_3'      => 0,
                    'added_by'     => $this->requested_by,
                    'added_on'     => date('Y-m-d H:i:s'),
                    'is_deleted'   => 0
                ];

                if (!$this->franchiseSaleItemPrice->insert_on_duplicate($franchisee_sale_item_price_values, $this->requested_by, $this->db)) {
                    $this->errorMessage = $this->db->error()['message'];
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Attempt to create item
     */
    protected function _attempt_create()
    {
        $values = [
            'name'        => $this->request->getVar('name'),
            'detail'      => $this->request->getVar('detail'),
            'type'        => $this->request->getVar('type'),
            'is_dsr'      => $this->request->getVar('is_dsr'),
            'is_active'   => $this->request->getVar('is_active'),
            'is_for_sale' => $this->request->getVar('is_for_sale'),
            'added_by'    => $this->requested_by,
            'added_on'    => date('Y-m-d H:i:s'),
            'is_deleted'  => 0
        ];

        if (!$item_id = $this->itemModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $item_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($item)
    {
        $values = [
            'name'        => $this->request->getVar('name') ?? $item['name'],
            'detail'      => $this->request->getVar('detail') ?? $item['detail'],
            'type'        => $this->request->getVar('type') ?? $item['type'],
            'is_dsr'      => $this->request->getVar('is_dsr') ?? $item['is_dsr'],
            'is_active'   => $this->request->getVar('is_active') ?? $item['is_active'],
            'is_for_sale' => $this->request->getVar('is_for_sale') ?? $item['is_for_sale'],
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        if (!$this->itemModel->update($item['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // if ($this->request->getVar('prices')) {
        //     if (!$this->itemUnitModel->delete_by_item_id($item['id'], $this->requested_by, $this->db)) {
        //         $this->errorMessage = $this->db->error()['message'];
        //         return false;
        //     }

        //     if (!$this->franchiseSaleItemPrice->delete_by_item_id($item['id'], $this->requested_by, $this->db)) {
        //         $this->errorMessage = $this->db->error()['message'];
        //         return false;
        //     }
        //     if (!$this->_attempt_generate_units($item['id']))
        //         return false;      
        // }
        
        // // If item is for sale, add them to franchisee sale item price
        // if ($this->request->getVar('is_for_sale') == '0') {
        //     if (!$this->franchiseSaleItemPrice->delete_by_item_id($item['id'], $this->requested_by, $this->db)) {
        //         $this->errorMessage = $this->db->error()['message'];
        //         return false;
        //     }
        // } 

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($item_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->itemModel->update($item_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->itemUnitModel->delete_by_item_id($item_id, $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->franchiseSaleItemPrice->delete_by_item_id($item_id, $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->itemModel              = model('App\Models\Item');
        $this->itemUnitModel          = model('App\Models\Item_unit');
        $this->franchiseSaleItemPrice = model('App\Models\Franchise_sale_item_price');
        $this->webappResponseModel    = model('App\Models\Webapp_response');
    }
}

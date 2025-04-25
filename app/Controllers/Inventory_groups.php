<?php

namespace App\Controllers;

class Inventory_groups extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get inventory_group
     */
    public function get_inventory_group()
    {
        if (($response = $this->_api_verification('inventory_groups', 'get_inventory_group')) !== true )
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $inventory_group_id      = $this->request->getVar('inventory_group_id') ? : null;
        $inventory_group         = $inventory_group_id ? $this->inventoryGroupModel->get_details_by_id($inventory_group_id) : null;
        $inventory_group_details = $inventory_group_id ? $this->inventoryGroupDetailModel->get_details_by_inventory_group_id($inventory_group_id) : null;
        
        if (!$inventory_group) {
            $response = $this->failNotFound('No inventory group found');
        } else {
            $inventory_group[0]['inventory_group_details'] = $inventory_group_details;
            $response = $this->respond([
                'data'   => $inventory_group,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all inventory_groups
     */
    public function get_all_inventory_group()
    {
        if (($response = $this->_api_verification('inventory_groups', 'get_all_inventory_group')) !== true) {
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
        
        if (!$inventory_groups = $this->inventoryGroupModel->get_all()) {
            $response = $this->failNotFound('No inventory group found');
        } else {
            foreach ($inventory_groups as $key => $inventory_group) {
                $inventory_groups[$key]['inventory_group_details'] = $this->inventoryGroupDetailModel->get_details_by_inventory_group_id($inventory_group['id']);
            }

            $response = $this->respond([
                'data' => $inventory_groups,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get item levels (min, max, current, and etc.)
     */
    public function get_item_levels()
    {
        if (($response = $this->_api_verification('inventory_groups', 'get_item_levels')) !== true) {
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $item_id = $this->request->getVar('item_id');

        if (!$item_levels = $this->inventoryModel->get_item_levels($item_id)) {
            $response = $this->failNotFound('No item levels found');
        } else {
            $response = $this->respond([
                'data' => $item_levels,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create inventory_group
     */
    public function create()
    {
        if (($response = $this->_api_verification('inventory_groups', 'create')) !== true) {
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $name = $this->request->getVar('name');
        if ($response = $this->_is_existing($this->inventoryGroupModel, ['name' => $name]))
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$inventory_group_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_details($inventory_group_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'data' => $inventory_group_id,
                'status' => 'success'
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update inventory_group
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('inventory_groups', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $inventory_group_id = $this->request->getVar('inventory_group_id');
        $where   = ['id' => $inventory_group_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$inventory_group = $this->inventoryGroupModel->select('', $where, 1)) {
            $response = $this->failNotFound('inventory group not found');
        } elseif (!$this->_attempt_update($inventory_group)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'inventory group updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete inventory_groups
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('inventory_groups', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $inventory_group_id = $this->request->getVar('inventory_group_id');
        $where   = ['id' => $inventory_group_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$inventory_group = $this->inventoryGroupModel->select('', $where, 1)) {
            $response = $this->failNotFound('inventory group not found');
        } elseif (!$this->_attempt_delete($inventory_group_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'inventory group deleted successfully', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search inventory_groups based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('inventory_groups', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $name                = $this->request->getVar('name');
        $min                 = $this->request->getVar('min');
        $max                 = $this->request->getVar('max');
        $acceptable_variance = $this->request->getVar('acceptable_variance');
        $details             = $this->request->getVar('details');

        if (!$inventory_groups = $this->inventoryGroupModel->search($name, $min, $max, $acceptable_variance, $details)) {
            $response = $this->failNotFound('No inventory group found');
        } else {
            foreach ($inventory_groups as $key => $inventory_group) {
                $inventory_groups[$key]['inventory_group_details'] = $this->inventoryGroupDetailModel->get_details_by_inventory_group_id($inventory_group['id']);
            }

            $response = $this->respond([
                'data'   => $inventory_groups,
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
    protected function _attempt_generate_details($inventory_group_id)
    {
        $branch_ids = $this->request->getVar('branch_ids');

        $values = [
            'inventory_group_id' => $inventory_group_id,
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s'),
        ];

        foreach ($branch_ids as $key => $branch_id) {
            $values['branch_id'] = $branch_id;
            if (!$this->inventoryGroupDetailModel->insert_on_duplicate($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }
        
        if (!$this->inventoryGroupModel->update($inventory_group_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt to create inventory_group
     */
    protected function _attempt_create()
    {
        $values = [
            'name'                => $this->request->getVar('name'),
            'min'                 => $this->request->getVar('min'),
            'max'                 => $this->request->getVar('max'),
            'acceptable_variance' => $this->request->getVar('acceptable_variance'),
            'details'             => $this->request->getVar('details'),
            'added_by'            => $this->requested_by,
            'added_on'            => date('Y-m-d H:i:s'),
            'is_deleted'          => 0
        ];

        if (!$inventory_group_id = $this->inventoryGroupModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $inventory_group_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($inventory_group)
    {
        $values = [
            'name'                => $this->request->getVar('name'),
            'min'                 => $this->request->getVar('min'),
            'max'                 => $this->request->getVar('max'),
            'acceptable_variance' => $this->request->getVar('acceptable_variance'),
            'details'             => $this->request->getVar('details'),
            'updated_by'          => $this->requested_by,
            'updated_on'          => date('Y-m-d H:i:s')
        ];

        if (!$this->inventoryGroupModel->update($inventory_group['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->inventoryGroupDetailModel->delete_by_inventory_group_id($inventory_group['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        if (!$this->_attempt_generate_details($inventory_group['id']))
            return false;
            
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($inventory_group_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->inventoryGroupModel->update($inventory_group_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->inventoryGroupDetailModel->delete_by_inventory_group_id($inventory_group_id, $this->requested_by, $this->db)) {
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
        $this->inventoryModel            = model('App\Models\Inventory');
        $this->inventoryGroupModel       = model('App\Models\Inventory_group');
        $this->inventoryGroupDetailModel = model('App\Models\Inventory_group_detail');
        $this->webappResponseModel       = model('App\Models\Webapp_response');
    }
}

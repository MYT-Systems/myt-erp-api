<?php

namespace App\Controllers;

class Wastage_items extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }
    
    /**
     * Change status wastage item
     */
    public function change_status()
    {
        if (($response = $this->_api_verification('wastage_items', 'change_status')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('wastage_item_id'),
            'is_deleted' => 0
        ];

        $values = [
            'wastage_cost' => $this->request->getVar('amount') ? : 0.00,
            'status' => $this->request->getVar('status'),
            'status_change_by' => $this->requested_by,
            'status_change_on' => date("Y-m-d H:i:s")
        ];

        if (!$wastage_item = $this->wastageItemModel->select('', $where, 1)) {
            $response = $this->failNotFound('Wastage item not found');
        } else {
            $wastage = $this->wastageModel->select('', ['id' => $wastage_item['wastage_id']], 1);

            if (!$this->wastageItemModel->update($wastage_item['id'], $values)) {
                $response = $this->fail(['response' => 'Failed to update wastage item status.']);
            } elseif (!$this->_update_inventory($wastage['branch_id'], $wastage_item)) {
                $response = $this->fail($this->errorMessage);
            } else {
                $response = $this->respond(['response' => 'Wastage item status updated successfully.']);
            }
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _update_inventory($branch_id, $wastage_item)
    {
        $where = [
            'branch_id' => $branch_id,
            'item_id' => $wastage_item['item_id'],
            'unit' => $wastage_item['unit'],
            'is_deleted' => 0
        ];

        if (!$inventory_details = $this->inventoryModel->select('', $where, 1)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        } else {
            $current_qty = $inventory_details['current_qty'] - $wastage_item['qty'];
            $values = [
                'current_qty' => $current_qty,
                'updated_by' => $this->requested_by,
                'updated_on' => date("Y-m-d H:i:s")
            ];

            if (!$this->inventoryModel->update($inventory_details['id'], $values)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
            return true;
        }
    }

    /**
     * Update wastage item
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('wastage_items', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('wastage_item_id'),
            'is_deleted' => 0
        ];

        if (!$wastage_item = $this->wastageItemModel->select('', $where, 1)) {
            $response = $this->failNotFound('Wastage item not found');
        } elseif (!$this->_attempt_update($wastage_item['id'])) {
            $response = $this->fail(['response' => 'Failed to update wastage item.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Wastage item updated successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete wastage item
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('wastage_items', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('wastage_item_id'),
            'is_deleted' => 0
        ];

        if (!$wastage_item = $this->wastageItemModel->select('', $where, 1)) {
            $response = $this->failNotFound('Wastage item not found');
        } elseif (!$this->_attempt_delete($wastage_item['id'])) {
            $response = $this->fail(['response' => 'Failed to delete wastage item.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Wastage item deleted successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($wastage_item_id)
    {
        $values = [
            'name' => $this->request->getVar('name'),
            'item_id' => $this->request->getVar('item_id'),
            'qty' => $this->request->getVar('qty'),
            'unit' => $this->request->getVar('unit'),
            'reason' => $this->request->getVar('reason'),
            'remarks' => $this->request->getVar('remarks'),
            'wasted_by' => $this->request->getVar('wasted_by'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->wastageItemModel->update($wastage_item_id, $values)) return false;
        
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($wastage_item_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->wastageItemModel->update($wastage_item_id, $values)) return false;

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($wastage_id, $action)
    {
        $values = [
            'action'      => $action,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        switch ($action) {
            case 'approved':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                break;
            case 'rejected':
                $values['rejected_by'] = $this->requested_by;
                $values['rejected_on'] = date('Y-m-d H:i:s');
                break;
        }

        if (!$this->wastageModel->update($wastage_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->wastageModel           = model('App\Models\Wastage');
        $this->wastageItemModel       = model('App\Models\Wastage_item');
        $this->inventoryModel         = model('App\Models\Inventory');
        $this->webappResponseModel    = model('App\Models\Webapp_response');

        $this->wastage = null;
    }
}

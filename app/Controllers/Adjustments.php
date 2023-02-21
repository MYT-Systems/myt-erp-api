<?php

namespace App\Controllers;

class Adjustments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get adjustment
     * Used by ERP
     */
    public function get_adjustment()
    {
        if (($response = $this->_api_verification('adjustments', 'get_adjustment')) !== true)
            return $response;

        $adjustment_id = $this->request->getVar('adjustment_id') ? : null;
        $adjustment    = $adjustment_id ? $this->adjustmentModel->get_details_by_id($adjustment_id) : null;

        if (!$adjustment) {
            $response = $this->failNotFound('No adjustment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $adjustment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all adjustments
     * Used by ERP
     */
    public function get_all_adjustment()
    {
        if (($response = $this->_api_verification('adjustments', 'get_all_adjustment')) !== true)
            return $response;

        $branch_id = $this->request->getVar('branch_id') ? : null;
        $adjustments = $this->adjustmentModel->get_all_adjustment($branch_id);

        if (!$adjustments) {
            $response = $this->failNotFound('No adjustment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $adjustments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /*
    * Get all adjustment types
    * Used by ERP
    */
    public function get_all_adjustment_types()
    {
        if (($response = $this->_api_verification('adjustments', 'get_all_adjustment_types')) !== true)
            return $response;

        $adjustment_types = $this->adjustmentTypeModel->get_all();

        if (!$adjustment_types) {
            $response = $this->failNotFound('No adjustment type found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $adjustment_types
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create adjustment
     * Used by ERP
     */
    public function create()
    {
        if (($response = $this->_api_verification('adjustments', 'create')) !== true)
            return $response;


        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$adjustment_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create adjustment.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully created adjustment.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete adjustments
     * Used by ERP
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('adjustments', 'delete')) !== true)
            return $response;

        $adjustment_id = $this->request->getVar('adjustment_id');

        $where = [
            'id' => $adjustment_id,
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$adjustment = $this->adjustmentModel->select('', $where, 1)) {
            $response = $this->failNotFound('adjustment not found');
        } elseif (!$this->_attempt_delete($adjustment_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete adjustment', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'adjustment deleted successfully', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search adjustments based on parameters passed
     * Used by ERP
     */
    public function search()
    {
        if (($response = $this->_api_verification('adjustments', 'search')) !== true)
            return $response;

        $inventory_id   = $this->request->getVar('inventory_id') ? : null;
        $branch_id      = $this->request->getVar('branch_id') ? : null;
        $item_id        = $this->request->getVar('item_id') ? : null;
        $type_id        = $this->request->getVar('type_id') ? : null;
        $counted_by     = $this->request->getVar('counted_by') ? : null;
        $status         = $this->request->getVar('status') ? : null;
        $added_on_from  = $this->request->getVar('added_on_from') ? : null;
        $added_on_to    = $this->request->getVar('added_on_to') ? : null;
        $item_name      = $this->request->getVar('item_name') ? : null;
        $limit_by       = $this->request->getVar('limit_by') ? : null;
        $remarks        = $this->request->getVar('remarks') ? : null;
        $request_type   = $this->request->getVar('request_type');

        if (!$adjustments = $this->adjustmentModel->search($inventory_id, $branch_id, $item_id, $type_id, $counted_by, $status, $added_on_from, $added_on_to, $item_name, $limit_by, $remarks, $request_type)) {
            $response = $this->failNotFound('No adjustment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $adjustments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update the status of an adjustment
     * Used by ERP
     */
    public function update_status()
    {
        if (($response = $this->_api_verification('adjustments', 'update_status')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$this->_attempt_update_status()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update adjustment status', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Adjustment status updated successfully', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create adjustment
     * Used by ERP
     */
    protected function _attempt_create()
    {
        $inventory_id = $this->request->getVar('inventory_id') ? : null;
        if (!$inventory = $this->inventoryModel->get_details_by_id($inventory_id)) {
            var_dump("Inventory not found");
            return false;
        }

        $inventory = $inventory[0];

        // TODO: Update the computer count based on the daily inventory count
        $computer_count = $inventory['current_qty'];
        $physical_count = $this->request->getVar('physical_count') ?? 0;
        $difference     = $physical_count - $computer_count;
        // $is_acceptable  = $difference < $inventory['acceptable_variance'] ? true : false;

        $adjustment_data = [
            'inventory_id'   => $inventory_id,
            'branch_id'      => $this->request->getVar('branch_id'),
            'item_id'        => $this->request->getVar('item_id'),
            'type_id'        => $this->request->getVar('type_id'),
            'counted_by'     => $this->request->getVar('counted_by'),
            'physical_count' => $physical_count,
            'unit'           => $this->request->getVar('unit'),
            'difference'     => $difference,
            'computer_count' => $computer_count,
            'status'         => 'pending',
            'remarks'        => $this->request->getVar('remarks'),
            'added_by'       => $this->requested_by,
            'added_on'       => date('Y-m-d H:i:s')
        ];

        if (!$adjustment_id = $this->adjustmentModel->insert($adjustment_data)) {
            var_dump("Failed to insert adjustment");
            return false;
        }

        return $adjustment_id;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($adjustment_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->adjustmentModel->update($adjustment_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt to update adjustment status
     * can update multiple adjustments at once
     */
    protected function _attempt_update_status()
    {
        $adjustment_ids = $this->request->getVar('adjustment_ids') ? : null;
        foreach ($adjustment_ids as $adjustment_id) {
            $adjustment = $this->adjustmentModel->get_details_by_id($adjustment_id);
            if (!$adjustment) {
                var_dump("Adjustment not found: " . $adjustment_id);
                return false;
            }

            $adjustment = $adjustment[0];

            $status = $this->request->getVar('status') ? : null;

            $values = [
                'status'        => $status,
                'admin_remarks' => $this->request->getVar('remarks') ?? $adjustment['admin_remarks'],
                'updated_by'    => $this->requested_by,
                'updated_on'    => date('Y-m-d H:i:s')
            ];

            switch ($status) {
                case 'approved':
                    $values['status'] = 'approved';
                    $values['approved_by'] = $this->requested_by;
                    $values['approved_on'] = date('Y-m-d H:i:s');

                    // get the inventory details
                    if (!$inventory = $this->inventoryModel->get_details_by_id($adjustment['inventory_id'])) {
                        var_dump("Inventory not found");
                        return false;
                    }
                    $inventory = $inventory[0];
                    $inventory_data = [
                        'current_qty' => $inventory['current_qty'] + $adjustment['difference'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s')
                    ];

                    if (!$this->inventoryModel->update($adjustment['inventory_id'], $inventory_data))
                        return false;
                    break;
                case 'disapproved':
                    $values['status'] = 'disapproved';
                    $values['disapproved_by'] = $this->requested_by;
                    $values['disapproved_on'] = date('Y-m-d H:i:s');
                    break;
                case 'pending':
                    $values['status'] = 'pending';
                    break;
                default:
                    return false;
            }

            if (!$this->adjustmentModel->update($adjustment_id, $values)) {
                var_dump("Failed to update adjustment status");
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
        $this->adjustmentModel     = model('App\Models\Adjustment');
        $this->adjustmentTypeModel = model('App\Models\Adjustment_type');
        $this->inventoryModel      = model('App\Models\Inventory');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}

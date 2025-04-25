<?php

namespace App\Controllers;

use App\Models\Build_item;
use App\Models\Build_item_detail;
use App\Models\Inventory;
use App\Models\Webapp_response;

class Build_items extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get build_item
     */
    public function get_build_item()
    {
        if (($response = $this->_api_verification('build_item', 'get_build_item')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $build_item_id      = $this->request->getVar('build_item_id') ? : null;
        $build_item         = $build_item_id ? $this->buildItemModel->get_details_by_id($build_item_id) : null;
        $build_item_details = $build_item_id ? $this->buildItemDetailModel->get_details_by_build_item_id($build_item_id) : null;

        if (!$build_item) {
            $response = $this->failNotFound('No build item found');
        } else {
            $build_item[0]['build_item_details']  = $build_item_details;

            $response = $this->respond([
                'status' => 'success',
                'data' => $build_item
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get production report
     */
    public function get_production_report()
    {
        if (($response = $this->_api_verification('build_item', 'get_production_report')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $item_id = $this->request->getVar('item_id') ?? null;
        $date_from = $this->request->getVar('date_from') ?? null;
        $date_to = $this->request->getVar('date_to') ?? null;

        if (!$build_items = $this->buildItemModel->production_report($item_id, $date_from, $date_to)) {
            $response = $this->failNotFound('No build item found');
        } else {
            $total_qty = 0;
            $final_data = [];
            $all_items_total_qty = 0;
            $raw_materials = [];
            
            foreach ($build_items as $build_item) {
                if (!array_key_exists($build_item['item_id'], $final_data)) {
                    $final_data[$build_item['item_id']] = [
                        'item_name' => $build_item['name'],
                        'unit' => $build_item['unit'],
                        'avg_yield' => $build_item['average_yield'],
                        'total_qty' => $build_item['qty'],
                        'raw_materials' => []
                    ];
                }

                $all_items_total_qty += $build_item['qty'];
                
                $final_data[$build_item['item_id']]['raw_materials'][] = [
                    'name' => $build_item['raw_material_name'],
                    'qty' => $build_item['raw_material_qty'],
                    'unit' => $build_item['raw_material_unit']
                ];
            }

            if (count($final_data) > 0)
                $final_data = array_values($final_data);

            $response = $this->respond([
                'data' => $final_data,
                'all_items_total_qty' => $all_items_total_qty,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all build_item
     */
    public function get_all_build_item()
    {
        if (($response = $this->_api_verification('build_item', 'get_all_build_item')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $build_items = $this->buildItemModel->get_all_build_item();

        if (!$build_items) {
            $response = $this->failNotFound('No build item found');
        } else {
            foreach ($build_items as $key => $build_item) {         
                $build_items[$key]['build_item_details'] = $this->buildItemDetailModel->get_details_by_build_item_id($build_item['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $build_items
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create build_item
     */
    public function create()
    {
        if (($response = $this->_api_verification('build_items', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$build_item_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create build item.', 'status' => 'error']);
        } else if (!$this->_attempt_generate_build_item_details($build_item_id, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate build items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'   => 'build_item created successfully.',
                'status'     => 'success',
                'build_item_id' => $build_item_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update build_item
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('build_items', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $build_item_id = $this->request->getVar('build_item_id');
        $where      = ['id' => $build_item_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();
        if (!$build_item = $this->buildItemModel->select('', $where, 1)) {
            $response = $this->failNotFound('build_item not found');
        } elseif (!$this->_attempt_update_build_item($build_item)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update build item.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_build_item_details($build_item, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update build item items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'build_item updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete build_item
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('build_items', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $build_item_id = $this->request->getVar('build_item_id');
        $where = ['id' => $build_item_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$build_item = $this->buildItemModel->select('', $where, 1)) {
            $response = $this->failNotFound('build_item not found');
        } elseif (!$this->_attempt_delete($build_item, $db)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'build_item deleted successfully.']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search build item based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('build_item', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $from_branch_id      = $this->request->getVar('from_branch_id') ?? null;
        $to_branch_id        = $this->request->getVar('to_branch_id') ?? null;
        $item_id             = $this->request->getVar('item_id') ?? null;
        $qty                 = $this->request->getVar('qty') ?? null;
        $item_unit_id        = $this->request->getVar('item_unit_id') ?? null;
        $production_date     = $this->request->getVar('production_date') ?? null;
        $production_slip_no  = $this->request->getVar('production_slip_no') ?? null;
        $expiration_date     = $this->request->getVar('expiration_date') ?? null;
        $added_on_from       = $this->request->getVar('added_on_from') ?? null;
        $added_on_to         = $this->request->getVar('added_on_to') ?? null;
        $yield               = $this->request->getVar('yield') ?? null;
        $batch               = $this->request->getVar('batch') ?? null;
        $name                = $this->request->getVar('name') ?? null;

        if (!$build_items = $this->buildItemModel->search($from_branch_id, $to_branch_id, $item_id, $qty, $item_unit_id, $production_date, $production_slip_no, $expiration_date, $added_on_from, $added_on_to, $yield, $batch, $name)) {
            $response = $this->failNotFound('No build item found');
        } else {
            foreach ($build_items as $key => $build_item) {         
                $build_items[$key]['build_item_details'] = $this->buildItemDetailModel->get_details_by_build_item_id($build_item['id']);
            }
            $response = $this->respond([
                'data' => $build_items,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get Bills
     */
    public function get_bills()
    {
        if (($response = $this->_api_verification('build_item', 'get_bills')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $type = $this->request->getVar('type');
        if (!$build_items = $this->buildItemModel->get_bills($type)) {
            $response = $this->failNotFound('No build item found');
        } else {
            $response = [];
            $response['data'] = $build_items;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all invoice payments
     */
    public function get_all_invoice_payments()
    {
        if (($response = $this->_api_verification('build_item', 'get_all_invoice_payments')) !== true)
            return $response;
    
        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
        
        $build_item_id       = $this->request->getVar('build_item_id') ? : null;
        $invoice_payments = $build_item_id ? $this->suppliesPaymentModel->get_all_payment_by_build_item($build_item_id) : null;

        if (!$invoice_payments) {
            $response = $this->failNotFound('No invoice payments found');
        } else {
            $response = $this->respond([
                'build_item_id' => $build_item_id,
                'data'       => $invoice_payments,
                'status'     => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Update Build Item
     */
    private function _update_inventory($item_id, $to_branch_id, $item_unit_id)
    {
        if ($inventory = $this->inventoryModel->get_inventory_detail($item_id, $to_branch_id, $item_unit_id)) {
            $inventory = $inventory[0];

            $values = [
                'current_qty' => $inventory['current_qty'] + $this->request->getVar('qty'),
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ];

            $this->inventoryModel->update($inventory['id'], $values);
        } else {
            $values = [
                'item_id'       => $item_id,
                'branch_id'     => $to_branch_id,
                'item_unit_id'  => $item_unit_id,
                'beginning_qty' => $this->request->getVar('qty'),
                'current_qty'   => $this->request->getVar('qty'),
                'added_by'      => $this->requested_by,
                'added_on'      => date('Y-m-d H:i:s'),
            ];

            $this->inventoryModel->insert($values);
        }

        return true;
    }

    /**
     * Restore Build item's qty
     */
    private function _restore_build_item($build_item)
    {
        if ($inventory = $this->inventoryModel->get_inventory_detail($build_item['item_id'], $build_item['to_branch_id'], $build_item['item_unit_id'])) {
            $inventory = $inventory[0];
            $values = [
                'current_qty' => $inventory['current_qty'] - $build_item['qty'],
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ];

            if (!$this->inventoryModel->update($inventory['id'], $values)) {
                var_dump("Error updating inventory");
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt to create
     */
    private function _attempt_create()
    {
        $from_branch_id = $this->request->getVar('from_branch_id');
        $to_branch_id   = $this->request->getVar('to_branch_id');
        $item_id        = $this->request->getVar('item_id');
        $item_unit_id   = $this->request->getVar('item_unit_id');

        $values = [
            'from_branch_id'     => $this->request->getVar('from_branch_id'),
            'to_branch_id'       => $this->request->getVar('to_branch_id'),
            'item_id'            => $this->request->getVar('item_id'),
            'qty'                => $this->request->getVar('qty'),
            'item_unit_id'       => $this->request->getVar('item_unit_id'),
            'production_date'    => $this->request->getVar('production_date'),
            'production_slip_no' => $this->request->getVar('production_slip_no'),
            'expiration_date'    => $this->request->getVar('expiration_date'),
            'yield'              => $this->request->getVar('yield'),
            'batch'              => $this->request->getVar('batch'),
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        if (!$build_item_id = $this->buildItemModel->insert($values)) {
            var_dump("Error inserting build item");
            return false;
        }

        if (!$this->_update_inventory($item_id, $to_branch_id, $item_unit_id)) {
            var_dump("Error updating inventory.");
            return false;
        }

        return $build_item_id;
    }

    /**
     * Attempt to generate build item details
     */
    private function _attempt_generate_build_item_details($build_item_id, $db = null)
    {
        $item_ids       = $this->request->getVar('item_ids');
        $quantities     = $this->request->getVar('quantities');
        $item_unit_ids  = $this->request->getVar('item_unit_ids');
        $units          = $this->request->getVar('units');
        $from_branch_id = $this->request->getVar('from_branch_id');

        $values = [
            'build_item_id' => $build_item_id,
            'added_by'      => $this->requested_by,
            'added_on'      => date('Y-m-d H:i:s'),
        ];

        foreach ($item_ids as $key => $item_id) {
            $values['item_id']      = $item_ids[$key];
            $values['item_unit_id'] = $item_unit_ids[$key];
            $values['qty']          = $quantities[$key];

            if (!$this->buildItemDetailModel->insert_on_duplicate($values, $this->requested_by, $db)) {
                return false;
            }

            if ($inventory = $this->inventoryModel->get_inventory_detail($values['item_id'], $from_branch_id, $values['item_unit_id'])) {
                $inventory = $inventory[0];

                $new_values = [
                    'current_qty' => $inventory['current_qty'] - $values['qty'],
                    'updated_by'  => $this->requested_by,
                    'updated_on'  => date('Y-m-d H:i:s'),
                ];

                $this->inventoryModel->update($inventory['id'], $new_values);
            } else {
                var_dump("ITEM DOES NOT EXIST");
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt update
     */
    function _attempt_update_build_item($build_item)
    {
        $item_id = $this->request->getVar('item_id');
        $to_branch_id = $this->request->getVar('to_branch_id');
        $item_unit_id = $this->request->getVar('item_unit_id');

        if (!$this->_restore_build_item($build_item)) {
            var_dump("Error restoring build item");
            return false;
        }

        $values = [
            'from_branch_id'     => $this->request->getVar('from_branch_id'),
            'to_branch_id'       => $this->request->getVar('to_branch_id'),
            'item_id'            => $item_id,
            'qty'                => $this->request->getVar('qty'),
            'item_unit_id'       => $item_unit_id,
            'production_date'    => $this->request->getVar('production_date'),
            'production_slip_no' => $this->request->getVar('production_slip_no'),
            'expiration_date'    => $this->request->getVar('expiration_date'),
            'yield'              => $this->request->getVar('yield'),
            'batch'              => $this->request->getVar('batch'),
            'updated_by'         => $this->requested_by,
            'updated_on'         => date('Y-m-d H:i:s'),
        ];

        if (!$this->buildItemModel->update($build_item['id'], $values))
            return false;
        
        if (!$this->_update_inventory($item_id, $to_branch_id, $item_unit_id)) {
            var_dump("Error updating inventory.");
            return false;
        }

        return true;
    }

    protected function _attempt_update_build_item_details($build_item, $db)
    {
        if (!$this->_revert_build_item_details($build_item, $db))
            return false;

        // Delete build_items
        if (!$this->buildItemDetailModel->delete_by_build_item_id($build_item['id'], $this->requested_by, $db)) {
            var_dump("Error deleting build item details");
            return false;
        }
        
        return $this->_attempt_generate_build_item_details($build_item['id'], $db);
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($build_item, $db)
    {
        if (!$this->_restore_build_item($build_item)) {
            return false;
        }
           
        if (!$this->_revert_build_item_details($build_item, $db))
            return false;

        if (!$this->buildItemDetailModel->delete_by_build_item_id($build_item['id'], $this->requested_by, $db))
            return false;
            
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->buildItemModel->update($build_item['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Delete build item details and restore inventory
     */
    function _revert_build_item_details($build_item, $db = null)
    {
        // Get the build item items first and decrease the inventory
        $build_item_details = $this->buildItemDetailModel->get_details_by_build_item_id($build_item['id']);
        $branch_id          = $build_item['from_branch_id'];

        // Decrease the inventory based on the build item items
        foreach ($build_item_details as $build_item_detail) {
            if (!$inventory = $this->inventoryModel->get_inventory_detail($build_item_detail['item_id'], $branch_id, $build_item_detail['item_unit_id'])) {
                var_dump($build_item_detail['item_id'] . ' ' . $branch_id . ' ' . $build_item_detail['item_unit_id']);
                continue;
            }

            $inventory = $inventory[0];
            $new_values = [
                'current_qty' => $inventory['current_qty'] + $build_item_detail['qty'],
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ];
            
            if (!$this->inventoryModel->update($inventory['id'], $new_values)) {
                var_dump("inventory not updated");
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
        $this->buildItemModel       = new Build_item();
        $this->buildItemDetailModel = new Build_item_detail();
        $this->inventoryModel       = new Inventory();
        $this->webappResponseModel  = new Webapp_response();
    }
}

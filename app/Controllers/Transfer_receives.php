<?php

namespace App\Controllers;

class Transfer_receives extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get transfer_receive
     */
    public function get_transfer_receive()
    {
        if (($response = $this->_api_verification('transfer_receive', 'get_transfer_receive')) !== true)
            return $response;

        $transfer_receive_id    = $this->request->getVar('transfer_receive_id') ? : null;
        $transfer_receive       = $transfer_receive_id ? $this->transferReceiveModel->get_details_by_id($transfer_receive_id) : null;
        $transfer_receive_items = $transfer_receive_id ? $this->transferReceiveItemModel->get_details_by_transfer_receive_id($transfer_receive_id) : null;

        if (!$transfer_receive) {
            $response = $this->failNotFound('No transfer receive found');
        } else {
            $transfer_receive[0]['transfer_receive_items'] = $transfer_receive_items;
            $response = $this->respond([
                'data'   => $transfer_receive,
                'status' => 'success'
            ]);
        }


        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all transfer_receive
     */
    public function get_all_transfer_receive()
    {
        if (($response = $this->_api_verification('transfer_receive', 'get_all_transfer_receive')) !== true)
            return $response;

        $transfer_receives = $this->transferReceiveModel->get_all_transfer_receive();

        if (!$transfer_receives) {
            $response = $this->failNotFound('No transfer receive found');
        } else {
            foreach ($transfer_receives as $key => $transfer_receive) {
                $transfer_receives[$key]['transfer_receive_items'] = $this->transferReceiveItemModel->get_details_by_transfer_receive_id($transfer_receive['id']);
            }
            $response = $this->respond([
                'data'   => $transfer_receives,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create Tranfer
     */
    public function create()
    {
        if (($response = $this->_api_verification('transfer_receives', 'create')) !== true) 
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        $status = "completed";

        if (!$transfer = $this->transferModel->get_details_by_id($this->request->getVar('transfer_id'))) {
            $response = $this->failNotFound('No transfer found');
        } elseif (!$transfer_receive_id = $this->_attempt_create($this->db)) {
            $this->db->transRollback();
            $response = $this->fail("Failed to create transfer receive: " . $this->errorMessage);
        } elseif (!$status = $this->_attempt_generate_transfer_receive_items($transfer_receive_id, false)) {
            $this->db->transRollback();
            $response = $this->fail("Failed to generate transfer receive items: " . $this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response'            => ($status == "completed" ? "Transfer receive created successfully." : "Transfer receive on hold."),
                'status'              => 'success',
                'transfer_receive_id' => $transfer_receive_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update transfer_receive
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('transfer_receives', 'update')) !== true)
            return $response;

        $transfer_receive_id = $this->request->getVar('transfer_receive_id');
        $where = [
            'id' => $transfer_receive_id, 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        $user_type = $this->request->getVar('user_type') ? : null;

        $status = "completed";
        
        if ($user_type AND $user_type === "branch") {
            $response = $this->fail('User type "BRANCH" is not allowed to edit transfer receives');
        } elseif (!$transfer_receive = $this->transferReceiveModel->select('', $where, 1)) {
            $response = $this->failNotFound('No transfer receive found');
        } elseif (!$this->_attempt_update_transfer_receive($transfer_receive_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$status = $this->_attempt_update_transfer_receive_items($transfer_receive)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response' => ($status == "completed" ? "Transfer receive updated successfully." : "Transfer receive on hold."),
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete transfer_receive
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('transfer_receives', 'delete')) !== true)
            return $response;

        $transfer_receive_id = $this->request->getVar('transfer_receive_id');
        $where = ['id' => $transfer_receive_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$transfer_receive = $this->transferReceiveModel->select('', $where, 1)) {
            $response = $this->failNotFound('No transfer receive found');
        } elseif (!$this->_attempt_delete_transfer_receive($transfer_receive_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->transferReceiveItemModel->delete_by_transfer_receive_id($transfer_receive_id, $this->requested_by, $this->db)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'transfer receive deleted successfully.']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Change status of transfer_receive
     */
    public function change_status()
    {
        if (($response = $this->_api_verification('transfer_receives', 'change_status')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('transfer_receive_id'), 
            'is_deleted' => 0
        ];
        $new_status  = $this->request->getVar('new_status');

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$transfer_receive = $this->transferReceiveModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'No transfer receive found']);
        } elseif (!$this->_attempt_change_status($transfer_receive, $new_status)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Status changed successfully']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search transfer receive based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('transfer_receive', 'search')) !== true)
            return $response;

        $branch_from             = $this->request->getVar('branch_from');
        $branch_to               = $this->request->getVar('branch_to');
        $date_from               = $this->request->getVar('date_from');
        $date_to                 = $this->request->getVar('date_to');
        $date_completed_from     = $this->request->getVar('date_completed_from');
        $date_completed_to       = $this->request->getVar('date_completed_to');
        $transfer_id             = $this->request->getVar('transfer_id');
        $transfer_receive_number = $this->request->getVar('transfer_number');
        $transfer_receive_date   = $this->request->getVar('transfer_receive_date');
        $remarks                 = $this->request->getVar('remarks');
        $grand_total             = $this->request->getVar('grand_total');
        $status                  = $this->request->getVar('status');

        if (!$transfer_receives = $this->transferReceiveModel->search($branch_from, $branch_to, $date_from, $date_to, $date_completed_from, $date_completed_to, $transfer_receive_number, $transfer_receive_date, $remarks, $grand_total, $status, $transfer_id)) {
            $response = $this->failNotFound('No transfer receive found');
        } else {
            foreach ($transfer_receives as $key => $transfer_receive) {
                $transfer_receives[$key]['transfer_receive_items'] = $this->transferReceiveItemModel->get_details_by_transfer_receive_id($transfer_receive['id']);
            }

            $response = $this->respond([
                'status' => 200,
                'data' => $transfer_receives
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create transfer_receive
     */
    private function _attempt_create()
    {
        $values = [
            'transfer_id'             => $this->request->getVar('transfer_id'),
            'branch_from'             => $this->request->getVar('branch_from'),
            'branch_to'               => $this->request->getVar('branch_to'),
            'transfer_receive_number' => $this->request->getVar('transfer_number'),
            'transfer_receive_date'   => $this->request->getVar('transfer_receive_date'),
            'remarks'                 => $this->request->getVar('remarks'),
            'grand_total'             => $this->request->getVar('grand_total'),
            'status'                  => 'for_approval',
            'added_by'                => $this->requested_by,
            'added_on'                => date('Y-m-d H:i:s'),
            'is_deleted'              => 0
        ];

        if (!$transfer_receive_id = $this->transferReceiveModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $transfer_receive_id;
    }
    
    /**
     * Attempt generate PO
     */
    protected function _attempt_generate_transfer_receive_items($transfer_receive_id, $is_update = false)
    {
        $item_ids          = $this->request->getVar('item_ids');
        $units             = $this->request->getVar('units');
        $quantities        = $this->request->getVar('quantities');
        $transfer_id       = $this->request->getVar('transfer_id');
        $branch_to         = $this->request->getVar('branch_to');
        $branch_from       = $this->request->getVar('branch_from');
        $transfer_item_ids = $this->request->getVar('transfer_item_ids');
        
        $grand_total = 0;
        $transfer_receive_items = [];
        foreach ($item_ids as $key => $item_id) {
            // get the item unit details
            if (!$item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($branch_to, $item_id, $units[$key])) {
                $this->errorMessage = "Item unit not found for item id: $item_id and unit: $units[$key] in branch: $branch_to";
                return false;
            }
            $item_unit = $item_unit[0];
            $inventory_id = null;
            // Check if inventory exists, if not create one
            if (!$inventory_to = $this->inventoryModel->get_inventory_detail($item_id, $branch_to, $item_unit['id'])) {
                $inventory_values = [
                    'branch_id'     => $branch_to,
                    'item_id'       => $item_id,
                    'beginning_qty' => 0,
                    'current_qty'   => 0,
                    'item_unit_id'  => $item_unit['id'],
                    'unit'          => $item_unit['inventory_unit'],
                    'added_on'      => date('Y-m-d H:i:s'),
                    'is_deleted'    => 0
                ];
                if (!$inventory_id = $this->inventoryModel->insert($inventory_values)) {
                    $this->errorMessage = $this->db->error()['message'];
                    return false;
                }
            }

            // get the inventory id
            $inventory_id = $inventory_to ? $inventory_to[0]['id'] : $inventory_id;

            // check if the inventory exist where the items will be procured
            if (!$inventory_from = $this->inventoryModel->get_inventory_detail($item_id, $branch_from, $item_unit['id'])) {
                $this->errorMessage = "Trying to transfer item that doesn't exist in the inventory: " . $item_id . " - " . $branch_from . " - " . $item_unit['id'];
                return false;
            }
            
            $data = [
                'transfer_receive_id' => $transfer_receive_id,
                'to_inventory_id'     => $inventory_id,
                'from_inventory_id'   => $inventory_from[0]['id'],
                'item_unit_id'        => $item_unit['id'],
                'transfer_item_id'    => $transfer_item_ids[$key],
                'item_id'             => $item_id,
                'qty'                 => $quantities[$key],
                'unit'                => $units[$key],
                'added_by'            => $this->requested_by,
                'added_on'            => date('Y-m-d H:i:s')
            ];

            $transfer_receive_items[] = $data;
            if (!$this->transferReceiveItemModel->insert_on_duplicate($data, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        $transfer = [
            'id'          => $transfer_id,
            'branch_from' => $branch_from,
            'branch_to'   => $branch_to,
        ];

        if (!$status = $this->_attempt_update_receive_qty($transfer_receive_id, $transfer, $transfer_receive_items, $is_update)) {
            return false;
        }

        $data = [
            'status'      => $status,
            'grand_total' => $grand_total,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        if (!$this->transferReceiveModel->update($transfer_receive_id, $data)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $status;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update_transfer_receive($transfer_receive_id)
    {
        $data = [
            'branch_from'             => $this->request->getVar('branch_from'),
            'branch_to'               => $this->request->getVar('branch_to'),
            'transfer_receive_number' => $this->request->getVar('transfer_receive_number'),
            'transfer_receive_date'   => $this->request->getVar('transfer_receive_date'),
            'remarks'                 => $this->request->getVar('remarks'),
            'grand_total'             => $this->request->getVar('grand_total'),
            'status'                  => $this->request->getVar('status'),
            'completed_on'            => $this->request->getVar('completed_on'),
            'updated_by'              => $this->requested_by,
            'updated_on'              => date('Y-m-d H:i:s')
        ];

        if (!$this->transferReceiveModel->update($transfer_receive_id, $data)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt update transfer receive items
     */
    protected function _attempt_update_transfer_receive_items($transfer_receive)
    {
        if (!$this->_revert_transfer_receive_items($transfer_receive)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->transferReceiveItemModel->delete_by_transfer_receive_id($transfer_receive['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$status = $this->_attempt_generate_transfer_receive_items($transfer_receive['id'], true)) {
            return false;
        }

        return $status;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete_transfer_receive($transfer_receive_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->transferReceiveModel->update($transfer_receive_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt change transfer receive status
     */
    protected function _attempt_change_status($transfer_receive, $new_status)
    {
        $values = [
            'status'     => $new_status,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if ($new_status == 'completed') {
            $values['completed_by'] = $this->requested_by;
            $values['completed_on'] = date('Y-m-d H:i:s');

            $transfer = [
                'id'          => $transfer_receive['transfer_id'],
                'branch_from' => $transfer_receive['branch_from'],
                'branch_to'   => $transfer_receive['branch_to'],
            ];

            $where = ['transfer_receive_id' => $transfer_receive_id, 'is_deleted' => 0];
            $transfer_receive_items = $this->transferReceiveItemModel->select('', $where);

            if (!$this->_attempt_update_stock($transfer_receive_items, $transfer)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        if (!$this->transferReceiveModel->update($transfer_receive['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

     /**
     * Attempt update stock
     */
    protected function _attempt_update_receive_qty($transfer_receive_id, $transfer, $transfer_receive_items, $is_update = false)
    {
        $status = 'completed';
        $total_transfer_item_qty = 0;
        $total_transfer_receive_item_qty = 0;
        $user_type = $this->request->getVar('user_type');

        foreach ($transfer_receive_items as $transfer_receive_item) {
            // Get the transfer item if there is a transfer_item_id in transfer_receive_item
            $transfer_item = null;
            if ($transfer_receive_item['transfer_item_id']) {
                $transfer_item = $this->transferItemModel->get_details_by_id($transfer_receive_item['transfer_item_id']);
            } else {
                $status = 'on_hold';
            }
            $transfer_item_qty = $transfer_item ? $transfer_item[0]['qty'] : false;

            // since the transfer item exists, update the received qty in each transfer item
            if ($transfer_item_qty) {
                $total_transfer_item_qty += $transfer_item_qty;
                $total_transfer_receive_item_qty += $transfer_receive_item['qty'];

                $data = [
                    'received_qty' => $transfer_receive_item['qty'],
                    'updated_by'   => $this->requested_by,
                    'updated_on'   => date('Y-m-d H:i:s')
                ];

                if ($is_update) {
                    if ($user_type != "branch" OR $transfer_item_qty == $transfer_receive_item['qty']) {
                        $data['status'] = 'completed';
                    } else {
                        $status = 'on_hold';
                        $data['status'] = 'incomplete';
                    }
                } else {
                    if ($transfer_item_qty == $transfer_receive_item['qty']) {
                        $data['status'] = 'completed';
                    } else {
                        $status = 'on_hold';
                        $data['status'] = 'incomplete';
                    }
                }

                $where = [
                    'transfer_id' => $transfer['id'],
                    'item_id'     => $transfer_receive_item['item_id'],
                    'unit'        => $transfer_receive_item['unit']
                ];
                
                if (!$this->transferItemModel->update_received($where, $data, true, $this->db)) {
                    $this->errorMessage = $this->db->error()['message'];
                    return false;
                }
            }
        }

        // $is_complete = $this->transferItemModel->is_all_received_by_transfer_id($transfer['id']);
        if ($status === 'completed') {
            $completed_by = $this->request->getVar('received_by');
            $completed_on = date('Y-m-d H:i:s');

            if (!$this->_attempt_update_stock($transfer_receive_items, $transfer))
                return false;
        } else {
            $completed_by = null;
            $completed_on = null;
        }

        $values = [
            'status'       => $status,
            'completed_by' => $completed_by,
            'completed_on' => $completed_on,
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s')
        ];

        $transfer_details = $this->transferModel->select('', ['id' => $transfer['id'], 'is_deleted' => 0], 1);

        if (!$this->transferModel->update($transfer['id'], $values) OR
            !$this->requestModel->update($transfer_details['request_id'], $values) OR
            !$this->transferReceiveModel->update($transfer_receive_id, $values)
        ) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $status;
    }

    protected function _attempt_update_stock($transfer_receive_items, $transfer)
    {
        foreach ($transfer_receive_items as $transfer_receive_item) {
            $where = [
                'item_id'      => $transfer_receive_item['item_id'],
                'item_unit_id' => $transfer_receive_item['item_unit_id'],
                'branch_id'    => $transfer['branch_from']
            ];

            $inventory_details = $this->inventoryModel->select('', $where, 1);
            $inventory_details = $inventory_details ? $inventory_details : ['id' => 0];

            $where = [
                'date' => date("Y-m-d"),
                'branch_id' => $transfer['branch_to'],
                'is_deleted' => 0
            ];

            // if walay daily sale, update initial inventory
            // if naay daily sale, ayaw iupdate ang initial inventory
                // ang condition create initial inventory dapat apil gabii beyond the time nga giinsert ang daily sale
            if (!$this->dailySaleModel->select('', $where, 1)) {

                $initial_inventory_condition = [
                    'date' => date("Y-m-d"),
                    'branch_id' => $transfer['branch_to'],
                    'item_id' => $transfer_receive_item['item_id'],
                    'unit' => $transfer_receive_item['unit'],
                    'is_deleted' => 0
                ];
                
                if ($initial_inventory = $this->initialInventoryModel->select('', $initial_inventory_condition, 1)) {
                    $this->initialInventoryModel->update($initial_inventory['id'], [
                        'delivered_qty' => $initial_inventory['delivered_qty'] + $transfer_receive_item['qty'],
                        'total_qty' => $initial_inventory['total_qty'] + $transfer_receive_item['qty'],
                        'updated_by' => $this->requested_by,
                        'updated_on' => date("Y-m-d H:i:s")
                    ]);
                } else {
                    $this->initialInventoryModel->insert([
                        'branch_id' => $transfer['branch_to'],
                        'date' => date("Y-m-d"),
                        'user_id' => $this->requested_by,
                        'inventory_id' => $inventory_details['id'],
                        'item_id' => $transfer_receive_item['item_id'],
                        'qty' => 0.00,
                        'delivered_qty' => $transfer_receive_item['qty'],
                        'total_qty' => $transfer_receive_item['qty'],
                        'unit' => $transfer_receive_item['unit'],
                        'added_by' => $this->requested_by,
                        'added_on' => date("Y-m-d H:i:s")
                    ]);
                }
            }

            $where = [
                'item_id'      => $transfer_receive_item['item_id'],
                'item_unit_id' => $transfer_receive_item['item_unit_id'],
                'branch_id'    => $transfer['branch_from']
            ];

            // Decrease the inventory qty from the branch_from
            if (!$this->inventoryModel->update_quantity($where, $transfer_receive_item['qty'] * -1, $this->requested_by)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
            
            // Increase the inventory qty from the branch_to
            $where['branch_id'] = $transfer['branch_to'];
            if (!$this->inventoryModel->update_quantity($where, $transfer_receive_item['qty'], $this->requested_by)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }
        return true;
    }

    /**
     * Revert Tranfer Receive Items
     */
    protected function _revert_transfer_receive_items($transfer)
    {
        $transfer_receive_items = $this->transferReceiveItemModel->get_details_by_transfer_receive_id($transfer['id']);
        foreach ($transfer_receive_items as $transfer_receive_item) {        
            $data = [
                'status'       => 'incomplete',
                'received_qty' => (float)$transfer_receive_item['qty'] * -1,
                'updated_by'   => $this->requested_by,
                'updated_on'   => date('Y-m-d H:i:s')
            ];

            $where = [
                'transfer_id' => $transfer['id'],
                'item_id'     => $transfer_receive_item['item_id'],
                'unit'        => $transfer_receive_item['unit']
            ];

            if (!$this->transferItemModel->update_received($where, $data, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }

            $where = [
                'item_id'      => $transfer_receive_item['item_id'],
                'item_unit_id' => $transfer_receive_item['item_unit_id'],
                'branch_id'    => $transfer['branch_from']
            ];

            if ($transfer['status'] == 'completed') {
                if (!$this->inventoryModel->update_quantity($where, $transfer_receive_item['qty'], $this->requested_by)) {
                    $this->errorMessage = $this->db->error()['message'];
                    return false;
                }
    
                // Increase the inventory qty from the branch_to
                $where['branch_id'] = $transfer['branch_to'];
                if (!$this->inventoryModel->update_quantity($where, $transfer_receive_item['qty']  * -1, $this->requested_by)) {
                    var_dump("inventory not found");
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->transferReceiveModel     = model('App\Models\Transfer_receive');
        $this->transferReceiveItemModel = model('App\Models\Transfer_receive_item');
        $this->transferItemModel        = model('App\Models\Transfer_item');
        $this->transferModel            = model('App\Models\Transfer');
        $this->requestModel             = model('App\Models\Request');
        $this->inventoryModel           = model('App\Models\Inventory');
        $this->initialInventoryModel    = model('App\Models\Initial_inventory');
        $this->dailySaleModel           = model('App\Models\Daily_sale');
        $this->itemUnitModel            = model('App\Models\Item_unit');
        $this->transferModel            = model('App\Models\Transfer');
        $this->webappResponseModel      = model('App\Models\Webapp_response');
    }
}

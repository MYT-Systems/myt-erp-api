<?php

namespace App\Controllers;

class Transfers extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get transfer report
     */
    public function get_transfer_report()
    {
        if (($response = $this->_api_verification('transfers', 'get_transfer_report')) !== true)
            return $response;

        $branch_from = $this->request->getVar('branch_from') ?? null;
        $branch_to = $this->request->getVar('branch_to') ?? null;
        $item_id = $this->request->getVar('item_id') ?? null;
        $date_from = $this->request->getVar('date_from') ?? null;
        $date_to = $this->request->getVar('date_to') ?? null;

        if (!$transfers = $this->transferModel->transfer_report($item_id, $date_from, $date_to, $branch_from, $branch_to)) {
            $response = $this->failNotFound('No build item found');
        } else {
            $final_data = [];
            $total_transfers = 0;
            $total_qty = 0;
            $source_branch = null;
            $destination_branch = null;
            foreach ($transfers as $transfer) {
                $total_transfers += $transfer['total_transfer'];
                $total_qty += $transfer['total_qty'];

                if ($branch_from != null) {
                    $source_branch = $transfer['source_branch'];
                }

                if ($branch_to != null) {
                    $destination_branch = $transfer['destination_branch'];
                }
            }
            
            $response = $this->respond([
                'data' => $transfers,
                'summary' => [
                    'total_transfers' => $total_transfers,
                    'total_qty' => $total_qty,
                    'source_branch' => $source_branch,
                    'destination_branch' => $destination_branch
                ],
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get transfer
     */
    public function get_transfer()
    {
        if (($response = $this->_api_verification('transfer', 'get_transfer')) !== true)
            return $response;

        $transfer_id    = $this->request->getVar('transfer_id') ? : null;
        $transfer       = $transfer_id ? $this->transferModel->get_details_by_id($transfer_id) : null;
        $transfer_items = $transfer_id ? $this->transferItemModel->get_details_by_transfer_id($transfer_id) : null;
        $transfer_receive_items = $transfer_id ? $this->transferReceiveItemModel->get_details_by_transfer_id($transfer_id) : null;


        if (!$transfer) {
            $response = $this->failNotFound('No transfer found');
        } else {
            $transfer[0]['transfer_items'] = $transfer_items;
            $transfer[0]['transfer_receive_items'] = $transfer_receive_items;
            $response = $this->respond([
                'data'   => $transfer,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all transfer
     */
    public function get_all_transfer()
    {
        if (($response = $this->_api_verification('transfer', 'get_all_transfer')) !== true)
            return $response;

        $transfers = $this->transferModel->get_all_transfer();

        if (!$transfers) {
            $response = $this->failNotFound('No transfer found');
        } else {
            foreach ($transfers as $key => $transfer) {
                $transfers[$key]['transfer_items'] = $this->transferItemModel->get_details_by_transfer_id($transfer['id']);
            }
            $response = $this->respond([
                'data'   => $transfers,
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
        if (($response = $this->_api_verification('transfers', 'create')) !== true) 
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$transfer_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail("Failed to create transfer: " . $this->errorMessage);
        } else if (!$this->_attempt_generate_transfer_items($transfer_id)) {
            $this->db->transRollback();
            $response = $this->fail("Failed to create transfer items: " . $this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response'    => 'Transfer created successfully.', 
                'status'      => 'success',
                'transfer_id' => $transfer_id
            ]);
        }

        $this->db->close();
        
        if ($this->version == '1') {
            $this->db = \Config\Database::connect();
            $this->db->transBegin();
            $transfer = $this->transferModel->get_details_by_id($transfer_id);
            if ($transfer && !$this->_attempt_record_status($transfer[0], 'approved')) {
                $response = $this->fail("Failed to record transfer status: " . $this->errorMessage);
            } else {
                $this->db->transCommit();
            }

            $this->db->close();
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update Transfer
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('transfers', 'update')) !== true)
            return $response;

        $transfer_id = $this->request->getVar('transfer_id');
        $where = [
            'id' => $transfer_id, 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();
        
        if (!$transfer = $this->transferModel->select('', $where, 1)) {
            $response = $this->failNotFound('Transfer not found');
        } elseif (!$this->_attempt_update_transfer($transfer_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_update_transfer_items($transfer)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'transfer updated successfully']);
        }

        if ($transfer['transfer_status'] == 'approved' && $this->version == '1' && !$this->_attempt_update_stock($transfer)) {
            var_dump('failed to update stock');
            return false;
        }

        $this->db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete transfer
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('transfers', 'delete')) !== true)
            return $response;

        $transfer_id = $this->request->getVar('transfer_id');
        $where = ['id' => $transfer_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$transfer = $this->transferModel->select('', $where, 1)) {
            $response = $this->failNotFound('Transfer not Found.');
        } elseif (!$this->_attempt_delete($transfer)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->transferItemModel->delete_by_transfer_id($transfer_id, $this->requested_by, $this->db)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Transfer deleted successfully.']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Change status of transfer
     */
    public function change_status()
    {
        if (($response = $this->_api_verification('transfers', 'change_status')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('transfer_id'), 
            'is_deleted' => 0
        ];
        $new_status  = $this->request->getVar('new_status');

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$transfer = $this->transferModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'transfer not found']);
        } elseif (!$this->_attempt_change_status($transfer, $new_status)) {
            if ($this->db->transStatus() === FALSE) {
                $this->db->transRollback();
            }
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->transferItemModel->update_status_by_transfer_id($transfer['id'], $this->requested_by, $new_status, $this->db)) {
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
     * Search multiple status
     */
    public function search_multiple_status()
    {
        if (($response = $this->_api_verification('transfers', 'search_multiple_status')) !== true)
            return $response;

        $transfer_id     = $this->request->getVar('transfer_id') ? : null;
        $branch_from     = $this->request->getVar('branch_from') ? : null;
        $branch_to       = $this->request->getVar('branch_to') ? : null;
        $transfer_number = $this->request->getVar('transfer_number') ? : null;
        $date_from       = $this->request->getVar('date_from') ? : null;
        $date_to         = $this->request->getVar('date_to') ? : null;
        $remarks         = $this->request->getVar('remarks') ? : null;
        $grand_total     = $this->request->getVar('grand_total') ? : null;
        $status          = $this->request->getVar('status') ? : null;

        if (!$transfers = $this->transferModel->search_multiple_status($transfer_id, $branch_from, $branch_to, $transfer_number, $date_from, $date_to, $remarks, $grand_total, $status)) {
            $response = $this->failNotFound('No transfer found');
        } else {
            $response = $this->respond([
                'data' => $transfers
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search transfer based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('transfers', 'search')) !== true)
            return $response;

        $transfer_id        = $this->request->getVar('transfer_id') ? : null;
        $branch_from        = $this->request->getVar('branch_from') ? : null;
        $branch_to          = $this->request->getVar('branch_to') ? : null;
        $transfer_number    = $this->request->getVar('transfer_number') ? : null;
        $transfer_date_to   = $this->request->getVar('transfer_date_to') ? : null;
        $transfer_date_from = $this->request->getVar('transfer_date_from') ? : null;
        $date_completed_to   = $this->request->getVar('date_completed_to') ? : null;
        $date_completed_from = $this->request->getVar('date_completed_from') ? : null;
        $remarks            = $this->request->getVar('remarks') ? : null;
        $grand_total        = $this->request->getVar('grand_total') ? : null;
        $status             = $this->request->getVar('status') ? : null;
        $limit_by           = $this->request->getVar('limit_by') ? : null;

        if (!$transfer = $this->transferModel->search($transfer_id, $branch_from, $branch_to, $transfer_number, $transfer_date_to, $transfer_date_from, $date_completed_from, $date_completed_to, $remarks, $grand_total, $status, $limit_by)) {
            $response = $this->failNotFound('No transfer found');
        } else {
            $response = $this->respond([
                'data' => $transfer
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record transfer status of transfer
     */
    public function record_status()
    {
        if (($response = $this->_api_verification('transfers', 'record_status')) !== true)
            return $response;

        $transfer_id = $this->request->getVar('transfer_id');
        $new_status  = $this->request->getVar('new_status');

        $where = ['id' => $transfer_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$transfer = $this->transferModel->select('', $where, 1)) {
            $response = $this->failNotFound('Transfer not Found.');
        } elseif ($transfer['transfer_status'] == 'approved' && $new_status == 'approved') {
            $response = $this->fail('Transfer already approved.');
        } elseif (!$this->_attempt_record_status($transfer, $new_status)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status' => 'success',
                'response' => 'Status recorded successfully',
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all for adjustments
     */
    public function get_all_for_adjustments()
    {
        if (($response = $this->_api_verification('transfers', 'get_all_for_adjustments')) !== true)
            return $response;

        if (!$transfer_items = $this->transferItemModel->get_all_for_adjustment()) {
            $response = $this->failNotFound('No transfers found');
        } else {
            $response = $this->respond([
                'data' => $transfer_items
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create transfer
     */
    private function _attempt_create()
    {
        $values = [
            'request_id'      => $this->request->getVar('request_id'),
            'branch_from'     => $this->request->getVar('branch_from'),
            'branch_to'       => $this->request->getVar('branch_to'),
            'dispatcher'      => $this->request->getVar('dispatcher'),
            'transfer_number' => $this->request->getVar('transfer_number'),
            'transfer_date'   => $this->request->getVar('transfer_date'),
            'remarks'         => $this->request->getVar('remarks'),
            'grand_total'     => $this->request->getVar('grand_total'),
            'status'          => 'processed',
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s'),
            'is_deleted'      => 0
        ];

        if (!$transfer_id = $this->transferModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Update the status of request to processing
        if ($this->request->getVar('request_id')) {
            $values = [
                'transfer_number' => $this->request->getVar('transfer_number'),
                'updated_by' => $this->requested_by,
                'updated_on' => date('Y-m-d H:i:s')
            ];
            
            if (!$this->requestModel->update($this->request->getVar('request_id'), $values)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        return $transfer_id;
    }
    /**
     * Attempt generate PO
     */
    protected function _attempt_generate_transfer_items($transfer_id)
    {
        $item_ids   = $this->request->getVar('item_ids');
        $units      = $this->request->getVar('units');
        $quantities = $this->request->getVar('quantities');
        $prices     = $this->request->getVar('prices');

        $grand_total = 0;
        foreach ($item_ids as $key => $item_id) {
            $current_total = $quantities[$key] * $prices[$key];
            $grand_total   += $current_total;

            $data = [
                'transfer_id'   => $transfer_id,
                'item_id'       => $item_id,
                'unit'          => $units[$key],
                'qty'           => $quantities[$key],
                'price'         => $prices[$key],
                'total'         => $current_total,
                'status'        => 'pending',
                'added_by'      => $this->requested_by,
                'added_on'      => date('Y-m-d H:i:s')
            ];

            if (!$this->transferItemModel->insert_on_duplicate($data, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        $data = [
            'grand_total' => $grand_total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->transferModel->update($transfer_id, $data)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update_transfer($transfer_id)
    {
        $data = [
            'request_id'      => $this->request->getVar('request_id'),
            'branch_from'     => $this->request->getVar('branch_from'),
            'branch_to'       => $this->request->getVar('branch_to'),
            'dispatcher'      => $this->request->getVar('dispatcher'),
            'transfer_number' => $this->request->getVar('transfer_number'),
            'transfer_date'   => $this->request->getVar('transfer_date'),
            'remarks'         => $this->request->getVar('remarks'),
            'status'          => $this->request->getVar('status'),
            'completed_on'    => $this->request->getVar('completed_on'),
            'updated_by'      => $this->requested_by,
            'updated_on'      => date('Y-m-d H:i:s')
        ];

        if (!$this->transferModel->update($transfer_id, $data)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt update transfer items
     */
    protected function _attempt_update_transfer_items($transfer)
    {
        // Check if the transfer status is approved and if true, update the inventory
        if ($transfer['transfer_status'] == 'approved' && !$this->_revert_transfer_items($transfer))
            return false;

        // Delete all transfer items
        if (!$this->transferItemModel->delete_by_transfer_id($transfer['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_attempt_generate_transfer_items($transfer['id'])) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete transfer
     */
    protected function _attempt_delete($transfer) 
    {
        // Check if the transfer status is approved
        if ($transfer['transfer_status'] == 'approved' && !$this->_revert_transfer_items($transfer))
            return false;

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->transferModel->update($transfer['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Revert Tranfer Items
     */
    protected function _revert_transfer_items($transfer = null)
    {
        $transfer_items = $this->transferItemModel->get_details_by_transfer_id($transfer['id']);
        foreach ($transfer_items as $transfer_item) {        
            $data = [
                'status'       => 'incomplete',
                'received_qty' => (float)$transfer_item['qty'],
                'updated_by'   => $this->requested_by,
                'updated_on'   => date('Y-m-d H:i:s')
            ];

            $where = [
                'transfer_id' => $transfer['id'],
                'item_id'     => $transfer_item['item_id'],
                'unit'        => $transfer_item['unit']
            ];

            if (!$this->transferItemModel->update_received($where, $data, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }

            // // get the item unit details
            // if (!$item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($transfer['branch_from'], $transfer_item['item_id'], $transfer_item['unit'])) {
            //     $this->errorMessage = $this->db->error()['message'];
            //     return false;
            // }

            // $where = [
            //     'item_id'      => $transfer_item['item_id'],
            //     'item_unit_id' => $item_unit[0]['id'],
            //     'branch_id'    => $transfer['branch_from']
            // ];

            // if (!$this->inventoryModel->update_quantity($where, $transfer_item['qty'], $this->requested_by)) {
            //     $this->errorMessage = $this->db->error()['message'];
            //     return false;
            // }

            // // Increase the inventory qty from the branch_to
            // $where['branch_id'] = $transfer['branch_to'];
            // if (!$this->inventoryModel->update_quantity($where, $transfer_item['qty']  * -1, $this->requested_by)) {
            //     $this->errorMessage = $this->db->error()['message'];
            //     return false;
            // }
        }

        return true;
    }

    /**
     * Attempt change transfer status
     */
    protected function _attempt_change_status($transfer, $new_status)
    {
        // Check old status
        // if ($transfer['status'] == 'deleted') {
        //     $this->errorMessage = 'Cannot change status of deleted transfer.';
        //     return false;
        // }

        $values = [
            'status'     => $new_status,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if ($new_status == 'completed') {
            $values['completed_by'] = $this->requested_by;
            $values['completed_on'] = date('Y-m-d H:i:s');
        }

        if ($new_status == 'deleted' && !$this->_revert_transfer_items($transfer))
            return false;
        
        if ($new_status == 'deleted' && !$this->transferItemModel->delete_by_transfer_id($transfer['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if ($new_status == 'deleted') {
            $values['is_deleted'] = 1;
        }

        if (!$this->transferModel->update($transfer['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt record status
     */
    protected function _attempt_record_status($transfer, $new_status = null)
    {
        // Check if the status is already approved 
        if ($transfer['transfer_status'] == 'approved')
            return true;

        $values = [
            'transfer_status' => $new_status,
            'updated_by'      => $this->requested_by,
            'updated_on'      => date('Y-m-d H:i:s')
        ];

        switch ($values['transfer_status']) {
            case 'approved':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                $values['dispatcher'] = $this->request->getVar('dispatcher');
                $values['transfer_number'] = $this->request->getVar('transfer_number');
                $values['status'] = 'processed';
                break;
            case 'rejected':
                $values['rejected_by'] = $this->requested_by;
                $values['rejected_on'] = date('Y-m-d H:i:s');
                break;
        }

        if (!$this->transferModel->update($transfer['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if ($new_status == 'approved' && $this->version == '1' && !$this->_attempt_update_stock($transfer))
            return false;

        return true;
    }

    /**
     * Attempt update stock
     */
    protected function _attempt_update_stock($transfer)
    {
        $transfer_items = $this->transferItemModel->get_details_by_transfer_id($transfer['id']);
        foreach ($transfer_items as $transfer_item) {        
            $data = [
                'received_qty' => $transfer_item['qty'],
                'updated_by'   => $this->requested_by,
                'updated_on'   => date('Y-m-d H:i:s')
            ];

            if ($data['received_qty'] == $transfer_item['qty']) {
                $data['status'] = 'completed';
            } else {
                $date['status'] = 'incomplete';
            }

            $where = [
                'transfer_id' => $transfer['id'],
                'item_id'     => $transfer_item['item_id'],
                'unit'        => $transfer_item['unit']
            ];

            if (!$this->transferItemModel->update_received($where, $data, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }

            // get the item unit details
            if (!$item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($transfer['branch_from'], $transfer_item['item_id'], $transfer_item['unit'])) {
                $this->errorMessage = "Failed to get item unit details: " . 'item_id: ' . $transfer_item['item_id'] . ' unit: ' . $transfer_item['unit'] . ' branch_id: ' . $transfer['branch_from'];
                return false;
            }

            $where = [
                'item_id'      => $transfer_item['item_id'],
                'item_unit_id' => $item_unit[0]['id'],
                'branch_id'    => $transfer['branch_from']
            ];

            if (!$this->inventoryModel->get_inventory_detail($transfer_item['item_id'], $transfer['branch_from'], $item_unit[0]['id'])) {
                $this->errorMessage = "Failed to get inventory details: " . 'item_id: ' . $transfer_item['item_id'] . ' branch_id: ' . $transfer['branch_from'] . ' item_unit_id: ' . $item_unit[0]['id'];
                return false;
            } elseif (!$this->inventoryModel->update_quantity($where, $transfer_item['qty'] * -1, $this->requested_by)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }

            // Increase the inventory qty from the branch_to
            $where['branch_id'] = $transfer['branch_to'];
            if (!$this->inventoryModel->get_inventory_detail($transfer_item['item_id'], $transfer['branch_to'], $item_unit[0]['id'])) {
                 // create the inventory if the branch does not have one
                 $values = [
                    'item_id'       => $transfer_item['item_id'],
                    'branch_id'     => $transfer['branch_to'],
                    'item_unit_id'  => $item_unit[0]['id'],
                    'beginning_qty' => 0,
                    'current_qty'   => $transfer_item['qty'],
                    'added_by'      => $this->requested_by,
                    'added_on'      => date('Y-m-d H:i:s'),
                ];
    
                if (!$this->inventoryModel->insert($values)) {
                    $this->errorMessage = $this->db->error()['message'];
                    return false;
                }
            } elseif (!$this->inventoryModel->update_quantity($where, $transfer_item['qty'], $this->requested_by)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        $is_complete = $this->transferItemModel->is_all_received_by_transfer_id($transfer['id']);

        if ($is_complete) {
            $values = [
                'status'      => 'completed',
                'completed_by' => $this->requested_by,
                'completed_on' => date('Y-m-d H:i:s'),
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ];

            if (!$this->transferModel->update($transfer['id'], $values)) {
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
        // Version 1 = Automatic acceptance of transfer
        // Version 2 = Manual acceptance of transfer
        $this->version = '2';
        
        $this->transferModel       = model('App\Models\Transfer');
        $this->requestModel        = model('App\Models\Request');
        $this->transferItemModel   = model('App\Models\Transfer_item');
        $this->transferReceiveItemModel = model('App\Models\Transfer_receive_item');
        $this->checkInvoiceModel   = model('App\Models\Check_invoice');
        $this->inventoryModel      = model('App\Models\Inventory');
        $this->itemUnitModel       = model('App\Models\Item_unit');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}

<?php

namespace App\Controllers;

class Receives extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get receive
     */
    public function get_receive()
    {
        if (($response = $this->_api_verification('receive', 'get_receive')) !== true)
            return $response;

        $receive_id    = $this->request->getVar('receive_id') ? : null;
        $receive       = $receive_id ? $this->receiveModel->get_details_by_id($receive_id) : null;
        $receive_items = $receive_id ? $this->receiveItemModel->get_details_by_receive_id($receive_id) : null;

        if (!$receive) {
            $response = $this->failNotFound('No receive found');
        } else {
            $receive[0]['receive_items']  = $receive_items;
            $receive[0]['payments']      = $this->suppliesPaymentModel->get_all_payment_by_receive($receive[0]['id']);
            $response = $this->respond([
                'status' => 'success',
                'data' => $receive
            ]);
        }


        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all receive
     */
    public function get_all_receive()
    {
        if (($response = $this->_api_verification('receive', 'get_all_receive')) !== true)
            return $response;

        $supplier_id = $this->request->getVar('supplier_id') ? : null;
        $vendor_id   = $this->request->getVar('vendor_id') ? : null;
        $bill_type   = $this->request->getVar('bill_type') ? : null;
        $receives    = $this->receiveModel->get_all_receive($supplier_id, $vendor_id, $bill_type);

        if (!$receives) {
            $response = $this->failNotFound('No receive found');
        } else {
            foreach ($receives as $key => $receive) {         
                $receives[$key]['receive_items'] = $this->receiveItemModel->get_details_by_receive_id($receive['id']);
                $receives[$key]['payments']      = $this->suppliesPaymentModel->get_all_payment_by_receive($receive['id']);
            }
            $response = $this->respond([
                'status' => 'success',
                'data' => $receives
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create receive
     */
    public function create()
    {
        if (($response = $this->_api_verification('receives', 'create')) !== true)
            return $response;

        if ($this->_has_duplicate_invoice()) {
            $response = $this->fail(['response' => 'Either waybill, invoice, or DR number is duplicate.', 'status' => 'error']);
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        $po_id = $this->request->getVar('po_id');
        $purchase    = $this->purchaseModel->get_details_by_id($po_id);
        if ($purchase && $purchase[0]['order_status'] == 'complete') {
            $response = $this->fail(['response' => 'Purchase order is already complete.', 'status' => 'error']);
        } elseif (!$receive_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create receive.', 'status' => 'error']);
        } else if (!$this->_attempt_generate_receive_items($receive_id, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate receive items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'   => 'Receive created successfully.',
                'status'     => 'success',
                'receive_id' => $receive_id
            ]);
        }

        // Check if PO is with payment and if receive was created
        if ($purchase && isset($receive_id) && $purchase[0]['with_payment']) {
            $this->_attempt_create_payment($receive_id, $purchase[0], $db);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);

        return $response;
    }

    /**
    * Check duplicate invoice, waybill, or DR numbers
    */
    protected function _has_duplicate_invoice()
    {
        $waybill_no = $this->request->getVar('waybill_no') ? : NULL;
        $invoice_no = $this->request->getVar('invoice_no') ? : NULL;
        $dr_no = $this->request->getVar('dr_no') ? : NULL;

        return (($this->receiveModel->check_duplicate_invoice($waybill_no, $invoice_no, $dr_no)) ? true : false);
    }

    /**
     * Update Receive
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('receives', 'update')) !== true)
            return $response;

        $receive_id = $this->request->getVar('receive_id');
        $where      = ['id' => $receive_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();
        if (!$receive = $this->receiveModel->select('', $where, 1)) {
            $response = $this->failNotFound('Receive not found');
        } elseif (!$this->_attempt_update_receive($receive_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update receive.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_receive_items($receive, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update receive items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Receive updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    /**
     * Delete receive
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('receives', 'delete')) !== true)
            return $response;

        $receive_id = $this->request->getVar('receive_id');
        $where = ['id' => $receive_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$receive = $this->receiveModel->select('', $where, 1)) {
            $response = $this->failNotFound('receive not found');
        } elseif (!$this->_attempt_delete($receive)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } elseif (!$this->receiveItemModel->delete_by_receive_id($receive_id, $this->requested_by)) {
            $db->transRollback();
            $response = $this->respond(['response' => 'Receive items not deleted successfully.']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Receive deleted successfully.']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search receive based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('receive', 'search')) !== true)
            return $response;

        $po_id             = $this->request->getVar('po_id');
        $branch_id         = $this->request->getVar('branch_id');
        $supplier_id       = $this->request->getVar('supplier_id');
        $vendor_id         = $this->request->getVar('vendor_id');
        $receive_date      = $this->request->getVar('receive_date');
        $waybill_no        = $this->request->getVar('waybill_no');
        $invoice_no        = $this->request->getVar('invoice_no');
        $dr_no             = $this->request->getVar('dr_no');
        $remarks           = $this->request->getVar('remarks');
        $receive_date_to   = $this->request->getVar('receive_date_to');
        $receive_date_from = $this->request->getVar('receive_date_from');
        $payment_status    = $this->request->getVar('payment_status');

        if (!$receives = $this->receiveModel->search($po_id, $branch_id, $supplier_id, $vendor_id, $receive_date, $waybill_no, $invoice_no, $dr_no, $remarks, $receive_date_to, $receive_date_from, $payment_status)) {
            $response = $this->failNotFound('No receive found');
        } else {
            $summary = [
                'total' => 0,
                'total_paid' => 0,
                'total_balance' => 0
            ];
            
            foreach ($receives as $key => $receive) {
                $receives[$key]['payments'] = $this->suppliesPaymentModel->get_all_payment_by_receive($receive['id']);
                
                $summary['total'] += $receive['grand_total'];
                $summary['total_paid'] += $receive['paid_amount'];
                $summary['total_balance'] += $receive['balance'];
            }

            $response = $this->respond([
                'summary'  => $summary,
                'data'     => $receives,
                'response' => 'Receive found successfully.',
                'status'   => 'success',
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
        if (($response = $this->_api_verification('receive', 'get_bills')) !== true)
            return $response;

        $type = $this->request->getVar('type');
        if (!$receives = $this->receiveModel->get_bills($type)) {
            $response = $this->failNotFound('No receive found');
        } else {
            $response = [];
            $response['data'] = $receives;
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
        if (($response = $this->_api_verification('receive', 'get_all_invoice_payments')) !== true)
            return $response;
        
        $receive_id       = $this->request->getVar('receive_id') ? : null;
        $invoice_payments = $receive_id ? $this->suppliesPaymentModel->get_all_payment_by_receive($receive_id) : null;

        if (!$invoice_payments) {
            $response = $this->failNotFound('No invoice payments found');
        } else {
            $response = $this->respond([
                'receive_id' => $receive_id,
                'data'       => $invoice_payments,
                'status'     => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
    
    /**
     * Close overpaid receive
     */
    public function close_overpaid_receive()
    {
        if (($response = $this->_api_verification('receive', 'close_overpaid_receive')) !== true)
            return $response;

        $receive_id = $this->request->getVar('receive_id');
        $where = ['id' => $receive_id, 'is_deleted' => 0];

        if (!$receive = $this->receiveModel->select('', $where, 1)) {
            $response = $this->failNotFound('receive not found');
        } elseif (!$this->_attempt_close_overpaid_receive($receive)) {
            $response = $this->respond(['response' => 'Receive not closed successfully.']);
        } else {
            $response = $this->respond(['response' => 'Receive closed successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create
     */
    private function _attempt_create()
    {
        $values = [
            'po_id'         => $this->request->getVar('po_id'),
            'branch_id'     => $this->request->getVar('branch_id'),
            'supplier_id'   => $this->request->getVar('supplier_id'),
            'vendor_id'     => $this->request->getVar('vendor_id'),
            'purchase_date' => $this->request->getVar('purchase_date'),
            'receive_date'  => $this->request->getVar('receive_date'),
            'purpose'       => $this->request->getVar('purpose'),
            'forwarder_id'  => $this->request->getVar('forwarder_id'),
            'waybill_no'    => $this->request->getVar('waybill_no'),
            'invoice_no'    => $this->request->getVar('invoice_no'),
            'dr_no'         => $this->request->getVar('dr_no'),
            'freight_cost'  => $this->request->getVar('freight_cost'),
            'discount'      => $this->request->getVar('discount'),
            'paid_amount'   => $this->request->getVar('paid_amount'),
            'remarks'       => $this->request->getVar('remarks'),
            'added_by'      => $this->requested_by,
            'added_on'      => date('Y-m-d H:i:s'),
        ];

        if (!$receive_id = $this->receiveModel->insert($values)) {
            return false;
        }

        return $receive_id;
    }

    /**
     * Attempt to generate receive items
     */
    private function _attempt_generate_receive_items($receive_id, $db)
    {
        $item_ids       = $this->request->getVar('item_ids');
        $quantities     = $this->request->getVar('quantities');
        $units          = $this->request->getVar('units');
        $prices         = $this->request->getVar('prices');
        $types          = $this->request->getVar('types');
        $po_item_ids    = $this->request->getVar('po_item_ids');
        $branch_id      = $this->request->getVar('branch_id');

        $total = 0;
        foreach ($item_ids as $key => $item_id) {
            $total += $quantities[$key] * $prices[$key];

            // check if the key exists in the po_item_ids array
            if (!isset($po_item_ids[$key])) {
                $po_item_id = null;
            } else {
                $po_item_id = $po_item_ids[$key];
            }

            // get the item unit details
            if (!$item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($branch_id, $item_id, $units[$key])) {
                var_dump("item unit not found");
                return false;
            }
            $item_unit = $item_unit[0];
            
            $inventory_qty = $this->inventoryModel->get_inventory_qty_by_branch($item_id, 1, $units[$key]) ? : 0;

            $values = [
                'receive_id'    => $receive_id,
                'po_item_id'    => $po_item_id,
                'item_id'       => $item_id,
                'item_unit_id'  => $item_unit['id'],
                'qty'           => $quantities[$key],
                'unit'          => $units[$key],
                'price'         => (float)$prices[$key],
                'total'         => $quantities[$key] * $prices[$key],
                'inventory_qty' => $inventory_qty,
                'added_by'      => $this->requested_by,
                'added_on'      => date('Y-m-d H:i:s'),
            ];

            if (!$receive_item_id = $this->receiveItemModel->insert($values)) {
                return false;
            }

            // Update the po item receive quanity
            if ($po_item_id && !$this->purchaseItemModel->update_receive_qty_by_id($po_item_id, $quantities[$key], $this->requested_by, $db)) {
                var_dump("Error updating po item receive quantity");
                return false;
            }

            if ($inventory = $this->inventoryModel->get_inventory_detail($item_id, $branch_id, $item_unit['id'])) {
                $inventory = $inventory[0];
                $inventory_id = $inventory['id'];

                $new_values = [
                    'current_qty' => $inventory['current_qty'] + $quantities[$key],
                    'updated_by'  => $this->requested_by,
                    'updated_on'  => date('Y-m-d H:i:s'),
                ];
        
                if (!$this->inventoryModel->update($inventory_id, $new_values)) {
                    var_dump("Error updating inventory");
                    return false;
                }
            } else {
                $inventory_value = [
                    'branch_id'           => $branch_id,
                    'item_id'             => $item_id,
                    'item_unit_id'        => $item_unit['id'],
                    'beginning_qty'       => 0,
                    'current_qty'         => $quantities[$key],
                    'max'                 => $item_unit['max'],
                    'min'                 => $item_unit['min'],
                    'unit'                => $item_unit['inventory_unit'],
                    'acceptable_variance' => $item_unit['acceptable_variance'],
                    'added_by'            => $this->requested_by,
                    'added_on'            => date('Y-m-d H:i:s'),
                ];

                if (!$inventory_id = $this->inventoryModel->insert($inventory_value)) {
                    var_dump("Error inserting inventory");
                    return false;
                }
            }

            $values = [
                'inventory_id' => $inventory_id,
                'updated_by'   => $this->requested_by,
                'updated_on'   => date('Y-m-d H:i:s'),
            ];

            if (!$this->receiveItemModel->update($receive_item_id, $values)) {
                var_dump("Error updating receive item inventory id");
                return false;
            }
        }

        // Update if purchase order is complete
        $purchase_id = $this->request->getVar('po_id');
        $is_complete = $this->purchaseItemModel->is_all_received_by_purchase_id($purchase_id);

        $values = [
            'order_status' => $is_complete ? 'complete' : 'incomplete',
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$this->purchaseModel->update($purchase_id, $values)) {
            var_dump("Error updating purchase order");
            return false;
        }
        
        // Update the receive table's amounts
        $discount     = $this->request->getVar('discount') ?? 0;
        $freight_cost = $this->request->getVar('freight_cost') ?? 0;
        $service_fee  = $this->request->getVar('service_fee') ?? 0;

        // get the old receive details
        $paid_amount = 0;
        if ($receive = $this->receiveModel->get_details_by_id($receive_id)) {
            $paid_amount = (float)$receive[0]['paid_amount'] ?? 0;
        }


        $values = [
            'service_fee'   => $service_fee,
            'subtotal'      => $total,
            'grand_total'   => $total - (float)$discount + (float)$freight_cost + (float)$service_fee,
            'paid_amount'   => $paid_amount,
            'balance'       => $total - (float)$discount + (float)$freight_cost + (float)$service_fee - $paid_amount,
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s')
        ];

        if (!$this->receiveModel->update($receive_id, $values)) {
            var_dump("Error updating receive table");
            return false;
        }

        return true;
    }

    /** 
     * Attempt update
     */
    function _attempt_update_receive($receive_id)
    {
        $values = [
            'po_id'         => $this->request->getVar('po_id'),
            'branch_id'     => $this->request->getVar('branch_id'),
            'supplier_id'   => $this->request->getVar('supplier_id'),
            'vendor_id'     => $this->request->getVar('vendor_id'),
            'purchase_date' => $this->request->getVar('purchase_date'),
            'receive_date'  => $this->request->getVar('receive_date'),
            'purpose'       => $this->request->getVar('purpose'),
            'forwarder_id'  => $this->request->getVar('forwarder_id'),
            'waybill_no'    => $this->request->getVar('waybill_no'),
            'invoice_no'    => $this->request->getVar('invoice_no'),
            'dr_no'         => $this->request->getVar('dr_no'),
            'freight_cost'  => $this->request->getVar('freight_cost'),
            'discount'      => $this->request->getVar('discount'),
            'paid_amount'   => $this->request->getVar('paid_amount'),
            'remarks'       => $this->request->getVar('remarks'),
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s'),
        ];

        if (!$this->receiveModel->update($receive_id, $values))
            return false;

        return true;
    }

    function _attempt_restore_inventory_by_receive_id($receive)
    {
        // Get the receive items first and decrease the inventory
        $receive_items = $this->receiveItemModel->get_receive_items_by_receive_id($receive['id']);
        $branch_id = $receive['branch_id'];

        // Decrease the inventory based on the receive items
        foreach ($receive_items as $receive_item) {
            $item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($branch_id, $receive_item['item_id'], $receive_item['unit']);
            if (!$item_unit) {
                var_dump("item unit not found");
                return false;
            }
            $item_unit = $item_unit[0];
            
            $inventory = $this->inventoryModel->get_inventory_detail($receive_item['item_id'], $branch_id, $item_unit['id']);
            if (!$inventory) {
                var_dump($receive_item['item_id'] . " " . $branch_id . " " . $item_unit['id']);
                var_dump("inventory not found");
                return false;
            }

            // Update the po item receive quanity
            if ($receive_item['po_item_id'] && !$this->purchaseItemModel->update_receive_qty_by_id($receive_item['po_item_id'], $receive_item['qty'] * -1, $this->requested_by)) {
                var_dump("Error updating po item receive quantity");
                return false;
            }

            $inventory = $inventory[0];
            $new_values = [
                'current_qty' => $inventory['current_qty'] - $receive_item['qty'],
                'updated_by'  => $this->requested_by,
                'updated_on'  => date('Y-m-d H:i:s'),
            ];

            if (!$this->inventoryModel->update($inventory['id'], $new_values)) {
                var_dump("Error updating inventory");
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt update receive items
     */
    function _attempt_update_receive_items($receive, $db)
    {
        if (!$this->_attempt_restore_inventory_by_receive_id($receive)) {
            var_dump("Error restoring inventory");
            return false;
        }

        if (!$this->receiveItemModel->delete_by_receive_id($receive['id'], $this->requested_by, $db))
            return false;
        
        return $this->_attempt_generate_receive_items($receive['id'], $db);
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($receive)
    {
        if (!$this->_attempt_restore_inventory_by_receive_id($receive)) {
            var_dump("Error restoring inventory");
            return false;
        }

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->receiveModel->update($receive['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt add payment
     */
    protected function _attempt_create_payment($receive_id, $purchase, $db) {
        // Get the purchase payment
        if (!$purchase_payment = $this->purchasePaymentModel->get_details_by_purchase_id($purchase['id'])) {
            $db->close();
            var_dump("Error getting purchase payment");
            return false;
        }

        // Check if there is balance that can be use for payment
        $purchase_payment = $purchase_payment[0];
        if ($purchase_payment['balance'] <= 0) {
            $db->close();
            var_dump("Purchase payment balance is 0");
            return false;
        }

        // Get the receive
        if (!$receive = $this->receiveModel->get_details_by_id($receive_id)) {
            $db->close();
            var_dump("Error getting receive");
            return false;
        }
        $receive = $receive[0];

        // Get the balance of the receive
        $balance = $receive['balance'];
        // Compute what is the amount that can be paid
        $paid_amount = ($purchase_payment['balance'] - $balance) >= 0 ? $balance : $purchase_payment['balance'];

        // Update the purchase payment
        $new_purchase_payment_balance = $purchase_payment['balance'] - $paid_amount;
        $purchase_payment_values = [
            'balance' => $new_purchase_payment_balance,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->purchasePaymentModel->update($purchase_payment['id'], $purchase_payment_values)) {
            $db->transRollback();
            $db->close();
            var_dump("Error updating purchase payment");
            return false;
        }

        // Update the receive
        $receive_values = [
            'paid_amount' => $receive['paid_amount'] + $paid_amount,
            'balance' => $receive['balance'] - $paid_amount,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->receiveModel->update($receive['id'], $receive_values)) {
            $db->transRollback();
            $db->close();
            var_dump("Error updating receive");
            return false;
        }


        // ----------------- Create the payment -----------------
        // Check the purchase payment details
        if (!$purchase_payment_details = $this->purchasePaymentDetailModel->get_details_by_purchase_payment_id($purchase_payment['id'])) {
            $db->close();
            var_dump("Error getting purchase payment details");
            return false;
        }

        // Create the commmon values for slips
        $slip_details = [
            'status'              => 'pending',
            'purchase_payment_id' => $purchase_payment['id'],
            'added_by'            => $this->requested_by,
            'added_on'            => date('Y-m-d H:i:s'),
        ];

        // Create the common values for entries
        $entry_details = [
            'receive_id' => $receive['id'],
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
        ];

        // Loop through the purchase payment details and create the payment
        foreach ($purchase_payment_details as $purchase_payment_detail) {
            // Check if the payment detail balance is 0
            if ($purchase_payment_detail['balance'] <= 0) {
                // Skip since the payment detail is used up
                continue;
            }

            // Compute the amount that can be paid using the payment detail
            $payment_amount = ($purchase_payment_detail['balance'] - $paid_amount) >= 0 ? $paid_amount : $purchase_payment_detail['balance'];
            // Update the amount that should be paid using the purchase payment
            $paid_amount -= $payment_amount;

            // This stops the loop from adding more payment details
            if ($payment_amount <= 0) {
                // Break the loop since the payment amount is 0
                break;
            }

            // Update the balance of purchase_payment_detail
            $new_purchase_payment_detail_balance = $purchase_payment_detail['balance'] - $payment_amount;
            $purchase_payment_detail_values = [
                'balance' => $new_purchase_payment_detail_balance,
                'updated_by' => $this->requested_by,
                'updated_on' => date('Y-m-d H:i:s')
            ];

            if (!$this->purchasePaymentDetailModel->update($purchase_payment_detail['id'], $purchase_payment_detail_values)) {
                $db->transRollback();
                $db->close();
                var_dump("Error updating purchase payment detail");
                return false;
            }

            // Assigning the unique values for slips
            $slip_details['payee']       = $purchase_payment_detail['payee'];
            $slip_details['particulars'] = $purchase_payment_detail['particulars'];
            $slip_details['vendor_id']   = $purchase_payment_detail['vendor_id'];
            $slip_details['supplier_id'] = $purchase_payment_detail['supplier_id'];
            $slip_details['amount']      = $payment_amount;

            // Assigning the unique values for entry
            $entry_details['amount'] = $payment_amount;

            // Declaring global variables
            $slip_model = $entry_model = $merged_slip_details = $merged_entry_details = null;

            $payment_type = $purchase_payment_detail['payment_type'];
            switch ($payment_type) {
                case 'cash':
                    // Create the details for the cash slip
                    $cash_slip['payment_date'] = $purchase_payment_detail['payment_date'];
                    $merged_slip_details = array_merge($slip_details, $cash_slip);

                    // Declaring the models
                    $slip_model  = $this->cashSlipModel;
                    $entry_model = $this->cashEntryModel;
                    break;
                case 'check':
                    // Create the details for the check slip
                    $check_slip['bank_id']     = $purchase_payment_detail['from_bank_id'];
                    $check_slip['check_no']    = $purchase_payment_detail['check_no'];
                    $check_slip['check_date']  = $purchase_payment_detail['check_date'];
                    $check_slip['issued_date'] = $purchase_payment_detail['issued_date'];
                    $merged_slip_details       = array_merge($slip_details, $check_slip);

                    // declaring the models
                    $slip_model  = $this->checkSlipModel;
                    $entry_model = $this->checkEntryModel;
                    break;
                case 'bank':
                    // Create the details for the bank slip
                    $bank_slip['bank_from']         = $purchase_payment_detail['from_bank_id'];
                    $bank_slip['from_account_no']   = $purchase_payment_detail['from_account_no'];
                    $bank_slip['from_account_name'] = $purchase_payment_detail['from_account_name'];
                    $bank_slip['bank_to']           = $purchase_payment_detail['to_bank_name'];
                    $bank_slip['to_account_no']     = $purchase_payment_detail['to_account_no'];
                    $bank_slip['to_account_name']   = $purchase_payment_detail['to_account_name'];
                    $bank_slip['transaction_fee']   = $purchase_payment_detail['transaction_fee'];
                    $bank_slip['reference_no']      = $purchase_payment_detail['reference_no'];
                    $bank_slip['payment_date']      = $purchase_payment_detail['payment_date'];
                    $merged_slip_details            = array_merge($slip_details, $bank_slip);

                    // declaring the models
                    $slip_model  = $this->bankSlipModel;
                    $entry_model = $this->bankEntryModel;
                    break;
            }

            // Insert the entry
            if (!$this->_insert_data($payment_type, $slip_model, $entry_model, $merged_slip_details, $entry_details, $db)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Insert Entry and slip
     */
    private function _insert_data($payment_type, $slip_model, $entry_model, $merged_slip_details, $entry_details, $db) {
        if (!$slip_id = $slip_model->insert($merged_slip_details)) {
            $db->transRollback();
            $db->close();
            var_dump("Error inserting entry");
            return false;
        }
        
        $entry_slip_id = null;
        switch ($payment_type) {
            case 'cash':
                $entry_slip_id['cash_slip_id'] = $slip_id;
                break;
            case 'check':
                $entry_slip_id['check_slip_id'] = $slip_id;
                break;
            case 'bank':
                $entry_slip_id['bank_slip_id'] = $slip_id;
                break;
        }

        $merged_entry_details = array_merge($entry_details, $entry_slip_id);
        if (!$entry_model->insert($merged_entry_details)) {
            $db->transRollback();
            $db->close();
            var_dump("Error inserting entry");
            return false;
        }

        return $slip_id;
    }

    /**
     * Attempt close overpaid receives
     */
    private function _attempt_close_overpaid_receive($receive) {
        $value = [
            'is_closed'  => 1,
            'remarks'    => $receive['remarks'] . ' - ' . $this->request->getVar('remarks'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->receiveModel->update($receive['id'], $value)) {
            $db->transRollback();
            $db->close();
            var_dump("Error updating receive");
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->receiveModel               = model('App\Models\Receive');
        $this->receiveItemModel           = model('App\Models\Receive_item');
        $this->purchaseModel              = model('App\Models\Purchase');
        $this->purchaseItemModel          = model('App\Models\Purchase_item');
        $this->inventoryModel             = model('App\Models\Inventory');
        $this->itemUnitModel              = model('App\Models\Item_unit');
        $this->suppliesPaymentModel       = model('App\Models\Supplies_payment');
        $this->cashSlipModel              = model('App\Models\Cash_slip');
        $this->cashEntryModel             = model('App\Models\Cash_entry');
        $this->checkSlipModel             = model('App\Models\Check_slip');
        $this->checkEntryModel            = model('App\Models\Check_entry');
        $this->bankSlipModel              = model('App\Models\Bank_slip');
        $this->bankEntryModel             = model('App\Models\Bank_entry');
        $this->purchasePaymentModel       = model('App\Models\Purchase_payment');
        $this->purchasePaymentDetailModel = model('App\Models\Purchase_payment_detail');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}

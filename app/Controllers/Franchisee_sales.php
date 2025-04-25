<?php

namespace App\Controllers;

class Franchisee_sales extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get franchisee_sale
     */
    public function get_franchisee_sale()
    {
        if (($response = $this->_api_verification('franchisee_sales', 'get_franchisee_sale')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchisee_sale_id       = $this->request->getVar('franchisee_sale_id') ? : null;
        $franchisee_sale          = $franchisee_sale_id ? $this->franchiseeSaleModel->get_details_by_id($franchisee_sale_id) : null;
        $franchisee_sale_payments = $franchisee_sale_id ? $this->franchiseeSalePaymentModel->get_details_by_franchisee_sales_id($franchisee_sale_id) : null;
        $franchisee_sale_items    = $franchisee_sale_id ? $this->franchiseeSaleItemModel->get_details_by_franchisee_sales_id($franchisee_sale_id) : null;
        
        if (!$franchisee_sale) {
            $response = $this->failNotFound('No franchisee_sale found');
        } else {
            $franchisee_sale[0]['franchisee_sale_payments'] = $franchisee_sale_payments;
            $franchisee_sale[0]['franchisee_sale_items']    = $franchisee_sale_items;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_sale
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all franchisee_sales
     */
    public function get_all_franchisee_sale()
    {
        if (($response = $this->_api_verification('franchisee_sales', 'get_all_franchisee_sale')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchisee_sales = $this->franchiseeSaleModel->get_all();

        if (!$franchisee_sales) {
            $response = $this->failNotFound('No franchisee_sale found');
        } else {
            foreach ($franchisee_sales as $key => $franchisee_sale) {
                $franchisee_sale_payments = $this->franchiseeSalePaymentModel->get_details_by_franchisee_sales_id($franchisee_sale['id']);
                $franchisee_sales[$key]['franchisee_sale_payments'] = $franchisee_sale_payments;
                $franchisee_sales_items = $this->franchiseeSaleItemModel->get_details_by_franchisee_sales_id($franchisee_sale['id']);
                $franchisee_sales[$key]['franchisee_sale_items'] = $franchisee_sales_items;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee_sales
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create franchisee_sale
     */
    public function create()
    {
        if (($response = $this->_api_verification('franchisee_sales', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$franchisee_sale_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Failed to create franchisee sale.');
        } elseif (!$this->_attempt_generate_franchisee_sale_items($franchisee_sale_id, $db)) {
            $db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'status'        => 'success',
                'franchisee_sale_id' => $franchisee_sale_id
            ]);
        }

        $db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update franchisee_sale
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('franchisee_sales', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id'         => $this->request->getVar('franchisee_sale_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$franchisee_sale = $this->franchiseeSaleModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_sale not found');
        } elseif (!$this->_attempt_update($franchisee_sale['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update franchisee_sale.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_franchisee_sale_items($franchisee_sale, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update franchisee_sale items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Franchisee sale updated successfully.', 'status' => 'success']);
        }

        // Refetch the updated franchisee_sale
        $franchisee_sale = $franchisee_sale ? $this->franchiseeSaleModel->get_details_by_id($franchisee_sale['id'])[0] : null;
        if ($franchisee_sale && $this->request->getVar('payment_type') && $payment_id = $this->_attempt_add_payment($franchisee_sale)) {
            $response = $this->respond([
                'status'             => 'success',
                'franchisee_sale_id' => $franchisee_sale['id'],
                'payment_id'         => $payment_id
            ]);
        }

        // Record the credit limit since there is no payment method but the fs_status is processing
        // Only record if the original status is not processing
        // Since it will be redundant when the user updtates it to processing
        if ($franchisee_sale 
            && $franchisee_sale['fs_status'] == 'quoted' 
            && !$this->request->getVar('payment_type') 
            && $this->request->getVar('fs_status') == 'processing') 
        {
            // Check if credit limit is not exceeded
            $franchisee = $this->franchiseeModel->get_details_by_id($franchisee_sale['franchisee_id'])[0];
            $remaining_credit = $this->franchiseeModel->get_remaining_credit_by_franchisee_name($franchisee['name']);
            if ($remaining_credit[0]['remaining_credit'] < $franchisee_sale['grand_total']) {
                var_dump("Credit limit exceeded");
                $db->transRollback();
            }

            $values = [
                'fs_status'  => 'processing',
                'updated_by' => $this->requested_by,
                'updated_on' => date('Y-m-d H:i:s')
            ];
            // Add credit limit to franchisee
            if (!$this->_record_credit_limit($franchisee_sale['franchisee_id'], $franchisee_sale['grand_total'])) {
                $db->transRollback();
                var_dump("record credit limit failed");
            } elseif (!$this->franchiseeSaleModel->update($franchisee_sale['id'], $values)) {
                $db->transRollback();
                var_dump("update franchisee sale failed");
            } else {
                $db->transCommit();
            } 
        }

        if ($franchisee_sale['fs_status'] == 'invoiced' && !$this->_record_inventory($franchisee_sale)) {
            $response = $this->fail(['response' => 'Failed to record inventory.', 'status' => 'error']);
            $db->transRollback();
        }

        $db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete franchisee_sales
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('franchisee_sales', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('franchisee_sale_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$franchisee_sale = $this->franchiseeSaleModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_sale not found');
        } elseif (!$this->_attempt_delete($franchisee_sale, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete franchisee_sale.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'franchisee_sale deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search franchisee_sales based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('franchisee_sales', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchise_sale_id       = $this->request->getVar('franchisee_sale_id');
        $franchisee_id           = $this->request->getVar('franchisee_id') ?? null;
        $sales_date_from         = $this->request->getVar('sales_date_from') ?? null;
        $sales_date_to           = $this->request->getVar('sales_date_to') ?? null;
        $delivery_date_from      = $this->request->getVar('delivery_date_from') ?? null;
        $delivery_date_to        = $this->request->getVar('delivery_date_to') ?? null;
        $order_request_date_from = $this->request->getVar('order_request_date_from') ?? null;
        $order_request_date_to   = $this->request->getVar('order_request_date_to') ?? null;
        $seller_branch_id        = $this->request->getVar('seller_branch_id') ?? null;
        $buyer_branch_id         = $this->request->getVar('buyer_branch_id') ?? null;
        $sales_invoice_no        = $this->request->getVar('sales_invoice_no') ?? null;
        $dr_no                   = $this->request->getVar('dr_no') ?? null;
        $charge_invoice_no       = $this->request->getVar('charge_invoice_no') ?? null;
        $collections_invoice_no  = $this->request->getVar('collections_invoice_no') ?? null;
        $address                 = $this->request->getVar('address') ?? null;
        $remarks                 = $this->request->getVar('remarks') ?? null;
        $sales_staff             = $this->request->getVar('sales_staff') ?? null;
        $payment_status          = $this->request->getVar('payment_status') ?? null;
        $status                  = $this->request->getVar('fs_status') ?? null;
        $fully_paid_on           = $this->request->getVar('fully_paid_on') ?? null;
        $franchisee_name         = $this->request->getVar('franchisee_name') ?? null;
        $anything                = $this->request->getVar('anything') ?? null;
        $id                      = $this->request->getVar('id') ?? null;

        if (!$franchisee_sales = $this->franchiseeSaleModel->search($franchise_sale_id, $franchisee_id, $franchisee_name, $sales_date_from, $sales_date_to, $delivery_date_from, $delivery_date_to, $order_request_date_from, $order_request_date_to, $seller_branch_id, $buyer_branch_id, $sales_invoice_no, $dr_no, $charge_invoice_no, $collections_invoice_no, $address, $remarks, $sales_staff, $payment_status, $status, $fully_paid_on, $anything, $id)) {
            $response = $this->failNotFound('No franchisee_sale found');
        } else {
            $summary = [
                'total' => 0,
                'total_paid_amount' => 0,
                'total_balance' => 0,
            ];

            foreach ($franchisee_sales as $key => $franchisee_sale) {
                $franchisee_sale_payments = $this->franchiseeSalePaymentModel->get_details_by_franchisee_sales_id($franchisee_sale['id']);
                $franchisee_sales[$key]['franchisee_sale_payments'] = $franchisee_sale_payments;
                $summary['total'] += $franchisee_sale['grand_total'];
                $summary['total_paid_amount'] += $franchisee_sale['paid_amount'];
                $summary['total_balance'] += $franchisee_sale['balance'];
            }

            $response = $this->respond([
                'summary' => $summary,
                'data'    => $franchisee_sales,
                'status'  => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record status change
     */
    public function record_status_change()
    {
        if (($response = $this->_api_verification('franchisee_sales', 'record_status_change')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchisee_sale_id = $this->request->getVar('franchisee_sale_id');
        $status             = $this->request->getVar('status');

        if (!$franchisee_sale = $this->franchiseeSaleModel->get_details_by_id($franchisee_sale_id)) {
            $response = $this->failNotFound('franchisee_sale not found');
        } elseif (!$this->_attempt_record_status_change($franchisee_sale[0], $status)) {
            $response = $this->fail(['response' => 'Failed to record status change.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Status change recorded successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * 
     */
      /**
     * Close overpaid franchisee_sale
     */
    public function close_overpaid_franchisee_sale()
    {
        if (($response = $this->_api_verification('franchisee_sale', 'close_overpaid_franchisee_sale')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchisee_sale_id = $this->request->getVar('franchisee_sale_id');
        $where = ['id' => $franchisee_sale_id, 'is_deleted' => 0];

        if (!$franchisee_sale = $this->franchiseeSaleModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee_sale not found');
        } elseif (!$this->_attempt_close_overpaid_franchisee_sale($franchisee_sale)) {
            $response = $this->respond(['response' => 'franchisee_sale not closed successfully.']);
        } else {
            $response = $this->respond(['response' => 'franchisee_sale closed successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // Private methods
    // --------------------------------------------------------------------

    /**
     * Create franchisee_sales
     */
    private function _attempt_create()
    {
        $values = [
            'franchisee_id'         => $this->request->getVar('franchisee_id'),
            'sales_date'            => $this->request->getVar('sales_date'),
            'delivery_date'         => $this->request->getVar('delivery_date'),
            'delivery_fee'          => $this->request->getVar('delivery_fee'),
            'service_fee'           => $this->request->getVar('service_fee'),
            'franchise_order_no'    => $this->request->getVar('franchise_order_no'),
            'transfer_slip_no'      => $this->request->getVar('transfer_slip_no'),
            'order_request_date'    => $this->request->getVar('order_request_date'),
            'seller_branch_id'      => $this->request->getVar('seller_branch_id'),
            'buyer_branch_id'       => $this->request->getVar('buyer_branch_id'),
            'sales_invoice_no'      => $this->request->getVar('sales_invoice_no'),
            'dr_no'                 => $this->request->getVar('dr_no'),
            'ship_via'              => $this->request->getVar('ship_via'),
            'charge_invoice_no'     => $this->request->getVar('charge_invoice_no'),
            'collection_invoice_no' => $this->request->getVar('collection_invoice_no'),
            'address'               => $this->request->getVar('address'),
            'remarks'               => $this->request->getVar('remarks'),
            'sales_staff'           => $this->request->getVar('sales_staff'),
            'grand_total'           => $this->request->getVar('grand_total'),
            'balance'               => 0,
            'paid_amount'           => 0,
            'payment_status'        => 'processing',
            'added_by'              => $this->requested_by,
            'added_on'              => date('Y-m-d H:i:s'),
        ];

        if (!$franchisee_sale_id = $this->franchiseeSaleModel->insert($values))
           return false;

        return $franchisee_sale_id;
    }

    /**
     * Update franchisee_sales
     */
    protected function _attempt_generate_franchisee_sale_items($franchisee_sale_id, $db)
    {
        $item_ids   = $this->request->getVar('item_ids') ?? [];
        // Check if there are duplicate item_ids
        if (count($item_ids) !== count(array_unique($item_ids))) {
            $this->errorMessage = 'Duplicate item_ids found.';
            return false;
        }

        $item_names = $this->request->getVar('item_names') ?? [];
        $units      = $this->request->getVar('units') ?? [];
        $prices     = $this->request->getVar('prices') ?? [];
        $quantities = $this->request->getVar('quantities') ?? [];
        $discounts  = $this->request->getVar('discounts') ?? [];

        $values = [
            'franchisee_sale_id' => $franchisee_sale_id,
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        $seller_branch_id = $this->request->getVar('seller_branch_id');
        $buyer_branch_id  = $this->request->getVar('buyer_branch_id');
        $grand_total = 0;
        foreach ($item_ids as $key => $item_id) {
            $subtotal = $prices[$key] * $quantities[$key];
            $subtotal = $subtotal - $discounts[$key];

            // checks if it is an item in case an item_name was passed
            $item_id = $item_id == 'null' ? null : $item_id;
            if ($item_id && !$item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($seller_branch_id, $item_id, $units[$key])) {
                $this->errorMessage = `item unit not found: {$seller_branch_id} - {$item_id} - {$units[$key]}`;
                return false;
            }

            $item_unit = $item_id ? $item_unit[0] : null;
            $item_unit_id = $item_unit ? $item_unit['id'] : null;

            // check if the item_name key exist
            if (array_key_exists($key, $item_names)) {
                $values['item_name'] = $item_names[$key];
            } else {
                $values['item_name'] = null;
            }
        
            $values['item_id']      = $item_id;
            $values['item_unit_id'] = $item_unit_id;
            $values['unit']         = $units[$key];
            $values['price']        = $prices[$key];
            $values['qty']          = $quantities[$key];
            $values['discount']     = $discounts[$key];
            $values['subtotal']     = $subtotal;
            $values['status']       = 'processed';

            $grand_total += $values['subtotal'];

            if (!$this->franchiseeSaleItemModel->insert_on_duplicate($values, $this->requested_by, $db)) {
                $this->errorMessage = $db->error()['message'];
                return false;
            }
        }

        $grand_total = $grand_total + (float)$this->request->getVar('service_fee') + (float)$this->request->getVar('delivery_fee');
        $values = [
            'grand_total' => $grand_total,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];
        
        if ($franchise_sale = $this->franchiseeSaleModel->get_details_by_id($franchisee_sale_id)) {
            $values['balance'] = $grand_total - $franchise_sale[0]['paid_amount'];
        } else {
            $values['balance'] = $grand_total;
        }

        // Check if balance is greater than 0
        if ($values['balance'] > 0) {
            $values['payment_status'] = 'open_bill';
        } else {
            $values['payment_status'] = 'closed_bill';
        }

        if (!$this->franchiseeSaleModel->update($franchisee_sale_id, $values)) {
            $this->errorMessaage = $db->error()['message'];
            return false;
        }

        return $grand_total;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($franchisee_sale_id)
    {
        $values = [
            'franchisee_id'         => $this->request->getVar('franchisee_id'),
            'sales_date'            => $this->request->getVar('sales_date'),
            'delivery_date'         => $this->request->getVar('delivery_date'),
            'delivery_fee'          => $this->request->getVar('delivery_fee'),
            'service_fee'           => $this->request->getVar('service_fee'),
            'franchise_order_no'    => $this->request->getVar('franchise_order_no'),
            'transfer_slip_no'      => $this->request->getVar('transfer_slip_no'),
            'order_request_date'    => $this->request->getVar('order_request_date'),
            'seller_branch_id'      => $this->request->getVar('seller_branch_id'),
            'buyer_branch_id'       => $this->request->getVar('buyer_branch_id'),
            'sales_invoice_no'      => $this->request->getVar('sales_invoice_no'),
            'dr_no'                 => $this->request->getVar('dr_no'),
            'ship_via'              => $this->request->getVar('ship_via'),
            'charge_invoice_no'     => $this->request->getVar('charge_invoice_no'),
            'collection_invoice_no' => $this->request->getVar('collection_invoice_no'),
            'address'               => $this->request->getVar('address'),
            'remarks'               => $this->request->getVar('remarks'),
            'sales_staff'           => $this->request->getVar('sales_staff'),
            'grand_total'           => $this->request->getVar('grand_total'),
            'updated_by'            => $this->requested_by,
            'updated_on'            => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeSaleModel->update($franchisee_sale_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt to revert and delete franchise sale items
     */
    protected function _revert_franchisee_item($franchisee_sale_id, $seller_branch_id, $buyer_branch_id)
    {
        // revert the balance and grand total
        $franchisee_sale_items = $this->franchiseeSaleItemModel->get_details_by_franchisee_sales_id($franchisee_sale_id);

        foreach ($franchisee_sale_items as $franchisee_sale_item) {
            if ($item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($seller_branch_id, $franchisee_sale_item['item_id'], $franchisee_sale_item['unit'])) {
                if ($seller_inventory = $this->inventoryModel->get_inventory_detail($franchisee_sale_item['item_id'], $seller_branch_id, $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $seller_inventory[0]['current_qty'] + $franchisee_sale_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];

                    if (!$this->inventoryModel->update($seller_inventory[0]['id'], $new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                }

                if ($buyer_inventory = $this->inventoryModel->get_inventory_detail($franchisee_sale_item['item_id'], $buyer_branch_id, $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $buyer_inventory[0]['current_qty'] - $franchisee_sale_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];

                    if (!$this->inventoryModel->update($buyer_inventory[0]['id'], $new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                }
            }
         }

        return true;
    }
    
    /**
     * Attempt generate franchisee sales items
     */
    protected function _attempt_update_franchisee_sale_items($franchisee_sale, $db)
    {
        $seller_branch_id = $this->request->getVar('seller_branch_id');
        $buyer_branch_id  = $this->request->getVar('buyer_branch_id');
        $old_grand_total  = $franchisee_sale['grand_total'];

        if ($franchisee_sale['fs_status'] == 'invoiced' && !$this->_revert_franchisee_item($franchisee_sale['id'], $seller_branch_id, $buyer_branch_id)) {
            var_dump("failed to revert and delete");
            return false;
        }

        if (!$this->franchiseeSaleItemModel->delete_by_franchisee_sale_id($franchisee_sale['id'], $this->requested_by, $db)) {
            var_dump("failed to delete franchisee sale item model");      
            return false;
        }

        // Reset the credit limit
        if ($franchisee_sale['fs_status'] != 'quoted' && !$new_credit = $this->_restore_credit_limit($franchisee_sale)) {
            var_dump("failed to restore credit limit");
            return false;
        }

        // insert new franchisee sale items
        if (!$grand_total = $this->_attempt_generate_franchisee_sale_items($franchisee_sale['id'], $db)) {
            var_dump("Error in generating franchisee sale items");
            return false;
        }

        // Check if new grand total is under credit limit
        if ($franchisee_sale['fs_status'] != 'quoted' && $new_credit < $grand_total && $old_grand_total != $grand_total) {
            var_dump("New grand total is over the credit limit");
            return false;
        }

        // Record the new credit limit
        if ($franchisee_sale['fs_status'] != 'quoted' && !$this->_record_credit_limit($franchisee_sale['franchisee_id'], $grand_total)) {
            var_dump("record credit limit failed");
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($franchisee_sale, $db)
    {
        $seller_branch_id = $franchisee_sale['seller_branch_id'];
        $buyer_branch_id  = $franchisee_sale['buyer_branch_id'];

        if ($franchisee_sale['fs_status'] == 'invoiced' && !$this->_revert_franchisee_item($franchisee_sale['id'], $seller_branch_id, $buyer_branch_id)) {
            var_dump("failed to revert and delete");
            return false;
        } elseif (!$this->franchiseeSaleItemModel->delete_by_franchisee_sale_id($franchisee_sale['id'], $this->requested_by, $db)) {
            var_dump("failed to delete franchisee sale item model");
            return false;
        } elseif (($franchisee_sale['fs_status'] == 'invoiced' || $franchisee_sale['fs_status'] == 'processing') && 
                !$this->_restore_credit_limit($franchisee_sale)) {
            var_dump("failed to restore credit limit");
            return false;
        }

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeSaleModel->update($franchisee_sale['id'], $values))
            return false;

        return true;
    }

    /**
     * Restore credit limit
     */
    protected function _restore_credit_limit($franchisee_sale)
    {

        if (!$franchisee = $this->franchiseeModel->get_details_by_id($franchisee_sale['franchisee_id'])) {
            var_dump("failed to get franchisee");
            return false;
        }

        $franchisee = $franchisee[0];
        $new_values = [
            'current_credit_limit' => $franchisee['current_credit_limit'] + $franchisee_sale['grand_total'],
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s'),
        ];

        if (!$this->franchiseeModel->update($franchisee['id'], $new_values)) {
            var_dump("failed to update franchisee model");
            return false;
        }


        return $new_values['current_credit_limit'] ?? true;
    }

    /**
     * Attempt add payment
     */
    protected function _attempt_add_payment($franchisee_sale)
    {
        $db = \Config\Database::connect();
        
        $values = [
            'franchisee_id'      => $this->request->getVar('franchisee_id'),
            'franchisee_sale_id' => $franchisee_sale['id'],
            'payment_type'       => $this->request->getVar('payment_type'),
            'payment_date'       => $this->request->getVar('payment_date'),
            'remarks'            => $this->request->getVar('payment_remarks'),
            'from_bank_id'       => $this->request->getVar('from_bank_id'),
            'to_bank_name'       => $this->request->getVar('to_bank_name'),
            'to_bank_id'         => $this->request->getVar('to_bank_id'),
            'cheque_number'      => $this->request->getVar('cheque_number'),
            'cheque_date'        => $this->request->getVar('cheque_date'),
            'reference_number'   => $this->request->getVar('reference_number'),
            'transaction_number' => $this->request->getVar('transaction_number'),
            'payment_description'=> $this->request->getVar('payment_description'),
            'invoice_no'         => $this->request->getVar('invoice_no'),
            'term_day'           => $this->request->getVar('term_day'),
            'delivery_address'   => $this->request->getVar('delivery_address'),
            'delivery_date'      => $this->request->getVar('delivery_date'),
            'paid_amount'        => $this->request->getVar('paid_amount'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'subtotal'           => $this->request->getVar('subtotal'),
            'service_fee'        => $this->request->getVar('payment_service_fee'),
            'delivery_fee'       => $this->request->getVar('payment_delivery_fee'),
            'withholding_tax'    => $this->request->getVar('withholding_tax'),
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        // Check if the franchisee can create the order request based on the credit limit and paid amount
        $franchisee = $this->franchiseeModel->get_details_by_id($values['franchisee_id'])[0];
        $remaining_credit = $this->franchiseeModel->get_remaining_credit_by_franchisee_name($franchisee['name']);
        $current_credit_limit = $remaining_credit[0]['remaining_credit'];
        $paid_amount = $values['paid_amount'];
        $grand_total = $values['grand_total'];

        // Check if the franchisee can create the order request based on the credit limit and paid amount
        if (((float)$current_credit_limit + (float)$paid_amount) < (float)$grand_total && (float)$paid_amount != (float)$grand_total) {
            $db->close();
            var_dump("Exceed credit limit, grand total: $grand_total, paid amount: $paid_amount, current credit limit: $current_credit_limit");
            var_dump("Need to pay: " . ((float)$grand_total - ((float)$current_credit_limit + (float)$paid_amount)));
            return false;
        }

        if (!$franchisee_sale_payment_id = $this->franchiseeSalePaymentModel->insert($values)) {
            $db->transRollback();
            $db->close();
            var_dump("Failed to create franchisee sale payment.");
            return false;
        }
    
        // check if franchisee sale fs status is invoiced or processing
        // don't record anymore since it's already recorded during item update
        if ($franchisee_sale['fs_status'] != 'invoiced' && $franchisee_sale['fs_status'] != 'processing') {
            if (!$this->_record_credit_limit($values['franchisee_id'], $grand_total)) {
                $db->transRollback();
                $db->close();
                var_dump("record credit limit failed");
                return false;
            }
        }
        
        // Record the payment
        if (!$this->_record_sale_payment($franchisee_sale, $values)) {
            $db->transRollback();
            $db->close();
            var_dump("Failed to record sale payment.");
            return false;
        }

        $values = [
            'fs_status' => 'processing',
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeSaleModel->update($franchisee_sale['id'], $values)) {
            $db->transRollback();
            $db->close();
            var_dump("Failed to update franchisee sale status.");
            return false;
        }

        $db->transCommit();
        $db->close();
        
        return $franchisee_sale_payment_id;
    }

    /**
     * Record Frachisee Sale Payment
     */
    protected function _record_sale_payment($franchisee_sale, $values) {
        $update_values = [
            'balance'     => $franchisee_sale['balance'] - $values['paid_amount'],
            'paid_amount' => $franchisee_sale['paid_amount'] + $values['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($update_values['balance'] <= 0) {
            $update_values['payment_status'] = 'closed_bill';
            $update_values['fully_paid_on']  = date('Y-m-d H:i:s');
        } else {
            $update_values['payment_status'] = 'open_bill';
        }

        if (!$this->franchiseeSaleModel->update($franchisee_sale['id'], $update_values))
            return false;

        if (!$this->_record_credit_limit($franchisee_sale['franchisee_id'], (float)$values['paid_amount'] * -1)) {
            var_dump("Failed to increase credit limit.");
            return false;
        }

        return true;
    }

    /**
     * Attempt change status
     */
    protected function _attempt_record_status_change($franchisee_sale, $status)
    {
        $db = \Config\Database::connect();

        $values = [
            'franchisee_sale_id' => $franchisee_sale['id'],
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        switch ($status) {
            case 'invoiced':
                $values['fs_status'] = 'invoiced';
                if ($franchisee_sale['fs_status'] != 'invoiced' && !$this->_record_inventory($franchisee_sale)) {
                    var_dump("Failed to record inventory.");
                    return false;
                }
                break;
            case 'processing':
                $values['fs_status'] = 'processing';
                if ($franchisee_sale['fs_status'] == 'invoiced') {

                    $seller_branch_id = $franchisee_sale['seller_branch_id'];
                    $buyer_branch_id  = $franchisee_sale['buyer_branch_id'];

                    if (!$this->_revert_franchisee_item($franchisee_sale['id'], $seller_branch_id, $buyer_branch_id)) {
                        var_dump("failed to revert and delete");
                        return false;
                    }
                }
                break;
            default:
                return false;
        }

        if (!$this->franchiseeSaleModel->update($franchisee_sale['id'], $values)) {
            $db->transRollback();
            var_dump("Failed to update franchisee sale status.");
            return false;
        }

        $db->transCommit();
        $db->close();
        return true;
    }

    /**
     * record inventory 
     */
    private function _record_inventory($franchisee_sale)
    {
        $franchisee_sale_items = $this->franchiseeSaleItemModel->get_details_by_franchisee_sales_id($franchisee_sale['id']);
        foreach ($franchisee_sale_items as $franchisee_sale_item) {
            if (!$franchisee_sale_item['item_id']) {
                continue;
            }

            if ($item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($franchisee_sale['seller_branch_id'], $franchisee_sale_item['item_id'], $franchisee_sale_item['unit'])) {
                if ($seller_inventory = $this->inventoryModel->get_inventory_detail($franchisee_sale_item['item_id'], $franchisee_sale['seller_branch_id'], $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $seller_inventory[0]['current_qty'] - $franchisee_sale_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->update($seller_inventory[0]['id'], $new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                } else {
                    $new_values = [
                        'item_id'       => $franchisee_sale_item['item_id'],
                        'branch_id'     => $franchisee_sale['seller_branch_id'],
                        'item_unit_id'  => $item_unit[0]['id'],
                        'beginning_qty' => 0,
                        'current_qty'   => (float)$franchisee_sale_item['qty'] * -1,
                        'added_by'      => $this->requested_by,
                        'added_on'      => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->insert($new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                }
    
                if ($buyer_inventory = $this->inventoryModel->get_inventory_detail($franchisee_sale_item['item_id'], $franchisee_sale['buyer_branch_id'], $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $buyer_inventory[0]['current_qty'] + $franchisee_sale_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->update($buyer_inventory[0]['id'], $new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                } else {
                    $new_values = [
                        'item_id'       => $franchisee_sale_item['item_id'],
                        'branch_id'     => $franchisee_sale['buyer_branch_id'],
                        'item_unit_id'  => $item_unit[0]['id'],
                        'beginning_qty' => 0,
                        'current_qty'   => $franchisee_sale_item['qty'],
                        'added_by'      => $this->requested_by,
                        'added_on'      => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->insert($new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Record credit limit base on franchisee id
     */
    protected function _record_credit_limit($franchisee_id, $grand_total)
    {
        // var_dump("Grand total: " . $grand_total);
        if (!$franchisee = $this->franchiseeModel->get_details_by_id($franchisee_id))
            return false;
        
        $franchisee = $franchisee[0];

        $values = [
            'current_credit_limit' => $franchisee['current_credit_limit'] - $grand_total,
            'updated_by'           => $this->requested_by,
            'updated_on'           => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeModel->update($franchisee_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt close overpaid franchisee sales
     */
    private function _attempt_close_overpaid_franchisee_sale($franchisee_sale) {
        $value = [
            'is_closed'  => 1,
            'remarks'    => $franchisee_sale['remarks'] . ' - ' . $this->request->getVar('remarks'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeSaleModel->update($franchisee_sale['id'], $value)) {
            $db->transRollback();
            $db->close();
            var_dump("Error updating franchisee_sale");
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->franchiseeSaleModel        = model('App\Models\Franchisee_sale');
        $this->franchiseeSaleItemModel    = model('App\Models\Franchisee_sale_item');
        $this->franchiseeSalePaymentModel = model('App\Models\Franchisee_sale_payment');
        $this->franchiseeModel            = model('App\Models\Franchisee');
        $this->itemUnitModel              = model('App\Models\Item_unit');
        $this->inventoryModel             = model('App\Models\Inventory');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}
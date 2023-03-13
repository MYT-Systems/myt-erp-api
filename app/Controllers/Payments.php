<?php

namespace App\Controllers;

class Payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get payment
     */
    public function get_payment()
    {
        if (($response = $this->_api_verification('payments', 'get_payment')) !== true)
            return $response;

        $payment_id = $this->request->getVar('payment_id') ? : null;
        $payment    = $payment_id ? $this->paymentModel->get_details_by_id($payment_id) : null;

        if (!$payment) {
            $response = $this->failNotFound('No payment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all payments
     */
    public function get_all_payment()
    {
        if (($response = $this->_api_verification('payments', 'get_all_payment')) !== true)
            return $response;

        $payments = $this->paymentModel->get_all_payment();

        if (!$payments) {
            $response = $this->failNotFound('No payment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create payment
     */
    public function create()
    {
        if (($response = $this->_api_verification('payments', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$payment_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create payment.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'   => 'Payment created successfully.', 
                'status'     => 'success',
                'payment_id' => $payment_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    /**
     * Delete payments
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('payments', 'delete')) !== true)
            return $response;

        $payment_id = $this->request->getVar('payment_id');

        $where = ['id' => $payment_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$payment = $this->paymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('payment not found');
        } elseif (!$this->_attempt_delete($payment_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete payment.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Payment deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search payments based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('payments', 'search')) !== true)
            return $response;

        $name          = $this->request->getVar('name');
        $template_name = $this->request->getVar('template_name');

        if (!$payments = $this->paymentModel->search($name, $template_name)) {
            $response = $this->failNotFound('No payment found');
        } else {;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function test()
    {
        $this->_record_inventory(3);

        return $this->respond("test");
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create payment
     * @return int
     */
    protected function _attempt_create()
    {
        $order_id            = $this->request->getVar('order_id') ? : null;
        $branch_id           = $this->request->getVar('branch_id') ? : null;
        $grand_total         = (float)$this->request->getVar('grand_total');
        $transaction_type_id = $this->request->getVar('transaction_type_id');
        
        $percentage = 0;
        if (!$order = $this->orderModel->get_details_by_id($order_id))
            return $this->failNotFound('No order found');
        elseif (!$percentage = $this->discountModel->get_discount_by_id($order[0]['discount_id']))
            return $this->failNotFound('No discount found');
        
        $commission  = $this->branchCommissionModel->get_commission($transaction_type_id, $branch_id) ? : 0;

        $values = [
            'branch_id'           => $branch_id,
            'order_id'            => $order_id,
            'transaction_type_id' => $this->request->getVar('transaction_type_id'),
            'transaction_no'      => $this->request->getVar('transaction_no'),
            'payment_type'        => $this->request->getVar('payment_type'),
            'paid_amount'         => $this->request->getVar('paid_amount'),
            'subtotal'            => $this->request->getVar('subtotal'),       
            'discount'            => $this->request->getVar('discount'),
            'grand_total'         => $this->request->getVar('grand_total'),
            'commission'          => $commission * $grand_total,
            'remarks'             => $this->request->getVar('remarks'),
            'acc_no'              => $this->request->getVar('acc_no'),
            'cvc_cvv'             => $this->request->getVar('cvc_cvv'),
            'card_type'           => $this->request->getVar('card_type'),
            'card_expiry'         => $this->request->getVar('card_expiry'),
            'card_bank'           => $this->request->getVar('card_bank'),
            'proof'               => $this->request->getVar('proof'),
            'or_no'               => $this->request->getVar('or_no'),            
            'added_by'            => $this->requested_by,
            'added_on'            => date('Y-m-d H:i:s'),
        ];

        if (!$payment_id = $this->paymentModel->insert($values)) {
            return false;
        }

        // Record the items in the daily inventory record by getting the order items using order_id
        // if (!$this->_record_inventory($order_id)) {
        //     var_dump("Failed to record inventory");
        //     return false;
        // }

        return $payment_id;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($payment_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->paymentModel->update($payment_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Record inventory
     */
    protected function _record_inventory($order_id)
    {
        if (!$order = $this->orderModel->get_details_by_id($order_id)) {
            var_dump("No order found");
            return false;
        }

        // 1. Check if the daily inventory was initialized for the current date
        // --- If not, initialize the daily inventory record for the current date
        // --- If yes, do nothing
        $date = date('Y-m-d');
        $branch_id = $order[0]['branch_id'];

        if (!($daily_inventory = $this->dailyInventoryModel->is_initialized($date, $branch_id)) && 
             ($daily_inventory = !$this->dailyInventoryModel->initialize($date, $branch_id, $this->requested_by))) {
            var_dump("Failed to initialize daily inventory");
            return false;
        }
        
        // 2. Get the order details of the order and loop through each item
        $order_details = $order ? $this->orderDetailModel->get_details_by_order_id($order_id) : null;
        foreach ($order_details as $key => $order_detail) {
            if (!$this->_record_product_items($order_detail['product_id'], $order_id, $branch_id, $daily_inventory, $order_detail['qty'])) {
                var_dump("Failed to record product items");
                return false;
            }

            //Get the add_ons
            $order_product_details = $this->orderProductDetailModel->get_details_by_order_detail_id($order_detail['id']);

            foreach ($order_product_details as $key => $order_product_detail) {
                if (!$this->_record_product_items($order_product_detail['addon_id'], $order_id, $branch_id, $daily_inventory, $order_product_detail['qty'])) {
                    var_dump("Failed to record product items");
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Record product items
     */
    protected function _record_product_items($product_id, $order_id, $branch_id, $daily_inventory, $quantity)
    {
        // Get the items that is under product
        $product_items = $this->productItemModel->get_details_by_product_id($product_id);
        foreach ($product_items as $key2 => $product_item) {
            if (!$item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($branch_id, $product_item['item_id'], null, $product_item['unit'])) {
                var_dump("branch_id: " . $branch_id . " " . "item_id: " . $product_item['item_id'] . " " . "unit: " . $product_item['unit']);
                var_dump("Failed to get item unit");
            }

            // 2. Check if the item is already recorded in the daily inventory item record
            // --- If yes, update the quantity
            // --- If no, insert the item in the daily inventory record

            if ($di_item = $this->diItemModel->is_recorded($product_item['item_id'], $item_unit[0]['id'],'breakdown', $product_item['unit'], $daily_inventory[0]['id'], 'purchase')) {
                var_dump("Item is already recorded");
            } else {
                $values = [
                    'order_id'           => $order_id,
                    'item_id'            => $product_item['item_id'],
                    'item_unit_id'       => $item_unit[0]['id'],
                    'unit_type'          => 'breakdown',
                    'unit_name'          => $product_item['unit'],
                    'daily_inventory_id' => $daily_inventory[0]['id'],
                    'inventory_type'     => 'purchase',
                ];

                if (!$di_item_id = $this->diItemModel->insert($values)) {
                    var_dump("Failed to insert item in daily inventory");
                    return false;
                }

                if (!$di_item = $this->diItemModel->get_details_by_id($di_item_id)) {
                    var_dump("Failed to get daily inventory item");
                    return false;
                }
            }

            // 3. Update the quantity of the item in the daily inventory item record
            $values = [
                'breakdown_unit_qty' => $di_item[0]['breakdown_unit_qty'] + ($product_item['qty'] * $quantity),
                'inventory_unit_qty' => $di_item[0]['inventory_unit_qty'] + (($product_item['qty'] * $quantity) / $item_unit[0]['breakdown_value']),
                'total_cost'         => $di_item[0]['total_cost'] + (($product_item['qty'] * $quantity) * $item_unit[0]['price']),
                'updated_by'         => $this->requested_by,
                'updated_on'         => date('Y-m-d H:i:s')
            ];

            if (!$this->diItemModel->update($di_item[0]['id'], $values)) {
                var_dump("Failed to update item");
                return false;
            }

            // 4. Insert the item in the daily inventory item breakdown record
            $values = [
                'di_item_id' => $di_item[0]['id'],
                'qty'        => ($product_item['qty'] * $quantity),
                'total_cost' => ($product_item['qty'] * $quantity) * $item_unit[0]['price'],
            ];

            if (!$this->diItemBreakdownModel->insert($values)) {
                var_dump("Failed to insert item detail");
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
        $this->paymentModel            = model('App\Models\Payment');
        $this->discountModel           = model('App\Models\Discount');
        $this->orderModel              = model('App\Models\Order');
        $this->orderDetailModel        = model('App\Models\Order_detail');
        $this->productItemModel        = model('App\Models\Product_item');
        $this->orderProductDetailModel = model('App\Models\Order_product_detail');
        $this->branchCommissionModel   = model('App\Models\Branch_commission');
        $this->dailyInventoryModel     = model('App\Models\Daily_inventory');
        $this->diItemModel             = model('App\Models\DI_item');
        $this->itemUnitModel           = model('App\Models\Item_unit');
        $this->diItemBreakdownModel    = model('App\Models\DI_item_breakdown');
        $this->webappResponseModel     = model('App\Models\Webapp_response');
    }
}

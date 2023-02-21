<?php

namespace App\Controllers;

class Orders extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get order
     */
    public function get_order()
    {
        if (($response = $this->_api_verification('orders', 'get_order')) !== true)
            return $response;

        $order_id = $this->request->getVar('order_id') ? : null;

        $order         = $order_id ? $this->orderModel->get_details_by_id($order_id) : null;
        $order_detail  = $order_id ? $this->orderDetailModel->get_details_by_order_id($order_id) : null;
        $payment       = $order_id ? $this->paymentModel->get_details_by_order_id($order_id) : null;
        
        if (!$order) {
            $response = $this->failNotFound('No order found');
        } else {
            $summary = [
                'number_of_items' => 0,
            ];
            foreach ($order_detail as $key => $value) {
                $summary['number_of_items'] += $value['qty'];
                $order_detail[$key]['product_detail'] = $this->orderProductDetailModel->get_details_by_order_detail_id($value['id']);
            }
            $order[0]['payment'] = $payment;
            $order[0]['payment'][0]['discounts'] = $this->discountPaymentModel->get_details_by_payment_id($payment[0]['id']);
            $order[0]['payment'][0]['attachments'] = $order[0]['payment'] ? $this->paymentAttachmentModel->get_details_by_payment_id($order[0]['payment'][0]['id']) : null;
            $order[0]['order_detail'] = $order_detail;
            $response = $this->respond([
                'summary' => $summary,
                'data'    => $order,
                'status'  => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all orders
     */
    public function get_all_order()
    {
        if (($response = $this->_api_verification('orders', 'get_all_order')) !== true)
            return $response;

        $branch_id = $this->request->getVar('branch_id') ? : null;
        $orders = $this->orderModel->get_all_order($branch_id);

        if (!$orders) {
            $response = $this->failNotFound('No order found');
        } else {
            foreach ($orders as $key => $order) {
                $orders[$key]['payment'] = $this->paymentModel->get_details_by_order_id($order['id']) ? : null;
                $orders[$key]['payment'][0]['discounts'] = $orders[$key]['payment'] ? $this->discountPaymentModel->get_details_by_payment_id($orders[$key]['payment'][0]['id']) : null;
                $orders[$key]['order_detail'] = $this->orderDetailModel->get_details_by_order_id($order['id']);
                foreach ($orders[$key]['order_detail'] as $new_key => $order_detail) {
                    $orders[$key]['order_detail'][$new_key]['product_detail'] = $this->orderProductDetailModel->get_details_by_order_detail_id($order_detail['id']);
                }
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $orders,
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create order
     */
    public function create()
    {
        if (($response = $this->_api_verification('orders', 'create')) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$order_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage . ' Order creation failed');
        } elseif (!$this->_attempt_generate_order_detail($order_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage . ' Order detail creation failed');
        } elseif (!$this->_attempt_record_payment($order_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage . ' Payment creation failed');
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'   => 'success',
                'response' => 'Order created successfully', 
                'order_id' => $order_id
            ]);
        }

        $this->db->close();

        $order         = $order_id ? $this->orderModel->get_details_by_id($order_id) : null;
        $order_detail  = $order_id ? $this->orderDetailModel->get_details_by_order_id($order_id) : null;
        $payment       = $order_id ? $this->paymentModel->get_details_by_order_id($order_id) : null;
        
        if (!$order) {
            $response = $this->failNotFound('No order found');
        } else {
            $summary = [
                'number_of_items' => 0,
            ];
            foreach ($order_detail as $key => $value) {
                $summary['number_of_items'] += $value['qty'];
                $order_detail[$key]['product_detail'] = $this->orderProductDetailModel->get_details_by_order_detail_id($value['id']);
            }
            $order[0]['payment'] = $payment;
            $order[0]['payment'][0]['discounts'] = $this->discountPaymentModel->get_details_by_payment_id($payment[0]['id']);
            $order[0]['payment'][0]['attachments'] = $order[0]['payment'] ? $this->paymentAttachmentModel->get_details_by_payment_id($order[0]['payment'][0]['id']) : null;
            $order[0]['order_detail'] = $order_detail;
            $response = $this->respond([
                'summary' => $summary,
                'data'   => $order,
                'status' => 'success'
            ]);
        }
        
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete orders
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('orders', 'delete')) !== true)
            return $response;

        $order_id = $this->request->getVar('order_id');

        $where = ['id' => $order_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$order = $this->orderModel->select('', $where, 1)) {
            $response = $this->failNotFound('order not found');
        } elseif (!$this->_attempt_delete($order_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Order deleted successfully', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search orders based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('orders', 'search')) !== true)
            return $response;

        $branch_id        = $this->request->getVar('branch_id') ? : null;
        $branch_name      = $this->request->getVar('branch_name') ? : null;
        $added_on_from    = $this->request->getVar('added_on_from') ? : null;
        $added_on_to      = $this->request->getVar('added_on_to') ? : null;
        $group_orders     = $this->request->getVar('group_orders') ? : false;
        $transaction_type = $this->request->getVar('transaction_type') ? : null;
        $payment_type     = $this->request->getVar('payment_type') ? : null;

        if (!$orders = $this->orderModel->search($branch_id, $branch_name, $added_on_from, $added_on_to, $transaction_type, $group_orders)) {
            $response = $this->failNotFound('No order found');
        } else {
            $summary = [
                'number_of_items' => 0,
                'total_sales'     => 0,
            ];

            $transaction_types = ['food_panda'];

            foreach ($orders as $key => $order) {
                $orders[$key]['payment'] = $this->paymentModel->get_details_by_order_id($order['id'], $payment_type) ? : null;
                $orders[$key]['payment'][0]['discounts'] = $orders[$key]['payment'] ? $this->discountPaymentModel->get_details_by_payment_id($orders[$key]['payment'][0]['id']) : null;
                $orders[$key]['payment'][0]['attachments'] = $orders[$key]['payment'] ? $this->paymentAttachmentModel->get_details_by_payment_id($orders[$key]['payment'][0]['id']) : null;
                $orders[$key]['order_detail'] = $this->orderDetailModel->get_details_by_order_id($order['id']);
                $summary['total_sales'] += $orders[$key]['grand_total'];
                $total_number_of_items = 0;
                foreach ($orders[$key]['order_detail'] as $new_key => $order_detail) {
                    $total_number_of_items += $order_detail['qty'];
                    $orders[$key]['order_detail'][$new_key]['product_detail'] = $this->orderProductDetailModel->get_details_by_order_detail_id($order_detail['id']);
                }
                $orders[$key]['total_number_of_items'] = $total_number_of_items;
                $summary['number_of_items'] += $order_detail['qty'];
                $transaction_types[] = $order['transaction_type'];
            }

            $summary['transaction_types'] = array_unique($transaction_types);
            $summary['transaction_types'] = array_values($summary['transaction_types']);

            $response = $this->respond([
                'summary' => $summary,
                'status' => 'success',
                'data'   => $orders
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /*
    * Get computed discounts
    */
    public function compute_possible_discounts()
    {
        if (($response = $this->_api_verification('orders', 'compute_possible_discounts')) !== true)
            return $response;

        if (!$discounts = $this->_get_computed_discounts()) {
            $response = $this->failNotFound('No discount found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $discounts,
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create order
     */
    protected function _attempt_create()
    {
        $price_level_type_id = $this->request->getVar('price_level_type_id');
        if (!$transaction_type = $this->priceLevelTypeModel->get_details_by_id($price_level_type_id)) {
            $this->errorMessage = "Price Level Type not found";
            return false;
        }

        $transaction_type = $transaction_type[0];

        $grand_total = $this->request->getVar('grand_total') ?? 0;
        $paid_amount = $this->request->getVar('paid_amount') ?? 0;
        $branch_id = $this->request->getVar('branch_id');

        $values = [
            'branch_id'        => $this->request->getVar('branch_id'),
            'paid_amount'      => $paid_amount,
            'transaction_no'   => $branch_id . date("Ymd") . "-" . time(),
            'change'           => (float)$grand_total - (float)$paid_amount,
            'grand_total'      => $grand_total,
            'remarks'          => $this->request->getVar('remarks'),
            'gift_cert_code'   => $this->request->getVar('gift_cert_code'),
            'transaction_type' => $transaction_type['name'],
            'added_by'         => $this->requested_by,
            'added_on'         => date('Y-m-d H:i:s'),
        ];

        if (!$order_id = $this->orderModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $order_id;
    }

    /**
     * Attempt to record payment
     */
    protected function _attempt_record_payment($order_id) {
        $branch_id        = $this->request->getVar('branch_id') ? : null;
        $grand_total      = (float)$this->request->getVar('grand_total');
        $price_level_type_id = $this->request->getVar('price_level_type_id');
        
        $commission = $this->priceLevelModel->get_commission($price_level_type_id, $this->db);
        $commission = $commission ? $commission[0]['commission_rate'] : 0;

        $values = [
            'branch_id'           => $branch_id,
            'order_id'            => $order_id,
            'price_level_type_id' => $price_level_type_id,
            'transaction_no'      => $this->request->getVar('transaction_no'),
            'reference_no'        => $this->request->getVar('reference_no'),
            'payment_type'        => $this->request->getVar('payment_type'),
            'paid_amount'         => (float)$this->request->getVar('paid_amount'),
            'subtotal'            => (float)$this->request->getVar('subtotal'),       
            'grand_total'         => $grand_total,
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

        $is_gift_cert = $this->request->getVar('gift_cert_code') ? true : false;

        if (!$payment_id = $this->paymentModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        } elseif ($this->request->getFile('file') AND 
                !$this->_attempt_upload_file_base64($this->paymentAttachmentModel, ['payment_id' => $payment_id, 'type' => $is_gift_cert ? 'gift_cert' : 'payment'])) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_attempt_record_discounts($payment_id)) {
            return false;
        }

        return $payment_id;
    }


    /**
     * Attempt to record discounts
     */
    protected function _attempt_record_discounts($payment_id)
    {
        // Get the most expensive products
        $product_ids         = $this->request->getVar('product_ids') ?? [];
        $product_ids         = $product_ids ? explode(",", $product_ids) : [];
        $quantities          = $this->request->getVar('quantities') ?? [];
        $quantities          = $quantities ? explode(",", $quantities) : [];
        $price_level_type_id = $this->request->getVar('price_level_type_id');
        $price_level_id      = $this->request->getVar('price_level_id');
        $products = [];

        foreach ($product_ids as $key => $product_id) {
            $price = $this->priceLevelModel->get_price($product_id, $price_level_type_id, $price_level_id);
            $price = $price ? $price[0]['price'] : 0;
            $product = $this->productModel->get_details_by_id($product_id);
            $products[] = [
                'key'           => $key,
                'product_id'    => $product_id,
                'item_price'    => (float)$price,
                'quantity'      => (int)$quantities[$key],
                'product_name'  => $product[0]['name'] ?? ''
            ];
        }

        $discount_ids = $this->request->getVar('discount_ids') ? : [];
        $discount_ids = $discount_ids ? explode(",", $discount_ids) : [];
        $names        = $this->request->getVar('names') ? : null;
        $names        = $names ? explode(",", $names) : [];
        $id_no        = $this->request->getVar('id_nos') ? : null;
        $id_no        = $id_no ? explode(",", $id_no) : [];
        $values = [
            'payment_id' => $payment_id,
            'added_on' => date('Y-m-d H:i:s'),
            'added_by' => $this->requested_by,
        ];

        $total_discount = 0;
        foreach ($discount_ids as $key => $discount_id) {
            $discount   = $this->discountModel->get_discount_by_id($discount_id);
            $percentage = $discount ? (float)$discount[0]['percentage'] : 0;
            $percentage = $percentage / 100;

            // get the most expensive product
            $most_expensive_product = $this->_get_most_expensive_product($products);
            // mark the product as discounted
            $products[$most_expensive_product['key']]['quantity'] -= 1;

            $values['product_id']     = $most_expensive_product['product_id'];
            $values['product_name']   = $most_expensive_product['product_name'];
            $values['product_price']  = $most_expensive_product['item_price'];
            $values['savings']        = ((float)$most_expensive_product['item_price']/1.12) * $percentage;
            $values['discount_price'] = $most_expensive_product['item_price'] - $values['savings'];
            $values['discount_id']    = $discount_id;
            $values['name']           = $names[$key];
            $values['id_no']          = $id_no[$key];
            $values['percentage']     = $percentage;

            $total_discount += $values['savings'];

            if (!$this->discountPaymentModel->insert($values)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        $payment_values = [
            'discount' => $total_discount,
            'grand_total' => (float)$this->request->getVar('grand_total') - $total_discount,
        ];

        if (!$this->paymentModel->update($payment_id, $payment_values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Get the most expensive product
     */
    protected function _get_most_expensive_product($products)
    {
        $most_expensive_product = [
            'key'           => 0,
            'product_id'    => 0,
            'item_price'    => 0,
            'product_name'  => '',
            'is_discounted' => false,
        ];
        $most_expensive_price   = 0;
        foreach ($products as $product) {
            if ($product['item_price'] > $most_expensive_price && $product['quantity']) {
                $most_expensive_product = $product;
                $most_expensive_price   = $product['item_price'];
            }
        }

        return $most_expensive_product;
    }

    /**
     * Attempt generate order detail
     */
    protected function _attempt_generate_order_detail($order_id)
    {
        $branch_id           = $this->request->getVar('branch_id');
        $product_ids         = $this->request->getVar('product_ids') ?? [];
        $product_ids         = $product_ids ? explode(",", $product_ids) : [];
        $quantities          = $this->request->getVar('quantities') ?? [];
        $quantities          = $quantities ? explode(",", $quantities) : [];
        $remarks             = $this->request->getVar('order_detail_remarks') ?? [];
        $remarks             = $remarks ? explode(",", $remarks) : [];
        $price_level_type_id = $this->request->getVar('price_level_type_id');
        $price_level_id      = $this->request->getVar('price_level_id');

        foreach ($product_ids as $key => $product_id) {
            $price = $this->priceLevelModel->get_price($product_id, $price_level_type_id, $price_level_id);
            $price = $price ? $price[0]['price'] : 0;
            $quantity = $quantities[$key] ?? 0;

            $values = [
                'order_id'   => $order_id,
                'product_id' => $product_id,
                'price'      => $price,
                'qty'        => $quantity,
                'subtotal'   => (float)$price * (float)$quantity,
                'remarks'    => $remarks[$key],
                'added_on'   => date('Y-m-d H:i:s'),
                'added_by'   => $this->requested_by,
            ];

            if (!$order_detail_id = $this->orderDetailModel->insert_on_duplicate_update($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'] . ' ' . $this->db->getLastQuery();
                return false;
            } elseif (!$this->_subtract_inventory($order_detail_id, $product_id, $quantity, $branch_id, $key)) {
                return false;
            } elseif (!$this->_attempt_generate_order_product_detail($order_detail_id, $key, $branch_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Subtract inventory qty
     */
    protected function _subtract_inventory($order_detail_id, $product_id, $quantity, $branch_id, $index = false)
    {
        $addon_ids = $this->request->getVar('addon_ids_' . $index) ?? [];

        $where = [
            'product_id' => $product_id,
            'is_deleted' => 0
        ];

        if (!$product_ingredients = $this->productItemModel->select('', $where)) {
            $this->errorMessage = $this->db->error()['message'] . ' ' . $this->db->getLastQuery();
            return false;
        }

        $addon_requirements = [];

        foreach ($product_ingredients as $ingredient) {

            $ingredient['item_id'] = $this->_replace_product_item_based_on_addon($ingredient, $addon_ids);

            if (($item_unit_details = $this->_convert_units($ingredient, $quantity)) === false)
                return false;

            $inventory_condition = [
                'branch_id' => $branch_id,
                'item_id' => $ingredient['item_id'],
                'item_unit_id' => $item_unit_details['item_unit_id'],
                'is_deleted' => 0
            ];

            if (!$inventory_details = $this->inventoryModel->select('', $inventory_condition, 1)) {
                $this->errorMessage = $this->db->error()['message'] . ' ' . $this->db->getLastQuery();
                return false;
            }

            $current_qty = $inventory_details['current_qty'] - $item_unit_details['final_qty'];

            $values = [
                'current_qty' => $current_qty,
                'updated_on' => date('Y-m-d H:i:s'),
                'updated_by' => $this->requested_by
            ];

            if (!$this->inventoryModel->update($inventory_details['id'], $values) OR
                !$this->_save_final_ingredient_used($order_detail_id, $product_id, $ingredient['item_id'], $current_qty, $inventory_details['unit'])
            ) {
                $this->errorMessage = $this->db->error()['message'] . ' ' . $this->db->getLastQuery();
                return false;
            }
        }
        return true;
    }

    protected function _save_final_ingredient_used($order_detail_id, $product_id, $item_id, $qty, $unit)
    {
        $values = [
            'order_detail_id' => $order_detail_id,
            'product_id' => $product_id,
            'item_id' => $item_id,
            'qty' => $qty,
            'unit' => $unit,
            'added_on' => date("Y-m-d H:i:s"),
            'added_by' => $this->requested_by
        ];

        if (!$this->orderDetailIngredModel->insert($values))
            return false;
    }

    protected function _replace_product_item_based_on_addon($ingredient, $addon_ids)
    {
        $final_item_id = $ingredient['item_id'];

        $addon_req_condition = [
            'product_item_id' => $ingredient['item_id'],
            'is_deleted' => 0
        ];
        $addon_requirements = $this->productAddonReqModel->select('', $addon_req_condition);
        if ($addon_requirements) {
            foreach ($addon_requirements as $addon_requirement) {
                if (in_array($addon_requirement['addon_id'], $addon_ids)) {
                    $final_item_id = $addon_requirement['item_id'];
                    break;
                }
            }
        }

        return $final_item_id;
    }

    /**
     * Convert units if necessary
     */
    protected function _convert_units($ingredient, $order_qty)
    {
        $item_unit_condition = [
            'item_id' => $ingredient['item_id'],
            'is_deleted' => 0
        ];

        if (!$item_unit = $this->itemUnitModel->select('', $item_unit_condition, 1)) {
            $this->errorMessage = $this->db->error()['message'] . ' ' . $this->db->getLastQuery();
            return false;
        }

        $final_qty = $ingredient['qty'] * $order_qty;

        if ($item_unit['breakdown_unit'] == $ingredient['unit'] AND $item_unit['breakdown_unit'] != $item_unit['inventory_unit']) {
            $final_qty = (($final_qty / $item_unit['breakdown_value']) * $item_unit['inventory_value']);
            $final_qty = str_replace(',', '', number_format($final_qty, 2));
        }

        return [
            'final_qty' => $final_qty,
            'item_unit_id' => $item_unit['id']
        ];
    }

    /**
     * Attempt generate order product detail
     */
    protected function _attempt_generate_order_product_detail($order_detail_id, $key, $branch_id)
    {
        $addon_ids           = $this->request->getVar('addon_ids') ?? [];
        $addon_ids           = $addon_ids ? explode("~", $addon_ids) : [];
        $addon_ids           = $addon_ids ? explode(",", $addon_ids[$key]) : [];

        $quantities          = $this->request->getVar('quantities_' . $key);
        $quantities          = $quantities ? explode("~", $quantities) : [];
        $quantities          = $quantities ? explode(",", $quantities[$key]) : [];

        $price_level_type_id = $this->request->getVar('price_level_type_id');
        $price_level_id      = $this->request->getVar('price_level_id');
        
        foreach ($addon_ids as $key => $addon_id) {
            if (!$addon_id) continue; // Skip if addon_id is empty
            $price = $this->priceLevelModel->get_price($addon_id, $price_level_type_id, $price_level_id);
            $price = $price ? $price[0]['price'] : 0;
            $quantity = $quantities[$key] ?? 0;

            $values = [
                'order_detail_id' => $order_detail_id,
                'addon_id'        => $addon_id,
                'price'           => $price,
                'qty'             => $quantity,
                'subtotal'        => (float)$price * (float)$quantity,
                'added_on'        => date('Y-m-d H:i:s'),
                'added_by'        => $this->requested_by,
            ];

            if (!$order_product_detail_id = $this->orderProductDetailModel->insert_on_duplicate_key_update($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            } elseif (!$this->_subtract_inventory($order_detail_id, $addon_id, $quantity, $branch_id)) {
                return false;
            } 
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($order_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->orderModel->update($order_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }
 
    /**
     * Get computed discounts
     */
    protected function _get_computed_discounts()
    {
        // Get the most expensive products
        $product_ids         = $this->request->getVar('product_ids') ?? [];
        $product_ids         = $product_ids ? explode(",", $product_ids) : [];
        $quantities          = $this->request->getVar('quantities') ?? [];
        $quantities          = $quantities ? explode(",", $quantities) : [];
        $prices              = $this->request->getVar('prices') ? : [];
        $prices              = $prices ? explode(",", $prices) : [];
        $add_on_ids          = $this->request->getVar('add_on_ids') ? : [];
        $add_on_ids          = $add_on_ids ? explode(",", $add_on_ids) : [];

        $products = [];
        foreach ($product_ids as $key => $product_id) {
            $products[] = [
                'key'           => $key,
                'product_id'    => $product_id,
                'item_price'    => (float)$prices[$key],
                'quantity'      => (int)$quantities[$key],
                'add_on_id'     => $add_on_ids[$key],
            ];
        }

        $discount_ids = $this->request->getVar('discount_ids') ? : [];
        $discount_ids = $discount_ids ? explode(",", $discount_ids) : [];
        $total_discount = 0;
        $discount_data = [];
        foreach ($discount_ids as $key => $discount_id) {
            $discount   = $this->discountModel->get_discount_by_id($discount_id);
            $percentage = $discount ? (float)$discount[0]['percentage'] : 0;
            $percentage = $percentage / 100;

            // get the most expensive product
            $most_expensive_product = $this->_get_most_expensive_product($products);
            // decrease the count so that it wont be included in the next iteration
            $products[$most_expensive_product['key']]['quantity'] = 0;
            // compute savings
            $savings = ((float)$most_expensive_product['item_price']/1.12) * $percentage;
            
            // check if product_id index is in discount_data
            if (!array_key_exists($most_expensive_product['product_id'] . '-' . $most_expensive_product['add_on_id'], $discount_data)) {
                $discount_data[$most_expensive_product['product_id'] . '-' . $most_expensive_product['add_on_id']] = [
                    'product_id'  => $most_expensive_product['product_id'],
                    'discount_id' => $discount_id,
                    'savings'     => $savings,
                    'add_on_id'   => $most_expensive_product['add_on_id'],
                    'discounted_prices' => $products[$most_expensive_product['key']]['item_price'] - $savings,
                ];
            } else {
                $discount_data[$most_expensive_product['product_id'] . '-' . $most_expensive_product['add_on_id']]['savings'] += $savings;
                $discount_data[$most_expensive_product['product_id'] . '-' . $most_expensive_product['add_on_id']]['discounted_prices'] += $products[$most_expensive_product['key']]['item_price'] - $savings;
            }

            $total_discount += $savings;
        }

        // remove the index in discount_data
        $discount_data = array_values($discount_data);

        $final_discount_data = [
            'total_discount' => $total_discount,
            'discount_data'  => $discount_data,
        ];

        return $final_discount_data;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->orderModel              = model('App\Models\Order');
        $this->orderDetailModel        = model('App\Models\Order_detail');
        $this->orderProductDetailModel = model('App\Models\Order_product_detail');
        $this->orderDetailIngredModel  = model('App\Models\Order_detail_ingredient');
        $this->priceLevelModel         = model('App\Models\Price_level');
        $this->paymentModel            = model('App\Models\Payment');
        $this->discountModel           = model('App\Models\Discount');
        $this->productItemModel        = model('App\Models\Product_item');
        $this->inventoryModel          = model('App\Models\Inventory');
        $this->branchCommissionModel   = model('App\Models\Branch_commission');
        $this->itemUnitModel           = model('App\Models\Item_unit');
        $this->discountPaymentModel    = model('App\Models\Discount_payment');
        $this->priceLevelTypeModel     = model('App\Models\Price_level_type');
        $this->paymentAttachmentModel  = model('App\Models\Payment_attachment');
        $this->productModel            = model('App\Models\Product');
        $this->productAddonReqModel    = model('App\Models\Product_addon_requirement');
        $this->webappResponseModel     = model('App\Models\Webapp_response');
    }
}

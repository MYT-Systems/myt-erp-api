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
        $this->errors = [];
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
     * Bulk create order
     */
    public function bulk_create()
    {
        if (($response = $this->_api_verification('orders', 'bulk_create')) !== true)
            return $response;

        $orders = $this->request->getVar('bulk_order');
        $filename = $this->_write_json('bulk_order', $orders);

        if ($filename === false) {
            $response = $this->fail('Something went wrong. Please try again.');
        }

        $response = $this->respond([
            'status' => 'success',
            'sync_time' => date("Y-m-d H:i:s")
        ]);

        // elseif (!$this->_attempt_bulk_create($filename)) {
        //     $response = $this->fail([
        //         'errors' => $this->errors
        //     ]);
        // } else {
        //     $response = $this->respond([
        //         'status' => 'success'
        //     ]);
        // }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    protected function _attempt_bulk_create($filename)
    {
        $upload_path = FCPATH . 'public/bulk_order/' . $filename;
        $json = file_get_contents($upload_path);
        $orders  = json_decode($json);

        $unsaved_orders = [];

        $this->db = \Config\Database::connect();

        foreach ($orders as $order) {
            $order = json_decode($order);
            $this->orders_payload = (array) $order;

            $this->db->transBegin();

            if (!$order_id = $this->_attempt_create()) {
                $this->db->transRollback();
                $unsaved_orders[] = $order;
                $this->errors[] = $this->errorMessage;
                continue;
            } elseif (!$this->_attempt_generate_order_detail($order_id)) {
                $this->db->transRollback();
                $unsaved_orders[] = $order;
                $this->errors[] = $this->errorMessage;
                continue;
            } elseif (!$this->_attempt_record_payment($order_id)) {
                $this->db->transRollback();
                $unsaved_orders[] = $order;
                $this->errors[] = $this->errorMessage;
                continue;
            }

            $this->db->transCommit();
            $this->orders_payload = null;
        }

        if ($unsaved_orders) {
            $write_response = $this->_write_json('bulk_order', $unsaved_orders);
            
            $old_file_path = FCPATH . 'public/bulk_order/' . $filename;
            unlink($old_file_path);
        
            return false;
        }
        
        $old_file_path = FCPATH . 'public/bulk_order/' . $filename;
        unlink($old_file_path);

        return true;
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

        $has_error = false;

        if (!$order_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage . ' Order creation failed');
            $has_error = true;
        } elseif (!$this->_attempt_generate_order_detail($order_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage . ' Order detail creation failed');
            $has_error = true;
        } elseif (!$this->_attempt_record_payment($order_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage . ' Payment creation failed');
            $has_error = true;
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'   => 'success',
                'response' => 'Order created successfully', 
                'order_id' => $order_id
            ]);
        }

        if ($has_error) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
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
     * Get total order amount per branch
     */
    public function sales_per_branch()
    {
        if (($response = $this->_api_verification('orders', 'sales_per_branch')) !== true)
            return $response;

        $branch_id        = $this->request->getVar('branch_id') ? : null;
        $branch_name      = $this->request->getVar('branch_name') ? : null;
        $added_on_from    = $this->request->getVar('added_on_from') ? : null;
        $added_on_to      = $this->request->getVar('added_on_to') ? : null;
        $transaction_type = $this->request->getVar('transaction_type') ? : null;
        $payment_type     = $this->request->getVar('payment_type') ? : null;

        if (!$orders = $this->orderModel->get_sales_per_branch($branch_id, $branch_name, $added_on_from, $added_on_to, $transaction_type)) {
            $response = $this->failNotFound('No order found');
        } else {
            $summary = [
                'total_sales'           => 0,
                'total_store_sales'     => 0,
                'total_foodpanda_sales' => 0,
                'total_grabfood_sales'  => 0
            ];

            foreach ($orders as $key => $order) {
                $summary['total_sales'] += $orders[$key]['grand_total'];
                $summary['total_store_sales'] += $orders[$key]['store_sales'];
                $summary['total_foodpanda_sales'] += $orders[$key]['foodpanda_sales'];
                $summary['total_grabfood_sales'] += $orders[$key]['grabfood_sales'];
            }

            $response = $this->respond([
                'summary' => $summary,
                'status' => 'success',
                'data'   => $orders
            ]);
        }

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
                'number_of_items'       => 0,
                'total_sales'           => 0
            ];

            $transaction_types = [];
            
            foreach ($orders as $key => $order) {
                $orders[$key]['payment'] = $this->paymentModel->get_details_by_order_id($order['id'], $payment_type) ? : null;

                if ($orders[$key]['payment']) {
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
                } else {
                    unset($orders[$key]);
                }
            }

            $summary['transaction_types'] = array_unique($transaction_types);
            $summary['transaction_types'] = array_values($summary['transaction_types']);

            $response = $this->respond([
                'summary' => $summary,
                'status' => 'success',
                'data'   => array_values($orders)
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
        $price_level_type_id = $this->_get_payload_value('price_level_type_id');
        if (!$transaction_type = $this->priceLevelTypeModel->get_details_by_id($price_level_type_id)) {
            $this->errorMessage = "Price Level Type not found";
            return false;
        }

        $transaction_type = $transaction_type[0];

        $grand_total = $this->_get_payload_value('grand_total', 0);
        $paid_amount = $this->_get_payload_value('paid_amount', 0);
        $branch_id = $this->_get_payload_value('branch_id');

        $values = [
            'branch_id'        => $branch_id,
            'offline_id'       => $this->_get_payload_value('id', null),
            'paid_amount'      => $paid_amount,
            'transaction_no'   => $branch_id . date("Ymd") . "-" . time(),
            'change'           => (float)$grand_total - (float)$paid_amount,
            'grand_total'      => $grand_total,
            'remarks'          => $this->_get_payload_value('remarks', null),
            'gift_cert_code'   => $this->_get_payload_value('gift_cert_code'),
            'transaction_type' => $transaction_type['name'],
            'added_by'         => $this->requested_by,
            'added_on'   => ($this->orders_payload AND array_key_exists('ordered_on', $this->orders_payload)) ? 
                                    $this->orders_payload['ordered_on'] : date('Y-m-d H:i:s'),
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
        $branch_id        = $this->_get_payload_value('branch_id', null);
        $grand_total      = (float) $this->_get_payload_value('grand_total', 0);
        $price_level_type_id = $this->_get_payload_value('price_level_type_id');
        
        $commission = $this->priceLevelModel->get_commission($price_level_type_id, $this->db);
        $commission = $commission ? $commission[0]['commission_rate'] : 0;

        $values = [
            'branch_id'           => $branch_id,
            'order_id'            => $order_id,
            'price_level_type_id' => $price_level_type_id,
            'transaction_no'      => $this->_get_payload_value('transaction_no'),
            'reference_no'        => $this->_get_payload_value('reference_no'),
            'payment_type'        => $this->_get_payload_value('payment_type'),
            'paid_amount'         => (float)$this->_get_payload_value('paid_amount'),
            'subtotal'            => (float)$this->_get_payload_value('subtotal'),
            'discount'            => (float)$this->_get_payload_value('discount'),
            'grand_total'         => $grand_total,
            'commission'          => $commission * $grand_total,
            'remarks'             => $this->_get_payload_value('remarks'),
            'acc_no'              => $this->_get_payload_value('acc_no'),
            'cvc_cvv'             => $this->_get_payload_value('cvc_cvv'),
            'card_type'           => $this->_get_payload_value('card_type'),
            'card_expiry'         => $this->_get_payload_value('card_expiry'),
            'card_bank'           => $this->_get_payload_value('card_bank'),
            'proof'               => $this->_get_payload_value('proof'),
            'or_no'               => $this->_get_payload_value('or_no'),
            'added_by'            => $this->requested_by,
            'added_on'   => ($this->orders_payload AND array_key_exists('ordered_on', $this->orders_payload)) ? 
                                    $this->orders_payload['ordered_on'] : date('Y-m-d H:i:s'),
        ];

        $is_gift_cert = $this->_get_payload_value('gift_cert_code') ? true : false;

        if (!$payment_id = $this->paymentModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        } elseif ($this->request->getFile('file') AND 
                !$this->_attempt_upload_file_base64($this->paymentAttachmentModel, ['payment_id' => $payment_id, 'type' => $is_gift_cert ? 'gift_cert' : 'payment'])) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        $discount_prices = $this->request->getVar('discount_prices') ? : null;
        if ($discount_prices) {
            if (!$this->_attempt_record_discounts($payment_id))
                return false;
        }

        return $payment_id;
    }


    /**
     * Attempt to record discounts
     */
    protected function _attempt_record_discounts($payment_id)
    {
        $product_ids     = $this->_get_payload_value('product_ids', []);
        $product_ids     = $product_ids ? explode(",", $product_ids) : [];
        $quantities          = $this->_get_payload_value('quantities', []);
        $quantities          = $quantities ? explode(",", $quantities) : [];

        $product_prices  = $this->_get_payload_value('product_prices', []);
        $product_prices  = $product_prices ? explode(",", $product_prices) : [];
        $discount_ids    = $this->_get_payload_value('discount_ids', []);
        $discount_ids    = $discount_ids ? explode(",", $discount_ids) : [];
        $names           = $this->_get_payload_value('names', []);
        $names           = $names ? explode(",", $names) : [];
        $id_no           = $this->_get_payload_value('id_no', []);
        $id_no           = $id_no ? explode(",", $id_no) : [];
        $discount_prices = $this->_get_payload_value('discount_prices', []);
        $discount_prices = $discount_prices ? explode(",", $discount_prices) : [];

        $discount_index = 0;
        foreach ($product_ids as $index => $product_id) {
            
            if ($discount_prices[$index] != "") {

                for ($i=0; $i < $quantities[$index]; $i++) {

                    $product = "";
                    if ($discount_ids[$discount_index] != "") {
    
                        $where = ['id' => $product_id, 'is_deleted' => 0];
                        $product_details = $this->productModel->select('', $where, 1);
                        $discount_price = $quantities[$index] <= 1 ? $discount_prices[$index] : round($discount_prices[$index] / $quantities[$index], 2);
            
                        $values = [
                            'payment_id' => $payment_id,
                            'discount_id' => $discount_id,
                            'name' => $names[$discount_index],
                            'id_no' => $id_no[$discount_index],
                            'percentage' => 0.00,
                            'product_id' => $product_id,
                            'product_price' => $discount_prices[$index],
                            'product_name' => $product_details['name'],
                            'discount_price' => $discount_price,
                            'savings' => 0.00,
                            'added_by' => $this->requested_by,
                            'added_on' => date("Y-m-d H:i:s")
                        ];
            
                        if (!$this->discountPaymentModel->insert($values)) {
                            $this->errorMessage = $this->db->error()['message'];
                            return false;
                        }
                    }

                    $discount_index += 1;
                }
            }

            $discount_index += $quantities[$index];
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
        $branch_id           = $this->_get_payload_value('branch_id');
        $product_ids         = $this->_get_payload_value('product_ids', []);
        $product_ids         = $product_ids ? explode(",", $product_ids) : [];
        $quantities          = $this->_get_payload_value('quantities', []);
        $quantities          = $quantities ? explode(",", $quantities) : [];
        $remarks             = $this->_get_payload_value('order_detail_remarks', []);
        $remarks             = $remarks ? explode(",", $remarks) : [];
        $price_level_type_id = $this->_get_payload_value('price_level_type_id');
        $price_level_id      = $this->_get_payload_value('price_level_id');

        foreach ($product_ids as $key => $product_id) {
            $price = $this->priceLevelModel->get_price($product_id, $price_level_type_id, $price_level_id);
            $price = $price ? $price[0]['price'] : 0;
            $quantity = ($quantities AND $quantities[$key]) ? $quantities[$key] : 0;

            $values = [
                'order_id'   => $order_id,
                'product_id' => $product_id,
                'price'      => $price,
                'qty'        => $quantity,
                'subtotal'   => (float)$price * (float)$quantity,
                'remarks'    => $remarks ? $remarks[$key] : null,
                'added_on'   => ($this->orders_payload AND array_key_exists('ordered_on', $this->orders_payload)) ? 
                                    $this->orders_payload['ordered_on'] : date('Y-m-d H:i:s'),
                'added_by'   => $this->requested_by,
            ];

            if (!$order_detail_id = $this->orderDetailModel->insert_on_duplicate_update($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'] . '<br>' . $this->db->getLastQuery();
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
        $addon_ids = $this->_get_payload_value('addon_ids', []);
        $addon_ids = $addon_ids ? explode("~", $addon_ids) : [];
        $addon_ids = $addon_ids ? explode(",", $addon_ids[$index]) : [];

        $where = ['id' => $branch_id, 'is_deleted' => 0];
        $branch = $this->branchModel->select('', $where, 1);

        if (!$product_ingredients = $this->productItemModel->search($product_id, $branch['type'])) {
            $error = $this->db->error()['message'] ? : "Product does not exist.";
            $this->errorMessage = $this->db->error()['message'] . '<br>' . $this->db->getLastQuery();
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
                $item = $this->itemModel->select('', ['id' => $ingredient['item_id']], 1);
                $this->errorMessage = $this->db->error()['message'] ? : ucfirst($item['name']) . " (" . $item_unit_details['unit'] . ") does not exist in your branch.";
                return false;
            }

            $current_qty = $inventory_details['current_qty'] - $item_unit_details['final_qty'];

            $values = [
                'current_qty' => $current_qty,
                'updated_on' => date('Y-m-d H:i:s'),
                'updated_by' => $this->requested_by
            ];

            if (!$this->inventoryModel->update($inventory_details['id'], $values) OR
                !$this->_save_final_ingredient_used($branch_id, $order_detail_id, $product_id, $ingredient['item_id'], $item_unit_details['final_qty'], $inventory_details['unit'])
            ) {
                $this->errorMessage = $this->db->error()['message'] . '<br>' . $this->db->getLastQuery();
                return false;
            }
        }
        return true;
    }

    protected function _save_final_ingredient_used($branch_id, $order_detail_id, $product_id, $item_id, $qty, $unit)
    {
        $values = [
            'branch_id' => $branch_id,
            'order_detail_id' => $order_detail_id,
            'product_id' => $product_id,
            'item_id' => $item_id,
            'qty' => $qty,
            'unit' => $unit,
            'added_on'   => ($this->orders_payload AND array_key_exists('ordered_on', $this->orders_payload)) ? 
                                    $this->orders_payload['ordered_on'] : date('Y-m-d H:i:s'),
            'added_by' => $this->requested_by
        ];

        if (!$this->orderDetailIngredModel->insert($values))
            return false;
        return true;
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
            $this->errorMessage = $this->db->error()['message'] . '<br>' . $this->db->getLastQuery();
            return false;
        }

        $final_qty = $ingredient['qty'] * $order_qty;

        if ($item_unit['breakdown_unit'] == $ingredient['unit'] AND $item_unit['breakdown_unit'] != $item_unit['inventory_unit']) {
            $final_qty = (($final_qty / $item_unit['breakdown_value']) * $item_unit['inventory_value']);
            $final_qty = str_replace(',', '', number_format($final_qty, 2));
        }

        return [
            'final_qty' => $final_qty,
            'item_unit_id' => $item_unit['id'],
            'unit' => $item_unit['inventory_unit']
        ];
    }

    /**
     * Attempt generate order product detail
     */
    protected function _attempt_generate_order_product_detail($order_detail_id, $key, $branch_id)
    {
        $addon_ids           = $this->_get_payload_value('addon_ids', []);
        $addon_ids           = $addon_ids ? explode("~", $addon_ids) : [];
        $addon_ids           = $addon_ids ? explode(",", $addon_ids[$key]) : [];

        $addon_qtys          = $this->_get_payload_value('addon_qtys', []);
        $addon_qtys          = $addon_qtys ? explode("~", $addon_qtys) : [];
        $addon_qtys          = $addon_qtys ? explode(",", $addon_qtys[$key]) : [];

        $price_level_type_id = $this->_get_payload_value('price_level_type_id');
        $price_level_id      = $this->_get_payload_value('price_level_id');
        
        foreach ($addon_ids as $key => $addon_id) {
            if (!$addon_id) continue; // Skip if addon_id is empty
            $price = $this->priceLevelModel->get_price($addon_id, $price_level_type_id, $price_level_id);
            $price = $price ? $price[0]['price'] : 0;
            $quantity = $addon_qtys[$key] ?? 0;

            $values = [
                'order_detail_id' => $order_detail_id,
                'addon_id'        => $addon_id,
                'price'           => $price,
                'qty'             => $quantity,
                'subtotal'        => (float)$price * (float)$quantity,
                'added_on'   => ($this->orders_payload AND array_key_exists('ordered_on', $this->orders_payload)) ? 
                                    $this->orders_payload['ordered_on'] : date('Y-m-d H:i:s'),
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
            $discount = $this->discountModel->get_discount_by_id($discount_id);
            $discount = $discount ? $discount[0] : null;

            // get the most expensive product
            $most_expensive_product = $this->_get_most_expensive_product($products);
            // decrease the count so that it wont be included in the next iteration
            $products[$most_expensive_product['key']]['quantity'] = 0;

            $savings = 0;
            if ($discount AND $discount['type'] == 'percentage') {
                $percentage = $discount ? (float)$discount[0]['discount_amount'] : 0;
                $percentage = $percentage / 100;
                // compute savings
                $savings = ((float)$most_expensive_product['item_price']/1.12) * $percentage;
            } elseif ($discount AND $discount['type'] == 'fixed') {
                $savings = $discount['discount_amount'];
            }
            
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

    protected function _get_payload_value($parameter, $default_value = null)
    {
        $final_value = null;
        if ($this->orders_payload)
            $final_value = array_key_exists($parameter, $this->orders_payload) ? $this->orders_payload[$parameter] : $default_value;
        else
            $final_value = $this->request->getVar($parameter) ? : $default_value;
        
        return $final_value;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->branchModel             = model('App\Models\Branch');
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
        $this->itemModel               = model('App\Models\Item');
        $this->itemUnitModel           = model('App\Models\Item_unit');
        $this->discountPaymentModel    = model('App\Models\Discount_payment');
        $this->priceLevelTypeModel     = model('App\Models\Price_level_type');
        $this->paymentAttachmentModel  = model('App\Models\Payment_attachment');
        $this->productModel            = model('App\Models\Product');
        $this->productAddonReqModel    = model('App\Models\Product_addon_requirement');
        $this->webappResponseModel     = model('App\Models\Webapp_response');

        $this->orders_payload = null;
        $this->db = null;
    }
}

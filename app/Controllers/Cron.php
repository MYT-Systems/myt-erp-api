<?php

namespace App\Controllers;

class Cron extends MYTController
{

    public function __construct()
    {
        $this->userModel               = model('App\Models\User');
        $this->branchModel             = model('App\Models\Branch');
        $this->orderModel              = model('App\Models\Order');
        $this->orderDetailModel        = model('App\Models\Order_detail');
        $this->priceLevelModel         = model('App\Models\Price_level');
        $this->paymentModel            = model('App\Models\Payment');
        $this->discountModel           = model('App\Models\Discount');
        $this->discountPaymentModel    = model('App\Models\Discount_payment');
        $this->priceLevelTypeModel     = model('App\Models\Price_level_type');
        $this->paymentAttachmentModel  = model('App\Models\Payment_attachment');
        $this->productModel            = model('App\Models\Product');
        $this->productItemModel        = model('App\Models\Product_item');
        $this->orderProductDetailModel = model('App\Models\Order_product_detail');
        $this->orderDetailIngredModel  = model('App\Models\Order_detail_ingredient');
        $this->inventoryModel          = model('App\Models\Inventory');
        $this->itemModel               = model('App\Models\Item');
        $this->itemUnitModel           = model('App\Models\Item_unit');
        $this->productAddonReqModel    = model('App\Models\Product_addon_requirement');
        $this->attendanceModel         = model('App\Models\Attendance');
        $this->attendanceEntryModel    = model('App\Models\Attendance_entry');
        $this->expenseModel            = model('App\Models\Expense');
        $this->expenseItemModel        = model('App\Models\Expense_item');
        $this->expenseAttachmentModel  = model('App\Models\Expense_attachment');

        $this->requested_by = 0;
        $this->orders_payload = null;
        $this->db = null;
    }

    /**
     * Time out all users who were not able to time out
     */
    public function time_out_employees()
    {
        $entries_not_timed_out = $this->attendanceEntryModel->get_not_timed_out();

        $total_worktimes = [];
        foreach ($entries_not_timed_out as $entry) {
            $current_datetime = date("Y-m-d H:i:s");
            $worked_minutes = $this->_get_worked_minutes($entry['time_in'], $current_datetime);

            if (!array_key_exists($entry['attendance_id'], $total_worktimes))
                $total_worktimes[$entry['attendance_id']] = $entry['total_minutes'];

            $total_worktimes[$entry['attendance_id']] += $worked_minutes;

            $attendance_values = [
                'total_minutes' => $total_worktimes[$entry['attendance_id']],
                'updated_on' => date("Y-m-d H:i:s")
            ];

            $attendance_entry_values = [
                'time_out' => $current_datetime,
                'worked_minutes' => $worked_minutes,
                'is_automatic_timeout' => 1,
                'updated_on' => $current_datetime
            ];

            $this->attendanceEntryModel->update($entry['id'], $attendance_entry_values);
            $this->attendanceModel->update($entry['attendance_id'], $attendance_values);
        }

        foreach ($total_worktimes as $attendance_id => $total_minutes) {
            $this->attendanceModel->update($attendance_id, [
                'total_minutes' => $total_minutes,
                'updated_on' => date("Y-m-d H:i:s")
            ]);
        }
    }

    /**
     * Get worked minutes
     */
    protected function _get_worked_minutes($time_in, $time_out)
    {
        $time_in = new \DateTime($time_in);
        $time_out = new \DateTime($time_out);
        $diff = $time_in->diff($time_out);
        
        return $diff->h * 60 + $diff->i;
    }

    /**
     * Logout all users
     */
    public function logout_all_users()
    {
        $not_logged_out_users = $this->operationLogModel->get_user_not_logged_out();
        $not_logged_out_users = array_map(function($value) {
            return $value['user_id'];
        }, $not_logged_out_users);

        $user_details = $this->userModel->get_details_by_id($not_logged_out_users);
        $branches = array_map(function($value) {
            return $value['branch_id'];
        }, $user_details);

        $this->userModel->log_out_all_users($not_logged_out_users);
        $this->branchModel->close_branches($branches);
        $this->operationLogModel->log_out_all_users();
    }

    /**
     * Bulk create orders
     */
    public function bulk_create($folder_name)
    {
        $upload_path = FCPATH . 'public/' . $folder_name . '/';
        $files = array_diff(scandir($upload_path), array('.', '..'));
        $files = array_values($files);

        if (count($files) > 0) {
            switch ($folder_name) {
                case 'bulk_order':
                    $response = $this->_attempt_bulk_create_orders($files[0]);
                    break;
                case 'expenses':
                    $response = $this->_attempt_bulk_create_expenses($files[0]);
                    break;
                default:
                    $response = null;
                    break;
                    
            }
        }
    }

    /**
     * Attempt bulk create
     */
    protected function _attempt_bulk_create_expenses($filename)
    {
        $upload_path = FCPATH . 'public/expenses/' . $filename;
        $json = file_get_contents($upload_path);
        $expenses = json_decode($json);
        $expenses = (array) $expenses;

        $unsaved_expenses = [];

        $this->db = \Config\Database::connect();
        
        foreach ($expenses['expenses'] as $expense) {
            $expense = json_decode($expense);
            $expense = (array) $expense;

            $this->db->transBegin();

            if (!$expense_id = $this->_attempt_create_expense($expense)) {
                $unsaved_expenses[] = $expense;
                $this->errorMessage = $this->db->error()['message'];
                $this->db->transRollback();
            } elseif (!$this->_attempt_generate_expense_item_from_sync($expense_id, $expense['expense_items'])) {
                $unsaved_expenses[] = $expense;
                $this->db->transRollback();
            }
        }

        $this->db->transCommit();

        if ($unsaved_expenses) {
            $write_response = $this->_write_json('expenses', $unsaved_expenses);
            
            $old_file_path = FCPATH . 'public/expenses/' . $filename;
            unlink($old_file_path);
        
            return false;
        }
        
        $old_file_path = FCPATH . 'public/expenses/' . $filename;
        unlink($old_file_path);

        return true;
    }

    /**
     * Attempt create expense
     */
    private function _attempt_create_expense($data = null)
    {
        $values = [
            'branch_id'    => $this->_get_expenses_payload_value('branch_id', $data),
            'expense_date' => $this->_get_expenses_payload_value('expense_date', $data),
            'store_name'   => $this->_get_expenses_payload_value('store_name', $data),
            'invoice_no'   => $this->_get_expenses_payload_value('invoice_no', $data, null),
            'encoded_by'   => $this->_get_expenses_payload_value('encoded_by', $data, null),
            'remarks'      => $this->_get_expenses_payload_value('remarks', $data, null),
            'added_by'     => $this->requested_by,
            'added_on'     => date('Y-m-d H:i:s')
        ];

        if (!$expense_id = $this->expenseModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        } elseif ($data AND !$this->_save_attachment_from_sync($data, $expense_id)) {
            return false;
        }

        return $expense_id;
    }

    protected function _save_attachment_from_sync($data, $expense_id)
    {
        $values = [];
        foreach ($data['expense_attachments'] as $attachment) {
            $values[] = [
                'expense_id' => $expense_id,
                'base_64' => $attachment,
                'added_by' => $this->requested_by,
                'added_on' => date('Y-m-d H:i:s')
            ];
        }

        if (count($values) > 0 AND !$this->expenseAttachmentModel->insertBatch($values))
            return false;
        return true;
    }

    /**
     * Attempt generate expense item from sync
     */
    protected function _attempt_generate_expense_item_from_sync($expense_id, $expense_items)
    {
        $grand_total = 0;
        foreach ($expense_items as $index => $expense_item) {
            $expense_item = (array) $expense_item;

            $price = $this->_get_expenses_payload_value('price', $expense_item);
            $qty = $this->_get_expenses_payload_value('qty', $expense_item);

            $current_total = $price * $qty;
            $grand_total  += $current_total;

            $values = [
                'expense_id' => $expense_id,
                'name'  => $this->_get_expenses_payload_value('name', $expense_item),
                'unit'  => $this->_get_expenses_payload_value('unit', $expense_item),
                'price' => $price,
                'qty'   => $qty,
                'total' => $current_total,
                'added_by'   => $this->_get_expenses_payload_value('added_by', $expense_item),
                'added_on'   => $this->_get_expenses_payload_value('added_on', $expense_item)
            ];

            if (!$this->expenseItemModel->insert_on_duplicate_key_update($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        
        }

        $values = [
            'grand_total' => $grand_total,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$this->expenseModel->update($expense_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    protected function _attempt_bulk_create_orders($filename)
    {
        $upload_path = FCPATH . 'public/bulk_order/' . $filename;
        $json = file_get_contents($upload_path);
        $orders  = json_decode($json);

        $unsaved_orders = [];

        $this->db = \Config\Database::connect();

        foreach ($orders as $order) {
            if (!is_object($order)) {
                $order = json_decode($order);    
            }
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
     * Attempt to create order
     */
    protected function _attempt_create()
    {
        $price_level_type_id = $this->_get_orders_payload_value('price_level_type_id');
        if (!$transaction_type = $this->priceLevelTypeModel->get_details_by_id($price_level_type_id)) {
            $this->errorMessage = "Price Level Type not found";
            return false;
        }

        $transaction_type = $transaction_type[0];

        $grand_total = $this->_get_orders_payload_value('grand_total', 0);
        $paid_amount = $this->_get_orders_payload_value('paid_amount', 0);
        $branch_id = $this->_get_orders_payload_value('branch_id');

        $values = [
            'branch_id'        => $branch_id,
            'offline_id'       => $this->_get_orders_payload_value('id', null),
            'paid_amount'      => $paid_amount,
            'transaction_no'   => $branch_id . date("Ymd") . "-" . time(),
            'change'           => (float)$grand_total - (float)$paid_amount,
            'grand_total'      => $grand_total,
            'remarks'          => $this->_get_orders_payload_value('remarks', null),
            'gift_cert_code'   => $this->_get_orders_payload_value('gift_cert_code'),
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
     * Attempt generate order detail
     */
    protected function _attempt_generate_order_detail($order_id)
    {
        $branch_id           = $this->_get_orders_payload_value('branch_id');
        $product_ids         = $this->_get_orders_payload_value('product_ids', []);
        $product_ids         = $product_ids ? explode(",", $product_ids) : [];
        $quantities          = $this->_get_orders_payload_value('quantities', []);
        $quantities          = $quantities ? explode(",", $quantities) : [];
        $remarks             = $this->_get_orders_payload_value('order_detail_remarks', []);
        $remarks             = $remarks ? explode(",", $remarks) : [];
        $price_level_type_id = $this->_get_orders_payload_value('price_level_type_id');
        $price_level_id      = $this->_get_orders_payload_value('price_level_id');

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
        $addon_ids = $this->_get_orders_payload_value('addon_ids', []);
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

    /**
     * Attempt generate order product detail
     */
    protected function _attempt_generate_order_product_detail($order_detail_id, $key, $branch_id)
    {
        $addon_ids           = $this->_get_orders_payload_value('addon_ids', []);
        $addon_ids           = $addon_ids ? explode("~", $addon_ids) : [];
        $addon_ids           = $addon_ids ? explode(",", $addon_ids[$key]) : [];

        $addon_qtys          = $this->_get_orders_payload_value('addon_qtys_' . $key, []);
        $addon_qtys          = $addon_qtys ? explode("~", $addon_qtys) : [];
        $addon_qtys          = $addon_qtys ? explode(",", $addon_qtys[$key]) : [];

        $price_level_type_id = $this->_get_orders_payload_value('price_level_type_id');
        $price_level_id      = $this->_get_orders_payload_value('price_level_id');
        
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
     * Attempt to record payment
     */
    protected function _attempt_record_payment($order_id)
    {
        $branch_id        = $this->_get_orders_payload_value('branch_id', null);
        $grand_total      = (float) $this->_get_orders_payload_value('grand_total', 0);
        $price_level_type_id = $this->_get_orders_payload_value('price_level_type_id');
        $total_discount = (float)$this->_get_orders_payload_value('discount', 0);
        $subtotal = (float)$this->_get_orders_payload_value('subtotal', 0);
        $paid_amount = (float)$this->_get_orders_payload_value('paid_amount');
        $transaction_type = $this->_get_orders_payload_value('transaction_type');
        
        $commission = $this->priceLevelModel->get_commission($price_level_type_id, $this->db);
        $commission = $commission ? $commission[0]['commission_rate'] : 0;

        $merchant_discount_id = null;
        $merchant_discount_share = 0.00;

        if ($transaction_type != "store") {
            $where = [];
            if ($discount = $this->discountModel->search($branch_id, null, null, null, 'valid', null, null, true)) {
                $discount = $discount ? $discount[0] : null;
                $merchant_discount_id = $discount ? $discount['id'] : null;
    
                if ($discount['type'] == 'percentage') {
                    $total_discount = $grand_total * $discount['mm_discount_share'];
                    $merchant_discount_share = $grand_total * $discount['delivery_discount_share'];
                } else {
                    $total_discount = $discount['mm_discount_share'];
                    $merchant_discount_share = $discount['delivery_discount_share'];
                }
                
                $subtotal = $grand_total;
                $grand_total -= ($total_discount + $merchant_discount_share);
                $paid_amount = $grand_total;
            }
        }

        $values = [
            'branch_id'           => $branch_id,
            'order_id'            => $order_id,
            'price_level_type_id' => $price_level_type_id,
            'transaction_no'      => $this->_get_orders_payload_value('transaction_no'),
            'reference_no'        => $this->_get_orders_payload_value('reference_no'),
            'payment_type'        => $this->_get_orders_payload_value('payment_type'),
            'paid_amount'         => $paid_amount,
            'subtotal'            => $subtotal,
            'merchant_discount_id' => $merchant_discount_id,
            'merchant_discount_share' => $merchant_discount_share,
            'discount'            => $total_discount,
            'additional_discounts' => (float)$this->_get_orders_payload_value('discount', 0),
            'grand_total'         => $grand_total,
            'commission'          => $commission * $grand_total,
            'remarks'             => $this->_get_orders_payload_value('remarks'),
            'acc_no'              => $this->_get_orders_payload_value('acc_no'),
            'cvc_cvv'             => $this->_get_orders_payload_value('cvc_cvv'),
            'card_type'           => $this->_get_orders_payload_value('card_type'),
            'card_expiry'         => $this->_get_orders_payload_value('card_expiry'),
            'card_bank'           => $this->_get_orders_payload_value('card_bank'),
            'proof'               => $this->_get_orders_payload_value('proof'),
            'or_no'               => $this->_get_orders_payload_value('or_no'),
            'added_by'            => $this->requested_by,
            'added_on'   => ($this->orders_payload AND array_key_exists('ordered_on', $this->orders_payload)) ? 
                                    $this->orders_payload['ordered_on'] : date('Y-m-d H:i:s'),
        ];

        $is_gift_cert = $this->_get_orders_payload_value('gift_cert_code') ? true : false;

        if (!$payment_id = $this->paymentModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        } elseif ($this->request->getFile('file') AND 
                !$this->_attempt_upload_file_base64($this->paymentAttachmentModel, ['payment_id' => $payment_id, 'type' => $is_gift_cert ? 'gift_cert' : 'payment'])) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        $discount_prices = $this->_get_orders_payload_value('discount_prices', null);
        if ($discount_prices AND $transaction_type == "store") {
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
        $product_ids     = $this->_get_orders_payload_value('product_ids', []);
        $product_ids     = $product_ids ? explode(",", $product_ids) : [];
        $quantities          = $this->_get_orders_payload_value('quantities', []);
        $quantities          = $quantities ? explode(",", $quantities) : [];

        $product_prices  = $this->_get_orders_payload_value('product_prices', []);
        $product_prices  = $product_prices ? explode(",", $product_prices) : [];
        $discount_ids    = $this->_get_orders_payload_value('discount_ids', []);
        $discount_ids    = $discount_ids ? explode(",", $discount_ids) : [];
        $names           = $this->_get_orders_payload_value('names', []);
        $names           = $names ? explode(",", $names) : [];
        $id_no           = $this->_get_orders_payload_value('id_no', []);
        $id_no           = $id_no ? explode(",", $id_no) : [];
        $discount_prices = $this->_get_orders_payload_value('discount_prices', []);
        $discount_prices = $discount_prices ? explode(",", $discount_prices) : [];

        $discount_index = 0;
        foreach ($product_ids as $index => $product_id) {
            if ($discount_prices[$index] != "") {

                for ($i=0; $i < $quantities[$index]; $i++) {

                    $product = "";

                    if ($discount_index < count($discount_ids) AND $discount_ids[$discount_index] != "") {
    
                        $where = ['id' => $product_id, 'is_deleted' => 0];
                        $product_details = $this->productModel->select('', $where, 1);
                        $discount_price = $quantities[$index] <= 1 ? $discount_prices[$index] : round($discount_prices[$index] / $quantities[$index], 2);
            
                        $values = [
                            'payment_id' => $payment_id,
                            'discount_id' => $discount_ids[$discount_index],
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

        }
        
        return true;
    }

    protected function _get_orders_payload_value($parameter, $default_value = null)
    {
        $final_value = null;
        if ($this->orders_payload)
            $final_value = array_key_exists($parameter, $this->orders_payload) ? $this->orders_payload[$parameter] : $default_value;
        else
            $final_value = $this->request->getVar($parameter) ? : $default_value;
        
        return $final_value;
    }

    protected function _get_expenses_payload_value($parameter, $data = null, $default_value = null)
    {
        $final_value = null;
        if ($data)
            $final_value = array_key_exists($parameter, $data) ? $data[$parameter] : $default_value;
        else
            $final_value = $this->request->getVar($parameter) ? : $default_value;
        
        return $final_value;
    }
}

<?php

namespace App\Controllers;

class Purchases extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get purchase
     */
    public function get_purchase()
    {

        if (($response = $this->_api_verification('purchases', 'get_purchase')) !== true) 
            return $response;

        $purchase_id    = $this->request->getVar('purchase_id') ? : null;
        $purchase       = $purchase_id ? $this->purchaseModel->get_details_by_id($purchase_id) : null;
        $purchase_items = $purchase_id ? $this->purchaseItemModel->get_details_by_purchase_id($purchase_id) : null;

        if (!$purchase) {
            $response = $this->failNotFound('No purchase found');
        } else {
            $purchase[0]['purchase_items'] = $purchase_items;
            $purchase_payment = $this->purchasePaymentModel->get_details_by_purchase_id($purchase_id);
            $purchase_payment[0]['purchase_payment_details'] = $purchase_payment ? $this->purchasePaymentDetailModel->get_details_by_purchase_payment_id($purchase_payment[0]['id']) : null;
            $purchase[0]['purchase_payment'] = $purchase_payment;

            $receives = $this->receiveModel->get_id_by_po_id($purchase[0]['id']);
            $purchase[0]['invoice_no'] = $receives;

            $response = $this->respond([
                'status' => 'success',
                'data' => $purchase
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get purchase by status
     */
    public function filter_purchase_status()
    {
        if (($response = $this->_api_verification('purchases', 'filter_purchase_status')) !== true) 
            return $response;

        $status    = $this->request->getVar('status') ? : null;
        $purchase = $status ? $this->purchaseModel->filter_purchase_status($status) : null;

        if (!$purchase) {
            $response = $this->failNotFound('No purchase found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $purchase
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Filter by order status
     */
    public function filter_order_status()
    {
        if (($response = $this->_api_verification('purchases', 'filter_order_status')) !== true) 
            return $response;

        $status    = $this->request->getVar('status') ? : null;
        $purchase = $status ? $this->purchaseModel->filter_order_status($status) : null;

        if (!$purchase) {
            $response = $this->failNotFound('No purchase found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $purchase
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all purchases
     */
    public function get_all_purchase()
    {
        if (($response = $this->_api_verification('purchases', 'get_all_purchase')) !== true) 
            return $response;

        $purchases = $this->purchaseModel->get_all_purchase();

        if (!$purchases) {
            $response = $this->failNotFound('No purchase found');
        } else {
            foreach ($purchases as $key => $purchase) {
                $purchases[$key]['purchase_items'] = $this->purchaseItemModel->get_details_by_purchase_id($purchase['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $purchases
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create purchase
     */
    public function create()
    {
        if (($response = $this->_api_verification('purchases', 'create')) !== true) 
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        $is_save = $this->request->getVar('is_save');

        if (!$purchase_id = $this->_attempt_create($is_save)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else if (!$this->_attempt_generate_po_items($purchase_id, $is_save, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate PO items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'purchase_id' => $purchase_id,
                'response'    => 'purchase created successfully'
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update purchase
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('purchases', 'update')) !== true) 
            return $response;
            
        $purchase_id = $this->request->getVar('purchase_id');
        $where       = ['id' => $purchase_id, 'is_deleted' => 0];
        $is_save     = $this->request->getVar('is_save');

        $db = \Config\Database::connect();
        $db->transBegin();
        
        if (!$purchase = $this->purchaseModel->select('', $where, 1)) {
            $response = $this->failNotFound('purchase not found');
        } elseif (!$this->_attempt_update($purchase, $is_save)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } elseif (!$this->_attempt_update_po_items($purchase, $is_save, $db)) {
            $db->transRollback();
            $response = $this->respond([
                'status'  => 'error',
                'message' => 'Failed to generate PO items'
            ]);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'purchase_id' => $purchase_id,
                'response'    => 'purchase updated successfully'
            ]);
        }
        
        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete purchases
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('purchases', 'delete')) !== true) 
            return $response;
        
        $purchase_id = $this->request->getVar('purchase_id');

        $where = ['id' => $purchase_id, 'is_deleted' => 0];

        if (!$purchase = $this->purchaseModel->select('', $where, 1)) {
            $response = $this->failNotFound('purchase not found');
        } elseif (!$this->_attempt_delete($purchase_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'purchase deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search purchases based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('purchases', 'search')) !== true) 
            return $response;

        $branch_id          = $this->request->getVar('branch_id') ? : null;
        $supplier_id        = $this->request->getVar('supplier_id') ? : null;
        $vendor_id          = $this->request->getVar('vendor_id') ? : null;
        $forwarder_id       = $this->request->getVar('forwarder_id') ? : null;
        $expense_type_id       = $this->request->getVar('expense_type_id') ? : null;
        $purchase_date      = $this->request->getVar('purchase_date') ? : null;
        $delivery_date      = $this->request->getVar('delivery_date') ? : null;
        $delivery_address   = $this->request->getVar('delivery_address') ? : null;
        $company            = $this->request->getVar('company') ? : null;
        $remarks            = $this->request->getVar('remarks') ? : null;
        $purpose            = $this->request->getVar('purpose') ? : null;
        $requisitioner      = $this->request->getVar('requisitioner') ? : null;
        $status             = $this->request->getVar('status') ? : null;
        $order_status       = $this->request->getVar('order_status') ? : null;
        $purchase_date_from = $this->request->getVar('purchase_date_from') ? : null;
        $purchase_date_to   = $this->request->getVar('purchase_date_to') ? : null;
        $delivery_date_from = $this->request->getVar('delivery_date_from') ? : null;
        $delivery_date_to   = $this->request->getVar('delivery_date_to') ? : null;
        $limit_by           = $this->request->getVar('limit_by') ? : null;
        $anything           = $this->request->getVar('anything') ? : null;

        if (!$purchases = $this->purchaseModel->search($branch_id, $supplier_id, $vendor_id, $forwarder_id, $expense_type_id, $purchase_date, $delivery_date, $delivery_address, $company, $remarks, $purpose, $requisitioner, $status, $order_status, $purchase_date_from, $purchase_date_to, $delivery_date_from, $delivery_date_to, $limit_by, $anything)) {
            $response = $this->failNotFound('No purchase found');
        } else {
            foreach ($purchases as $key => $purchase) {
                $receives = $this->receiveModel->get_id_by_po_id($purchase['id']);
                $payments = [];
                foreach ($receives as $key2 => $receive) {
                    $payments[] = $this->suppliesPaymentModel->get_all_payment_by_receive($receive['id']);
                }
                $purchases[$key]['payments'] = $payments ? $payments[0] : [];
                $purchases[$key]['invoice_no'] = $receives;
            }

            $response = $this->respond([
                'data' => $purchases
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Send email to supplier
     */
    public function send_email_to_supplier()
    {
        if (($response = $this->_api_verification('purchases', 'send_email_to_supplier')) !== true)
            return $response;

        $purchase_id = $this->request->getVar('purchase_id');

        if (!$purchase = $this->purchaseModel->get_details_by_id($purchase_id)){
            $response = $this->failNotFound('The purchase does not exist');
        } elseif (!$this->_attempt_send_email_to_supplier($purchase[0])) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond([
                'response' => 'Email sent successfully to supplier.'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record the action of the user
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('purchases', 'record_action')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('purchase_id'),
            'is_deleted' => 0
        ];
        $action = $this->request->getVar('action');

        if (!$purchase = $this->purchaseModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'purchase not found']);
        } elseif (!$this->_attempt_record_action($purchase, $action)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'purchase status changed successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Add payment
     */
    public function add_payment()
    {
        if (($response = $this->_api_verification('purchases', 'add_payment')) !== true)
            return $response;
        
        $where = [
            'id' => $this->request->getVar('purchase_id'),
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$purchase = $this->purchaseModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'purchase not found']);
        } elseif ($purchase['status'] != 'sent') {
            $response = $this->fail('Purchase must be sent first. Current status: ' . $purchase['status']);
        } elseif (!$this->_attempt_add_payment($purchase)) {
            $db->transRollback();
            $response = $this->fail('Failed to add payment.');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'payment added successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create a purchase
     */
    private function _attempt_create($is_save)
    {
        $values = [
            'branch_id'        => $this->request->getVar('branch_id'),
            'supplier_id'      => $this->request->getVar('supplier_id'),
            'vendor_id'        => $this->request->getVar('vendor_id'),
            'forwarder_id'     => $this->request->getVar('forwarder_id'),
            'expense_type_id'  => $this->request->getVar('expense_type_id'),
            'purchase_date'    => $this->request->getVar('purchase_date'),
            'delivery_date'    => $this->request->getVar('delivery_date'),
            'delivery_address' => $this->request->getVar('delivery_address'),
            'company'          => $this->request->getVar('company'),
            'remarks'          => $this->request->getVar('remarks'),
            'requisitioner'    => $this->request->getVar('requisitioner'),
            'status'           => $is_save ? 'pending' : 'for_approval',
            'added_by'         => $this->requested_by,
            'added_on'         => date('Y-m-d H:i:s'),
        ];

        if (!$purchase_id = $this->purchaseModel->insert($values)) {
            return false;
        }

        return $purchase_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($purchase, $is_save)
    {
        $values = [
            'branch_id'        => $this->request->getVar('branch_id'),
            'supplier_id'      => $this->request->getVar('supplier_id'),
            'vendor_id'        => $this->request->getVar('vendor_id'),
            'forwarder_id'     => $this->request->getVar('forwarder_id'),
            'expense_type_id'     => $this->request->getVar('expense_type_id'),
            'purchase_date'    => $this->request->getVar('purchase_date'),
            'delivery_date'    => $this->request->getVar('delivery_date'),
            'delivery_address' => $this->request->getVar('delivery_address'),
            'remarks'          => $this->request->getVar('remarks'),
            'company'          => $this->request->getVar('company'),
            'requisitioner'    => $this->request->getVar('requisitioner'),
            'updated_by'       => $this->requested_by,
            'updated_on'       => date('Y-m-d H:i:s')
        ];

        if (!$is_save && $purchase['status'] == 'pending') {
            $values['status'] = 'for_approval';
        }

        if (!$this->purchaseModel->update($purchase['id'], $values))
            return false;
    
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($purchase_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $purchase_id];
        $values = [
            'status'     => 'deleted',
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->purchaseModel->update($where, $values)) {
            $db->transRollback();
            $db->close();
            return false;
        } 

        $db->transCommit();
        $db->close();

        return true;
    }

    /**
     * Attempt generate PO
     */
    protected function _attempt_generate_po_items($purchase_id, $is_save, $db)
    {
        $item_ids   = $this->request->getVar('item_ids');
        $quantities = $this->request->getVar('quantities');
        $units      = $this->request->getVar('units');
        $prices     = $this->request->getVar('prices');
        $branch_id  = $this->request->getVar('branch_id');
        $remarks    = $this->request->getVar('item_remarks');

        $grand_total = 0;
        foreach ($item_ids as $key => $item_id) {
            $current_amount = $quantities[$key] * $prices[$key];
            $grand_total   += $current_amount;

            // get current qty upon purchase
            $current_qty = $this->inventoryModel->get_inventory_qty_by_branch($item_id, $branch_id, $units[$key]);

            $data = [
                'purchase_id' => $purchase_id,
                'item_id'     => $item_id,
                'unit'        => $units[$key],
                'qty'         => $quantities[$key],
                'current_qty' => $current_qty,
                'price'       => $prices[$key] ?? 0,
                'amount'      => $current_amount,
                'status'      => $is_save ? 'pending' : 'for_approval',
                'remarks'     => $remarks[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];

            if (!$this->purchaseItemModel->insert($data, $this->requested_by, $db)) {
                return false;
            }
        }
        $discount    = $this->request->getVar('discount');
        $service_fee = $this->request->getVar('service_fee');
        $data = [
            'service_fee' => $service_fee,
            'discount'    => $discount,
            'grand_total' => $grand_total - $discount + $service_fee,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        if (!$this->purchaseModel->update($purchase_id, $data)) {
            return false;
        }

        return true;
    }

    /*
    * Attempt update PO items
    */
    protected function _attempt_update_po_items($purchase, $is_save, $db)
    {
        // // delete all items first
        if (!$this->purchaseItemModel->delete_by_purchase_id($purchase['id'], $this->requested_by, $db)) {
            return false;
        }

        $item_ids     = $this->request->getVar('item_ids');
        $quantities   = $this->request->getVar('quantities');
        $units        = $this->request->getVar('units');
        $prices       = $this->request->getVar('prices');
        $item_remarks = $this->request->getVar('item_remarks');

        $grand_total = 0;
        foreach ($item_ids as $key => $item_id) {
            $current_amount = $quantities[$key] * $prices[$key];
            $grand_total   += $current_amount;

            $data = [
                'purchase_id'   => $purchase['id'],
                'item_id'       => $item_id,
                'qty'           => $quantities[$key],
                'unit'          => $units[$key],
                'price'         => $prices[$key],
                'amount'        => $current_amount,
                'remarks'       => $item_remarks[$key],
                'status'        => $is_save ? 'pending' : $purchase['status'],
                'added_by'      => $this->requested_by,
                'added_on'      => date('Y-m-d H:i:s')
            ];

            if (!$this->purchaseItemModel->insert($data)) {
                return false;
            }
        }

        $discount    = $this->request->getVar('discount');
        $service_fee = $this->request->getVar('service_fee');

        $data = [
            'service_fee' => $service_fee,
            'discount'    => $discount,
            'grand_total' => $grand_total - $discount + $service_fee,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->purchaseModel->update($purchase['id'], $data)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt send email to supplier
     */
    protected function _attempt_send_email_to_supplier($purchase) {
        $supplier = $this->supplierModel->get_details_by_id($purchase['supplier_id']);
        $vendor   = $this->vendorModel->get_details_by_id($purchase['vendor_id']);
        if (!$supplier && !$vendor) {
            var_dump('no supplier and vendor');
            return false;
        }

        $supplier_email = $supplier ? $supplier[0]['email'] : $vendor[0]['email'];
        if (!$supplier_email) {
            var_dump('no email');
            return false;
        }
        
        $purchase_items = $this->purchaseItemModel->get_details_by_purchase_id($purchase['id']);
        if (!$branch = $this->branchModel->get_details_by_id($purchase['branch_id'])) {
            var_dump("no branch found");
            return false;
        }

        $data = [
            'purchase'      => $purchase,
            'purchase_items'=> $purchase_items,
            'supplier'      => $supplier ? $supplier[0] : $vendor[0],
            'branch'        => $branch[0]
        ];

        $email = \Config\Services::email();
        $email->setFrom('triplekexpressfoods@gmail.com', 'Triple K Expressfoods');
        $email->setTo($supplier_email);
        $email->setCC('triplekexpressfoods@gmail.com');
        $email->setSubject('PURCHASE ORDER #'.$purchase['id']);
        $email->setMessage(view('emails/purchase_order', $data));
        
        
        $response = "";
        if ($email->send()) {
            $response = [
                'response' => 'Email sent successfully'
            ];

            if (!$this->_attempt_record_action($purchase, 'sent')) {
                return false;
            }
        } else {
            var_dump("failed to send email");
            return false;
        }
        return true;
    }
    
   /**
     * Attempt record action
     */
    protected function _attempt_record_action($purchase, $action)
    {
        $db = \Config\Database::connect();
        $db->transBegin();
        
        $current_status = $purchase['status'];

        $values = [
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        switch ($action) {
            case 'unclose':
                $values['is_closed'] = 0;
                $values['closed_by'] = null;
                $values['closed_on'] = null;
                $values['order_status'] = 'incomplete';
                break;
            case 'closed':
                $values['is_closed'] = 1;
                $values['closed_by'] = $this->requested_by;
                $values['closed_on'] = date('Y-m-d H:i:s');
                $values['order_status'] = 'complete';
                break;
            case 'pending':
                $values['status'] = 'pending';
                $values['order_status'] = 'pending';
                $values['is_deleted'] = 0;
                break;
            case 'authorize':
                $values['authorized_by'] = $this->requested_by;
                $values['authorized_on'] = date('Y-m-d H:i:s');
                break;
            case 'recommend':
                $values['recommended_by'] = $this->requested_by;
                $values['recommended_on'] = date('Y-m-d H:i:s');
                break;
            case 'approve':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'approved';
                break;
            case 'disapproved':
                $values['disapproved_by'] = $this->requested_by;
                $values['disapproved_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'disapproved'; 
                break;
            case 'print':
                $values['printed_by'] = $this->requested_by;
                $values['printed_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'printed';
                break;
            case 'deleted':
                $values['deleted_by'] = $this->requested_by;
                $values['deleted_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'deleted';
                $values['is_deleted'] = 1;
                break;
            case 'sent':
                $values['sent_by'] = $this->requested_by;
                $values['sent_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'sent';
                break;
            default:
                $db->close();
                return false;
        }

        // reverted the is_delted status if the status is changed from deleted to other status
        if (!isset($values['status']) || ($values['status'] != 'deleted' && $purchase['is_deleted'])) {
            $values['is_deleted'] = 0;
        }

        // Added to make sure sent purchase's status cannot be changed
        // Unless it is deleted
        if ($current_status == 'sent' && $action != 'delete' && $action != 'pending') {
            $values['status'] = 'sent';
        }

        // Added to make sure deleted purchase's status cannot be changed
        // Unless it is change to pending
        if ($current_status == 'deleted' && $action != 'pending') {
            $values['status'] = 'deleted';
        }

        if (!$this->purchaseModel->update($purchase['id'], $values)) {
            $db->transRollback();
            return false;
        } elseif ($values['status'] && !$this->purchaseItemModel->update_status_by_purchase_id($purchase['id'], $this->requested_by, $values['status'], $db)) {
            $db->transRollback();
            return false;
        }

        $db->transCommit();
        $db->close();
        return true;
    }

    /**
     * Attempt add payment in Purchase
     */
    protected function _attempt_add_payment($purchase)
    {
        // Check if there is an existing purchase payment based on the purchase id
        if (!$purchase_payment = $this->purchasePaymentModel->get_details_by_purchase_id($purchase['id'])) {
            // Add new purchase payment
            $purchase_payment = [
                'purchase_id'   => $purchase['id'],
                'total_payment' => $this->request->getVar('amount'),
                'balance'       => $this->request->getVar('amount'),
                'added_by'      => $this->requested_by,
                'added_on'      => date('Y-m-d H:i:s')
            ];

            if (!$purchase_payment_id = $this->purchasePaymentModel->insert($purchase_payment)) {
                var_dump("failed to add purchase payment");
                return false;
            }
        } else {
            // check if purchase_payment is more than the grand total of the purchase
            // this makes sure that the payment is not more than the grand total
            if ($purchase_payment[0]['total_payment'] > $purchase['grand_total']) {
                var_dump("purchase payment is more than the grand total of the purchase");
                return false;
            }

            // Increase the total payment and balance
            $updated_values = [
                'total_payment' => $purchase_payment[0]['total_payment'] + $this->request->getVar('amount'),
                'balance'       => $purchase_payment[0]['balance'] + $this->request->getVar('amount'),
                'updated_by'    => $this->requested_by,
                'updated_on'    => date('Y-m-d H:i:s')
            ];

            if (!$this->purchasePaymentModel->update($purchase_payment[0]['id'], $updated_values)) {
                var_dump("failed to update purchase payment");
                return false;
            }

            $purchase_payment_id = $purchase_payment[0]['id'];
        }

        // Create the payment details
        $payment_details = [
            'purchase_id'         => $purchase['id'],
            'vendor_id'           => $purchase['vendor_id'],
            'supplier_id'         => $purchase['supplier_id'],
            'purchase_payment_id' => $purchase_payment_id,
            'amount'              => $this->request->getVar('amount'),
            'payment_type'        => $this->request->getVar('payment_type'),
            'payment_date'        => $this->request->getVar('payment_date'),
            'remarks'             => $this->request->getVar('remarks'),
            'from_bank_id'        => $this->request->getVar('from_bank_id'),
            'to_bank_id'          => $this->request->getVar('to_bank_id'),
            'to_bank_name'        => $this->request->getVar('to_bank_name'),
            'reference_number'    => $this->request->getVar('reference_number'),
            'transaction_number'  => $this->request->getVar('transaction_number'),
            'payment_description' => $this->request->getVar('payment_description'),
            'payment_date'        => $this->request->getVar('payment_date'),
            'from_account_no'     => $this->request->getVar('from_account_no'),
            'from_account_name'   => $this->request->getVar('from_account_name'),
            'to_account_no'       => $this->request->getVar('to_account_no'),
            'to_account_name'     => $this->request->getVar('to_account_name'),
            'transaction_fee'     => $this->request->getVar('transaction_fee'),
            'reference_no'        => $this->request->getVar('reference_no'),
            'payee'               => $this->request->getVar('payee'),
            'particulars'         => $this->request->getVar('particulars'),
            'check_no'            => $this->request->getVar('check_no'),
            'check_date'          => $this->request->getVar('check_date'),
            'issued_date'         => $this->request->getVar('issued_date'),
            'balance'             => $this->request->getVar('amount'),
            'added_by'            => $this->requested_by,
            'added_on'            => date('Y-m-d H:i:s')
        ];

        if (!$this->purchasePaymentDetailModel->insert($payment_details)) {
            var_dump("failed to add purchase payment details");
            return false;
        }

        // Update the purchase
        $purchase_values = [
            'with_payment' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->purchaseModel->update($purchase['id'], $purchase_values)) {
            var_dump("failed to update purchase");
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->purchaseModel               = model('App\Models\Purchase');
        $this->purchaseItemModel           = model('App\Models\Purchase_item');
        $this->supplierModel               = model('App\Models\Supplier');
        $this->vendorModel                 = model('App\Models\Vendor');
        $this->inventoryModel              = model('App\Models\Inventory');
        $this->branchModel                 = model('App\Models\Branch');
        $this->purchasePaymentModel        = model('App\Models\Purchase_payment');
        $this->purchasePaymentDetailModel  = model('App\Models\Purchase_payment_detail');
        $this->receiveModel                = model('App\Models\Receive');
        $this->suppliesPaymentModel        = model('App\Models\Supplies_payment');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
        
    }
}

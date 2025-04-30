<?php

namespace App\Controllers;

class Supplies_expenses extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get supplies_expense
     */
    public function get_supplies_expense()
    {
        if (($response = $this->_api_verification('supplies_expenses', 'get_supplies_expense')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplies_expense_id         = $this->request->getVar('supplies_expense_id') ? : null;

        $supplies_expense            = $supplies_expense_id ? $this->suppliesExpenseModel->get_details_by_id($supplies_expense_id) : null;
        $supplies_expense_item = $supplies_expense_id ? $this->suppliesExpenseItemModel->get_details_by_supplies_expense_id($supplies_expense_id) : null;
        $supplies_expense_attachment = $supplies_expense_id ? $this->suppliesExpenseAttachmentModel->get_details_by_supplies_expense_id($supplies_expense_id) : null;

        if (!$supplies_expense) {
            $response = $this->failNotFound('No supplies expense found');
        } else {
            $supplies_expense[0]['se_items'] = $supplies_expense_item;
            $supplies_expense[0]['attachments'] = $supplies_expense_attachment ? $supplies_expense_attachment : [];
            
            $payments = [];
            $invoice_no = '';
            $supplies_receives = $this->suppliesReceiveModel->get_id_by_se_id($supplies_expense[0]['id']);
            $supplies_expense[0]['invoice_no'] = $supplies_receives;

            $response = $this->respond([
                'data'   => $supplies_expense,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all supplies_expenses
     */
    public function get_all_supplies_expense()
    {
        if (($response = $this->_api_verification('supplies_expenses', 'get_all_supplies_expense')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplier_id = $this->request->getVar('supplier_id') ? : null;
        $supplies_expenses = $this->suppliesExpenseModel->get_all_supplies_expense($supplier_id);

        if (!$supplies_expenses) {
            $response = $this->failNotFound('No supplies expense found');
        } else {
            foreach ($supplies_expenses as $key => $supplies_expense) {
                $supplies_expense_items = $this->suppliesExpenseItemModel->get_details_by_supplies_expense_id($supplies_expense['id']);
                $supplies_expense_attachment = $this->suppliesExpenseAttachmentModel->get_details_by_supplies_expense_id($supplies_expense['id']);
                $supplies_expenses[$key]['se_items'] = $supplies_expense_items;
                $supplies_expenses[$key]['attachment'] = $supplies_expense_attachment;
            }

            $response = $this->respond([
                'data'   => $supplies_expenses,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create supplies_expense
     */
    public function create()
    {
        if (($response = $this->_api_verification('supplies_expenses', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();
    
        if (!$supplies_expense_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create supplies expense.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_se_items($supplies_expense_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate supplies expense items.', 'status' => 'error']);
        } elseif (($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$response = $this->_attempt_upload_file_base64($this->suppliesExpenseAttachmentModel, ['supplies_expense_id' => $supplies_expense_id]) AND
            $response === false) {
            $db->transRollback();
            $response = $this->respond(['response' => 'supplies_expense_attachment file upload failed']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'            => 'Supplies expense created successfully',
                'status'              => 'success',
                'supplies_expense_id' => $supplies_expense_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update supplies_expense
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('supplies_expenses', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplies_expense_id = $this->request->getVar('supplies_expense_id');
        $where = ['id' => $supplies_expense_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$supplies_expense = $this->suppliesExpenseModel->select('', $where, 1)) {
            $db->transRollback();
            $response = $this->failNotFound('supplies_expense not found');
        } elseif (!$this->_attempt_update_supplies_expense($supplies_expense_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update supplies expense.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_se_item($supplies_expense_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update supplies expense items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Supplies expense updated successfully', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete supplies_expenses
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('supplies_expenses', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplies_expense_id = $this->request->getVar('supplies_expense_id');

        $where = ['id' => $supplies_expense_id, 'is_deleted' => 0];

        if (!$this->suppliesExpenseModel->select('', $where, 1)) {
            $response = $this->failNotFound('supplies_expense not found');
        } elseif (!$this->_attempt_delete($supplies_expense_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'supplies_expense deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search supplies_expenses based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('supplies_expenses', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplier_id           = $this->request->getVar('supplier_id') ? : null;
        $vendor_id             = $this->request->getVar('vendor_id') ? : null;
        $type                  = $this->request->getVar('type') ? : null;
        $forwarder_id          = $this->request->getVar('forwarder_id') ? : null;
        $expense_type_id       = $this->request->getVar('expense_type_id') ? : null;    
        $supplies_expense_date = $this->request->getVar('supplies_expense_date') ? : null;
        $delivery_date         = $this->request->getVar('delivery_date') ? : null;
        $delivery_address      = $this->request->getVar('delivery_address') ? : null;
        $branch_name           = $this->request->getVar('branch_name') ? : null;
        $remarks               = $this->request->getVar('remarks') ? : null;
        $purpose               = $this->request->getVar('purpose') ? : null;
        $requisitioner         = $this->request->getVar('requisitioner') ? : null;
        $status                = $this->request->getVar('status') ? : null;
        $order_status          = $this->request->getVar('order_status') ? : null;
        $se_date_from          = $this->request->getVar('se_date_from') ? : null;
        $se_date_to            = $this->request->getVar('se_date_to') ? : null;
        $delivery_date_from    = $this->request->getVar('delivery_date_from') ? : null;
        $delivery_date_to      = $this->request->getVar('delivery_date_to') ? : null;
        $limit_by              = $this->request->getVar('limit_by') ? : null;
        $anything              = $this->request->getVar('anything') ? : null;

        if (!$supplies_expenses = $this->suppliesExpenseModel->search($supplier_id, $vendor_id, $type, $forwarder_id, $expense_type_id, $supplies_expense_date, $delivery_date, $delivery_address, $branch_name, $remarks, $purpose, $requisitioner, $status, $order_status, $se_date_from, $se_date_to, $delivery_date_from, $delivery_date_to, $limit_by, $anything)) {
            $response = $this->failNotFound('No supplies_expense found');
        } else {
            $total_expenses = 0;

            foreach ($supplies_expenses as $key => $supplies_expense) {
                // $supplies_receives = $this->suppliesReceiveModel->get_id_by_se_id($supplies_expense['id']);
                $payments = $this->suppliesExpenseModel->get_all_payment_by_se($supplies_expense['id']);

                // foreach ($supplies_receives as $key2 => $supplies_receive) {
                //     $payments[] = $this->suppliesPaymentModel->get_all_payment_by_se($supplies_receive['id']);
                // }

                $supplies_expenses[$key]['payments'] = $payments;
                // $supplies_expenses[$key]['invoice_no'] = $supplies_receives;

                // Add grand_total to total_expenses
                $total_expenses += $supplies_expense['grand_total'] ?? 0;
            }

            $response = $this->respond([
                'response'      => 'Supplies expense found',
                'status'        => 'success',
                'total_expenses'=> $total_expenses,
                'data'          => $supplies_expenses,
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
        if (($response = $this->_api_verification('supplies_expenses', 'send_email_to_supplier')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplies_expense_id = $this->request->getVar('supplies_expense_id');
        $where               = ['id' => $supplies_expense_id, 'is_deleted' => 0];

        if (!$supplies_expense = $this->suppliesExpenseModel->get_details_by_id($supplies_expense_id)) {
            $response = $this->failNotFound('The supplies expense was not found');
        } elseif (!$this->_attempt_send_email_to_supplier($supplies_expense[0])) {
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
     * Change status of supplies_expense
     */
    public function change_status()
    {
        if (($response = $this->_api_verification('supplies_expenses', 'change_status')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('supplies_expense_id'), 
            'is_deleted' => 0
        ];
        $new_status = $this->request->getVar('new_status');

        if (!$supplies_expense = $this->suppliesExpenseModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'Supplies expense not found']);
        } elseif (!$this->_attempt_change_status($supplies_expense, $new_status)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'Supplies expense status changed successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Add payment
     */
    public function add_payment()
    {
        if (($response = $this->_api_verification('supplies_expenses', 'add_payment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
        
        $where = [
            'id' => $this->request->getVar('supplies_expense_id'),
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$supplies_expense = $this->suppliesExpenseModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'supplies_expense not found']);
        } elseif ($supplies_expense['status'] != 'sent') {
            $response = $this->fail('supplies_expense must be sent first. Current status: ' . $supplies_expense['status']);
        } elseif (!$this->_attempt_add_payment($supplies_expense)) {
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
     * Attempt to create supplies_expense
     *
     * @return int|bool
     */
    protected function _attempt_create()
    {
        $values = [
            // 'branch_id'             => $this->request->getVar('branch_id') ? : null,
            'due_date'              => $this->request->getVar('due_date') ? : null,
            'supplier_id'           => $this->request->getVar('supplier_id') ? : null,
            // 'branch_name'           => $this->request->getVar('branch_name') ? : null,
            // 'vendor_id'             => $this->request->getVar('vendor_id') ? : null,
            'forwarder_id'          => $this->request->getVar('forwarder_id'),
            'supplies_expense_date' => $this->request->getVar('supplies_expense_date') ? : null,
            'type'                  => $this->request->getVar('expense_type_id'),
            // 'delivery_address'      => $this->request->getVar('delivery_address') ? : null,
            // 'delivery_date'         => $this->request->getVar('delivery_date') ? : null,
            // 'doc_no'                => $this->request->getVar('doc_no') ? : null,
            'payment_method'        => $this->request->getVar('payment_method') ? : null,
            'remarks'               => $this->request->getVar('remarks') ? : null,
            'requisitioner'         => $this->request->getVar('requisitioner') ? : null,
            'status'                => 'for_approval',
            'prepared_by'           => $this->requested_by,
            'added_by'              => $this->requested_by,
            'added_on'              => date('Y-m-d H:i:s'),
        ];

        if (!$supplies_expense_id = $this->suppliesExpenseModel->insert($values))
            return false;

        return $supplies_expense_id;
    }

    /**
     * Generate supplies expense item
     */
    protected function _attempt_generate_se_items($supplies_expense_id)
    {
        $names        = $this->request->getVar('names');
        $quantities   = $this->request->getVar('quantities');
        $units        = $this->request->getVar('units');
        $prices       = $this->request->getVar('prices');
        $item_remarks = $this->request->getVar('item_remarks');

        $grand_total = 0;
        foreach ($names as $key => $name) {
            // remove spaces in quantity and price
            $quantity = str_replace(' ', '', $quantities[$key]);
            $price    = str_replace(' ', '', $prices[$key]);

            $current_total = (float)$quantity * (float)$price;
            $grand_total += $current_total;
            $values = [
                'se_id'     => $supplies_expense_id,
                'name'      => $name,
                'qty'       => $quantity,
                'unit'      => $units[$key],
                'price'     => $price,
                'remarks'   => $item_remarks[$key],
                'total'     => $current_total,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s')
            ];

            if (!$this->suppliesExpenseItemModel->insert($values))
                return false;
        }

        $where = ['id' => $supplies_expense_id];

        $values = [
            'grand_total' => $grand_total,
            'balance'     => $grand_total,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesExpenseModel->update($where, $values))
            return false;

        return true;
    }


    /**
     * Attempt update
     */
    protected function _attempt_update_supplies_expense($supplies_expense_id)
    {
        $values = [
            'due_date'              => $this->request->getVar('due_date') ? : null,
            'supplier_id'           => $this->request->getVar('supplier_id') ? : null,
            // 'branch_name'           => $this->request->getVar('branch_name') ? : null,
            'forwarder_id'          => $this->request->getVar('forwarder_id'),
            'supplies_expense_date' => $this->request->getVar('supplies_expense_date') ? : null,
            'type'                  => $this->request->getVar('expense_type_id'),
            'payment_method'        => $this->request->getVar('payment_method') ? : null,
            // 'delivery_address'      => $this->request->getVar('delivery_address') ? : null,
            'remarks'               => $this->request->getVar('remarks') ? : null,
            'requisitioner'         => $this->request->getVar('requisitioner') ? : null,
            'prepared_by'           => $this->requested_by,
            'updated_by'            => $this->requested_by,
            'updated_on'            => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesExpenseModel->update($supplies_expense_id, $values)) {
            return false;
        }

        if (!$this->suppliesExpenseAttachmentModel->delete_attachments_by_supplies_expense_id($supplies_expense_id, $this->requested_by)) {
            return false;
        } elseif ($this->request->getFile('file') || $this->request->getFileMultiple('file') AND !$this->_attempt_upload_file_base64($this->suppliesExpenseAttachmentModel, ['supplies_expense_id' => $supplies_expense_id])) {
            return false;
        }

        return true;
    }

    /**
     * Update supplies expense item
     */
    protected function _attempt_update_se_item($supplies_expense_id)
    {
        $this->suppliesExpenseItemModel->delete_by_expense_id($supplies_expense_id, $this->requested_by);
        if (!$this->_attempt_generate_se_items($supplies_expense_id)) {
            return false;
        }
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($supplies_expense_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $supplies_expense_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesExpenseModel->update($where, $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        $db->transCommit();
        $db->close();

        return true;
    }

    /**
     * Attempt send email to supplier
     */
    protected function _attempt_send_email_to_supplier($supplies_expense) {
        $supplier = $this->supplierModel->get_details_by_id($supplies_expense['supplier_id']);
        $vendor   = $this->vendorModel->get_details_by_id($supplies_expense['vendor_id']);
        if (!$supplier && !$vendor) {
            var_dump('no supplier and vendor');
            return false;
        }

        $supplier_email = $supplier ? $supplier[0]['email'] : $vendor[0]['email'];
        if (!$supplier_email) {
            var_dump('no email');
            return false;
        }
        
        $supplies_expense_items = $this->suppliesExpenseItemModel->get_se_items_by_se_id($supplies_expense['id']);

        $data = [
            'supplies_expense'      => $supplies_expense,
            'supplies_expense_items'=> $supplies_expense_items,
            'supplier'              => $supplier ? $supplier[0] : $vendor[0],
        ];

        // Create an html message
        $content = view('emails/supplies_expense', $data);

        $curl = curl_init();

        $payload = [
            "sender_email" => MYT_EMAIL,
            "sender_name" => MYT_NAME,
            "sender_pass" => MYT_PASS,
            "recipients" => [$supplier_email],
            "subject" => 'Supplies Expenses',
            "content" => $content
        ];

        $this->_setup_curl($curl, $payload);

        $status = null;
        if (!$this->_attempt_change_status($supplies_expense, 'sent')) {
            var_dump('failed to change status');
            return false;
        }
        else {
            if ($response = curl_exec($curl)) {
                $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $response = json_decode(trim($response), true);
                $response['status'] = $status;
                $response['response'] = 'Email sent successfully';
            } else {
                curl_close($curl);
                $this->_error_message = "Failed to send email";
                return false;
            }
        }

        curl_close($curl);
        return $response;
    }

    /**
     * Attempt change supplies expense status
     */
    protected function _attempt_change_status($supplies_expense, $new_status)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $current_status = $supplies_expense['status'];
        $where = ['id' => $supplies_expense['id']];

        $values = [
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        switch ($new_status) {
            case 'pending':
                $values['status'] = 'pending';
                break;
            case 'for_approval':
                $values['status'] = 'for_approval';
                break;
            case 'approved':
                $values['status'] = 'approved';
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                break;
            case 'printed':
                $values['status'] = 'printed';
                $values['printed_by'] = $this->requested_by;
                $values['printed_on'] = date('Y-m-d H:i:s');
                break;
            case 'disapproved':
                $values['status'] = 'disapproved';
                $values['disapproved_by'] = $this->requested_by;
                $values['disapproved_on'] = date('Y-m-d H:i:s');
                break;
            case 'sent':
                $values['status'] = 'sent';
                $values['sent_by'] = $this->requested_by;
                $values['sent_on'] = date('Y-m-d H:i:s');
                break;
            case 'deleted':
                $values['status'] = 'deleted';
                $values['is_deleted'] = 1;
                break;
            case 'complete':
                $values['order_status'] = 'complete';
                break;
            case 'incomplete':
                $values['order_status'] = 'incomplete';
                break;
            case 'pending':
                $values['order_status'] = 'pending';
                break;
            default:
                $db->close();
                return false;
        }

        if (!$this->suppliesExpenseModel->update($where, $values)) {
            $db->transRollback();
            return false;
        }

        $db->transCommit();
        $db->close();
        return true;
    }

        /**
     * Attempt add payment in supplies_expense
     */
    protected function _attempt_add_payment($supplies_expense)
    {
        // Check if there is an existing supplies_expense payment based onthe supplies_expense id
        $supplies_expense_payment_id = null;
        if (!$supplies_expense_payment = $this->suppliesExpensePaymentModel->get_details_by_supplies_expense_id($supplies_expense['id'])) {
            // Add new supplies_expense payment
            $supplies_expense_payment = [
                'supplies_expense_id'   => $supplies_expense['id'],
                'total_payment'         => $this->request->getVar('amount'),
                'balance'               => $this->request->getVar('amount'),
                'added_by'              => $this->requested_by,
                'added_on'              => date('Y-m-d H:i:s')
            ];

            if (!$supplies_expense_payment_id = $this->suppliesExpensePaymentModel->insert($supplies_expense_payment)) {
                var_dump("failed to add supplies_expense payment");
                return false;
            }
        } else {
            // Increase the total payment and balance
            $updated_values = [
                'total_payment' => $supplies_expense_payment[0]['total_payment'] + $this->request->getVar('amount'),
                'balance'       => $supplies_expense_payment[0]['balance'] + $this->request->getVar('amount'),
                'updated_by'    => $this->requested_by,
                'updated_on'    => date('Y-m-d H:i:s')
            ];

            if (!$this->suppliesExpensePaymentModel->update($supplies_expense_payment[0]['id'], $updated_values)) {
                var_dump("failed to update supplies_expense payment");
                return false;
            }

            $supplies_expense_payment_id = $supplies_expense_payment[0]['id'];
        }

        // Create the payment details
        $payment_details = [
            'supplies_expense_id' => $supplies_expense['id'],
            'vendor_id'           => $supplies_expense['vendor_id'],
            'supplier_id'         => $supplies_expense['supplier_id'],
            'supplies_expense_payment_id' => $supplies_expense_payment_id,
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

        if (!$this->suppliesExpensePaymentDetailModel->insert($payment_details)) {
            var_dump("failed to add supplies_expense payment details");
            return false;
        }

        // Update the supplies_expense
        $supplies_expense_values = [
            'with_payment' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->suppliesExpenseModel->update($supplies_expense['id'], $supplies_expense_values)) {
            var_dump("failed to update supplies_expense");
            return false;
        }

        return true;
    }

    protected function _setup_curl($curl, $payload, $method = "POST")
    {
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://email.myt-enterprise.com/email_services/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'API-KEY: ' . 'a9452321-248b-11ee-bef7-0cc47a6461ea'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ));
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->suppliesExpenseModel              = model('App\Models\Supplies_expense');
        $this->suppliesExpenseItemModel          = model('App\Models\SE_item');
        $this->suppliesExpenseAttachmentModel    = model('App\Models\Supplies_expense_attachment');
        $this->supplierModel                     = model('App\Models\Supplier');
        $this->suppliesPaymentModel              = model('App\Models\SE_payment');
        $this->suppliesReceiveModel              = model('App\Models\Supplies_receive');
        $this->vendorModel                       = model('App\Models\Vendor');
        $this->branchModel                       = model('App\Models\Branch');
        $this->suppliesExpensePaymentModel       = model('App\Models\Supplies_expense_payment');
        $this->suppliesExpensePaymentDetailModel = model('App\Models\Supplies_expense_payment_detail');
        $this->webappResponseModel               = model('App\Models\Webapp_response');
    }
}

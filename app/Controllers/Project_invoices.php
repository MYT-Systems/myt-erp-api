<?php

namespace App\Controllers;

class Project_invoices extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get project_invoice
     */
    public function get_project_invoice()
    {
        if (($response = $this->_api_verification('project_invoices', 'get_project_invoice')) !== true)
            return $response;

        $project_invoice_id       = $this->request->getVar('project_invoice_id') ? : null;
        $project_invoice          = $project_invoice_id ? $this->projectInvoiceModel->get_details_by_id($project_invoice_id) : null;
        $project_invoice_payments = $project_invoice_id ? $this->projectInvoicePaymentModel->get_details_by_project_invoices_id($project_invoice_id) : null;
        $project_invoice_items    = $project_invoice_id ? $this->projectInvoiceItemModel->get_details_by_project_invoices_id($project_invoice_id) : null;

        foreach ($project_invoice_payments as $key => $payment) {
            $project_invoice_payments[$key]['attachment'] = $this->projectInvoicePaymentAttachmentModel->get_details_by_project_invoice_payment_id($payment['id']);
        }
        
        if (!$project_invoice) {
            $response = $this->failNotFound('No project_invoice found');
        } else {
            foreach ($project_invoice as $key => $pinv) {
                $one_time_fees = $this->projectOneTimeFeeModel->select('',['project_invoice_id' => $pinv['id'], 'project_id' => $pinv['project_id'], 'is_deleted' => 0]) ?? [];
                $recurring_costs = $this->projectRecurringCostModel->select('',['project_invoice_id' => $pinv['id'], 'project_id' => $pinv['project_id'], 'is_deleted' => 0]) ?? [];
                $data = array_merge($one_time_fees ?: [], $recurring_costs ?: []);
                if (!empty($data)) {
                    foreach ($data as $jey => $item) {
                        (float)$balance = $item['amount'];
                        $payments = $this->projectInvoicePaymentModel->get_balance($item['project_id']) ?? [];
                        foreach ($payments as $payment) {
                            //$balance -= $payment['paid_amount'];
                            $one_time_fee_pinv_items = $this->projectInvoiceItemModel->select('', ['item_id' => $item['id'], 'project_invoice_id' => $payment['project_invoice_id'], 'is_deleted' => 0]) ?? [];
                            if (!empty($one_time_fee_pinv_items)) {
                                foreach ($one_time_fee_pinv_items as $pinv_item) {
                                    $balance -= (float)$pinv_item['billed_amount'];
                                }
                            }
                        }
                        $data[$jey]['balance'] = $balance;
                    }
                }
                $project_invoice[$key]['particulars'] = $data;
            }

            $project_invoice[0]['project_invoice_payments'] = $project_invoice_payments;
            $project_invoice[0]['project_invoice_items']    = $project_invoice_items;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $project_invoice
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all project_invoices
     */
    public function get_all_project_invoice()
    {
        if (($response = $this->_api_verification('project_invoices', 'get_all_project_invoice')) !== true)
            return $response;

        $project_invoices = $this->projectInvoiceModel->get_all();

        if (!$project_invoices) {
            $response = $this->failNotFound('No project_invoice found');
        } else {
            foreach ($project_invoices as $key => $project_invoice) {
                $project_invoice_payments = $this->projectInvoicePaymentModel->get_details_by_project_invoices_id($project_invoice['id']);
                $project_invoices[$key]['project_invoice_payments'] = $project_invoice_payments;
                $project_invoices_items = $this->projectInvoiceItemModel->get_details_by_project_invoices_id($project_invoice['id']);
                $project_invoices[$key]['project_invoice_items'] = $project_invoices_items;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $project_invoices
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create project_invoice
     */
    public function create()
    {
        if (($response = $this->_api_verification('project_invoices', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$project_invoice_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Failed to create project invoice.');
        } elseif (!$this->_attempt_generate_project_invoice_items($project_invoice_id, $db)) {
            $db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$response = $this->_attempt_upload_file_base64($this->projectInvoiceAttachmentModel, ['project_expense_id' => $project_expense_id]) AND
                   $response === false) {
                $db->transRollback();
                $response = $this->respond(['response' => 'project_expense file upload failed']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'status'        => 'success',
                'project_invoice_id' => $project_invoice_id
            ]);
        }

        $db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update project_invoice
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('project_invoices', 'update')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('project_invoice_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        $project_invoice = $this->projectInvoiceModel->select('', $where, 1);
        if (!$project_invoice = $this->projectInvoiceModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_invoice not found');
        } elseif (!$this->_attempt_update($project_invoice['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update project_invoice.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_project_invoice_items($project_invoice, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update project_invoice items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'project invoice updated successfully.', 'status' => 'success']);
        }

        // Refetch the updated project_invoice
        $project_invoice = $project_invoice ? $this->projectInvoiceModel->get_details_by_id($project_invoice['id'])[0] : null;
        if (!$this->projectInvoicePaymentModel->delete_by_project_invoice_id($project_invoice['id'], $this->requested_by, $db)) {
            var_dump("failed to delete project invoice payments");      
            $response = $this->fail(['response' => 'Failed to update project_invoice payments.', 'status' => 'error']);
            $db->transRollback();
        } else if ($project_invoice && $this->request->getVar('payment_type') && $payment_id = $this->_attempt_add_payment($project_invoice)) {
            $response = $this->respond([
                'status'             => 'success',
                'project_invoice_id' => $project_invoice['id'],
                'payment_id'         => $payment_id
            ]);
        }

        // Record the credit limit since there is no payment method but the status is pending
        // Only record if the original status is not pending
        // Since it will be redundant when the user updtates it to pending
        if ($project_invoice 
            && $project_invoice['status'] == 'quoted' 
            && !$this->request->getVar('payment_type') 
            && $this->request->getVar('status') == 'pending') 
        {
            // Check if credit limit is not exceeded
            $project = $this->projectModel->get_details_by_id($project_invoice['project_id'])[0];
            // $remaining_credit = $this->projectModel->get_remaining_credit_by_project_name($project['name']);
            // if ($remaining_credit[0]['remaining_credit'] < $project_invoice['grand_total']) {
            //     var_dump("Credit limit exceeded");
            //     $db->transRollback();
            // }

            $values = [
                'status'  => 'pending',
                'updated_by' => $this->requested_by,
                'updated_on' => date('Y-m-d H:i:s')
            ];
            // Add credit limit to project
            if (!$this->_record_credit_limit($project_invoice['project_id'], $project_invoice['grand_total'])) {
                $db->transRollback();
                var_dump("record credit limit failed");
            } elseif (!$this->projectInvoiceModel->update($project_invoice['id'], $values)) {
                $db->transRollback();
                var_dump("update project invoice failed");
            } else {
                $db->transCommit();
            } 
        }

        // if ($project_invoice['status'] == 'invoiced' && !$this->_record_inventory($project_invoice)) {
        //     $response = $this->fail(['response' => 'Failed to record inventory.', 'status' => 'error']);
        //     $db->transRollback();
        // }

        // if ($project_invoice['status'] == 'invoiced') {
        //     $response = $this->fail(['response' => 'Failed to record inventory.', 'status' => 'error']);
        //     $db->transRollback();
        // }

        $db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete project_invoices
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('project_invoices', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('project_invoice_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$project_invoice = $this->projectInvoiceModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_invoice not found');
        } elseif (!$this->_attempt_delete($project_invoice, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete project_invoice.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'project_invoice deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Send project_invoices to client
     */
    public function send_to_client($id = '')
    {
        if (($response = $this->_api_verification('project_invoices', 'send_to_client')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('project_invoice_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$project_invoice = $this->projectInvoiceModel->select('', $where, 1)) {
            $response = $this->failNotFound('Project invoice not found.');
        } elseif (!$this->_attempt_send_to_client($project_invoice, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to send project invoice.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Project invoice sent successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search project_invoices based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('project_invoices', 'search')) !== true)
            return $response;

        $project_invoice_id = $this->request->getVar('project_invoice_id');
        $project_id = $this->request->getVar('project_id');
        $invoice_date = $this->request->getVar('invoice_date');
        $address = $this->request->getVar('address');
        $company = $this->request->getVar('company');
        $remarks = $this->request->getVar('remarks');
        $payment_status = $this->request->getVar('payment_status');
        $status = $this->request->getVar('status');
        $fully_paid_on = $this->request->getVar('fully_paid_on');
        $anything = $this->request->getVar('anything') ?? null;
        $date_from = $this->request->getVar('date_from')??null;
        $date_to = $this->request->getVar('date_to')??null;


        if (!$project_invoices = $this->projectInvoiceModel->search($project_invoice_id, $project_id, $invoice_date, $address, $company, $remarks, $payment_status, $status, $fully_paid_on, $anything, $date_from, $date_to)) {
            $response = $this->failNotFound('No project_invoice found');
        } else {
            $summary = [
                'total' => 0,
                'total_paid_amount' => 0,
                'total_balance' => 0,
            ];

            foreach ($project_invoices as $key => $project_invoice) {
                $project_invoice_payments = $this->projectInvoicePaymentModel->get_details_by_project_invoices_id($project_invoice['id']);
                $project_invoices[$key]['project_invoice_payments'] = $project_invoice_payments;
                $summary['total'] += $project_invoice['grand_total'];
                $summary['total_paid_amount'] += $project_invoice['paid_amount'];
                $summary['total_balance'] += $project_invoice['balance'];
            }

            $response = $this->respond([
                'summary' => $summary,
                'data'    => $project_invoices,
                'status'  => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
    
    //search project invoice new
    public function search_invoice(){
        
        if (($response = $this->_api_verification('project_invoices', 'search')) !== true)
            return $response;
        
        $seach_text = $this->request->getVar('search_text')??null;
        $status = $this->request->getVar('status')??null;
        
        if(!$project_invoices = $this->projectInvoiceModel->search_text($search_text, $status)){
            $response = $this->failNotFound('No project_invoice found');
        }
        
        $response = $this->response([
                
        ]);
        
        
    }

    /**
     * Record status change
     */
    public function record_status_change()
    {
        if (($response = $this->_api_verification('project_invoices', 'record_status_change')) !== true)
            return $response;

        $project_invoice_id = $this->request->getVar('project_invoice_id');
        $status             = $this->request->getVar('status');

        if (!$project_invoice = $this->projectInvoiceModel->get_details_by_id($project_invoice_id)) {
            $response = $this->failNotFound('project_invoice not found');
        } elseif (!$this->_attempt_record_status_change($project_invoice[0], $status)) {
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
     * Close overpaid project_invoice
     */
    public function close_overpaid_project_invoice()
    {
        if (($response = $this->_api_verification('project_invoice', 'close_overpaid_project_invoice')) !== true)
            return $response;

        $project_invoice_id = $this->request->getVar('project_invoice_id');
        $where = ['id' => $project_invoice_id, 'is_deleted' => 0];

        if (!$project_invoice = $this->projectInvoiceModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_invoice not found');
        } elseif (!$this->_attempt_close_overpaid_project_invoice($project_invoice)) {
            $response = $this->respond(['response' => 'project_invoice not closed successfully.']);
        } else {
            $response = $this->respond(['response' => 'project_invoice closed successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // Private methods
    // --------------------------------------------------------------------

    /**
     * Create project_invoices
     */
    private function _attempt_create()
    {
        $invoice_no = $this->projectInvoiceModel->get_last_invoice_no_by_year();
        
        if ($invoice_no && isset($invoice_no['invoice_no'])) {
            $last_invoice_no = $invoice_no['invoice_no']; 
            $last_number = (int) substr($last_invoice_no, 5);
        } else {
            $last_number = 0; 
        }
        
        $next_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
        $invoice_no = date('Y') . '-' . $next_number; 

        $values = [
            'project_id'            => $this->request->getVar('project_id'),
            'invoice_date'          => $this->request->getVar('invoice_date'),
            'invoice_no'            => $invoice_no,
            'project_date'          => $this->request->getVar('project_date'),
            'due_date'              => $this->request->getVar('due_date'),
            'address'               => $this->request->getVar('address'),
            'company'               => $this->request->getVar('company'),
            'remarks'               => $this->request->getVar('remarks'),
            'subtotal'              => $this->request->getVar('subtotal'),
            'vat_twelve'            => $this->request->getVar('vat_twelve'),
            'vat_net'               => $this->request->getVar('vat_net'),
            'wht'                   => $this->request->getVar('wht'),
            'is_wht'                => $this->request->getVar('is_wht'),
            'wht_percent'           => $this->request->getVar('wht_percent'),
            'service_fee'           => $this->request->getVar('service_fee'),
            'delivery_fee'          => $this->request->getVar('delivery_fee'),
            'grand_total'           => $this->request->getVar('grand_total'),
            'vat_type'              => $this->request->getVar('vat_type'),
            'balance'               => 0,
            'paid_amount'           => 0,
            'payment_status'        => 'pending',
            'discount'              => $this->request->getVar('discount'),
            'added_by'              => $this->requested_by,
            'added_on'              => date('Y-m-d H:i:s'),
        ];

        if (!$project_invoice_id = $this->projectInvoiceModel->insert($values))
           return false;

        return $project_invoice_id;
    }

    /**
     * Update project_invoices
     */
    protected function _attempt_generate_project_invoice_items($project_invoice_id, $db)
    {
        $item_ids     = $this->request->getVar('item_ids')??[];
        $item_names   = $this->request->getVar('item_names') ?? [];
        $item_balances   = $this->request->getVar('item_balances') ?? [];
        $units      = $this->request->getVar('units') ?? [];
        $prices     = $this->request->getVar('prices') ?? [];
        $subtotal   = $this->request->getVar('subtotal') ?? 0;
        // $quantities = $this->request->getVar('quantities') ?? [];
        $grand_total = $this->request->getVar('grand_total') ?? 0;
        $project_id = $this->request->getVar('project_id')??null;
        $billed_amounts = $this->request->getVar('billed_amounts') ?? [];
        $values = [
            'project_invoice_id' => $project_invoice_id,
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        foreach ($item_names as $key => $item_name) {
            // checks if it is an item in case an item_name was passed
            $item_name = $item_names[$key];
            $item_unit = $units[$key];
            $item_id = $item_ids[$key]; 
            $item_balance = $item_balances[$key]; 

            // check if the item_name key exist
            if (array_key_exists($key, $item_names)) {
                $values['item_name'] = $item_names[$key];
            } else {
                $values['item_name'] = null;
            }
            $update_occupied_where = [
                'id'    =>  $item_id,
                'description'   =>  $item_name
                
            ];
            $update_occupied = [
                //'is_occupied'   =>  1,
                'project_invoice_id' => $project_invoice_id
            ];
        
            $updated_change_requests = $this->projectChangeRequestItemModel->custom_update($update_occupied_where, $update_occupied);
            $updated_one_time_fees = $this->projectOneTimeFeeModel->custom_update($update_occupied_where,$update_occupied);
            $updated_recurring_costs = $this->projectRecurringCostModel->custom_update($update_occupied_where,$update_occupied);
            if(!$updated_one_time_fees && !$updated_recurring_costs && !$updated_change_requests){
                $this->errorMessage = $db->error()['message'];
                return false;
            }
        
            $values['item_id']    = $item_id;
            $values['item_name']    = $item_name;
            $values['item_balance']    = $item_balance;
            $values['unit']         = $units[$key];
            $values['price']        = $prices[$key];
            // $values['qty']          = $quantities[$key];
            $values['subtotal']     = $subtotal;
            $values['billed_amount'] = $billed_amounts[$key];

            if (!$this->projectInvoiceItemModel->insert($values)) {
                $this->errorMessage = $db->error()['message'];
                return false;
            }

        }

        $values = [
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];
        
        if ($project_invoice = $this->projectInvoiceModel->get_details_by_id($project_invoice_id)) {
            $values['balance'] = $grand_total - $project_invoice[0]['paid_amount'];
        } else {
            $values['balance'] = $grand_total;
        }

        // Check if balance is greater than 0
        if ($values['balance'] > 0) {
            $values['payment_status'] = 'open_bill';
        } else {
            $values['payment_status'] = 'closed_bill';
        }

        if (!$this->projectInvoiceModel->update($project_invoice_id, $values)) {
            $this->errorMessage = $db->error()['message'];
            return false;
        }

        return $grand_total;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($project_invoice_id)
    {

        $values = [
            'project_id'            => $this->request->getVar('project_id'),
            'invoice_date'          => $this->request->getVar('invoice_date'),
            'invoice_no'            => $this->request->getVar('invoice_no'),
            'project_date'          => $this->request->getVar('project_date'),
            'due_date'              => $this->request->getVar('due_date'),
            'address'               => $this->request->getVar('address'),
            'company'               => $this->request->getVar('company'),
            'remarks'               => $this->request->getVar('remarks'),
            'subtotal'              => $this->request->getVar('subtotal'),
            'service_fee'           => $this->request->getVar('service_fee'),
            'delivery_fee'          => $this->request->getVar('delivery_fee'),
            'vat_twelve'            => $this->request->getVar('vat_twelve'),
            'vat_net'               => $this->request->getVar('vat_net'),
            'wht'                   => $this->request->getVar('wht'),
            'is_wht'                => $this->request->getVar('is_wht'),
            'wht_percent'           => $this->request->getVar('wht_percent'),
            'grand_total'           => $this->request->getVar('grand_total'),
            'vat_type'              => $this->request->getVar('vat_type'),
            'updated_by'            => $this->requested_by,
            'updated_on'            => date('Y-m-d H:i:s')
        ];

        if (!$this->projectInvoiceModel->update($project_invoice_id, $values)) {
            var_dump("JKJK");
            return false;            
        }


        if (!$this->projectInvoiceAttachmentModel->delete_attachments_by_project_invoice_id($project_invoice_id, $this->requested_by)) {
            var_dump("DFDF");
            return false;
        } elseif ($this->request->getFile('file') AND
                  $this->projectInvoiceAttachmentModel->delete_attachments_by_project_invoice_id($project_invoice_id, $this->requested_by)
        ) {
            var_dump("HEY");
            return false;
            // $this->_attempt_upload_file_base64($this->projectInvoiceAttachmentModel, ['expense_id' => $expense_id]);
        } elseif(($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$response = $this->_attempt_upload_file_base64($this->projectInvoiceAttachmentModel, ['project_expense_id' => $project_expense_id]) AND
                   $response === false) {
            var_dump("THIS");
            return false;
        }

        return true;
    }

    /**
     * Attempt to revert and delete project invoice items
     */
    protected function _revert_project_item($project_invoice_id)
    {
        // revert the balance and grand total
        $project_invoice_items = $this->projectInvoiceItemModel->get_details_by_project_invoices_id($project_invoice_id);

        foreach ($project_invoice_items as $project_invoice_item) {
            if ($item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($project_invoice_item['item_name'], $project_invoice_item['unit'])) {
                if ($seller_inventory = $this->inventoryModel->get_inventory_detail($project_invoice_item['item_name'], $seller_branch_id, $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $seller_inventory[0]['current_qty'] + $project_invoice_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];

                    if (!$this->inventoryModel->update($seller_inventory[0]['id'], $new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                }

                if ($buyer_inventory = $this->inventoryModel->get_inventory_detail($project_invoice_item['item_name'], $buyer_branch_id, $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $buyer_inventory[0]['current_qty'] - $project_invoice_item['qty'],
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
     * Attempt generate project invoices items
     */
    protected function _attempt_update_project_invoice_items($project_invoice, $db)
    {
        $old_grand_total  = $project_invoice['grand_total'];

        // if (!$this->_revert_project_item($project_invoice['id'])) {
        //     var_dump("failed to revert and delete");
        //     return false;
        // }

        if (!$this->projectInvoiceItemModel->delete_by_project_invoice_id($project_invoice['id'], $this->requested_by, $db)) {
            var_dump("failed to delete project invoice item model");      
            return false;
        }

        // Reset the credit limit
        // if ($project_invoice['status'] != 'quoted') {
        //     var_dump("failed to restore credit limit");
        //     return false;
        // }

        // insert new project invoice items
        if (!$grand_total = $this->_attempt_generate_project_invoice_items($project_invoice['id'], $db)) {
            var_dump("Error in generating project invoice items");
            return false;
        }

        // Check if new grand total is under credit limit
        // if ($project_invoice['status'] != 'quoted') {
        //     var_dump("New grand total is over the credit limit");
        //     return false;
        // }

        // Record the new credit limit
        // if ($project_invoice['status'] != 'quoted' && !$this->_record_credit_limit($project_invoice['project_id'], $grand_total)) {
        //     var_dump("record credit limit failed");
        //     return false;
        // }

        return true;
    }

    /**
     * Attempt send to client
     */
    protected function _attempt_send_to_client($project_invoice, $db)
    {        
        $values = [
            'status' => 'sent',
            'is_sent' => 1,
            'sent_by' => $this->requested_by,
            'sent_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectInvoiceModel->update($project_invoice['id'], $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($project_invoice, $db)
    {

        // if (!$this->_revert_project_item($project_invoice['id'])) {
        //     var_dump("failed to revert and delete");
        //     return false;
        // } else
        
        if (!$this->projectInvoiceItemModel->delete_by_project_invoice_id($project_invoice['id'], $this->requested_by, $db)) {
            var_dump("failed to delete project invoice item model");
            return false;
        }
        
        $update_occupied_where = [
            'project_invoice_id'    =>  $project_invoice['id']
        ];
        
        $update_occupied = [
            'is_occupied'   =>  0,
            'project_invoice_id' => NULL
        ];
        $updated_one_time_fees = $this->projectOneTimeFeeModel->custom_update($update_occupied_where,$update_occupied);
        $updated_recurring_costs = $this->projectRecurringCostModel->custom_update($update_occupied_where,$update_occupied);
        
        if(!$updated_one_time_fees && !$updated_recurring_costs){
            
            $this->errorMessage = $db->error()['message'];
            return false;
        }

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectInvoiceModel->update($project_invoice['id'], $values))
            return false;

        return true;
    }

    /**
     * Restore credit limit
     */
    protected function _restore_credit_limit($project_invoice)
    {

        if (!$project = $this->projectModel->get_details_by_id($project_invoice['project_id'])) {
            var_dump("failed to get project");
            return false;
        }

        $project = $project[0];
        $new_values = [
            // 'current_credit_limit' => $project['current_credit_limit'] + $project_invoice['grand_total'],
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s'),
        ];

        if (!$this->projectModel->update($project['id'], $new_values)) {
            var_dump("failed to update project model");
            return false;
        }


        return $new_values['current_credit_limit'] ?? true;
    }

    /**
     * Attempt add payment
     */
    protected function _attempt_add_payment($project_invoice)
    {
        $db = \Config\Database::connect();
        
        $values = [
            'project_id'      => $this->request->getVar('project_id'),
            'project_invoice_id' => $project_invoice['id'],
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

        // Check if the project can create the order request based on the credit limit and paid amount
        $project = $this->projectModel->get_details_by_id($values['project_id']);
        // $remaining_credit = $this->projectModel->get_remaining_credit_by_project_name($project['name']);
        // $current_credit_limit = $remaining_credit[0]['remaining_credit'];
        $paid_amount = $values['paid_amount'];
        $grand_total = $values['grand_total'];

        // Check if the project can create the order request based on the credit limit and paid amount
        // if (((float)$paid_amount) < (float)$grand_total && (float)$paid_amount != (float)$grand_total) {
        //     $db->close();
        //     var_dump("Exceed credit limit, grand total: $grand_total, paid amount: $paid_amount");
        //     var_dump("Need to pay: " . ((float)$grand_total - ((float)$paid_amount)));
        //     return false;
        // }

        if (!$project_invoice_payment_id = $this->projectInvoicePaymentModel->insert($values)) {
            $db->transRollback();
            $db->close();
            var_dump("Failed to create project invoice payment.");
            return false;
        }
    
        // check if project invoice fs status is invoiced or pending
        // don't record anymore since it's already recorded during item update
        // if ($project_invoice['status'] != 'invoiced' && $project_invoice['status'] != 'pending') {
        //     if (!$this->_record_credit_limit($values['project_id'], $grand_total)) {
        //         $db->transRollback();
        //         $db->close();
        //         var_dump("record credit limit failed");
        //         return false;
        //     }
        // }
        
        // Record the payment
        if (!$this->_record_sale_payment($project_invoice, $values)) {
            $db->transRollback();
            $db->close();
            var_dump("Failed to record sale payment.");
            return false;
        }

        $values = [
            'status' => 'pending',
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectInvoiceModel->update($project_invoice['id'], $values)) {
            $db->transRollback();
            $db->close();
            var_dump("Failed to update project invoice status.");
            return false;
        }

        $db->transCommit();
        $db->close();
        
        return $project_invoice_payment_id;
    }

    /**
     * Record Frachisee Sale Payment
     */
    protected function _record_sale_payment($project_invoice, $values) {
        $update_values = [
            'balance'     => $project_invoice['balance'] - $values['paid_amount'],
            'paid_amount' => $project_invoice['paid_amount'] + $values['paid_amount'],
            'updated_by'  => $this->requested_by,   
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if ($update_values['balance'] <= 0) {
            $update_values['payment_status'] = 'closed_bill';
            $update_values['fully_paid_on']  = date('Y-m-d H:i:s');
        } else {
            $update_values['payment_status'] = 'open_bill';
        }

        if (!$this->projectInvoiceModel->update($project_invoice['id'], $update_values))
            return false;

        // if (!$this->_record_credit_limit($project_invoice['project_id'], (float)$values['paid_amount'] * -1)) {
        //     var_dump("Failed to increase credit limit.");
        //     return false;
        // }

        return true;
    }

    /**
     * Attempt change status
     */
    protected function _attempt_record_status_change($project_invoice, $status)
    {
        $db = \Config\Database::connect();

        $values = [
            'project_invoice_id' => $project_invoice['id'],
            'added_by'           => $this->requested_by,
            'added_on'           => date('Y-m-d H:i:s'),
        ];

        switch ($status) {
            case 'invoiced':
                $values['status'] = 'invoiced';
                // if ($project_invoice['status'] != 'invoiced' && !$this->_record_inventory($project_invoice)) {
                //     var_dump("Failed to record inventory.");
                //     return false;
                // }
                // if ($project_invoice['status'] != 'invoiced') {
                //     var_dump("Failed to record inventory.");
                //     return false;
                // }
                break;
            case 'pending':
                $values['status'] = 'pending';
                if ($project_invoice['status'] == 'invoiced') {

                    // if (!$this->_revert_project_item($project_invoice['id'])) {
                    //     var_dump("failed to revert and delete");
                    //     return false;
                    // }
                }
                break;
            default:
                return false;
        }

        if (!$this->projectInvoiceModel->update($project_invoice['id'], $values)) {
            $db->transRollback();
            var_dump("Failed to update project invoice status.");
            return false;
        }

        $db->transCommit();
        $db->close();
        return true;
    }

    /**
     * record inventory 
     */
    private function _record_inventory($project_invoice)
    {
        $project_invoice_items = $this->projectInvoiceItemModel->get_details_by_project_invoices_id($project_invoice['id']);
        foreach ($project_invoice_items as $project_invoice_item) {
            if (!$project_invoice_item['item_name']) {
                continue;
            }

            if ($item_unit = $this->itemUnitModel->get_details_by_item_id_and_unit($project_invoice['seller_branch_id'], $project_invoice_item['item_name'], $project_invoice_item['unit'])) {
                if ($seller_inventory = $this->inventoryModel->get_inventory_detail($project_invoice_item['item_name'], $project_invoice['seller_branch_id'], $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $seller_inventory[0]['current_qty'] - $project_invoice_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->update($seller_inventory[0]['id'], $new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                } else {
                    $new_values = [
                        'item_name'       => $project_invoice_item['item_name'],
                        'branch_id'     => $project_invoice['seller_branch_id'],
                        'item_unit_id'  => $item_unit[0]['id'],
                        'beginning_qty' => 0,
                        'current_qty'   => (float)$project_invoice_item['qty'] * -1,
                        'added_by'      => $this->requested_by,
                        'added_on'      => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->insert($new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                }
    
                if ($buyer_inventory = $this->inventoryModel->get_inventory_detail($project_invoice_item['item_name'], $project_invoice['buyer_branch_id'], $item_unit[0]['id'])) {
                    $new_values = [
                        'current_qty' => $buyer_inventory[0]['current_qty'] + $project_invoice_item['qty'],
                        'updated_by'  => $this->requested_by,
                        'updated_on'  => date('Y-m-d H:i:s'),
                    ];
    
                    if (!$this->inventoryModel->update($buyer_inventory[0]['id'], $new_values)) {
                        var_dump($this->inventoryModel->errors());
                        return false;
                    }
                } else {
                    $new_values = [
                        'item_name'       => $project_invoice_item['item_name'],
                        'branch_id'     => $project_invoice['buyer_branch_id'],
                        'item_unit_id'  => $item_unit[0]['id'],
                        'beginning_qty' => 0,
                        'current_qty'   => $project_invoice_item['qty'],
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
     * Record credit limit base on project id
     */
    protected function _record_credit_limit($project_id, $grand_total)
    {
        // var_dump("Grand total: " . $grand_total);
        if (!$project = $this->projectModel->get_details_by_id($project_id))
            return false;
        
        $project = $project[0];

        $values = [
            // 'current_credit_limit' => $project['current_credit_limit'] - $grand_total,
            'updated_by'           => $this->requested_by,
            'updated_on'           => date('Y-m-d H:i:s')
        ];

        if (!$this->projectModel->update($project_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt close overpaid project invoices
     */
    private function _attempt_close_overpaid_project_invoice($project_invoice) {
        $value = [
            'is_closed'  => 1,
            'remarks'    => $project_invoice['remarks'] . ' - ' . $this->request->getVar('remarks'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectInvoiceModel->update($project_invoice['id'], $value)) {
            $db->transRollback();
            $db->close();
            var_dump("Error updating project_invoice");
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->projectInvoiceModel        = model('App\Models\Project_invoice');
        $this->projectInvoiceItemModel    = model('App\Models\Project_invoice_item');
        $this->projectInvoicePaymentModel = model('App\Models\Project_invoice_payment');
        $this->projectInvoicePaymentAttachmentModel = model('App\Models\Project_invoice_payment_attachment');
        $this->projectInvoiceAttachmentModel = model('App\Models\Project_invoice_attachment');
        $this->projectOneTimeFeeModel     = model('App\Models\Project_one_time_fee');
        $this->projectRecurringCostModel  = model('App\Models\Project_recurring_cost');
        $this->projectChangeRequestItemModel     = model('App\Models\Project_change_request_item');
        $this->projectModel               = model('App\Models\Project');
        $this->itemUnitModel              = model('App\Models\Item_unit');
        $this->inventoryModel             = model('App\Models\Inventory');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}
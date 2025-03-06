<?php

namespace App\Controllers;

class Project_change_requests extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get project_change_request
     */
    public function get_project_change_request_items()
    {
        if (($response = $this->_api_verification('project_change_requests', 'get_project_change_request_items')) !== true)
            return $response;

        $project_id                      = $this->request->getVar('project_id') ? : null;
        $project                         = $project_id ? $this->projectModel->get_details_by_id($project_id) : null;
        $project_change_request          = $project_id ? $this->projectChangeRequestModel->get_details_by_project_id($project_id) : null;

        if (!empty($project_change_request)) {
            foreach ($project_change_request as $index => $change_request) {
                $project_change_request[$index]['project_change_request_item'] = $this->projectChangeRequestItemModel->get_details_by_project_change_requests_id($change_request['id']);
            }
        }
        
        if (!$project) {
            $response = $this->failNotFound('No project found');
        } else {
            $project[0]['change_request'] = $project_change_request;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $project
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all project_change_requests
     */
    public function get_all_project_change_request()
    {
        if (($response = $this->_api_verification('project_change_requests', 'get_all_project_change_request')) !== true)
            return $response;

        $project_change_requests = $this->projectChangeRequestModel->get_all();

        if (!$project_change_requests) {
            $response = $this->failNotFound('No project_change_request found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $project_change_requests
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create project_change_request
     */
    public function create()
    {
        if (($response = $this->_api_verification('project_change_requests', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if ($this->projectChangeRequestModel->select('', ['request_no' => $this->request->getVar('request_no')], 1)) {
            $db->transRollback();
            $response = $this->fail('Project request number already exists.');
        } elseif ($this->projectChangeRequestItemModel->select('', ['name' => $this->request->getVar('names')], 1)) {
            $db->transRollback();
            $response = $this->fail('Project request name already exists.');
        } elseif (!$project_change_request_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Failed to create project change request.');
        } elseif (!$this->_attempt_generate_project_change_request_items($project_change_request_id)) {
            $db->transRollback();
            $response = $this->fail($this->errorMessage);
        }else {
            $db->transCommit();
            $response = $this->respond([
                'status'        => 'success',
                'project_change_request_id' => $project_change_request_id
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
        if (($response = $this->_api_verification('project_change_requests', 'update')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('project_change_request_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        $project_change_request = $this->projectChangeRequestModel->select('', $where, 1);
        if (!$project_change_request = $this->projectChangeRequestModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_change_request not found');
        } elseif (!$this->_attempt_update($project_change_request['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update project_change_request.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_project_change_request_items($project_change_request, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update project_change items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'project change request updated successfully.', 'status' => 'success']);
        }

        $db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete project_invoices
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('project_change_requests', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('project_change_request_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$project_change_request = $this->projectChangeRequestModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_change_request not found');
        } elseif (!$this->_attempt_delete($project_change_request, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete project_change_request.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'project_invoice deleted successfully.', 'status' => 'success']);
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
        if (($response = $this->_api_verification('project_change_requests', 'search')) !== true)
            return $response;

        $project_invoice_id = $this->request->getVar('project_change_request_id');
        $project_id = $this->request->getVar('project_id');
        $request_date = $this->request->getVar('request_date');
        $change_request_no = $this->request->getVar('change_request_no');
        $change_request_name = $this->request->getVar('change_request_name');
        $description = $this->request->getVar('description');
        $remarks = $this->request->getVar('remarks');
        $anything = $this->request->getVar('anything') ?? null;
        $date_from = $this->request->getVar('date_from')??null;
        $date_to = $this->request->getVar('date_to')??null;


        if (!$project_change_requests = $this->projectChangeRequestModel->search($project_change_request_id, $project_id, $request_date, $address, $company, $remarks, $payment_status, $status, $fully_paid_on, $anything)) {
            $response = $this->failNotFound('No project_change_request found');
        } else {

            $response = $this->respond([
                'data'    => $project_change_requests,
                'status'  => 'success',
            ]);
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
        $values = [
            'project_id'            => $this->request->getVar('project_id'),
            'request_date'          => $this->request->getVar('request_date'),
            'request_no'            => $this->request->getVar('request_no'),
            'remarks'               => $this->request->getVar('remarks'),
            'subtotal'              => $this->request->getVar('subtotal'),
            'vat_twelve'            => $this->request->getVar('vat_twelve'),
            'vat_net'               => $this->request->getVar('vat_net'),
            'wht'                   => $this->request->getVar('wht'),
            'is_wht'                => 0,
            'wht_percent'           => $this->request->getVar('wht_percent'),
            'grand_total'           => $this->request->getVar('grand_total'),
            'vat_type'              => $this->request->getVar('vat_type'),
            'discount'              => $this->request->getVar('discount'),
            'balance'               => 0,
            'paid_amount'           => 0,
            'added_by'              => $this->requested_by,
            'added_on'              => date('Y-m-d H:i:s'),
        ];

        //var_dump($this->projectChangeRequestModel->insert($values)); die();

        if (!$project_change_request_id = $this->projectChangeRequestModel->insert($values))
           return false;

        return $project_change_request_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($project_change_request_id)
    {

        $values = [
            'project_id'            => $this->request->getVar('project_id'),
            'project_invoice_id'    => $this->request->getVar('project_invoice_id'),
            'request_date'          => $this->request->getVar('request_date'),
            'change_request_no'     => $this->request->getVar('change_request_no'),
            'change_request_name'   => $this->request->getVar('change_request_name'),
            'description'           => $this->request->getVar('description'),
            'remarks'               => $this->request->getVar('remarks'),
            'subtotal'              => $this->request->getVar('subtotal'),
            'vat_twelve'            => $this->request->getVar('vat_twelve'),
            'vat_net'               => $this->request->getVar('vat_net'),
            'wht'                   => $this->request->getVar('wht'),
            'is_wht'                => 0,
            'grand_total'           => $this->request->getVar('grand_total'),
            'vat_type'              => $this->request->getVar('vat_type'),
            'updated_by'            => $this->requested_by,
            'updated_on'            => date('Y-m-d H:i:s')
        ];

        if (!$this->projectChangeRequestModel->update($project_change_request_id, $values)) {
            var_dump("JKJK");
            return false;            
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($project_change_request, $db)
    {

        // if (!$this->_revert_project_item($project_invoice['id'])) {
        //     var_dump("failed to revert and delete");
        //     return false;
        // } else
        
        $update_occupied_where = [
            'project_change_request_id'    =>  $project_change_request['id']
        ];
        
        $update_occupied = [
            'is_occupied'   =>  0,
            'project_change_request_id' => NULL
        ];
        
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectChangeRequestModel->update($project_change_request['id'], $values))
            return false;

        return true;
    }

    /**
     * Attempt generate project_change_request_items
     */
    protected function _attempt_generate_project_change_request_items($project_change_request_id)
    {
        $ids = $this->request->getVar('ids') ?? [];
        $names = $this->request->getVar('names') ?? [];
        $descriptions = $this->request->getVar('descriptions') ?? [];
        $amounts = $this->request->getVar('amounts') ?? [];

        // var_dump($names); die();
    
        // Define the query to fetch existing records
        $where = [
            'project_change_request_id' => $project_change_request_id,
            'is_deleted' => 0
        ];
        
        // Retrieve existing fee IDs for the project
        $existingFeeIds = $this->projectChangeRequestItemModel->select('id', $where);
        
        if($existingFeeIds){
            $existingFeeIds = array_column($existingFeeIds, 'id');
        
            // Determine IDs to soft delete (those not in the request)
            $idsToDelete = array_diff($existingFeeIds, $ids);
            
            // Soft delete records that are not in the request
            if (!empty($idsToDelete)) {
                $dataToUpdate = [
                    'is_deleted' => 1,
                    'updated_by' => $this->requested_by,
                    'updated_on' => date('Y-m-d H:i:s'),
                ];
        
                // Use standard query format to soft delete
                foreach ($idsToDelete as $id) {
                    if (!$this->projectChangeRequestItemModel->update($id, $dataToUpdate)) {
                        $this->errorMessage = $this->db->error()['message'];
                        return false;
                    }
                }
            }
        }
    
        // Process each fee in the request
        foreach ($names as $i => $name) {
            $id = $ids[$i] ?? null;
            $data = [
                'project_change_request_id'  => $project_change_request_id,
                'name' => $name,
                'description' => $descriptions[$i],
                'amount'      => $amounts[$i],
                'balance'     => $amounts[$i],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s'),
            ];
    
            if ($id) {
                // Update existing record
                $data['updated_on'] = date('Y-m-d H:i:s');
                $data['updated_by'] = $this->requested_by;
                if (!$this->projectChangeRequestItemModel->update($id, $data)) {
                    $this->errorMessage = $this->db->error()['message'];
                    return false;
                }
            } else {
                // Insert new record
                if (!$this->projectChangeRequestItemModel->insert($data)) {
                    $this->errorMessage = $this->db->error()['message'];
                    return false;
                }
            }
        }
    
        return true;
    }

    /**
     * Attempt generate project invoices items
     */
    protected function _attempt_update_project_change_request_items($project_change_request, $db)
    {
        $old_grand_total  = $project_invoice['grand_total'];

        // if (!$this->_revert_project_item($project_invoice['id'])) {
        //     var_dump("failed to revert and delete");
        //     return false;
        // }

        if (!$this->projectChangeRequestItemModel->delete_by_project_change_request_id($project_change_request['id'], $this->requested_by, $db)) {
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
     * Update project_invoices
     */
    // protected function _attempt_generate_project_change_request_items($project_change_request_id, $db)
    // {
    //     $ids     = $this->request->getVar('ids')??[];
    //     $names   = $this->request->getVar('names') ?? [];
    //     $balances   = $this->request->getVar('balances') ?? [];
    //     $amounts     = $this->request->getVar('amounts') ?? [];
    //     $project_id = $this->request->getVar('project_id')??null;
    //     $billed_amounts = $this->request->getVar('billed_amounts') ?? [];
    //     $values = [
    //         'project_change_request_id' => $project_change_request_id,
    //         'added_by'           => $this->requested_by,
    //         'added_on'           => date('Y-m-d H:i:s'),
    //     ];

    //     foreach ($names as $key => $name) {
    //         $subtotal = $amounts[$key]

    //         // checks if it is an item in case an item_name was passed
    //         $name = $names[$key];
    //         $balance = $balances[$key]; 
    //         $id = $ids[$key]; 

    //         // check if the item_name key exist
    //         if (array_key_exists($key, $names)) {
    //             $values['name'] = $names[$key];
    //         } else {
    //             $values['name'] = null;
    //         }
    //         $update_occupied_where = [
    //             'id'    =>  $id,
    //             'name'   =>  $name  
    //         ];
    //         $update_occupied = [
    //             //'is_occupied'   =>  1,
    //             'project_invoice_id' => $project_invoice_id
    //         ];
        
    //         $updated_change_requests = $this->projectChangeRequestModel->custom_update($update_occupied_where,$update_occupied);
    //         if(!$updated_change_requests){
    //             $this->errorMessage = $db->error()['message'];
    //             return false;
    //         }
        
    //         $values['id']    = $id;
    //         $values['name']    = $name;
    //         $values['balance']    = $balance;
    //         $values['amount']        = $amounts[$key];
    //         $values['subtotal']     = $subtotal;
    //         $values['billed_amount'] = $billed_amounts[$key];

    //         if (!$this->projectChangeRequestModel->insert($values)) {
    //             $this->errorMessage = $db->error()['message'];
    //             return false;
    //         }

    //     }

    //     $values = [
    //         'updated_by'  => $this->requested_by,
    //         'updated_on'  => date('Y-m-d H:i:s'),
    //     ];
        
    //     if ($project_invoice = $this->projectInvoiceModel->get_details_by_id($project_invoice_id)) {
    //         $values['balance'] = $grand_total - $project_invoice[0]['paid_amount'];
    //     } else {
    //         $values['balance'] = $grand_total;
    //     }

    //     // Check if balance is greater than 0
    //     if ($values['balance'] > 0) {
    //         $values['payment_status'] = 'open_bill';
    //     } else {
    //         $values['payment_status'] = 'closed_bill';
    //     }

    //     if (!$this->projectInvoiceModel->update($project_invoice_id, $values)) {
    //         $this->errorMessage = $db->error()['message'];
    //         return false;
    //     }

    //     return $grand_total;
    // }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->projectInvoiceModel        = model('App\Models\Project_invoice');
        $this->projectInvoiceItemModel    = model('App\Models\Project_invoice_item');
        $this->projectInvoicePaymentModel = model('App\Models\Project_invoice_payment');
        $this->projectInvoiceAttachmentModel = model('App\Models\Project_invoice_attachment');
        $this->projectChangeRequestModel  = model('App\Models\Project_change_request');
        $this->projectChangeRequestItemModel  = model('App\Models\Project_change_request_item');
        $this->projectOneTimeFeeModel     = model('App\Models\Project_one_time_fee');
        $this->projectRecurringCostModel  = model('App\Models\Project_recurring_cost');
        $this->projectModel               = model('App\Models\Project');
        $this->itemUnitModel              = model('App\Models\Item_unit');
        $this->inventoryModel             = model('App\Models\Inventory');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}
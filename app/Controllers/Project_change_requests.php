<?php

namespace App\Controllers;

class project_change_request extends MYTController
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
    public function get_project_change_request()
    {
        if (($response = $this->_api_verification('project_change_request', 'get_project_change_request')) !== true)
            return $response;

        $project_change_request_id       = $this->request->getVar('project_change_request_id') ? : null;
        $project_change_request          = $project_change_request_id ? $this->projectChangeRequestModel->get_details_by_id($project_change_request_id) : null;
        
        if (!$project_invoice) {
            $response = $this->failNotFound('No project_change_request found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $project_change_request
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

        if (!$project_invoice_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Failed to create project change request.');
        } elseif (!$this->_attempt_generate_project_change_request_items($project_change_request_id, $db)) {
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
            $response = $this->failNotFound('project not found');
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
            'request_no'     => $this->request->getVar('request_no'),
            'remarks'               => $this->request->getVar('remarks'),
            'subtotal'              => $this->request->getVar('subtotal'),
            'vat_twelve'            => $this->request->getVar('vat_twelve'),
            'vat_net'               => $this->request->getVar('vat_net'),
            'wht'                   => $this->request->getVar('wht'),
            'is_wht'                => $this->request->getVar('is_wht'),
            'grand_total'           => $this->request->getVar('grand_total'),
            'vat_type'              => $this->request->getVar('vat_type'),
            'discount'              => $this->request->getVar('discount'),
            'balance'               => 0,
            'paid_amount'           => 0,
            'discount'              => $this->request->getVar('discount'),
            'added_by'              => $this->requested_by,
            'added_on'              => date('Y-m-d H:i:s'),
        ];

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
            'is_wht'                => $this->request->getVar('is_wht'),
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
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->projectInvoiceModel        = model('App\Models\Project_invoice');
        $this->projectInvoiceItemModel    = model('App\Models\Project_invoice_item');
        $this->projectInvoicePaymentModel = model('App\Models\Project_invoice_payment');
        $this->projectInvoiceAttachmentModel = model('App\Models\Project_invoice_attachment');
        $this->projectChangeRequestModel  = model('App\Models\Project_change_request');
        $this->projectOneTimeFeeModel     = model('App\Models\Project_one_time_fee');
        $this->projectRecurringCostModel  = model('App\Models\Project_recurring_cost');
        $this->projectModel               = model('App\Models\Project');
        $this->itemUnitModel              = model('App\Models\Item_unit');
        $this->inventoryModel             = model('App\Models\Inventory');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}
<?php

namespace App\Controllers;

class Requests extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get request
     */
    public function get_request()
    {
        if (($response = $this->_api_verification('request', 'get_request')) !== true)
            return $response;

        $request_id    = $this->request->getVar('request_id') ? : null;
        $request       = $request_id ? $this->requestModel->get_details_by_id($request_id) : null;
        $request_items = $request_id ? $this->requestItemModel->get_details_by_request_id($request_id) : null;


        if (!$request) {
            $response = $this->failNotFound('No request found');
        } else {
            $request[0]['request_items'] = $request_items;
            $response = $this->respond([
                'data' => $request,
                'status' => 'success'
            ]);
        }


        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all request
     */
    public function get_all_request()
    {
        if (($response = $this->_api_verification('request', 'get_all_request')) !== true)
            return $response;

        $requests = $this->requestModel->get_all_request();

        if (!$requests) {
            $response = $this->failNotFound('No request found');
        } else {
            foreach ($requests as $key => $request) {
                $requests[$key]['request_items'] = $this->requestItemModel->get_details_by_request_id($request['id']);
            }
            $response = $this->respond([
                'data' => $requests,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

        /**
     * Create Tranfer
     */
    public function create()
    {
        if (($response = $this->_api_verification('requests', 'create')) !== true) 
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$request_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else if (!$this->_attempt_generate_request_items($request_id)) {
            $response = $this->fail($this->errorMessage);
            $this->db->transRollback();
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response' => 'Request created successfully',
                'status' => 'success',
                'request_id' => $request_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update Request
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('requests', 'request_request_orders')) !== true)
            return $response;

        $request_id = $this->request->getVar('request_id');
        $where      = ['id' => $request_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$request = $this->requestModel->select('', $where, 1)) {
            $response = $this->failNotFound('Request not found');
        } elseif (!$this->_attempt_update_request($request_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_update_request_items($request_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Request updated successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    /**
     * Delete request
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('requests', 'delete')) !== true)
            return $response;

        $request_id = $this->request->getVar('request_id');
        $where = ['id' => $request_id, 'is_deleted' => 0];

        if (!$request = $this->requestModel->select('', $where, 1)) {
            $response = $this->failNotFound('request not found');
        } elseif (!$this->_attempt_delete($request_id)) {
            $response = $this->fail($this->errorMessage);
        } else {
            $response = $this->respond(['response' => 'request deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search request based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('request', 'search')) !== true)
            return $response;

        $branch_from       = $this->request->getVar('branch_from');
        $branch_to         = $this->request->getVar('branch_to');
        $branch_to_name    = $this->request->getVar('branch_to_name');
        $transfer_number   = $this->request->getVar('transfer_number');
        $request_number    = $this->request->getVar('request_number');
        $request_date_from = $this->request->getVar('request_date_from');
        $request_date_to   = $this->request->getVar('request_date_to');
        $remarks           = $this->request->getVar('remarks');
        $grand_total       = $this->request->getVar('grand_total');
        $status            = $this->request->getVar('status');
        $limit_by          = $this->request->getVar('limit_by');

        if (!$requests = $this->requestModel->search($branch_from, $branch_to, $branch_to_name, $request_number, $transfer_number, $request_date_from, $request_date_to, $remarks, $grand_total, $status, $limit_by)) {
            $response = $this->failNotFound('No request found');
        } else {
            foreach ($requests as $key => $request) {
                $processors = $this->requestModel->get_processors_by_request_id($request['id']);
                $processors = array_column($processors, 'processor_name');
                $requests[$key]['processed_by'] = $processors[0];
                $requests[$key]['request_items'] = $this->requestItemModel->get_details_by_request_id($request['id']);
            }
            $response = [];
            $response['data'] = $requests;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Change status of request
     */
    public function change_status()
    {
        if (($response = $this->_api_verification('requests', 'change_status')) !== true)
            return $response;

        $request_id = $this->request->getVar('request_id');
        $new_status = $this->request->getVar('new_status');

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$request = $this->requestModel->select('', ['id' => $request_id, 'is_deleted' => 0], 1)) {
            $response = $this->fail(['response' => 'Request not found']);
        } elseif (!$this->_attempt_change_status($request, $new_status)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Request status changed successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create request
     *
     * @return int|boolean
     */
    protected function _attempt_create()
    {
        $request_date = $this->request->getVar('request_date');
        $request_date = date("Y-m-d", strtotime($request_date));
        $delivery_date = $this->request->getVar('delivery_date');
        $delivery_date = date("Y-m-d", strtotime($delivery_date));

        $values = [
            'branch_from'     => $this->request->getVar('branch_from'),
            'branch_to'       => $this->request->getVar('branch_to'),
            'transfer_number' => $this->request->getVar('transfer_number'),
            'request_date'    => $request_date,
            'remarks'         => $this->request->getVar('remarks'),
            'grand_total'     => $this->request->getVar('grand_total'),
            'status'          => 'for_approval',
            'encoded_by'      => $this->request->getVar('encoded_by'),
            'delivery_date'   => $delivery_date,
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s'),
            'is_deleted'      => 0
        ];


        if (!$request_id = $this->requestModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $request_id;
    }

    /**
     * Attempt generate PO
     */
    protected function _attempt_generate_request_items($request_id)
    {
        $item_ids   = $this->request->getVar('item_ids');
        $units      = $this->request->getVar('units');
        $quantities = $this->request->getVar('quantities');
        
        $grand_total = 0;
        foreach ($item_ids as $key => $item_id) {
            $data = [
                'request_id' => $request_id,
                'item_id'    => $item_id,
                'qty'        => $quantities[$key],
                'unit'       => $units[$key],
                'status'     => 'pending',
                'added_by'   => $this->requested_by,
                'added_on'   => date('Y-m-d H:i:s')
            ];

            if (!$this->requestItemModel->insert($data)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        $data = [
            'grand_total' => $grand_total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->requestModel->update($request_id, $data)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }
    
    /**
     * Attempt update
     */
    protected function _attempt_update_request($request_id)
    {
        $request_date = $this->request->getVar('request_date');
        $request_date = date("Y-m-d", strtotime($request_date));
        $delivery_date = $this->request->getVar('delivery_date');
        $delivery_date = date("Y-m-d", strtotime($delivery_date));

        $data = [
            'branch_from'     => $this->request->getVar('branch_from'),
            'branch_to'       => $this->request->getVar('branch_to'),
            'transfer_number' => $this->request->getVar('transfer_number'),
            'request_date'    => $request_date,
            'remarks'         => $this->request->getVar('remarks'),
            'grand_total'     => $this->request->getVar('grand_total'),
            'status'          => $this->request->getVar('status'),
            'completed_on'    => $this->request->getVar('completed_on'),
            'encoded_by'      => $this->request->getVar('encoded_by'),
            'delivery_date'   => $delivery_date,
            'updated_by'      => $this->requested_by,
            'updated_on'      => date('Y-m-d H:i:s')
        ];

        if (!$this->requestModel->update($request_id, $data)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    protected function _attempt_update_request_items($request_id)
    {
        $this->requestItemModel->delete_by_request_id($request_id);
        if (!$this->_attempt_generate_request_items($request_id)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($request_id)
    {
        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        $where = ['id' => $request_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->requestModel->update($where, $values) ||
            !$this->requestItemModel->delete_by_request_id($request_id, $this->requested_by)) {
            $this->errorMessage = $this->db->error()['message'];
            $this->db->transRollback();
            $this->db->close();
            return false;
        }

        $this->db->transCommit();
        $this->db->close();

        return true;
    }

    /**
     * Attempt change request status
     */
    protected function _attempt_change_status($request, $new_status)
    {
        $current_status = $request['status'];
        $where = ['id' => $request['id']];

        $values = [
            'status'     => $new_status,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        
        switch($new_status) {
            case 'completed':
                $values['completed_on'] = date('Y-m-d H:i:s');
                break;
            case 'pending':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                break;
            case 'rejected':
                $values['rejection_remarks'] = $this->request->getVar('rejection_remarks');
                $values['rejected_by'] = $this->requested_by;
                $values['rejected_on'] = date('Y-m-d H:i:s');
                break;
        }

        if (!$this->requestModel->update($where, $values) || 
            !$this->requestItemModel->update_status_by_request_id($request['id'], $this->requested_by, $new_status)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }


    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->requestModel        = model('App\Models\Request');
        $this->requestItemModel    = model('App\Models\Request_item');
        $this->checkInvoiceModel   = model('App\Models\Check_invoice');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}

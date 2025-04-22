<?php

namespace App\Controllers;

class Distributor_billings extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get distributor_billing
     */
    public function get_distributor_billing()
    {

        if (($response = $this->_api_verification('distributor_billings', 'get_distributor_billing')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $distributor_billing_id    = $this->request->getVar('distributor_billing_id') ? : null;
        $distributor_billing       = $distributor_billing_id ? $this->distributorBillingModel->get_details_by_id($distributor_billing_id) : null;
        $distributor = $distributor_billing ? $this->distributorModel->get_details_by_id($distributor_billing[0]['distributor_id']) : null;

        $distributor_billing_entries = $distributor_billing_id ? $this->distributorBillingEntryModel->get_details_by_distributor_billing_id($distributor_billing_id) : null;

        if (!$distributor_billing) {
            $response = $this->failNotFound('No distributor_billing found');
        } else {
            $distributor_billing[0]['distributor_billing_entries'] = $distributor_billing_entries;
            $distributor_billing[0]['distributor'] = $distributor;

            $response = $this->respond([
                'status' => 'success',
                'data' => $distributor_billing
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get distributor_billing by status
     */
    public function filter_distributor_billing_status()
    {
        if (($response = $this->_api_verification('distributor_billings', 'filter_distributor_billing_status')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status    = $this->request->getVar('status') ? : null;
        $distributor_billing = $status ? $this->distributorBillingModel->filter_distributor_billing_status($status) : null;

        if (!$distributor_billing) {
            $response = $this->failNotFound('No distributor_billing found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $distributor_billing
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
        if (($response = $this->_api_verification('distributor_billings', 'filter_order_status')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status    = $this->request->getVar('status') ? : null;
        $distributor_billing = $status ? $this->distributorBillingModel->filter_order_status($status) : null;

        if (!$distributor_billing) {
            $response = $this->failNotFound('No distributor_billing found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $distributor_billing
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all distributor_billings
     */
    public function get_all_distributor_billing()
    {
        if (($response = $this->_api_verification('distributor_billings', 'get_all_distributor_billing')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status = $this->request->getVar('status') ? : null;
        $distributor_name = $this->request->getVar('distributor_name') ? : null;

        $distributor_billings = $this->distributorBillingModel->get_all_distributor_billing($status, $distributor_name);

        if (!$distributor_billings) {
            $response = $this->failNotFound('No distributor_billing found');
        } else {
            foreach ($distributor_billings as $key => $distributor_billing) {
                $distributor_billings[$key]['distributor_billing_entries'] = $this->distributorBillingEntryModel->get_details_by_distributor_billing_id($distributor_billing['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $distributor_billings
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create distributor_billing
     */
    public function create()
    {
        if (($response = $this->_api_verification('distributor_billings', 'create')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$distributor_billing_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else if (!$this->_attempt_generate_distributor_billing_entries($distributor_billing_id, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate PO items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'distributor_billing_id' => $distributor_billing_id,
                'response'    => 'distributor_billing created successfully'
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Give distributor billing
     */
    public function generate_distributor_billing()
    {
        if (($response = $this->_api_verification('distributor_billings', 'create')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $distributor_id = $this->request->getVar('distributor_id');
        $billing_date = $this->request->getVar('billing_date');

        $distributor_billing_entries = $this->distributorModel->get_clients_to_bill($distributor_id, $billing_date);

        foreach($distributor_billing_entries AS $index => $distributor_billing_entry) {
            $where = [
                'id' => $distributor_billing_entries[$index]['distributor_id']
            ];
            $distributor = $this->distributorModel->select('', $where, 1);
            $distributor_billing_entries[$index]['distributor'] = $distributor;
        }

        $where = [
            'id' => $this->request->getVar('distributor_id')
        ];
        $distributor = $this->distributorModel->select('', $where, 1);

        $response = $this->respond([
            'distributor' => $distributor,
            'distributor_billing_entries' => $distributor_billing_entries,
            'response'    => 'distributor billing generated successfully'
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update distributor_billing
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('distributor_billings', 'update')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
            
        $distributor_billing_id = $this->request->getVar('distributor_billing_id');
        $where       = ['id' => $distributor_billing_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();
        
        if (!$distributor_billing = $this->distributorBillingModel->select('', $where, 1)) {
            $response = $this->failNotFound('distributor_billing not found');
        } elseif (!$this->_attempt_update($distributor_billing)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } elseif (!$this->_attempt_update_distributor_billing_entries($distributor_billing, $db)) {
            $db->transRollback();
            $response = $this->respond([
                'status'  => 'error',
                'message' => 'Failed to generate PO items'
            ]);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'distributor_billing_id' => $distributor_billing_id,
                'response'    => 'distributor_billing updated successfully'
            ]);
        }
        
        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete distributor_billings
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('distributor_billings', 'delete')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
        
        $distributor_billing_id = $this->request->getVar('distributor_billing_id');

        $where = ['id' => $distributor_billing_id, 'is_deleted' => 0];

        if (!$distributor_billing = $this->distributorBillingModel->select('', $where, 1)) {
            $response = $this->failNotFound('distributor_billing not found');
        } elseif (!$this->_attempt_delete($distributor_billing_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'distributor_billing deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search distributor_billings based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('distributor_billings', 'search')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $distributor_id          = $this->request->getVar('distributor_id') ? : null;

        if (!$distributor_billings = $this->distributorBillingModel->search($distributor_id)) {
            $response = $this->failNotFound('No distributor_billing found');
        } else {

            foreach ($distributor_billings as $key => $distributor_billing) {
                $distributor_billings[$key]['distributor_billing_entries'] = $this->distributorBillingEntryModel->get_details_by_distributor_billing_id($distributor_billing['id']);
            }

            $response = $this->respond([
                'data' => $distributor_billings
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create a distributor_billing
     */
    private function _attempt_create()
    {
        $values = [
            'distributor_id'        => $this->request->getVar('distributor_id'),
            'billing_date'        => $this->request->getVar('billing_date'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'added_by'         => $this->requested_by,
            'added_on'         => date('Y-m-d H:i:s'),
        ];

        if (!$distributor_billing_id = $this->distributorBillingModel->insert($values)) {
            return false;
        }

        return $distributor_billing_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($distributor_billing)
    {
        $values = [
            'distributor_id'        => $this->request->getVar('distributor_id'),
            'billing_date'        => $this->request->getVar('billing_date'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'updated_by'       => $this->requested_by,
            'updated_on'       => date('Y-m-d H:i:s')
        ];

        if (!$this->distributorBillingModel->update($distributor_billing['id'], $values))
            return false;
    
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($distributor_billing_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $distributor_billing_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->distributorBillingModel->update($where, $values)) {
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
    protected function _attempt_generate_distributor_billing_entries($distributor_billing_id, $db)
    {
        $distributor_client_ids   = $this->request->getVar('distributor_client_ids');
        $project_ids = $this->request->getVar('project_ids');
        $amounts = $this->request->getVar('amounts');
        $distributor_billing_entry_dates      = $this->request->getVar('distributor_billing_entry_dates');
        $due_dates      = $this->request->getVar('due_dates');

        $grand_total = 0;

        foreach ($distributor_client_ids as $key => $distributor_client_id) {
            $data = [
                'distributor_billing_id' => $distributor_billing_id,
                'distributor_client_id' => $distributor_client_id,
                'project_id'     => $project_ids[$key],
                'distributor_billing_entry_date' => $distributor_billing_entry_dates[$key],
                'due_date'     => $due_dates[$key],
                'amount'     => $amounts[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];


            if (!$this->distributorBillingEntryModel->insert($data)) {
                return false;
            }
        }

        return true;
    }

    /*
    * Attempt update PO items
    */
    protected function _attempt_update_distributor_billing_entries($distributor_billing, $db)
    {
        $distributor_billing_id = $distributor_billing['id'];
        // // delete all items first
        if (!$this->distributorBillingEntryModel->delete_by_distributor_billing_id($distributor_billing['id'], $this->requested_by, $db)) {
            return false;
        }

        $distributor_client_ids   = $this->request->getVar('distributor_client_ids');
        $project_ids = $this->request->getVar('project_ids');
        $amounts = $this->request->getVar('amounts');
        $distributor_billing_entry_dates      = $this->request->getVar('distributor_billing_entry_dates');
        $due_dates      = $this->request->getVar('due_dates');

        $grand_total = 0;

        foreach ($distributor_client_ids as $key => $distributor_client_id) {
            $data = [
                'distributor_billing_id' => $distributor_billing_id,
                'distributor_client_id' => $distributor_client_id,
                'project_id'     => $project_ids[$key],
                'distributor_billing_entry_date' => $distributor_billing_entry_dates[$key],
                'due_date'     => $due_dates[$key],
                'amount'     => $amounts[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];


            if (!$this->distributorBillingEntryModel->insert($data)) {
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

        $this->distributorModel               = model('App\Models\Distributor');
        $this->distributorBillingModel               = model('App\Models\Distributor_billing');
        $this->distributorBillingEntryModel           = model('App\Models\Distributor_billing_entry');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
        
    }
}

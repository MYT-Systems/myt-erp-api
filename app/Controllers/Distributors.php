<?php

namespace App\Controllers;

class Distributors extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get distributor
     */
    public function get_distributor()
    {

        if (($response = $this->_api_verification('distributors', 'get_distributor')) !== true) 
            return $response;

        $distributor_id    = $this->request->getVar('distributor_id') ? : null;
        $distributor       = $distributor_id ? $this->distributorModel->get_details_by_id($distributor_id) : null;
        $distributor_clients = $distributor_id ? $this->distributorClientModel->get_details_by_distributor_id($distributor_id) : null;

        if (!$distributor) {
            $response = $this->failNotFound('No distributor found');
        } else {
            $distributor[0]['distributor_clients'] = $distributor_clients;

            $response = $this->respond([
                'status' => 'success',
                'data' => $distributor
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get distributor by status
     */
    public function filter_distributor_status()
    {
        if (($response = $this->_api_verification('distributors', 'filter_distributor_status')) !== true) 
            return $response;

        $status    = $this->request->getVar('status') ? : null;
        $distributor = $status ? $this->distributorModel->filter_distributor_status($status) : null;

        if (!$distributor) {
            $response = $this->failNotFound('No distributor found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $distributor
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
        if (($response = $this->_api_verification('distributors', 'filter_order_status')) !== true) 
            return $response;

        $status    = $this->request->getVar('status') ? : null;
        $distributor = $status ? $this->distributorModel->filter_order_status($status) : null;

        if (!$distributor) {
            $response = $this->failNotFound('No distributor found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $distributor
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all distributors
     */
    public function get_all_distributor()
    {
        if (($response = $this->_api_verification('distributors', 'get_all_distributor')) !== true) 
            return $response;

        $distributors = $this->distributorModel->get_all_distributor();

        if (!$distributors) {
            $response = $this->failNotFound('No distributor found');
        } else {
            foreach ($distributors as $key => $distributor) {
                $distributors[$key]['distributor_clients'] = $this->distributorClientModel->get_details_by_distributor_id($distributor['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $distributors
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create distributor
     */
    public function create()
    {
        if (($response = $this->_api_verification('distributors', 'create')) !== true) 
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$distributor_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else if (!$this->_attempt_generate_distributor_clients($distributor_id, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate PO items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'distributor_id' => $distributor_id,
                'response'    => 'distributor created successfully'
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update distributor
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('distributors', 'update')) !== true) 
            return $response;
            
        $distributor_id = $this->request->getVar('distributor_id');
        $where       = ['id' => $distributor_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();
        
        if (!$distributor = $this->distributorModel->select('', $where, 1)) {
            $response = $this->failNotFound('distributor not found');
        } elseif (!$this->_attempt_update($distributor)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } elseif (!$this->_attempt_update_distributor_clients($distributor, $db)) {
            $db->transRollback();
            $response = $this->respond([
                'status'  => 'error',
                'message' => 'Failed to generate PO items'
            ]);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'distributor_id' => $distributor_id,
                'response'    => 'distributor updated successfully'
            ]);
        }
        
        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete distributors
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('distributors', 'delete')) !== true) 
            return $response;
        
        $distributor_id = $this->request->getVar('distributor_id');

        $where = ['id' => $distributor_id, 'is_deleted' => 0];

        if (!$distributor = $this->distributorModel->select('', $where, 1)) {
            $response = $this->failNotFound('distributor not found');
        } elseif (!$this->_attempt_delete($distributor_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'distributor deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search distributors based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('distributors', 'search')) !== true) 
            return $response;

        $name          = $this->request->getVar('name') ? : null;

        if (!$distributors = $this->distributorModel->search($name, $limit_by, $anything)) {
            $response = $this->failNotFound('No distributor found');
        } else {

            $response = $this->respond([
                'data' => $distributors
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create a distributor
     */
    private function _attempt_create()
    {
        $values = [
            'name'        => $this->request->getVar('name'),
            'address'        => $this->request->getVar('address'),
            'contact_person'        => $this->request->getVar('contact_person'),
            'contact_no'        => $this->request->getVar('contact_no'),
            'added_by'         => $this->requested_by,
            'added_on'         => date('Y-m-d H:i:s'),
        ];

        if (!$distributor_id = $this->distributorModel->insert($values)) {
            return false;
        }

        return $distributor_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($distributor)
    {
        $values = [
            'name'        => $this->request->getVar('name'),
            'address'        => $this->request->getVar('address'),
            'contact_person'        => $this->request->getVar('contact_person'),
            'contact_no'        => $this->request->getVar('contact_no'),
            'updated_by'       => $this->requested_by,
            'updated_on'       => date('Y-m-d H:i:s')
        ];

        if (!$this->distributorModel->update($distributor['id'], $values))
            return false;
    
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($distributor_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $distributor_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->distributorModel->update($where, $values)) {
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
    protected function _attempt_generate_distributor_clients($distributor_id, $db)
    {
        $customer_ids   = $this->request->getVar('customer_ids');
        $project_ids = $this->request->getVar('project_ids');
        $distributor_client_dates      = $this->request->getVar('distributor_client_dates');

        $grand_total = 0;

        foreach ($customer_ids as $key => $customer_id) {
            $data = [
                'distributor_id' => $distributor_id,
                'customer_id' => $customer_id,
                'project_id'     => $project_ids[$key],
                'distributor_client_date' => $distributor_client_dates[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];


            if (!$this->distributorClientModel->insert($data)) {
                return false;
            }
        }

        return true;
    }

    /*
    * Attempt update PO items
    */
    protected function _attempt_update_distributor_clients($distributor, $db)
    {
        // // delete all items first
        if (!$this->distributorClientModel->delete_by_distributor_id($distributor['id'], $this->requested_by, $db)) {
            return false;
        }

        $customer_ids   = $this->request->getVar('customer_ids');
        $project_ids = $this->request->getVar('project_ids');
        $distributor_client_dates      = $this->request->getVar('distributor_client_dates');

        $grand_total = 0;
        foreach ($customer_ids as $key => $customer_id) {
            $data = [
                'distributor_id' => $distributor['id'],
                'customer_id' => $customer_id,
                'project_id'     => $project_ids[$key],
                'distributor_client_date' => $distributor_client_dates[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];

            if (!$this->distributorClientModel->insert($data, $this->requested_by, $db)) {
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
        $this->distributorClientModel           = model('App\Models\Distributor_client');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
        
    }
}

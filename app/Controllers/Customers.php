<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Webapp_response;

class Customers extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get Customer
     */
    public function get_customer()
    {
        if (($response = $this->_api_verification('customers', 'get_customer')) !== true)
            return $response;

        $customer_id        = $this->request->getVar('customer_id') ? : null;
        $customer           = $customer_id ? $this->customerModel->get_details_by_id($customer_id) : null;

        if (!$customer) {
            $response = $this->failNotFound('No customer found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $customer
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all Customers
     */
    public function get_all_customer()
    {
        if (($response = $this->_api_verification('customers', 'get_all_customer')) !== true)
            return $response;

        $customers = $this->customerModel->get_all_customer();

        if (!$customers) {
            $response = $this->failNotFound('No customer found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $customers
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all Lead
     */
    public function get_all_lead()
    {
        if (($response = $this->_api_verification('customers', 'get_all_lead')) !== true)
            return $response;

        $customers = $this->customerModel->get_all_lead();

        if (!$customers) {
            $response = $this->failNotFound('No lead found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $customers
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create customer
     */
    public function create()
    {
        if (($response = $this->_api_verification('customers', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$customer_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['Failed to create customer.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'     => 'Customer created successfully.',
                'status'    => 'success',
                'customer_id' => $customer_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update customer
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('customers', 'update')) !== true)
            return $response;

         $customer_id = $this->request->getVar('customer_id');
         $where = ['id' => $customer_id, 'is_deleted' => 0];

         $db = \Config\Database::connect();
         $db->transBegin();

        if (!$customer = $this->customerModel->select('', $where, 1)) {
            $response = $this->failNotFound('customer not found');
        } elseif (!$this->_attempt_update($customer_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update customer.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'customer updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete customer
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('customers', 'delete')) !== true)
            return $response;

        $customer_id = $this->request->getVar('customer_id');

        $where = ['id' => $customer_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$customer = $this->customerModel->select('', $where, 1)) {
            $response = $this->failNotFound('customer not found');
        } elseif (!$this->_attempt_delete($customer_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete customer.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'customer deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search customer based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('customers', 'search')) !== true)
            return $response;

        // $customer_id           = $this->request->getVar('customer_id');
        $name                  = $this->request->getVar('name');
        $company               = $this->request->getVar('company');
        $address               = $this->request->getVar('address');
        $contact_number        = $this->request->getVar('contact_number');
        $email                 = $this->request->getVar('email');
        $contact_person        = $this->request->getVar('contact_person');
        $lead                  = $this->request->getVar('lead');
        $credit_limit          = $this->request->getVar('credit_limit');
        $terms                 = $this->request->getVar('terms');
        $tin_no                = $this->request->getVar('tin_no');

       if (!$customers = $this->customerModel->search($name, $company, $address, $contact_number, $email, $contact_person, $tin_no)) {
            $response = $this->failNotFound('No customer found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $customers
            ]);
        }
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Drop search for lead field
     */
    public function drop_search()
    {
        if (($response = $this->_api_verification('customers', 'dropsearch')) !== true)
            return $response;

        $lead = $this->request->getVar('lead');

        if (!$customers = $this->customerModel->searchByLead($lead)) {
            $response = $this->failNotFound('No customer found for the given lead');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $customers
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    /**
     * Create customers
     */
    protected function _attempt_create()
    {
        $values = [
            'name'                      => $this->request->getVar('name'),
            'address'                   => $this->request->getVar('address'),
            'company'                   => $this->request->getVar('company'),
            'contact_person'            => $this->request->getVar('contact_person'),
            'contact_number'            => $this->request->getVar('contact_number'),
            'email'                     => $this->request->getVar('email'),
            'tin_no'                    => $this->request->getVar('tin_no'),
            'lead'                      => $this->request->getVar('lead'),
            'credit_limit'              => $this->request->getVar('credit_limit'),
            'terms'                     => $this->request->getVar('terms'),
            'added_by'                  => $this->requested_by,
            'added_on'                  => date('Y-m-d H:i:s'),
        ];

        if (!$customer_id = $this->customerModel->insert($values)) {
            $this->errorMessage = $this->customerModel->error();
            return false;
        }

        return $customer_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($customer_id)
    {
        $values = [
            'name'                      => $this->request->getVar('name'),
            'address'                   => $this->request->getVar('address'),
            'company'                   => $this->request->getVar('company'),
            'contact_person'            => $this->request->getVar('contact_person'),
            'contact_number'            => $this->request->getVar('contact_number'),
            'email'                     => $this->request->getVar('email'),
            'tin_no'                    => $this->request->getVar('tin_no'),
            'lead_status'               => $this->request->getVar('lead_status'),
            'credit_limit'              => $this->request->getVar('credit_limit'),
            'terms'                     => $this->request->getVar('terms'),
            'lead'                      => $this->request->getVar('lead'),
            'updated_by'                => $this->requested_by,
            'updated_on'                => date('Y-m-d H:i:s')
        ];

        if (!$this->customerModel->update($customer_id, $values))
            return false;

        return true;
    }
        

    /**
     * Attempt delete
     */
    protected function _attempt_delete($customer_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->customerModel->update($customer_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
protected function _load_essentials()
    {
        $this->customerModel = new Customer();
        $this->webappResponseModel  = new Webapp_response();
    }
}


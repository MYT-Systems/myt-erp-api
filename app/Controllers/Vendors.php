<?php

namespace App\Controllers;

use App\Models\Vendor;
use App\Models\Webapp_response;

class Vendors extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get vendor
     */
    public function get_vendor()
    {
        if (($response = $this->_api_verification('vendors', 'get_vendor')) !== true)
            return $response;

        $vendor_id = $this->request->getVar('vendor_id') ? : null;
        $vendor    = $vendor_id ? $this->vendorModel->get_details_by_id($vendor_id) : null;

        if (!$vendor) {
            $response = $this->failNotFound('No vendor found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $vendor
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all vendors
     */
    public function get_all_vendor()
    {
        if (($response = $this->_api_verification('vendors', 'get_all_vendor')) !== true)
            return $response;

        $vendors = $this->vendorModel->get_all_vendor();

        if (!$vendors) {
            $response = $this->failNotFound('No vendor found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $vendors
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create vendor
     */
    public function create()
    {
        if ($response = $this->_api_verification('vendors', 'create') !== true)
            return $response;

        $phone_no = $this->request->getVar('phone_no');
        if ($response = $this->_is_existing($this->vendorModel, ['phone_no' => $phone_no]))
            return $response;
        

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$vendor_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'    => 'vendor created successfully',
                'status'      => 'success',
                'vendor_id' => $vendor_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update vendor
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('vendors', 'update')) !== true)
            return $response;

        $vendor_id = $this->request->getVar('vendor_id');
        $where       = ['id' => $vendor_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();
        
        if (!$vendor = $this->vendorModel->select('', $where, 1))
            $response = $this->failNotFound('vendor not found');
        elseif (!$this->_attempt_update($vendor_id)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'vendor updated successfully']);
        }


        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete vendors
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('vendors', 'delete')) !== true)
            return $response;

        $vendor_id = $this->request->getVar('vendor_id');

        $where = ['id' => $vendor_id, 'is_deleted' => 0];

        if (!$vendor = $this->vendorModel->select('', $where, 1)) {
            $response = $this->failNotFound('vendor not found');
        } elseif (!$this->_attempt_delete($vendor_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'vendor deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search vendors based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('vendors', 'search')) !== true)
            return $response;

        $trade_name             = $this->request->getVar('trade_name');
        $trade_address          = $this->request->getVar('trade_address');
        $bir_name               = $this->request->getVar('bir_name');
        $bir_number             = $this->request->getVar('bir_number');
        $bir_address            = $this->request->getVar('bir_address');
        $tin                    = $this->request->getVar('tin');
        $terms                  = $this->request->getVar('terms');
        $requirements           = $this->request->getVar('requirements');
        $phone_no               = $this->request->getVar('phone_no');
        $email                  = $this->request->getVar('email');
        $contact_person         = $this->request->getVar('contact_person');
        $bank_primary           = $this->request->getVar('bank_primary');
        $primary_account_no     = $this->request->getVar('primary_account_no');
        $primary_account_name   = $this->request->getVar('primary_account_name');
        $bank_alternate         = $this->request->getVar('bank_alternate');
        $alternate_account_no   = $this->request->getVar('alternate_account_no');
        $alternate_account_name = $this->request->getVar('alternate_account_name');

        if (!$vendors = $this->vendorModel->search($trade_name, $trade_address, $bir_name, $bir_number, $bir_address, $tin, $terms, $requirements, $phone_no, $email, $contact_person, $bank_primary, $primary_account_no, $primary_account_name, $bank_alternate, $alternate_account_no, $alternate_account_name)) {
            $response = $this->failNotFound('No vendor found');
        } else {
            $response = $this->respond([
                'response' => 'vendors found',
                'status'   => 'success',
                'data'     => $vendors
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Methods
    // ------------------------------------------------------------------------

    /**
     * Attempt to create vendor
     */
    private function _attempt_create()
    {
        $values = [
            'trade_name'             => $this->request->getVar('trade_name'),
            'trade_address'          => $this->request->getVar('trade_address'),
            'bir_name'               => $this->request->getVar('bir_name'),
            'bir_number'             => $this->request->getVar('bir_number'),
            'bir_address'            => $this->request->getVar('bir_address'),
            'tin'                    => $this->request->getVar('tin'),
            'terms'                  => $this->request->getVar('terms'),
            'requirements'           => $this->request->getVar('requirements'),
            'phone_no'               => $this->request->getVar('phone_no'),
            'email'                  => $this->request->getVar('email'),
            'contact_person'         => $this->request->getVar('contact_person'),
            'bank_primary'           => $this->request->getVar('bank_primary'),
            'primary_account_no'     => $this->request->getVar('primary_account_no'),
            'primary_account_name'   => $this->request->getVar('primary_account_name'),
            'bank_alternate'         => $this->request->getVar('bank_alternate'),
            'alternate_account_no'   => $this->request->getVar('alternate_account_no'),
            'alternate_account_name' => $this->request->getVar('alternate_account_name'),
            'payee'                  => $this->request->getVar('payee'), 
            'added_by'               => $this->requested_by,
            'added_on'               => date('Y-m-d H:i:s'),
        ];

        if (!$vendor_id = $this->vendorModel->insert($values)) {
            return false;
        }

        return $vendor_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($vendor_id)
    {
        $values = [
            'trade_name'             => $this->request->getVar('trade_name'),
            'trade_address'          => $this->request->getVar('trade_address'),
            'bir_name'               => $this->request->getVar('bir_name'),
            'bir_number'             => $this->request->getVar('bir_number'),
            'bir_address'            => $this->request->getVar('bir_address'),
            'tin'                    => $this->request->getVar('tin'),
            'terms'                  => $this->request->getVar('terms'),
            'requirements'           => $this->request->getVar('requirements'),
            'phone_no'               => $this->request->getVar('phone_no'),
            'email'                  => $this->request->getVar('email'),
            'contact_person'         => $this->request->getVar('contact_person'),
            'bank_primary'           => $this->request->getVar('bank_primary'),
            'primary_account_no'     => $this->request->getVar('primary_account_no'),
            'primary_account_name'   => $this->request->getVar('primary_account_name'),
            'bank_alternate'         => $this->request->getVar('bank_alternate'),
            'alternate_account_no'   => $this->request->getVar('alternate_account_no'),
            'alternate_account_name' => $this->request->getVar('alternate_account_name'),
            'payee'                  => $this->request->getVar('payee'), 
            'updated_by'     => $this->requested_by,
            'updated_on'     => date('Y-m-d H:i:s')
        ];

        if (!$this->vendorModel->update($vendor_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($vendor_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $vendor_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->vendorModel->update($where, $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        $db->transCommit();
        $db->close();

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->vendorModel         = new Vendor();
        $this->webappResponseModel = new Webapp_response();
    }
}

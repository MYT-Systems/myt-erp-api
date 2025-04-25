<?php

namespace App\Controllers;

use App\Models\Partner;
use App\Models\Webapp_response;

class Partners extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get partner
     */
    public function get_partner()
    {
        if (($response = $this->_api_verification('partners', 'get_partner')) !== true)
            return $response; 

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $partner_id = $this->request->getVar('partner_id') ? : null;
        $partner    = $partner_id ? $this->partnerModel->get_details_by_id($partner_id) : null;

        if (!$partner) {
            $response = $this->failNotFound('No partner found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $partner
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all partners
     */
    public function get_all_partner()
    {
        if (($response = $this->_api_verification('partners', 'get_all_partner')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $partners = $this->partnerModel->get_all_partner();

        if (!$partners) {
            $response = $this->failNotFound('No partner found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $partners
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create partner
     */
    public function create()
    {
        if (($response = $this->_api_verification('partners', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$partner_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create partner.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'     => 'partner created successfully.',
                'status'       => 'success',
                'partner_id' => $partner_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update partner
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('partners', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $partner_id = $this->request->getVar('partner_id');
        $where = ['id' => $partner_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$partner = $this->partnerModel->select('', $where, 1)) {
            $response = $this->failNotFound('partner not found');
        } elseif (!$this->_attempt_update($partner_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update partner.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'partner updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete partners
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('partners', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $partner_id = $this->request->getVar('partner_id');

        $where = ['id' => $partner_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$partner = $this->partnerModel->select('', $where, 1)) {
            $response = $this->failNotFound('partner not found');
        } elseif (!$this->_attempt_delete($partner_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Fail to delete partner.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'partner deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search partners based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('partners', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $name     = $this->request->getVar('name');

        if (!$partners = $this->partnerModel->search($name)) {
            $response = $this->failNotFound('No partner found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $partners
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create partner
     */
    protected function _attempt_create()
    {
        $values = [
            'name'     => $this->request->getVar('name'),
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s'),
        ];

        if (!$partner_id = $this->partnerModel->insert($values))
            return false;

        return $partner_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($partner_id)
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->partnerModel->update($partner_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($partner_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->partnerModel->update($partner_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->partnerModel = new Partner();
        $this->webappResponseModel  = new Webapp_response();
    }
}

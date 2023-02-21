<?php

namespace App\Controllers;

use App\Models\Forwarder;
use App\Models\Webapp_response;

class Forwarders extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get forwarder
     */
    public function get_forwarder()
    {
        if (($response = $this->_api_verification('forwarders', 'get_forwarder')) !== true)
            return $response;

        $forwarder_id = $this->request->getVar('forwarder_id') ? : null;
        $forwarder    = $forwarder_id ? $this->forwarderModel->get_details_by_id($forwarder_id) : null;

        if (!$forwarder) {
            $response = $this->failNotFound('No forwarder found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $forwarder
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all forwarders
     */
    public function get_all_forwarder()
    {
        if (($response = $this->_api_verification('forwarders', 'get_all_forwarder')) !== true)
            return $response;

        $forwarders = $this->forwarderModel->get_all_forwarder();

        if (!$forwarders) {
            $response = $this->failNotFound('No forwarder found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $forwarders
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create forwarder
     */
    public function create()
    {
        if (($response = $this->_api_verification('forwarders', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$forwarder_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create forwarder.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'     => 'Forwarder created successfully.',
                'status'       => 'success',
                'forwarder_id' => $forwarder_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update forwarder
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('forwarders', 'update')) !== true)
            return $response;

        $forwarder_id = $this->request->getVar('forwarder_id');
        $where = ['id' => $forwarder_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$forwarder = $this->forwarderModel->select('', $where, 1)) {
            $response = $this->failNotFound('forwarder not found');
        } elseif (!$this->_attempt_update($forwarder_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update forwarder.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Forwarder updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete forwarders
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('forwarders', 'delete')) !== true)
            return $response;

        $forwarder_id = $this->request->getVar('forwarder_id');

        $where = ['id' => $forwarder_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$forwarder = $this->forwarderModel->select('', $where, 1)) {
            $response = $this->failNotFound('forwarder not found');
        } elseif (!$this->_attempt_delete($forwarder_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Fail to delete forwarder.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Forwarder deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search forwarders based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('forwarders', 'search')) !== true)
            return $response;

        $name     = $this->request->getVar('name');
        $address  = $this->request->getVar('address');
        $phone_no = $this->request->getVar('phone_no');

        if (!$forwarders = $this->forwarderModel->search($name, $address, $phone_no)) {
            $response = $this->failNotFound('No forwarder found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $forwarders
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create forwarder
     */
    protected function _attempt_create()
    {
        $values = [
            'name'     => $this->request->getVar('name'),
            'address'  => $this->request->getVar('address'),
            'phone_no' => $this->request->getVar('phone_no'),
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s'),
        ];

        if (!$forwarder_id = $this->forwarderModel->insert($values))
            return false;

        return $forwarder_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($forwarder_id)
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'address'    => $this->request->getVar('address'),
            'phone_no'   => $this->request->getVar('phone_no'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->forwarderModel->update($forwarder_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($forwarder_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->forwarderModel->update($forwarder_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->forwarderModel = new Forwarder();
        $this->webappResponseModel  = new Webapp_response();
    }
}

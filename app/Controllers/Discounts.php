<?php

namespace App\Controllers;

use App\Models\Discount;
use App\Models\Check_template;
use App\Models\Webapp_response;

class Discounts extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get discount
     */
    public function get_discount()
    {
        if (($response = $this->_api_verification('discounts', 'get_discount')) !== true)
            return $response;

        $discount_id = $this->request->getVar('discount_id') ? : null;
        $discount    = $discount_id ? $this->discountModel->get_details_by_id($discount_id) : null;

        if (!$discount) {
            $response = $this->failNotFound('No discount found');
        } else {
            $response = $this->respond([
                'data'   => $discount,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all discounts
     */
    public function get_all_discount()
    {
        if (($response = $this->_api_verification('discounts', 'get_all_discount')) !== true)
            return $response;

        $discounts = $this->discountModel->get_all_discount();

        if (!$discounts) {
            $response = $this->failNotFound('No discount found');
        } else {
            $response = $this->respond([
                'data'   => $discounts,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create discount
     */
    public function create()
    {
        if (($response = $this->_api_verification('discounts', 'create')) !== true)
            return $response;

        $where = ['name' => $this->request->getVar('name')];
        if ($this->discountModel->select('', $where, 1)) {
            $response = $this->fail('discount already exists.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create discount.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Discount created successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update discount
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('discounts', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('discount_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$discount = $this->discountModel->select('', $where, 1)) {
            $response = $this->failNotFound('discount not found');
        } elseif (!$this->_attempt_update($discount['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update discount.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Discount updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete discounts
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('discounts', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('discount_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$discount = $this->discountModel->select('', $where, 1)) {
            $response = $this->failNotFound('discount not found');
        } elseif (!$this->_attempt_delete($discount['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete discount.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Discount deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search discounts based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('discounts', 'search')) !== true)
            return $response;

        $name          = $this->request->getVar('name');
        $template_name = $this->request->getVar('template_name');

        if (!$discounts = $this->discountModel->search($name, $template_name)) {
            $response = $this->failNotFound('No discount found');
        } else {
            $response = $this->respond([
                'data'     => $discounts,
                'status'   => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create discount
     */
    protected function _attempt_create()
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'percentage' => $this->request->getVar('percentage'),
            'valid_from' => $this->request->getVar('valid_from'),
            'valid_to'   => $this->request->getVar('valid_to'),
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
        ];

        if (!$discount_id = $this->discountModel->insert($values))
            return false;

        return $discount_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($discount_id)
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'percentage' => $this->request->getVar('percentage'),
            'valid_from' => $this->request->getVar('valid_from'),
            'valid_to'   => $this->request->getVar('valid_to'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->discountModel->update($discount_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($discount_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->discountModel->update($discount_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->discountModel       = new Discount();
        $this->webappResponseModel = new Webapp_response();
    }
}

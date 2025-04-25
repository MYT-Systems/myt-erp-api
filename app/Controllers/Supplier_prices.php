<?php

namespace App\Controllers;

use App\Models\Supplier_price;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\Webapp_response;

class Supplier_prices extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get supplier_price
     */
    public function get_supplier_price()
    {
        if (($response = $this->_api_verification('supplier_prices', 'get_supplier_price')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplier_price_id = $this->request->getVar('supplier_price_id') ? : null;
        $supplier_price    = $supplier_price_id ? $this->supplierPriceModel->get_details_by_id($supplier_price_id) : null;

        if (!$supplier_price) {
            $response = $this->failNotFound('No supplier_price found');
        } else {
            $response           = [];
            $response['data']   = $supplier_price;
            $response['status'] = 'success';
            $response           = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all supplier_prices
     *
     */
    public function get_all_supplier_price()
    {
        if (($response = $this->_api_verification('supplier_prices', 'get_all_supplier_price')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplier_prices = $this->supplierPriceModel->get_all_supplier_price();

        if (!$supplier_prices) {
            $response = $this->failNotFound('No supplier_price found');
        } else {
            $response           = [];
            $response['data']   = $supplier_prices;
            $response['status'] = 'success';
            $response           = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create supplier_price
     */
    public function create()
    {
        if (($response = $this->_api_verification('supplier_prices', 'create')) !== true) {
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplier_id = $this->request->getVar('supplier_id') ? : null;
        $where = [
            'id' => $supplier_id
        ];
        if (!$this->supplierModel->select('', $where, 1)) {
            $response = $this->fail('Supplier not found');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $item_id = $this->request->getVar('item_id') ? : null;
        $where = [
            'id' => $item_id
        ];
        if (!$this->itemModel->select('', $where, 1)) {
            $response = $this->fail('Item not found');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $values = [
            'supplier_id'   => $supplier_id,
            'item_id' => $item_id,
            'price'         => $this->request->getVar('price'),
            'added_by'      => $this->requested_by,
            'added_on'      => date('Y-m-d H:i:s'),
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$this->supplierPriceModel->insert($values)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'supplier_price created successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update supplier_price
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('supplier_prices', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplier_price_id = $this->request->getVar('supplier_price_id');
        $where = ['id' => $supplier_price_id, 'is_deleted' => 0];

        if (!$supplier_price = $this->supplierPriceModel->select('', $where, 1))
            $response = $this->failNotFound('supplier_price not found');
        elseif (!$response = $this->_attempt_update($supplier_price_id))
            $response = $this->fail('Server error');
        elseif ($response === true)
            $response = $this->respond(['response' => 'supplier_price updated successfully']);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete supplier_prices
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('supplier_prices', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplier_price_id = $this->request->getVar('supplier_price_id');

        $where = ['id' => $supplier_price_id, 'is_deleted' => 0];

        if (!$supplier_price = $this->supplierPriceModel->select('', $where, 1)) {
            $response = $this->failNotFound('supplier_price not found');
        } elseif (!$this->_attempt_delete($supplier_price_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'supplier_price deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search supplier_prices based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('supplier_prices', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $supplier_id   = $this->request->getVar('supplier_id') ? : null;
        $item_id = $this->request->getVar('item_id') ? : null;
        $price         = $this->request->getVar('price') ? : null;

        if (!$supplier_prices = $this->supplierPriceModel->search($supplier_id, $item_id, $price)) {
            $response = $this->failNotFound('No supplier_price found');
        } else {
            $response = [];
            $response['data'] = $supplier_prices;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($supplier_price_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $supplier_id = $this->request->getVar('supplier_id') ? : null;
        $where = [
            'id' => $supplier_id
        ];
        if (!$this->supplierModel->select('', $where, 1)) {
            $response = $this->fail('Supplier not found');
            return $response;
        }

        $item_id = $this->request->getVar('item_id') ? : null;
        $where = [
            'id' => $item_id
        ];
        if (!$this->itemModel->select('', $where, 1)) {
            $response = $this->fail('Item not found');
            return $response;
        }

        $where = [
            'id' => $supplier_price_id
        ];
        $values = [
            'supplier_id'   => $supplier_id,
            'item_id' => $item_id,
            'price'         => $this->request->getVar('price'),
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s'),
        ];

        if (!$this->supplierPriceModel->update($where, $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        $db->transCommit();
        $db->close();
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($supplier_price_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $supplier_price_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->supplierPriceModel->update($where, $values)) {
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
        $this->supplierPriceModel  = new Supplier_price();
        $this->supplierModel       = new Supplier();
        $this->itemModel     = new Item();
        $this->webappResponseModel = new Webapp_response();
    }
}

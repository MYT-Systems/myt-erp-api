<?php

namespace App\Controllers;

class Franchise_sale_item_prices extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get franchise_sale_item_price
     */
    public function get_franchise_sale_item_price()
    {
        if (($response = $this->_api_verification('franchise_sale_item_prices', 'get_franchise_sale_item_price')) !== true)
            return $response;
        
        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchise_sale_item_price_id = $this->request->getVar('franchise_sale_item_price_id') ? : null;
        $franchise_sale_item_price    = $franchise_sale_item_price_id ? $this->franchiseSaleItemPriceModel->get_details_by_id($franchise_sale_item_price_id) : null;

        if (!$franchise_sale_item_price) {
            $response = $this->failNotFound('No Franchise sale item price found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchise_sale_item_price
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all franchise_sale_item_prices
     */
    public function get_all_franchise_sale_item_price()
    {
        if (($response = $this->_api_verification('franchise_sale_item_prices', 'get_all_franchise_sale_item_price')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchise_sale_item_prices = $this->franchiseSaleItemPriceModel->get_all_franchise_sale_item_price();

        if (!$franchise_sale_item_prices) {
            $response = $this->failNotFound('No Franchise sale item price found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchise_sale_item_prices
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * create franchise sale item price.
     */
    public function create()
    {
        if (($response = $this->_api_verification('franchise_sale_item_prices', 'create')) !== true)
            return false;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchise_sale_item_price_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response'    => 'Franchise sale item price created successfully',
                'status'      => 'success',
                'franchise_sale_item_price_id' => $franchise_sale_item_price_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update franchise_sale_item_price
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('franchise_sale_item_prices', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchise_sale_item_price_id = $this->request->getVar('franchise_sale_item_price_id');
        $where       = ['id' => $franchise_sale_item_price_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();
        
        if (!$franchise_sale_item_price = $this->franchiseSaleItemPriceModel->select('', $where, 1))
            $response = $this->failNotFound('Franchise sale item price not found');
        elseif (!$this->_attempt_update($franchise_sale_item_price)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Franchise sale item price updated successfully']);
        }


        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete franchise_sale_item_prices
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('franchise_sale_item_prices', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchise_sale_item_price_id = $this->request->getVar('franchise_sale_item_price_id');

        $where = ['id' => $franchise_sale_item_price_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$franchise_sale_item_price = $this->franchiseSaleItemPriceModel->select('', $where, 1)) {
            $response = $this->failNotFound('Franchise sale item price not found');
        } elseif (!$this->_attempt_delete($franchise_sale_item_price)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Franchise sale item price deleted successfully']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search franchise_sale_item_prices based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('franchise_sale_item_prices', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $item_id   = $this->request->getVar('item_id') ?? null;
        $type      = $this->request->getVar('type') ?? null;
        $item_name = $this->request->getVar('item_name') ?? null;
        $branch_id = $this->request->getVar('branch_id') ?? null;

        if (!$franchise_sale_item_prices = $this->franchiseSaleItemPriceModel->search($item_id, $type, $item_name, $branch_id)) {
            $response = $this->failNotFound('No Franchise sale item price found');
        } else {
            $response = $this->respond([
                'response' => 'Franchise sale item prices found',
                'status'   => 'success',
                'data'     => $franchise_sale_item_prices
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Methods
    // ------------------------------------------------------------------------

    /**
     * Attempt to create franchise sale item price.
     */
    private function _attempt_create()
    {
        $values = [
            'item_id'       => $this->request->getVar('item_id'),
            'item_unit_id'  => $this->request->getVar('item_unit_id'),
            'unit'          => $this->request->getVar('unit'),
            'type'          => $this->request->getVar('type'),
            'price_1'       => $this->request->getVar('price_1'),
            'price_2'       => $this->request->getVar('price_2'),
            'price_3'       => $this->request->getVar('price_3'),
            'added_by'      => $this->requested_by,
            'added_on'      => date('Y-m-d H:i:s'),
        ];

        if (!$franchise_sale_item_price_id = $this->franchiseSaleItemPriceModel->insert_on_duplicate($values, $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Update the item to be is_for_sale
        if (!$this->_update_item_is_for_sale($values['item_id'], 1))
            return false;

        return $franchise_sale_item_price_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($franchise_sale_item_price)
    {
        if (!$this->_update_item_is_for_sale($franchise_sale_item_price['item_id'], 0))
            return false;

        $values = [
            'item_id'       => $this->request->getVar('item_id'),
            'item_unit_id'  => $this->request->getVar('item_unit_id'),
            'unit'          => $this->request->getVar('unit'),
            'type'          => $this->request->getVar('type'),
            'price_1'       => $this->request->getVar('price_1'),
            'price_2'       => $this->request->getVar('price_2'),
            'price_3'       => $this->request->getVar('price_3'),
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseSaleItemPriceModel->insert_on_duplicate($values, $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_update_item_is_for_sale($values['item_id'], 1))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($franchise_sale_item_price)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseSaleItemPriceModel->update($franchise_sale_item_price['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->_update_item_is_for_sale($franchise_sale_item_price['item_id'], 0))
            return false;
        
        return true;
    }

    /**
     * Update item to is_for_sale
     */
    protected function _update_item_is_for_sale($item_id, $is_for_sale)
    {
        $values = [
            'is_for_sale' => $is_for_sale,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        if (!$this->itemModel->update($item_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$is_for_sale) {
            // Delete all franchise sale item price
            if (!$this->franchiseSaleItemPriceModel->delete_by_item_id($item_id, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
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
        $this->franchiseSaleItemPriceModel = model('App\Models\Franchise_sale_item_price');
        $this->itemModel                   = model('App\Models\Item');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
    }
}

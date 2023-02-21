<?php

namespace App\Controllers;

use App\Models\Price_level;
use App\Models\Price_level_type;
use App\Models\Price_level_type_detail;
use App\Models\Webapp_response;

class Price_levels extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get price_level
     */
    public function get_price_level()
    {
        if (($response = $this->_api_verification('price_level', 'get_price_level')) !== true)
            return $response;

        $price_level_id           = $this->request->getVar('price_level_id') ? : null;
        $is_add_on                = $this->request->getVar('is_add_on');
        $price_level              = $price_level_id ? $this->priceLevelModel->get_details_by_id($price_level_id) : null;
        $price_level_types        = $price_level ? $this->priceLevelTypeModel->get_details_by_price_level_id($price_level_id) : null;

        if (!$price_level) {
            $response = $this->failNotFound('No price level found');
        } else {
            foreach ($price_level_types as $key => $value) {
                $products = $this->priceLevelTypeDetailModel->get_details_by_price_level_type_id($value['id']);
                // // var_dump($products);
                $variants = [];
                foreach ($products as $key2 => $value2) {
                    // check if product is addon
                  
                    if ($is_add_on == '0' && $value2['is_addon'] == '1') {
                        

                        continue;
                    } elseif ($is_add_on == '1' && $value2['is_addon'] == '0') {
                        continue;
                    }
                 
                    $splitted_value = explode(' - ', $value2['product_name'] ?? '');
                    $product_name = trim($splitted_value[0]);
                    $variant_name = isset($splitted_value[1]) ? trim($splitted_value[1]) : null;
                    
                    $temp_var = [
                        'product_name' => $product_name,
                        'variant' => [
                            [
                            'id' => $value2['product_id'] ?? null,
                            'name' => $variant_name,
                            'price' => $value2['price'] ?? null,
                            'details' => $value2['product_details'] ?? null,
                            'whole_name' => $value2['product_name'] ?? null
                            ]
                        ]
                    ];

                    if (count($variants) > 0) {
                        $is_found = false;
                        foreach ($variants as $key3 => $value3) {
                            if ($value3['product_name'] == $product_name) {
                                $is_found = true;
                                $variants[$key3]['variant'][] = $temp_var['variant'][0];
                            }
                        }
                        if (!$is_found) {
                            $variants[] = $temp_var;
                        }
                    } else {
                        $variants[] = $temp_var;
                    }
                }
                $price_level_types[$key]['products'] = $variants;
            }

            $price_level[0]['price_level_types'] = $price_level_types;

            $response = $this->respond([
                'status' => 'success',
                'data'   => $price_level[0]
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all price_level
     */
    public function get_all_price_level()
    {
        if (($response = $this->_api_verification('price_level', 'get_all_price_level')) !== true)
            return $response;

        $price_levels = $this->priceLevelModel->get_all_price_levels();

        if (!$price_levels) {
            $response = $this->failNotFound('No price level found');
        } else {
            foreach ($price_levels as $key => $price_level) {
                $price_levels[$key]['price_level_types'] = $this->priceLevelTypeModel->get_details_by_price_level_id($price_level['id']);
                foreach ($price_levels[$key]['price_level_types'] as $key2 => $value) {
                    $price_levels[$key]['price_level_types'][$key2]['price_level_type_details'] = $this->priceLevelTypeDetailModel->get_details_by_price_level_type_id($value['id']);
                }
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $price_levels
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create price_level
     */
    public function create()
    {
        if (($response = $this->_api_verification('price_level', 'create')) !== true)
            return $response;

        $name = $this->request->getVar('name');
        if ($response = $this->_is_existing($this->priceLevelModel, ['name' => $name]))
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$price_level_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create price level.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_price_level_type($price_level_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate price level type.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'price_level_id' => $price_level_id,
                'response' => 'Price level created successfully.', 
                'status' => 'success'
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update price_level
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('price_level', 'update')) !== true)
            return $response;

        $price_level_id = $this->request->getVar('price_level_id');
        $where     = ['id' => $price_level_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$price_level = $this->priceLevelModel->select('', $where, 1)) {
            $response = $this->failNotFound('price level not found');
        } elseif (!$this->_attempt_update($price_level_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update price_level.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_price_level_type($price_level['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update price level item.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'price level updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete price_level
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('price_level', 'delete')) !== true)
            return $response;

        $price_level_id = $this->request->getVar('price_level_id');

        $where = ['id' => $price_level_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$price_level = $this->priceLevelModel->select('', $where, 1)) {
            $response = $this->failNotFound('price level not found');
        } elseif (!$this->_attempt_delete($price_level_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Faield to delete price_level.', 'status' => 'error']);
        } elseif (!$this->_attempt_delete_price_level_type($price_level_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete price level type.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'price level deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search price level based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('price_level', 'search')) !== true)
            return $response;

        $name = $this->request->getVar('name');

        if (!$price_level = $this->priceLevelModel->search($name)) {
            $response = $this->failNotFound('No price level found');
        } else {
            $price_level_types = $this->priceLevelTypeModel->get_details_by_price_level_id($price_level[0]['id']);
            foreach ($price_level_types as $key => $value) {
                $price_level_types[$key]['price_level_type_details'] = $this->priceLevelTypeDetailModel->get_details_by_price_level_type_id($value['id']);
            }
            $price_level[0]['price_level_types'] = $price_level_types;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $price_level
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create price_level
     */
    private function _attempt_create()
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
            'is_deleted' => 0
        ];

        if (!$price_level_id = $this->priceLevelModel->insert($values))
            return false;

        return $price_level_id;
    }

    /**
     * Attempt generate price level items
     */
    protected function _attempt_generate_price_level_type($price_level_id)
    {
        $names   = $this->request->getVar('names');
        $commission_rates = $this->request->getVar('commission_rates');

        foreach($names as $key => $name) {
            $values = [
                'price_level_id'  => $price_level_id,
                'name'            => $name,
                'commission_rate' => $commission_rates[$key],
                'added_by'        => $this->requested_by,
                'added_on'        => date('Y-m-d H:i:s')
            ];

            if (!$price_level_type_id = $this->priceLevelTypeModel->insert($values))
                return false;
            
            if (!$this->_attempt_generate_price_level_type_details($key, $price_level_type_id, $price_level_id))
                return false;
        }

        return true;
    }

    /**
     * Attempt generate price level type details
     */
    protected function _attempt_generate_price_level_type_details($key, $price_level_type_id, $price_level_id)
    {
        $product_ids    = $this->request->getVar('product_ids_' . $key);
        $prices         = $this->request->getVar('prices_' . $key);

        $values = [
            'price_level_id'      => $price_level_id,
            'price_level_type_id' => $price_level_type_id,
            'added_by'            => $this->requested_by,
            'added_on'            => date('Y-m-d H:i:s')
        ];

        foreach($product_ids as $key => $product_id) {
            $values['product_id'] = $product_id;
            $values['price']      = $prices[$key];

            if (!$this->priceLevelTypeDetailModel->insert($values))
                return false;
        }

        return true;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($price_level_id)
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->priceLevelModel->update($price_level_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt update price level item
     */
    protected function _attempt_update_price_level_type($price_level_id)
    {
        $price_level_types = $this->priceLevelTypeModel->get_details_by_price_level_id($price_level_id);
        if (!$this->priceLevelTypeModel->delete_by_price_level_id($price_level_id, $this->requested_by))
            return false;

        foreach($price_level_types as $price_level_type) {
            var_dump($price_level_type['id']);
            if (!$this->priceLevelTypeDetailModel->delete_by_price_level_type_id($price_level_type['id'], $this->requested_by))
                return false;
        }

        if (!$this->_attempt_generate_price_level_type($price_level_id))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($price_level_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->priceLevelModel->update($price_level_id, $values)) {
            return false;
        }
        
        return true;
    }

    /**
     * Attempt delete price level type
     */
    protected function _attempt_delete_price_level_type($price_level_id)
    {
        $price_level_types = $this->priceLevelTypeModel->get_details_by_price_level_id($price_level_id);
        if (!$this->priceLevelTypeModel->delete_by_price_level_id($price_level_id, $this->requested_by))
            return false;

        foreach($price_level_types as $price_level_type) {
            if (!$this->priceLevelTypeDetailModel->delete_by_price_level_type_id($price_level_type['id'], $this->requested_by))
                return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->priceLevelModel           = new Price_level();
        $this->priceLevelTypeModel       = new Price_level_type();
        $this->priceLevelTypeDetailModel = new Price_level_type_detail();
        $this->webappResponseModel       = new Webapp_response();
    }
}

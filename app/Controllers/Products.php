<?php

namespace App\Controllers;

class Products extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get product
     */
    public function get_product()
    {
        if (($response = $this->_api_verification('products', 'get_product')) !== true)
            return $response;

        $product_id = $this->request->getVar('product_id') ? : null;
        $product    = $product_id ? $this->productModel->get_details_by_id($product_id) : null;
        $items      = $product ? $this->productItemModel->get_details_by_product_id($product_id) : null;

        if (!$product) {
            $response = $this->failNotFound('No product found');
        } else {
            $product[0]['items'] = $items;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $product[0]
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all products
     */
    public function get_all_product()
    {
        if (($response = $this->_api_verification('products', 'get_all_product')) !== true)
            return $response;

        $product_name = $this->request->getVar('product_name') ? : null;

        if (!$products = $this->productModel->get_all_product($product_name)) {
            $response = $this->failNotFound('No product found');
        } else {
            foreach ($products as $key => $product) {
                $products[$key]['items'] = $this->productItemModel->get_details_by_product_id($product['id']);
            }
            $response = $this->respond([
                'status' => 'success',
                'data'   => $products
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create product
     */
    public function create()
    {
        if (($response = $this->_api_verification('products', 'create')) !== true || 
            ($response = $this->_validation_check(['product'])) !== true) {
            return $response;
        }

        $name = $this->request->getVar('name');
        if ($response = $this->_is_existing($this->productModel, ['name' => $name]))
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        $is_addon = $this->request->getVar('is_addon');

        if (!$product_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create product.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_product_item($product_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate product item.', 'status' => 'error']);
        } elseif ($is_addon AND !$this->_attempt_generate_addon_requirements($product_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate product addon requirement items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response' => 'Product created successfully.', 
                'status' => 'success',
                'product_id' => $product_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update product
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('products', 'update')) !== true)
            return $response;

        $product_id = $this->request->getVar('product_id');
        $where     = ['id' => $product_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$product = $this->productModel->select('', $where, 1)) {
            $response = $this->failNotFound('product not found');
        } elseif (!$this->_attempt_update($product_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update product.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_product_item($product['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update product item.', 'status' => 'error']);
        } elseif ($is_addon AND !$this->_attempt_generate_update_requirements($product_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update product addon requirement items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Product updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete products
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('products', 'delete')) !== true)
            return $response;

        $product_id = $this->request->getVar('product_id');

        $where = ['id' => $product_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$product = $this->productModel->select('', $where, 1)) {
            $response = $this->failNotFound('product not found');
        } elseif (!$this->_attempt_delete($product_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Faield to delete product.', 'status' => 'error']);
        } elseif (!$this->productItemModel->delete_by_product_id($product_id, $this->requested_by)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete product item.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Product deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search products based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('products', 'search')) !== true)
            return $response;

        $name     = $this->request->getVar('name');
        $is_addon = $this->request->getVar('is_addon');
        $details  = $this->request->getVar('details');

        if (!$products = $this->productModel->search($name, $is_addon, $details)) {
            $response = $this->failNotFound('No product found');
        } else {
            $variants = [];
            foreach ($products as $key => $product) {
                $splitted_value = explode(' - ', $product['name']);
                $product_name = $splitted_value[0];
                $variant_name = $splitted_value[1] ?? null;
                $temp_var = [
                    'product_name' => $product_name,
                    'variant' => [
                        [
                        'id' => $product['id'] ?? null,
                        'name' => $variant_name,
                        'price' => $product['price'] ?? null,
                        'whole_name' => $product['name'] ?? null
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

            $products = $variants;


            $response = $this->respond([
                'status' => 'success',
                'data'   => $products
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt generate addon product requirements
     */
    protected function _attempt_generate_addon_requirements($addon_id)
    {
        $values = [
            'addon_id' => $addon_id,
            'added_on' => date("Y-m-d H:i:s"),
            'added_by' => $this->requested_by
        ];

        $req_product_item_ids = $this->request->getVar('req_product_item_ids');
        $req_item_ids = $this->request->getVar('req_item_ids');
        $req_quantities = $this->request->getVar('req_quantities');
        $req_units = $this->request->getVar('req_units');

        foreach ($req_product_item_ids as $index => $product_item_id) {
            $values['product_item_id'] = $product_item_id;
            $values['item_id'] = $req_item_ids[$index];
            $values['qty'] = $req_quantities[$index];
            $values['unit'] = $req_units[$index];

            if (!$this->productAddonReqModel->insert($values))
                return false;
        }

        return true;
    }

    /**
     * Attempt generate update product requirements
     */
    protected function _attempt_generate_update_requirements($addon_id)
    {
        $delete_conditions = ['addon_id' => $addon_id, 'is_deleted' => 0];
        $delete_values = [
            'is_deleted' => 1,
            'updated_on' => date("Y-m-d H:i:s"),
            'updated_by' => $this->requested_by
        ];

        if (!$this->productAddonReqModel->update($delete_conditions, $delete_values))
            return false;

        $values = [
            'addon_id' => $addon_id,
            'added_on' => date("Y-m-d H:i:s"),
            'added_by' => $this->requested_by
        ];

        $req_product_item_ids = $this->request->getVar('req_product_item_ids');
        $req_item_ids = $this->request->getVar('req_item_ids');
        $req_quantities = $this->request->getVar('req_quantities');
        $req_units = $this->request->getVar('req_units');

        foreach ($req_product_item_ids as $index => $product_item_id) {
            $values['product_item_id'] = $product_item_id;
            $values['item_id'] = $req_item_ids[$index];
            $values['qty'] = $req_quantities[$index];
            $values['unit'] = $req_units[$index];

            if (!$this->productAddonReqModel->insert($values))
                return false;
        }

        return true;
    }

    /**
     * Attempt create product
     */
    private function _attempt_create()
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'is_addon'   => $this->request->getVar('is_addon'),
            'details'    => $this->request->getVar('details'),
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
            'is_deleted' => 0
        ];

        if ($file = $this->request->getFile('image_file')) {
            // file upload error
            if (!$file || $file->getError() == 4) {
                var_dump($file->getError());
                return false;
            }
            
            // convert the uploaded file into base64
            $base64 = base64_encode(file_get_contents($file->getTempName()));
            $base64_file = 'data:' . $file->getMimeType() . ';base64,' . $base64;
            $values['image64'] = $base64_file;
        }

        if (!$product_id = $this->productModel->insert($values)) {
            return false;
        }

        return $product_id;
    }

    /**
     * Attempt generate product items
     */
    protected function _attempt_generate_product_item($product_id)
    {
        $item_ids   = $this->request->getVar('item_ids');
        $units      = $this->request->getVar('units');
        $quantities = $this->request->getVar('quantities');

        foreach($item_ids as $key => $item_id) {
            $values = [
                'product_id' => $product_id,
                'item_id'    => $item_id,
                'qty'        => $quantities[$key],
                'unit'       => $units[$key],
                'added_by'   => $this->requested_by,
                'added_on'   => date('Y-m-d H:i:s')
            ];

            if (!$this->productItemModel->insert($values)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($product_id)
    {
        $values = [
            'name'       => $this->request->getVar('name'),
            'is_addon'   => $this->request->getVar('is_addon'),
            'details'    => $this->request->getVar('details'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if ($file = $this->request->getFile('image_file')) {
            // file upload error
            if (!$file || $file->getError() == 4) {
                var_dump($file->getError());
                return false;
            }
            
            // convert the uploaded file into base64
            $base64 = base64_encode(file_get_contents($file->getTempName()));
            $base64_file = 'data:' . $file->getMimeType() . ';base64,' . $base64;
            $values['image64'] = $base64_file;
        }

        if (!$this->productModel->update($product_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt update product item
     */
    protected function _attempt_update_product_item($product_id)
    {
        if (!$this->productItemModel->delete_by_product_id($product_id, $this->requested_by))
            return false;

        if (!$this->_attempt_generate_product_item($product_id))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($product_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->productModel->update($product_id, $values)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->productModel         = model('App\Models\Product');
        $this->productItemModel     = model('App\Models\Product_item');
        $this->productAddonReqModel = model('App\Models\Product_addon_requirement');
        $this->webappResponseModel  = model('App\Models\Webapp_response');
    }
}

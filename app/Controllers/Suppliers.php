<?php

namespace App\Controllers;

use App\Models\Supplier;
use App\Models\Webapp_response;

class Suppliers extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get supplier
     */
    public function get_supplier()
    {
        if (($response = $this->_api_verification('suppliers', 'get_supplier')) !== true)
            return $response;

        $supplier_id = $this->request->getVar('supplier_id') ? : null;
        $supplier    = $supplier_id ? $this->supplierModel->get_details_by_id($supplier_id) : null;

        if (!$supplier) {
            $response = $this->failNotFound('No supplier found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $supplier
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all suppliers
     */
    public function get_all_supplier()
    {
        if (($response = $this->_api_verification('suppliers', 'get_all_supplier')) !== true)
            return $response;

        $suppliers = $this->supplierModel->get_all_supplier();

        if (!$suppliers) {
            $response = $this->failNotFound('No supplier found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $suppliers
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create supplier
     */
    public function create()
    {
        if (($response = $this->_api_verification('suppliers', 'create')) !== true)
            return $response;
            
        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$supplier_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->failServerError('Failed to create supplier: ' . ($this->errorMessage ?? 'Unknown error'));
        } else {
            if (($this->request->getFile('file') || $this->request->getFileMultiple('file'))
                && !$this->_attempt_upload_file_base64($this->supplierAttachmentModel, ['supplier_id' => $supplier_id])) {
                
                $db->transRollback();
                $response = $this->respond(['response' => 'Supplier file upload failed']);
            } else {
                $db->transCommit();
                $response = $this->respond([
                    'response'    => 'Supplier created successfully',
                    'status'      => 'success',
                    'supplier_id' => $supplier_id
                ]);
            }
        }
        
        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }
    /*public function create()
    {
        if (($response = $this->_api_verification('suppliers', 'create')) !== true)
            return $response;
            
        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$supplier_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->failServerError('Failed to create supplier');
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'    => 'supplier created successfully',
                'status'      => 'success',
                'supplier_id' => $supplier_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }*/

    /**
     * Update supplier
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('suppliers', 'update')) !== true)
            return $response;

        $supplier_id = $this->request->getVar('supplier_id');
        $where       = ['id' => $supplier_id, 'is_deleted' => 0];


        $db = \Config\Database::connect();
        $db->transBegin();
        
        if (!$supplier = $this->supplierModel->select('', $where, 1))
            $response = $this->failNotFound('supplier not found');
        elseif (!$this->_attempt_update($supplier_id)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'supplier updated successfully']);
        }


        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete suppliers
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('suppliers', 'delete')) !== true)
            return $response;

        $supplier_id = $this->request->getVar('supplier_id');

        $where = ['id' => $supplier_id, 'is_deleted' => 0];

        if (!$supplier = $this->supplierModel->select('', $where, 1)) {
            $response = $this->failNotFound('supplier not found');
        } elseif (!$this->_attempt_delete($supplier_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'supplier deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search suppliers based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('suppliers', 'search')) !== true)
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
        $vat_type               = $this->request->getVar('vat_type');
        $contact_person         = $this->request->getVar('contact_person');
        $bank_primary           = $this->request->getVar('bank_primary');
        $primary_account_no     = $this->request->getVar('primary_account_no');
        $primary_account_name   = $this->request->getVar('primary_account_name');
        $bank_alternate         = $this->request->getVar('bank_alternate');
        $alternate_account_no   = $this->request->getVar('alternate_account_no');
        $alternate_account_name = $this->request->getVar('alternate_account_name');

        if (!$suppliers = $this->supplierModel->search($trade_name, $trade_address, $bir_name, $bir_number, $bir_address, $tin, $terms, $requirements, $phone_no, $email, $contact_person, $bank_primary, $primary_account_no, $primary_account_name, $bank_alternate, $alternate_account_no, $alternate_account_name)) {
            $response = $this->failNotFound('No supplier found');
        } else {
            foreach ($suppliers as $key => $supplier) {
                $suppliers[$key]['attachments'] = $this->supplierAttachmentModel->get_details_by_supplier_id($supplier['id']);
            }
            $response = $this->respond([
                'response' => 'suppliers found',
                'status'   => 'success',
                'data'     => $suppliers
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Methods
    // ------------------------------------------------------------------------

    /**
     * Attempt to create supplier
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
            'vat_type'               => $this->request->getVar('vat_type'),
            'added_by'               => $this->requested_by,
            'added_on'               => date('Y-m-d H:i:s'),
        ];

        if (!$supplier_id = $this->supplierModel->insert($values))
            return false;

        return $supplier_id;
    }

    protected function _save_attachment_from_sync($data, $expense_id)
    {
        $values = [];
        foreach ($data['supplier_attachments'] as $attachment) {
            $values[] = [
                'supplier_id' => $supplier_id,
                // 'name'   => $attachment['file_name'],
                'base_64'   => $attachment,
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];
        }

        if(count($values) > 0 AND !$this->supplierAttachmentModel->insertBatch($values))
            return false;
        return true;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($supplier_id)
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
            'vat_type'               => $this->request->getVar('vat_type'),
            'updated_by'             => $this->requested_by,
            'updated_on'             => date('Y-m-d H:i:s')
        ];

        if (!$this->supplierModel->update($supplier_id, $values)) {
            return false;
        }

        // Check if file parameter is present and not empty
        $file = $this->request->getFileMultiple('file');
        if (!$this->supplierAttachmentModel->delete_attachment_by_supplier_id($supplier_id, $this->requested_by)) {
            return false;
        } elseif($file AND !$response = $this->_attempt_upload_file_base64($this->supplierAttachmentModel, ['supplier_id' => $supplier_id]) AND
                $response === false) {
            return false;
        }

        // if (!$this->supplierAttachmentModel->delete_attachment_by_supplier_id($supplier_id, $this->requested_by)) {
        //     return false;
        // }

        // if (($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$response = $this->_attempt_upload_file_base64($this->supplierAttachmentModel, ['supplier_id' => $supplier_id]) &&
        //     $response === false) {
        //     $db->transRollback();
        //     $response = $this->respond(['response' => 'supplier attachment file upload failed']);
        //     }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($supplier_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $supplier_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->supplierModel->update($where, $values)) {
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
        $this->supplierAttachmentModel = model('App\Models\Supplier_attachment');
        $this->supplierModel       = new Supplier();
        $this->webappResponseModel = new Webapp_response();
    }
}

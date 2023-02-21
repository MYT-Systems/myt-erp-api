<?php

namespace App\Controllers;

class Wastages extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get wastage
     */
    public function get_wastage()
    {
        if (($response = $this->_api_verification('wastages', 'get_wastage')) !== true)
            return $response;

        $wastage_id    = $this->request->getVar('wastage_id') ? : null;
        $wastage       = $wastage_id ? $this->wastageModel->get_details_by_id($wastage_id) : null;
        $wastage_item  = $wastage_id ? $this->wastageItemModel->get_all_wastage_item_by_wastage_id($wastage_id) : null;

        if (!$wastage) {
            $response = $this->failNotFound('No wastage found');
        } else {
            $wastage[0]['wastage_item'] = $wastage_item;
            $response = $this->respond([
                'data'   => $wastage,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all wastages
     */
    public function get_all_wastage()
    {
        if (($response = $this->_api_verification('wastages', 'get_all_wastage')) !== true)
            return $response;

        $wastages = $this->wastageModel->get_all_wastage();

        if (!$wastages) {
            $response = $this->failNotFound('No wastage found');
        } else {
            foreach ($wastages as $key => $wastage) {
                $wastages[$key]['wastage_item'] = $this->wastageItemModel->get_all_wastage_item_by_wastage_id($wastage['id']);
            }

            $response = $this->respond([
                'data'   => $wastages,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create wastage
     */
    public function create()
    {
        if (($response = $this->_api_verification('wastages', 'create')) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$wastage_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to create wastage.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_wastage_item($wastage_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {     
            $this->db->transCommit();
            $response = $this->respond([
                'response'   => 'wastage created successfully.',
                'status'     => 'success',
                'wastage_id' => $wastage_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update wastage
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('wastages', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('wastage_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$wastage = $this->wastageModel->select('', $where, 1))
            $response = $this->failNotFound('wastage not found');
        elseif (!$this->_attempt_update($wastage['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to update wastage.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_wastage_item($wastage['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to update wastage item.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'wastage updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete wastages
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('wastages', 'delete')) !== true)
            return $response;

        $where = [
            'id'  => $this->request->getVar('wastage_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$wastage = $this->wastageModel->select('', $where, 1)) {
            $response = $this->failNotFound('wastage not found');
        } elseif (!$this->_attempt_delete($wastage['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete wastage.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'wastage deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search wastages based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('wastages', 'search')) !== true)
            return $response;

        $branch_id         = $this->request->getVar('branch_id');
        $wastage_date_from = $this->request->getVar('wastage_date_from');
        $wastage_date_to   = $this->request->getVar('wastage_date_to');
        $description       = $this->request->getVar('description');
        $remarks           = $this->request->getVar('remarks');
        $branch_name       = $this->request->getVar('branch_name');

        if (!$wastages = $this->wastageModel->search($branch_id, $wastage_date_from, $wastage_date_to, $description, $remarks, $branch_name)) {
            $response = $this->failNotFound('No wastage found');
        } else {
            $response = $this->respond([
                'data' => $wastages,
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record Action
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('wastages', 'record_action')) !== true)
            return $response;

        $wastage_id = $this->request->getVar('wastage_id');
        $action     = $this->request->getVar('action');

        if (!$wastage = $this->wastageModel->get_details_by_id($wastage_id)) {
            $response = $this->failNotFound('wastage not found');
        } elseif (!$this->_attempt_record_action($wastage_id, $action)) {
            $response = $this->fail(['response' => 'Failed to record action.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Action recorded successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create wastage
     */
    private function _attempt_create()
    {
        $values = [
            'branch_id'    => $this->request->getVar('branch_id'),
            'wastage_date' => $this->request->getVar('wastage_date'),
            'description'  => $this->request->getVar('description'),
            'remarks'      => $this->request->getVar('remarks'),
            'added_by'     => $this->requested_by,
            'added_on'     => date('Y-m-d H:i:s'),
        ];

        if (!$wastage_id = $this->wastageModel->insert($values))
            return false;

        return $wastage_id;
    }

    /**
     * Attempt generate wastage item
     */
    protected function _attempt_generate_wastage_item($wastage_id)
    {
        $names      = $this->request->getVar('names');
        $units      = $this->request->getVar('units');
        $quantities = $this->request->getVar('quantities');
        $types      = $this->request->getVar('types');
        $reasons    = $this->request->getVar('reasons');

        $values = [
            'wastage_id' => $wastage_id,
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
        ];

        foreach ($names as $key => $name) {
            $values['name']   = $name;
            $values['unit']   = $units[$key];
            $values['qty']    = $quantities[$key];
            $values['type']   = $types[$key];
            $values['reason'] = $reasons[$key];

            if (!$this->wastageItemModel->insert($values)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }
        
        return true;
    }


    /**
     * Attempt update
     */
    protected function _attempt_update($wastage_id)
    {
        $values = [
            'branch_id'    => $this->request->getVar('branch_id'),
            'wastage_date' => $this->request->getVar('wastage_date'),
            'description'  => $this->request->getVar('description'),
            'remarks'      => $this->request->getVar('remarks'),
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s')
        ];

        if (!$this->wastageModel->update($wastage_id, $values))
            return false;
        
        return true;
    }

    /**
     * Attempt update wastage item
     */
    protected function _attempt_update_wastage_item($wastage_id)
    {
        if (!$this->wastageItemModel->delete_wastage_item_by_wastage_id($wastage_id, $this->requested_by, $this->db))
            return false;
        
        if (!$this->_attempt_generate_wastage_item($wastage_id))
            return false;
        
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($wastage_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->wastageModel->update($wastage_id, $values))
            return false;

        if (!$this->wastageItemModel->delete_wastage_item_by_wastage_id($wastage_id, $this->requested_by))
            return false;

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($wastage_id, $action)
    {
        $values = [
            'action'      => $action,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        switch ($action) {
            case 'approved':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                break;
            case 'rejected':
                $values['rejected_by'] = $this->requested_by;
                $values['rejected_on'] = date('Y-m-d H:i:s');
                break;
        }

        if (!$this->wastageModel->update($wastage_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->wastageModel           = model('App\Models\Wastage');
        $this->wastageItemModel       = model('App\Models\Wastage_item');
        $this->webappResponseModel    = model('App\Models\Webapp_response');
    }
}

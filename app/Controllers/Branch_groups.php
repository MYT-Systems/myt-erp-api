<?php

namespace App\Controllers;

class Branch_groups extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get branch_group
     */
    public function get_branch_group()
    {
        if (($response = $this->_api_verification('branch_groups', 'get_branch_group')) !== true )
            return $response;

        $branch_group_id    = $this->request->getVar('branch_group_id') ? : null;
        $branch_group       = $branch_group_id ? $this->branchGroupModel->get_details_by_id($branch_group_id) : null;
        $branch_group_details = $branch_group_id ? $this->branchGroupDetailModel->get_details_by_branch_group_id($branch_group_id) : null;
        
        if (!$branch_group) {
            $response = $this->failNotFound('No branch group found');
        } else {
            $branch_group[0]['branch_group_details'] = $branch_group_details;
            $response = $this->respond([
                'data'   => $branch_group,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all branch_groups
     */
    public function get_all_branch_group()
    {
        if (($response = $this->_api_verification('branch_groups', 'get_all_branch_group')) !== true) {
            return $response;
        }
        
        if (!$branch_groups = $this->branchGroupModel->get_all()) {
            $response = $this->failNotFound('No branch group found');
        } else {
            foreach ($branch_groups as $key => $branch_group) {
                $branch_groups[$key]['branch_group_details'] = $this->branchGroupDetailModel->get_details_by_branch_group_id($branch_group['id']);
            }

            $response = $this->respond([
                'data' => $branch_groups,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create branch_group
     */
    public function create()
    {
        if (($response = $this->_api_verification('branch_groups', 'create')) !== true) {
            return $response;
        }

        $name = $this->request->getVar('name');
        if ($response = $this->_is_existing($this->branchGroupModel, ['name' => $name]))
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$branch_group_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_details($branch_group_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'data' => $branch_group_id,
                'status' => 'success'
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update branch_group
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('branch_groups', 'update')) !== true)
            return $response;

        $branch_group_id = $this->request->getVar('branch_group_id');
        $where   = ['id' => $branch_group_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$branch_group = $this->branchGroupModel->select('', $where, 1)) {
            $response = $this->failNotFound('Branch group not found');
        } elseif (!$this->_attempt_update($branch_group)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Branch group updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete branch_groups
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('branch_groups', 'delete')) !== true)
            return $response;

        $branch_group_id = $this->request->getVar('branch_group_id');
        $where   = ['id' => $branch_group_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$branch_group = $this->branchGroupModel->select('', $where, 1)) {
            $response = $this->failNotFound('Branch group not found');
        } elseif (!$this->_attempt_delete($branch_group_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Branch group deleted successfully', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search branch_groups based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('branch_groups', 'search')) !== true)
            return $response;

        $name             = $this->request->getVar('name');
        $supervisor       = $this->request->getVar('supervisor');
        $supervisor_id    = $this->request->getVar('supervisor_id');
        $details          = $this->request->getVar('details');
        $number_of_branch = $this->request->getVar('number_of_branch');

        if (!$branch_groups = $this->branchGroupModel->search($name, $supervisor, $supervisor_id, $details, $number_of_branch)) {
            $response = $this->failNotFound('No branch group found');
        } else {
            foreach ($branch_groups as $key => $branch_group) {
                $branch_groups[$key]['branch_group_details'] = $this->branchGroupDetailModel->get_details_by_branch_group_id($branch_group['id']);
            }

            $response = $this->respond([
                'data'   => $branch_groups,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to generate units
     */
    protected function _attempt_generate_details($branch_group_id)
    {
        $branch_ids = $this->request->getVar('branch_ids');

        $values = [
            'branch_group_id' => $branch_group_id,
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s'),
        ];

        foreach ($branch_ids as $key => $branch_id) {
            $values['branch_id'] = $branch_id;
            if (!$this->branchGroupDetailModel->insert_on_duplicate($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }
        
        $number_of_branches = count($branch_ids);
        $values = [
            'number_of_branch' => $number_of_branches,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        if (!$this->branchGroupModel->update($branch_group_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt to create branch_group
     */
    protected function _attempt_create()
    {
        $values = [
            'name'          => $this->request->getVar('name'),
            'supervisor'    => $this->request->getVar('supervisor'),
            'supervisor_id' => $this->request->getVar('supervisor_id'),
            'details'       => $this->request->getVar('details'),
            'added_by'      => $this->requested_by,
            'added_on'      => date('Y-m-d H:i:s'),
            'is_deleted'    => 0
        ];

        if (!$branch_group_id = $this->branchGroupModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        $values = [
            'type' => 'supervisor',
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s'),
        ];

        if (!$this->userModel->update($this->request->getVar('supervisor_id'), $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $branch_group_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($branch_group)
    {
        $values = [
            'name'          => $this->request->getVar('name'),
            'supervisor'    => $this->request->getVar('supervisor'),
            'supervisor_id' => $this->request->getVar('supervisor_id'),
            'details'       => $this->request->getVar('details'),
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s')
        ];

        if (!$this->branchGroupModel->update($branch_group['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->branchGroupDetailModel->delete_by_branch_group_id($branch_group['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        if (!$this->_attempt_generate_details($branch_group['id']))
            return false;
            
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($branch_group_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->branchGroupModel->update($branch_group_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        if (!$this->branchGroupDetailModel->delete_by_branch_group_id($branch_group_id, $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->branchGroupModel       = model('App\Models\Branch_group');
        $this->branchGroupDetailModel = model('App\Models\Branch_group_detail');
        $this->userModel              = model('App\Models\User'); 
        $this->webappResponseModel    = model('App\Models\Webapp_response');
    }
}

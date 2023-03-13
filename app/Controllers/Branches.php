<?php

namespace App\Controllers;

class Branches extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get branch
     */
    public function get_branch()
    {
        if (($response = $this->_api_verification('branches', 'get_branch')) !== true)
            return $response;

        $branch_id         = $this->request->getVar('branch_id') ? : null;
        $branch            = $branch_id ? $this->branchModel->get_details_by_id($branch_id) : null;
        $branch_attachment = $branch_id ? $this->branchAttachmentModel->get_details_by_branch_id($branch_id) : null;

        if (!$branch) {
            $response = $this->failNotFound('No branch found');
        } else {
            $branch[0]['attachment'] = $branch_attachment;

            $response = $this->respond([
                'status' => 'success',
                'data'   => $branch
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all branches
     */
    public function get_all_branch()
    {
        if (($response = $this->_api_verification('branches', 'get_all_branch')) !== true)
            return $response;

        $branches = $this->branchModel->get_all_branch();

        if (!$branches) {
            $response = $this->failNotFound('No branch found');
        } else {
            foreach ($branches as $key => $branch) {
                $branch_attachment = $this->branchAttachmentModel->get_details_by_branch_id($branch['id']);
                $branches[$key]['attachment'] = $branch_attachment;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $branches
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create branch
     */
    public function create()
    {
        if (($response = $this->_api_verification('branches', 'create')) !== true)
            return $response;

        $where = ['name' => $this->request->getVar('name')];
        if ($this->branchModel->select('', $where, 1)) {
            $response = $this->fail('branch already exists.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$branch_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail('Failed to create branch.');
        } elseif ($this->request->getFile('file') AND !$response = $this->_attempt_upload_file_base64($this->branchAttachmentModel, ['branch_id' => $branch_id]) AND
                   $response === false) {
            $this->db->transRollback();
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'    => 'success',
                'branch_id' => $branch_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update branch
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('branches', 'update')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('branch_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$branch = $this->branchModel->select('', $where, 1)) {
            $response = $this->failNotFound('branch not found');
        } elseif (!$this->_attempt_update($branch)) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to update branch.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Branch updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete branches
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('branches', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('branch_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$branch = $this->branchModel->select('', $where, 1)) {
            $response = $this->failNotFound('branch not found');
        } elseif (!$this->_attempt_delete($branch['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete branch.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Branch deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search branches based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('branches', 'search')) !== true)
            return $response;

        $branch_id             = $this->request->getVar('branch_id');
        $name                  = $this->request->getVar('name');
        $address               = $this->request->getVar('address');
        $phone_no              = $this->request->getVar('phone_no');
        $contact_person        = $this->request->getVar('contact_person');
        $contact_person_no     = $this->request->getVar('contact_person_no');
        $franchisee_name       = $this->request->getVar('franchisee_name');
        $franchisee_contact_no = $this->request->getVar('franchisee_contact_no');
        $tin_no                = $this->request->getVar('tin_no');
        $bir_no                = $this->request->getVar('bir_no');
        $contract_start        = $this->request->getVar('contract_start');
        $contract_end          = $this->request->getVar('contract_end');
        $opening_date          = $this->request->getVar('opening_date');
        $is_open               = $this->request->getVar('is_open');
        $is_franchise          = $this->request->getVar('is_franchise');
        $no_branch_group       = $this->request->getVar('no_branch_group');
        $no_inventory_group    = $this->request->getVar('no_inventory_group');

        if (!$branches = $this->branchModel->search($branch_id, $name, $address, $phone_no, $contact_person, $contact_person_no, $franchisee_name, $franchisee_contact_no, $tin_no, $bir_no, $contract_start, $contract_end, $opening_date, $is_open, $is_franchise, $no_branch_group, $no_inventory_group)) {
            $response = $this->failNotFound('No branch found');
        } else {
            $response = [];
            $response['data'] = $branches;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create Branches
     */

    private function _attempt_create()
    {
        $values = [
            'name'                  => $this->request->getVar('name'),
            'type'                  => $this->request->getVar('type'),
            'initial_drawer'        => $this->request->getVar('initial_drawer'),
            'address'               => $this->request->getVar('address'),
            'phone_no'              => $this->request->getVar('phone_no'),
            'contact_person'        => $this->request->getVar('contact_person'),
            'contact_person_no'     => $this->request->getVar('contact_person_no'),
            'franchisee_name'       => $this->request->getVar('franchisee_name'),
            'franchisee_contact_no' => $this->request->getVar('franchisee_contact_no'),
            'tin_no'                => $this->request->getVar('tin_no'),
            'bir_no'                => $this->request->getVar('bir_no'),
            'contract_start'        => $this->request->getVar('contract_start'),
            'contract_end'          => $this->request->getVar('contract_end'),
            'opening_date'          => $this->request->getVar('opening_date'),
            'is_franchise'          => $this->request->getVar('is_franchise'),
            'operation_days'        => $this->request->getVar('operation_days'),
            'operation_times'       => $this->request->getVar('operation_times'),
            'delivery_days'         => $this->request->getVar('delivery_days'),
            'delivery_times'        => $this->request->getVar('delivery_times'),
            'price_level'           => $this->request->getVar('price_level'),
            'rental_monthly_fee'    => $this->request->getVar('rental_monthly_fee'),
            'inventory_group_id'    => $this->request->getVar('inventory_group_id'),
            'branch_group_id'       => $this->request->getVar('branch_group_id'),
            'added_by'              => $this->requested_by,
            'added_on'              => date('Y-m-d H:i:s'),
        ];

        if (!$branch_id = $this->branchModel->insert($values)) {
           return false;
        }

        if ($this->request->getVar('inventory_group_id')) {
            $values = [
                'inventory_group_id' => $this->request->getVar('inventory_group_id'),
                'branch_id' => $branch_id,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s'),
            ];

            if (!$this->inventoryGroupDetailModel->insert($values)) {
                return false;
            }
        }

        if ($this->request->getVar('branch_group_id')) {
            $values = [
                'branch_group_id' => $this->request->getVar('branch_group_id'),
                'branch_id' => $branch_id,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s'),
            ];

            if (!$this->branchGroupDetailModel->insert($values)) {
                return false;
            }
        }

        return $branch_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($branch)
    {
        $values = [
            'name'                  => $this->request->getVar('name'),
            'type'                  => $this->request->getVar('type'),
            'initial_drawer'        => $this->request->getVar('initial_drawer'),
            'address'               => $this->request->getVar('address'),
            'phone_no'              => $this->request->getVar('phone_no'),
            'contact_person'        => $this->request->getVar('contact_person'),
            'contact_person_no'     => $this->request->getVar('contact_person_no'),
            'franchisee_name'       => $this->request->getVar('franchisee_name'),
            'franchisee_contact_no' => $this->request->getVar('franchisee_contact_no'),
            'tin_no'                => $this->request->getVar('tin_no'),
            'bir_no'                => $this->request->getVar('bir_no'),
            'contract_start'        => $this->request->getVar('contract_start'),
            'contract_end'          => $this->request->getVar('contract_end'),
            'opening_date'          => $this->request->getVar('opening_date'),
            'is_franchise'          => $this->request->getVar('is_franchise'),
            'operation_days'        => $this->request->getVar('operation_days'),
            'operation_times'       => $this->request->getVar('operation_times'),
            'delivery_days'         => $this->request->getVar('delivery_days'),
            'delivery_times'        => $this->request->getVar('delivery_times'),
            'price_level'           => $this->request->getVar('price_level'),
            'rental_monthly_fee'    => $this->request->getVar('rental_monthly_fee'),
            'inventory_group_id'    => $this->request->getVar('inventory_group_id'),
            'branch_group_id'       => $this->request->getVar('branch_group_id'),
            'updated_by'            => $this->requested_by,
            'updated_on'            => date('Y-m-d H:i:s')
        ];

        if (!$this->branchModel->update($branch['id'], $values)) {
            return false;
        }

        if (!$this->branchAttachmentModel->delete_attachments_by_branch_id($branch['id'], $this->requested_by)) {
            return false;
        } elseif ($this->request->getFile('file') AND
                  $this->branchAttachmentModel->delete_attachments_by_branch_id($branch['id'], $this->requested_by)
        ) {
            $this->_attempt_upload_file_base64($this->branchAttachmentModel, ['expense_id' => $expense_id]);
        }
        
        /*
        * automatically add to inventory group or branch group if not yet added
        * if already added, update the branch group or inventory group
        */

        if ($branch['branch_group_id'] && $branch_group_detail = $this->branchGroupDetailModel->get_details_by_branch_id_and_branch_group_id($branch['id'], $branch['branch_group_id'])) {
            $values = [
                'branch_group_id' => $this->request->getVar('branch_group_id'),
                'branch_id'       => $branch['id'],
                'updated_by'      => $this->requested_by,
                'updated_on'      => date('Y-m-d H:i:s'),
            ];

            if (!$this->branchGroupDetailModel->update($branch_group_detail['id'], $values)) {
                return false;
            }
        } elseif ($this->request->getVar('branch_group_id')) {
            $values = [
                'branch_group_id' => $this->request->getVar('branch_group_id'),
                'branch_id' => $branch['id'],
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s'),
            ];

            if (!$this->branchGroupDetailModel->insert($values)) {
                return false;
            }
        }

        if ($branch['inventory_group_id'] && $inventory_group_detail = $this->inventoryGroupDetailModel->get_details_by_branch_id_and_branch_group_id($branch['id'], $branch['inventory_group_id'])) {
            $values = [
                'inventory_group_id' => $this->request->getVar('inventory_group_id'),
                'branch_id'          => $branch['id'],
                'updated_by'         => $this->requested_by,
                'updated_on'         => date('Y-m-d H:i:s'),
            ];

            if (!$this->inventoryGroupDetailModel->update($inventory_group_detail['id'], $values)) {
                return false;
            }
        } elseif ($this->request->getVar('inventory_group_id')) {
            $values = [
                'inventory_group_id' => $this->request->getVar('inventory_group_id'),
                'branch_id'          => $branch['id'],
                'added_by'           => $this->requested_by,
                'added_on'           => date('Y-m-d H:i:s'),
            ];

            if (!$this->inventoryGroupDetailModel->insert($values)) {
                return false;
            }
        }

        if ($this->request->getVar('opening_date') != $branch['opening_date']) {
            $values = [
                'opening_start' => $this->request->getVar('opening_date'),
                'updated_by'    => $this->requested_by,
                'updated_on'    => date('Y-m-d H:i:s'),
            ];

            if (!$this->franchiseeModel->update_schedule_by_branch_id($branch['id'], $values, $this->db)) {
                var_dump($this->db->error()['message']);
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($branch_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->branchModel->update($branch_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->branchModel               = model('App\Models\Branch');
        $this->branchAttachmentModel     = model('App\Models\Branch_attachment');
        $this->inventoryGroupDetailModel = model('App\Models\Inventory_group_detail');
        $this->branchGroupDetailModel    = model('App\Models\Branch_group_detail');
        $this->franchiseeModel           = model('App\Models\Franchisee');
        $this->webappResponseModel       = model('App\Models\Webapp_response');
    }
}

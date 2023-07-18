<?php

namespace App\Controllers;

class Projects extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get project
     */
    public function get_project()
    {
        if (($response = $this->_api_verification('projects', 'get_project')) !== true)
            return $response;

        $project_id        = $this->request->getVar('project_id') ? : null;
        $project            = $project_id ? $this->projectModel->get_details_by_id($project_id) : null;
        $project_attachment = $project_id ? $this->projectAttachmentModel->get_details_by_project_id($project_id) : null;
        $project_invoice    = $project_id ? $this->projectInvoiceModel->get_details_by_project_id($project_id) : null;

        if (!$project) {
            $response = $this->failNotFound('No project found');
        } else {
            $project[0]['attachment'] = $project_attachment;
            $project[0]['invoice'] = $project_invoice;

            $response = $this->respond([
                'status' => 'success',
                'data'   => $project
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all projects
     */
    public function get_all_project()
    {
        if (($response = $this->_api_verification('projects', 'get_all_project')) !== true)
            return $response;

        $projects = $this->projectModel->get_all_project();

        if (!$projects) {
            $response = $this->failNotFound('No project found');
        } else {
            foreach ($projects as $key => $project) {
                $project_attachment = $this->projectAttachmentModel->get_details_by_project_id($project['id']);
                $projects[$key]['attachment'] = $project_attachment;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $projects
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create project
     */
    public function create()
    {
        if (($response = $this->_api_verification('projects', 'create')) !== true)
            return $response;

        $where = ['name' => $this->request->getVar('name')];
        if ($this->projectModel->select('', $where, 1)) {
            $response = $this->fail('project already exists.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$project_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail('Failed to create project.');
        } elseif ($this->request->getFile('file') AND !$response = $this->_attempt_upload_file_base64($this->projectAttachmentModel, ['project_id' => $project_id]) AND
                   $response === false) {
            $this->db->transRollback();
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'status'    => 'success',
                'project_id' => $project_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update project
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('projects', 'update')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('project_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$project = $this->projectModel->select('', $where, 1)) {
            $response = $this->failNotFound('project not found');
        } elseif (!$this->_attempt_update($project)) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to update project.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Project updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete projects
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('projects', 'delete')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('project_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$project = $this->projectModel->select('', $where, 1)) {
            $response = $this->failNotFound('project not found');
        } elseif (!$this->_attempt_delete($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete project.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Project deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search projects based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('projects', 'search')) !== true)
            return $response;

        $project_id = $this->request->getVar('project_id');
        $name = $this->request->getVar('name');
        $project_date = $this->request->getVar('project_date');
        $start_date = $this->request->getVar('start_date');
        $customer_id = $this->request->getVar('customer_id');
        $address = $this->request->getVar('address');
        $company = $this->request->getVar('company');
        $contact_person = $this->request->getVar('contact_person');
        $contact_number = $this->request->getVar('contact_number');
        $project_type = $this->request->getVar('project_type');

        // var_dump($project_id, $name, $address, $phone_no, $contact_person, $contact_person_no, $franchisee_name, $franchisee_contact_no, $tin_no, $bir_no, $contract_start, $contract_end, $opening_date, $is_open, $is_franchise, $no_project_group, $no_inventory_group);
        // var_dump($this->projectModel->search($project_id, $name, $address, $phone_no, $contact_person, $contact_person_no, $franchisee_name, $franchisee_contact_no, $tin_no, $bir_no, $contract_start, $contract_end, $opening_date, $is_open, $is_franchise, $no_project_group, $no_inventory_group));
        // die();

        if (!$projects = $this->projectModel->search($project_id, $name, $project_date, $start_date, $customer_id, $address, $company, $contact_person, $contact_number, $project_type)) {
            $response = $this->failNotFound('No project found');
        } else {
            $response = [];
            $response['data'] = $projects;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create projects
     */

    private function _attempt_create()
    {
        $values = [
            'name' => $this->request->getVar('name'),
            'project_date' => $this->request->getVar('project_date'),
            'start_date' => $this->request->getVar('start_date'),
            'customer_id' => $this->request->getVar('customer_id'),
            'address' => $this->request->getVar('address'),
            'company' => $this->request->getVar('company'),
            'contact_person' => $this->request->getVar('contact_person'),
            'contact_number' => $this->request->getVar('contact_number'),
            'project_type' => $this->request->getVar('project_type'),
            'project_price' => $this->request->getVar('project_price'),
            'taxes' => $this->request->getVar('taxes'),
            'other_fees' => $this->request->getVar('other_fees'),
            'grand_total' => $this->request->getVar('grand_total'),
            'balance' => $this->request->getVar('grand_total'),
            'added_by'              => $this->requested_by,
            'added_on'              => date('Y-m-d H:i:s'),
        ];

        if (!$project_id = $this->projectModel->insert($values)) {
           return false;
        }

        if ($this->request->getVar('inventory_group_id')) {
            $values = [
                'inventory_group_id' => $this->request->getVar('inventory_group_id'),
                'project_id' => $project_id,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s'),
            ];

            if (!$this->inventoryGroupDetailModel->insert($values)) {
                return false;
            }
        }

        if ($this->request->getVar('project_group_id')) {
            $values = [
                'project_group_id' => $this->request->getVar('project_group_id'),
                'project_id' => $project_id,
                'added_by'  => $this->requested_by,
                'added_on'  => date('Y-m-d H:i:s'),
            ];

            if (!$this->projectGroupDetailModel->insert($values)) {
                return false;
            }
        }

        return $project_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($project)
    {
        $values = [
            'name' => $this->request->getVar('name'),
            'project_date' => $this->request->getVar('project_date'),
            'start_date' => $this->request->getVar('start_date'),
            'customer_id' => $this->request->getVar('customer_id'),
            'address' => $this->request->getVar('address'),
            'company' => $this->request->getVar('company'),
            'contact_person' => $this->request->getVar('contact_person'),
            'contact_number' => $this->request->getVar('contact_number'),
            'project_type' => $this->request->getVar('project_type'),
            'project_price' => $this->request->getVar('project_price'),
            'taxes' => $this->request->getVar('taxes'),
            'other_fees' => $this->request->getVar('other_fees'),
            'grand_total' => $this->request->getVar('grand_total'),
            'balance' => $this->request->getVar('grand_total'),
            'updated_by'            => $this->requested_by,
            'updated_on'            => date('Y-m-d H:i:s')
        ];

        // var_dump($this->inventoryGroupDetailModel->get_details_by_project_id_and_project_group_id($project['id'], $project['inventory_group_id']));
        // die();

        if (!$this->projectModel->update($project['id'], $values)) {
            return false;
        }

        if (!$this->projectAttachmentModel->delete_attachments_by_project_id($project['id'], $this->requested_by)) {
            return false;
        } elseif ($this->request->getFile('file') AND
                  $this->projectAttachmentModel->delete_attachments_by_project_id($project['id'], $this->requested_by)
        ) {
            $this->_attempt_upload_file_base64($this->projectAttachmentModel, ['expense_id' => $expense_id]);
        }
        
        /*
        * automatically add to inventory group or project group if not yet added
        * if already added, update the project group or inventory group
        */

        // if ($project['project_group_id'] && $project_group_detail = $this->projectGroupDetailModel->get_details_by_project_id_and_project_group_id($project['id'], $project['project_group_id'])) {
        //     $values = [
        //         'project_group_id' => $this->request->getVar('project_group_id'),
        //         'project_id'       => $project['id'],
        //         'updated_by'      => $this->requested_by,
        //         'updated_on'      => date('Y-m-d H:i:s'),
        //     ];

        //     if (!$this->projectGroupDetailModel->update($project_group_detail['id'], $values)) {
        //         return false;
        //     }
        // } elseif ($this->request->getVar('project_group_id')) {
        //     $values = [
        //         'project_group_id' => $this->request->getVar('project_group_id'),
        //         'project_id' => $project['id'],
        //         'added_by'  => $this->requested_by,
        //         'added_on'  => date('Y-m-d H:i:s'),
        //     ];

        //     if (!$this->projectGroupDetailModel->insert($values)) {
        //         return false;
        //     }
        // }

        // if ($project['inventory_group_id'] && $inventory_group_detail = $this->inventoryGroupDetailModel->get_details_by_project_id_and_project_group_id($project['id'], $project['inventory_group_id'])) {
        //     $values = [
        //         'inventory_group_id' => $this->request->getVar('inventory_group_id'),
        //         'project_id'          => $project['id'],
        //         'updated_by'         => $this->requested_by,
        //         'updated_on'         => date('Y-m-d H:i:s'),
        //     ];

        //     if (!$this->inventoryGroupDetailModel->update($inventory_group_detail['id'], $values)) {
        //         return false;
        //     }
        // } elseif ($this->request->getVar('inventory_group_id')) {
        //     $values = [
        //         'inventory_group_id' => $this->request->getVar('inventory_group_id'),
        //         'project_id'          => $project['id'],
        //         'added_by'           => $this->requested_by,
        //         'added_on'           => date('Y-m-d H:i:s'),
        //     ];

        //     if (!$this->inventoryGroupDetailModel->insert($values)) {
        //         return false;
        //     }
        // }

        // if ($this->request->getVar('opening_date') != $project['opening_date']) {
        //     $values = [
        //         'opening_start' => $this->request->getVar('opening_date'),
        //         'updated_by'    => $this->requested_by,
        //         'updated_on'    => date('Y-m-d H:i:s'),
        //     ];

        //     if (!$this->franchiseeModel->update_schedule_by_project_id($project['id'], $values, $this->db)) {
        //         var_dump($this->db->error()['message']);
        //         return false;
        //     }
        // }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($project_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectModel->update($project_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->projectModel = model('App\Models\Project');
        $this->projectInvoiceModel = model('App\Models\Project_invoice');
        $this->projectAttachmentModel = model('App\Models\Project_attachment');
        $this->inventoryGroupDetailModel = model('App\Models\Inventory_group_detail');
        $this->projectGroupDetailModel = model('App\Models\Project_group_detail');
        $this->franchiseeModel = model('App\Models\Franchisee');
        $this->webappResponseModel = model('App\Models\Webapp_response');
    }
}

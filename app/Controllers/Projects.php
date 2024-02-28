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
     * Get all project type names 
     */
    public function get_project_type_names()
    {
        $project_type_names = $this->projectTypeNameModel->select('',[]);
        $response = $this->respond([
            'status' => 'success',
            'data'   => $project_type_names
        ]);

        return $response;
    }

    /**
     * Get project
     */
    public function get_project()
    {
        if (($response = $this->_api_verification('projects', 'get_project')) !== true)
            return $response;

        $project_id             = $this->request->getVar('project_id') ? : null;
        $project                = $project_id ? $this->projectModel->get_details_by_id($project_id) : null;
        $project_attachment     = $project_id ? $this->projectAttachmentModel->get_details_by_project_id($project_id) : null;
        $project_invoice        = $project_id ? $this->projectInvoiceModel->get_details_by_project_id($project_id) : null;
        $project_one_time_fee   = $project_id ? $this->projectOneTimeFeeModel->get_details_by_project_id($project_id) : null;
        $project_recurring_cost = $project_id ? $this->projectRecurringCostModel->get_details_by_project_id($project_id) : null;
        $project_type           = $project_id ? $this->projectTypeModel->get_details_by_project_id($project_id) : null;

        if($project_recurring_cost) {
            foreach($project_recurring_cost AS $index => $project_recurring_cost_item) {
                $project_recurring_cost[$index]['descriptions'] = $project_recurring_cost[$index]['description'];
                $project_recurring_cost[$index]['types'] = $project_recurring_cost[$index]['type'];
                $project_recurring_cost[$index]['periods'] = $project_recurring_cost[$index]['period'];
                $project_recurring_cost[$index]['prices'] = $project_recurring_cost[$index]['price'];
            }
        }

        if (!$project) {
            $response = $this->failNotFound('No project found');
        } else {
            $project[0]['attachment'] = $project_attachment;
            $project[0]['invoice'] = $project_invoice;
            $project[0]['one_time_fee'] = $project_one_time_fee;
            $project[0]['recurring_cost'] = $project_recurring_cost;
            $project[0]['project_types'] = $project_type;


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

        $where = ['name' => $this->request->getVar('name'), 'is_deleted' => 0];
        if ($this->projectModel->select('', $where, 1)) {
            $response = $this->fail('project already exists.');
            $this->webappResponseModel->record_response($this->webapp_log_id, $response);
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$project_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail('Failed to create project.'. $this->errorMessage);
        } elseif (!$this->_attempt_generate_project_recurring_costs($project_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_project_one_time_fees($project_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_project_types($project_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
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
     * Batch insert project costs
     */
    protected function _attempt_generate_project_costs($project_id)
    {
        $project_cost_descriptions = $this->request->getVar('project_cost_description') ?? [];
        $project_cost_type = $this->request->getVar('project_cost_type') ?? [];
        $project_cost_amount = $this->request->getVar('project_cost_amount') ?? [];

        $values = [];
        foreach($project_cost_descriptions as $i => $project_cost_description) {
            $values[] = [
                'project_id'  => $project_id,
                'description' => $project_cost_description,
                'type'        => $project_cost_type[$i],
                'amount'      => $project_cost_amount[$i],
                'added_by'    => $this->request->getVar('requester'),
                'added_on'    => date('Y-m-d H:i:s')
            ];
        }

        if(!$this->projectCostModel->insertBatch($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        return true;
    }

    /**
     * Delete project costs by project_id
     */
    protected function _attempt_delete_project_costs($project_id)
    {
        $where = [
            'project_id' => $project_id,
            'is_deleted' => 0
        ];

        $values = [
            'is_deleted' => 1,
            'added_by'   => $this->request->getVar('requester'),
            'added_on'   => date('Y-m-d H:i:s')
        ];

        if(!$this->projectCostModel->update($where, $values)){
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        return true;
    }

    /**
     * Delete project one time fees by project_id
     */
    protected function _attempt_delete_project_one_time_fees($project_id)
    {
        $where = [
            'project_id' => $project_id,
            'is_deleted' => 0
        ];

        $values = [
            'is_deleted' => 1,
            'added_by'   => $this->request->getVar('requester'),
            'added_on'   => date('Y-m-d H:i:s')
        ];

        if(!$this->projectOneTimeFeeModel->update($where, $values)){
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        return true;
    }

    /**
     * Delete project types by project_id
     */
    protected function _attempt_delete_project_types($project_id)
    {
        $where = [
            'project_id' => $project_id,
            'is_deleted' => 0
        ];

        $values = [
            'is_deleted' => 1,
            'added_by'   => $this->request->getVar('requester'),
            'added_on'   => date('Y-m-d H:i:s')
        ];

        if(!$this->projectTypeModel->update($where, $values)){
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        return true;
    }

    /**
     * Batch insert project one time fees
     */
    protected function _attempt_generate_project_one_time_fees($project_id)
    {
        $project_one_time_fee_descriptions = $this->request->getVar('project_one_time_fee_description') ?? [];
        $project_one_time_fee_amount = $this->request->getVar('project_one_time_fee_amount') ?? [];
        $values = [];
        foreach($project_one_time_fee_descriptions as $i => $project_one_time_fee_description) {
            $values[] = [
                'project_id'  => $project_id,
                'description' => $project_one_time_fee_description,
                'amount'      => $project_one_time_fee_amount[$i],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];
        }

        if(!$this->projectOneTimeFeeModel->insertBatch($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        return true;
    }

    /**
     * Update project_invoices
     */
    protected function _attempt_generate_project_recurring_costs($project_id)
    {
        $descriptions = $this->request->getVar('descriptions') ?? [];
        $types = $this->request->getVar('types') ?? [];
        $periods = $this->request->getVar('periods') ?? [];
        $prices = $this->request->getVar('prices') ?? [];

        if($descriptions) {
            $values = [
                'project_id'         => $project_id,
                'added_by'           => $this->requested_by,
                'added_on'           => date('Y-m-d H:i:s'),
            ];
    
            $grand_total = 0;
    
            foreach ($descriptions as $key => $description) {
                // checks if it is an item in case an item_name was passed
                $description = $descriptions[$key];
                $type = $types[$key];
                $period = $periods[$key];
                $price = $prices[$key];
    
                $values['description']  = $description;
                $values['type']         = $type;
                $values['period']          = $period;
                $values['price']     = $price;
    
                if (!$this->projectRecurringCostModel->insert($values)) {
                    $this->errorMessage = $this->db->error()['message'];
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Batch Insert Project Types
     */
    protected function _attempt_generate_project_types($project_id)
    {
        $project_type_name_ids = $this->request->getVar('project_type_name_id') ?? [];

        if($project_type_name_ids) {
            $values = [];
            foreach($project_type_name_ids as $project_type_name_id) {
                $values[] = [
                    'project_id' => $project_id,
                    'project_type_name_id' => $project_type_name_id,
                    'added_by' => $this->requested_by,
                    'added_on' => date('Y-m-d H:i:s')
                ];
            }

            if(!$this->projectTypeModel->insertBatch($values)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        return true;
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
        } elseif (!$this->_attempt_delete_recurring_costs($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_project_recurring_costs($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif(!$this->_attempt_delete_project_one_time_fees($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_project_one_time_fees($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif(!$this->_attempt_delete_project_types($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_project_types($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Project updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete_recurring_costs($project_id)
    {
        if (!$this->projectRecurringCostModel->delete_recurring_costs_by_project_id($project_id, $this->requested_by)) {
            var_dump("failed to delete project recurring cost");
            return false;
        }

        return true;
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
        } elseif (!$this->_attempt_delete_recurring_costs($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif(!$this->_attempt_delete_project_one_time_fees($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif(!$this->_attempt_delete_project_types($project['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
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
        $contact_number = $this->request->getVar('contact_number') ?: null;
        $project_type = $this->request->getVar('project_type');

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
            'renewal_date' => $this->request->getVar('renewal_date'),
            'payment_structure' => $this->request->getVar('payment_structure'),
            'customer_id' => $this->request->getVar('customer_id'),
            'distributor_id' => $this->request->getVar('distributor_id'),
            'billing_date' => $this->request->getVar('billing_date'),
            'address' => $this->request->getVar('address'),
            'company' => $this->request->getVar('company'),
            'contact_person' => $this->request->getVar('contact_person'),
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'project_price' => $this->request->getVar('project_price'),
            'vat_type' => $this->request->getVar('vat_type'),
            'vat_twelve' => $this->request->getVar('vat_twelve'),
            'vat_net' => $this->request->getVar('vat_net'),
            'withholding_tax' => $this->request->getVar('withholding_tax'),
            'grand_total' => $this->request->getVar('grand_total'),
            'balance' => $this->request->getVar('grand_total'),
            'recurring_cost_total' => $this->request->getVar('recurring_cost_total'),
            'is_subscription' => $this->request->getVar('is_subscription'),
            'added_by'              => $this->requested_by,
            'added_on'              => date('Y-m-d H:i:s'),
        ];

        if (!$project_id = $this->projectModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
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
                $this->errorMessage = $this->db->error()['message'];
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
                $this->errorMessage = $this->db->error()['message'];
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
            'renewal_date' => $this->request->getVar('renewal_date'),
            'payment_structure' => $this->request->getVar('payment_structure'),
            'distributor_id' => $this->request->getVar('distributor_id'),
            'billing_date' => $this->request->getVar('billing_date'),
            'customer_id' => $this->request->getVar('customer_id'),
            'address' => $this->request->getVar('address'),
            'company' => $this->request->getVar('company'),
            'contact_person' => $this->request->getVar('contact_person'),
            'contact_number' => $this->request->getVar('contact_number') ?: null,
            'project_price' => $this->request->getVar('project_price'),
            'vat_type' => $this->request->getVar('vat_type'),
            'vat_twelve' => $this->request->getVar('vat_twelve'),
            'vat_net' => $this->request->getVar('vat_net'),
            'withholding_tax' => $this->request->getVar('withholding_tax'),
            'grand_total' => $this->request->getVar('grand_total'),
            'balance' => $this->request->getVar('grand_total'),
            'recurring_cost_total' => $this->request->getVar('recurring_cost_total'),
            'is_subscription' => $this->request->getVar('is_subscription'),
            'updated_by'            => $this->requested_by,
            'updated_on'            => date('Y-m-d H:i:s')
        ];

        if (!$this->projectModel->update($project['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
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
        $this->projectModel               = model('App\Models\Project');
        $this->projectInvoiceModel        = model('App\Models\Project_invoice');
        $this->projectOneTimeFeeModel     = model('App\Models\Project_one_time_fee');
        $this->projectCostModel           = model('App\Models\Project_cost');
        $this->projectRecurringCostModel  = model('App\Models\Project_recurring_cost');
        $this->projectTypeModel           = model('App\Models\Project_type');
        $this->projectTypeNameModel       = model('App\Models\Project_type_name');
        $this->projectAttachmentModel     = model('App\Models\Project_attachment');
        $this->inventoryGroupDetailModel  = model('App\Models\Inventory_group_detail');
        $this->projectGroupDetailModel    = model('App\Models\Project_group_detail');
        $this->franchiseeModel            = model('App\Models\Franchisee');
        $this->webappResponseModel        = model('App\Models\Webapp_response');
    }
}

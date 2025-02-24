<?php

namespace App\Controllers;

use App\Models\Project_expense;
use App\Models\Partner;
use App\Models\Webapp_response;

class Project_expenses extends MYTController
{
    
    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get all project type names and sort them alphabetically
     */
    public function get_requester_names()
    {
        // Fetch project type names
        $requester_names = $this->requesterNameModel->select('', []);
        
        // Sort the project type names alphabetically
        usort($requester_names, function($a, $b) {
            return strcmp($a['name'], $b['name']); // Assuming 'name' is the key for the project type name
        });
    
        // Respond with the sorted project type names
        $response = $this->respond([
            'status' => 'success',
            'data'   => $requester_names
        ]);
    
        return $response;
    }

    /**
     * Get project_expense
     */
    public function get_project_expense()
    {
        if (($response = $this->_api_verification('project_expense', 'get_project_expense')) !== true)
            return $response;

        $project_expense_id         = $this->request->getVar('project_expense_id') ? : null;
        $project_expense            = $project_expense_id ? $this->projectExpenseModel->get_details_by_id($project_expense_id) : null;
        $project_expense_attachment = $project_expense_id ? $this->projectExpenseAttachmentModel->get_details_by_project_expense_id($project_expense_id) : null;

        if (!$project_expense) {
            $response = $this->failNotFound('No project_expense found');
        } else {
            $project_expense[0]['attachment'] = $project_expense_attachment;

            $response           = [];
            $response['data']   = $project_expense;
            $response['status'] = 'success';
            $response           = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Send project_expense to client
     */
    public function update_status($id = '')
    {
        if (($response = $this->_api_verification('project_expense', 'update_status')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('project_expense_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        $status = $this->request->getVar('status');

        if (!$project_expense = $this->projectExpenseModel->select('', $where, 1)) {
            $response = $this->failNotFound('Project expense not found.');
        } elseif ($status === $project_expense['status']) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Project expense is already ' . $status, 'status' => 'error']);
        } elseif (!$this->_attempt_update_status($project_expense, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update project expense status.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Project expense status updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_update_status($project_expense, $db)
    {
        $status = $this->request->getVar('status');
        $values = [
            'status' => $status,
            'sent_by' => $this->requested_by,
            'sent_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectExpenseModel->update($project_expense['id'], $values))
            return false;

        return true;
    }

    /**
     * Get all project_expense
     */
    public function get_all_project_expense()
    {
        if (($response = $this->_api_verification('project_expense', 'get_all_project_expense')) !== true)
            return $response;

        $project_expenses = $this->projectExpenseModel->get_all_project_expense();

        if (!$project_expenses) {
            $response = $this->failNotFound('No project_expense found');
        } else {
            foreach ($project_expenses as $key => $project_expense) {
                $project_expense_attachment = $this->projectExpenseAttachmentModel->get_details_by_project_expense_id($project_expense['id']);
                $project_expenses[$key]['attachment'] = $project_expense_attachment;
            }

            $response           = [];
            $response['data']   = $project_expenses;
            $response['status'] = 'success';
            $response           = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create project_expense
     */
    public function create()
    {
        if (($response = $this->_api_verification('project_expense', 'create')) !== true)
            return $response;

            $db = \Config\Database::connect();
            $db->transBegin();

            $where = [
                'name' => $this->request->getVar('partner_name'),
                'is_deleted' => 0
            ];

            $partner = $this->partnerModel->select('', $where, 1);

            if(!$partner) {
                $values = [
                    'name'     => $this->request->getVar('partner_name'),
                    'added_by' => $this->requested_by,
                    'added_on' => date('Y-m-d H:i:s'),
                ];
                if(!$partner_id = $this->partnerModel->insert($values)) {
                    $db->transRollback();
                    $response = $this->fail('Server error');
                }
            } else {
                $partner_id = $partner['id'];
            }

            $values = [
                'project_id' => $this->request->getVar('project_id'),
                'expense_type_id' => $this->request->getVar('expense_type_id'),
                'partner_id' => $partner_id,
                'supplier_id' => $this->request->getVar('supplier_id'),
                'requester_name_id' => $this->request->getVar('requester_name_id'),
                'remarks' => $this->request->getVar('remarks'),
                'amount' => $this->request->getVar('amount'),
                'other_fees' => $this->request->getVar('other_fees'),
                'grand_total' => $this->request->getVar('grand_total'),
                'project_expense_date' => $this->request->getVar('project_expense_date') ?? date('Y-m-d'),
                'added_by' => $this->requested_by,
                'added_on' => date('Y-m-d H:i:s'),
            ];
            
            if (!$project_expense_id = $this->projectExpenseModel->insert($values)) {
                $db->transRollback();
                $response = $this->fail('Server error');
            } elseif (!$this->_attempt_generate_requesters($project_expense_id)) {
                $this->db->transRollback();
                $response = $this->fail($this->errorMessage);
            } elseif (($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$response = $this->_attempt_upload_file_base64($this->projectExpenseAttachmentModel, ['project_expense_id' => $project_expense_id]) AND
                   $response === false) {
                $db->transRollback();
                $response = $this->respond(['response' => 'project_expense file upload failed']);
            } else {
                $db->transCommit();
                $response = $this->respond([
                        'project_expense_id' => $project_expense_id,
                        'response' => 'project_expense created successfully'
                    ]);
            }
            
            $db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update project_expense
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('project_expense', 'update')) !== true)
            return $response;

        $project_expense_id = $this->request->getVar('project_expense_id');
        $where = ['id' => $project_expense_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$project_expense = $this->projectExpenseModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_expense not found');
        } elseif (!$this->_attempt_update($project_expense_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Fail to update project_expense', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'project_expense updated successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete project_expense
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('project_expense', 'delete')) !== true)
            return $response;

        $project_expense_id = $this->request->getVar('project_expense_id');

        $where = ['id' => $project_expense_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$project_expense = $this->projectExpenseModel->select('', $where, 1)) {
            $response = $this->failNotFound('project_expense not found');
        } elseif (!$this->_attempt_delete($project_expense_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Fail to delete project_expense', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'project_expense deleted successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search project_expense based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('project_expense', 'search')) !== true)
            return $response;

            $project_id = $this->request->getVar('project_id');
            $expense_type_id = $this->request->getVar('expense_type_id');
            $partner_id = $this->request->getVar('partner_id');
            $remarks = $this->request->getVar('remarks');
            $amount = $this->request->getVar('amount');
            $other_fees = $this->request->getVar('other_fees');
            $grand_total = $this->request->getVar('grand_total');
            $status = $this->request->getVar('status');
            $project_name = $this->request->getVar('project_name');
            $supplier_id = $this->request->getVar('supplier_id');
            $distributor_id = $this->request->getVar('distributor_id');

        if (!$project_expense = $this->projectExpenseModel->search($project_id, $expense_type_id, $partner_id, $remarks, $amount, $other_fees, $grand_total, $status, $project_name, $supplier_id, $distributor_id)) {
            $response = $this->failNotFound('No project_expense found');
        } else {
            $response = [];
            $response['data'] = $project_expense;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt update
     */
    protected function _attempt_update($project_expense_id)
    {
        $values = [
            'project_id' => $this->request->getVar('project_id'),
            'expense_type_id' => $this->request->getVar('expense_type_id'),
            'partner_id' => $this->request->getVar('partner_id'),
            'supplier_id' => $this->request->getVar('supplier_id'),
            'remarks' => $this->request->getVar('remarks'),
            'amount' => $this->request->getVar('amount'),
            'other_fees' => $this->request->getVar('other_fees'),
            'grand_total' => $this->request->getVar('grand_total'),
            'project_expense_date' => $this->request->getVar('project_expense_date'),
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectExpenseModel->update($project_expense_id, $values))
            return false;

        if (!$this->projectExpenseAttachmentModel->delete_attachments_by_project_expense_id($project_expense_id, $this->requested_by)) {
            return false;
        } elseif ($this->request->getFile('file') AND
                  $this->projectExpenseAttachmentModel->delete_attachments_by_project_expense_id($project_expense_id, $this->requested_by)
        ) {
            return false;
            // $this->_attempt_upload_file_base64($this->projectExpenseAttachmentModel, ['expense_id' => $expense_id]);
        } elseif(($this->request->getFile('file') || $this->request->getFileMultiple('file')) AND !$response = $this->_attempt_upload_file_base64($this->projectExpenseAttachmentModel, ['project_expense_id' => $project_expense_id]) AND
                   $response === false) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($project_expense_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->projectExpenseModel->update($project_expense_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Batch Insert Project Types
     */
    protected function _attempt_generate_requesters($project_expense_id)
    {
        $requester_name_ids = $this->request->getVar('requester_name_id') ?? [];

        if($requester_name_ids) {
            $values = [];
            foreach($requester_name_ids as $requester_name_id) {
                $values[] = [
                    'project_expense_id' => $project_expense_id,
                    'requester_name_id' => $requester_name_id,
                    'added_by' => $this->requested_by,
                    'added_on' => date('Y-m-d H:i:s')
                ];
            }

            if(!$this->requesterModel->insertBatch($values)) {
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
        $this->projectExpenseAttachmentModel = model('App\Models\Project_expense_attachment');
        $this->requesterNameModel = model('App\Models\Requester_name');
        $this->requesterModel = model('App\Models\Requester');
        $this->projectExpenseModel = new Project_expense();
        $this->partnerModel = new Partner();
        $this->webappResponseModel  = new Webapp_response();
    }
}

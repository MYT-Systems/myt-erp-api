<?php

namespace App\Controllers;

class Subscription_billings extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get subscription_billing
     */
    public function get_subscription_billing()
    {

        if (($response = $this->_api_verification('subscription_billings', 'get_subscription_billing')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $subscription_billing_id    = $this->request->getVar('subscription_billing_id') ? : null;
        $subscription_billing       = $subscription_billing_id ? $this->subscriptionBillingModel->get_details_by_id($subscription_billing_id) : null;
        $project = $subscription_billing ? $this->projectModel->get_details_by_id($subscription_billing[0]['project_id']) : null;

        $subscription_billing_entries = $subscription_billing_id ? $this->subscriptionBillingEntryModel->get_details_by_subscription_billing_id($subscription_billing_id) : null;

        if (!$subscription_billing) {
            $response = $this->failNotFound('No subscription_billing found');
        } else {
            $subscription_billing[0]['subscription_billing_entries'] = $subscription_billing_entries;
            $subscription_billing[0]['project'] = $project;

            $response = $this->respond([
                'status' => 'success',
                'data' => $subscription_billing
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get subscription_billing by status
     */
    public function filter_subscription_billing_status()
    {
        if (($response = $this->_api_verification('subscription_billings', 'filter_subscription_billing_status')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status    = $this->request->getVar('status') ? : null;
        $subscription_billing = $status ? $this->subscriptionBillingModel->filter_subscription_billing_status($status) : null;

        if (!$subscription_billing) {
            $response = $this->failNotFound('No subscription_billing found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $subscription_billing
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Filter by order status
     */
    public function filter_order_status()
    {
        if (($response = $this->_api_verification('subscription_billings', 'filter_order_status')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status    = $this->request->getVar('status') ? : null;
        $subscription_billing = $status ? $this->subscriptionBillingModel->filter_order_status($status) : null;

        if (!$subscription_billing) {
            $response = $this->failNotFound('No subscription_billing found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $subscription_billing
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all subscription_billings
     */
    public function get_all_subscription_billing()
    {
        if (($response = $this->_api_verification('subscription_billings', 'get_all_subscription_billing')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status = $this->request->getVar('status') ? : null;
        $project_name = $this->request->getVar('project_name') ? : null;
        $billing_id = $this->request->getVar('billing_id') ? : null;

        $subscription_billings = $this->subscriptionBillingModel->get_all_subscription_billing($status, $project_name, $billing_id);

        if (!$subscription_billings) {
            $response = $this->failNotFound('No subscription_billing found');
        } else {
            foreach ($subscription_billings as $key => $subscription_billing) {
                $subscription_billings[$key]['subscription_billing_entries'] = $this->subscriptionBillingEntryModel->get_details_by_subscription_billing_id($subscription_billing['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $subscription_billings
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create subscription_billing
     */
    public function create()
    {
        if (($response = $this->_api_verification('subscription_billings', 'create')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$subscription_billing_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else if (!$this->_attempt_generate_subscription_billing_entries($subscription_billing_id, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate PO items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'subscription_billing_id' => $subscription_billing_id,
                'response'    => 'subscription_billing created successfully'
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Give project billing
     */
    public function generate_subscription_billing()
    {
        if (($response = $this->_api_verification('subscription_billings', 'create')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $project_id = $this->request->getVar('project_id');
        $billing_date = $this->request->getVar('billing_date');

        $subscription_billing_entries = $this->projectModel->get_recurring_cost_to_bill($project_id, $billing_date)?:[];
        foreach($subscription_billing_entries AS $index => $subscription_billing_entry) {
            $where = [
                'id' => $subscription_billing_entries[$index]['project_id']
            ];
            $project = $this->projectModel->get_details_by_id($subscription_billing_entries[$index]['project_id']);
            $subscription_billing_entries[$index]['project'] = $project;
        }

        $where = [
            'id' => $this->request->getVar('project_id')
        ];
        $project = $this->projectModel->get_details_by_id($this->request->getVar('project_id'));
        // $project = $this->projectModel->select('', $where, 1);

        $response = $this->respond([
            'project' => $project,
            'subscription_billing_entries' => $subscription_billing_entries,
            'response'    => 'project billing generated successfully'
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update subscription_billing
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('subscription_billings', 'update')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
            
        $subscription_billing_id = $this->request->getVar('subscription_billing_id');
        $where       = ['id' => $subscription_billing_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();
        
        if (!$subscription_billing = $this->subscriptionBillingModel->select('', $where, 1)) {
            $response = $this->failNotFound('subscription_billing not found');
        } elseif (!$this->_attempt_update($subscription_billing)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } elseif (!$this->_attempt_update_subscription_billing_entries($subscription_billing, $db)) {
            $db->transRollback();
            $response = $this->respond([
                'status'  => 'error',
                'message' => 'Failed to generate PO items'
            ]);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'subscription_billing_id' => $subscription_billing_id,
                'response'    => 'subscription_billing updated successfully'
            ]);
        }
        
        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete subscription_billings
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('subscription_billings', 'delete')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
        
        $subscription_billing_id = $this->request->getVar('subscription_billing_id');

        $where = ['id' => $subscription_billing_id, 'is_deleted' => 0];

        if (!$subscription_billing = $this->subscriptionBillingModel->select('', $where, 1)) {
            $response = $this->failNotFound('subscription_billing not found');
        } elseif (!$this->_attempt_delete($subscription_billing_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'subscription_billing deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search subscription_billings based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('subscription_billings', 'search')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $project_id          = $this->request->getVar('project_id') ? : null;

        if (!$subscription_billings = $this->subscriptionBillingModel->search($project_id)) {
            $response = $this->failNotFound('No subscription_billing found');
        } else {

            foreach ($subscription_billings as $key => $subscription_billing) {
                $subscription_billings[$key]['subscription_billing_entries'] = $this->subscriptionBillingEntryModel->get_details_by_subscription_billing_id($subscription_billing['id']);
            }

            $response = $this->respond([
                'data' => $subscription_billings
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create a subscription_billing
     */
    private function _attempt_create()
    {
        $values = [
            'project_id'        => $this->request->getVar('project_id'),
            'billing_date'        => $this->request->getVar('billing_date'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'added_by'         => $this->requested_by,
            'added_on'         => date('Y-m-d H:i:s'),
        ];

        if (!$subscription_billing_id = $this->subscriptionBillingModel->insert($values)) {
            return false;
        }

        return $subscription_billing_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($subscription_billing)
    {
        $values = [
            'project_id'        => $this->request->getVar('project_id'),
            'billing_date'        => $this->request->getVar('billing_date'),
            'grand_total'        => $this->request->getVar('grand_total'),
            'updated_by'       => $this->requested_by,
            'updated_on'       => date('Y-m-d H:i:s')
        ];

        if (!$this->subscriptionBillingModel->update($subscription_billing['id'], $values))
            return false;
    
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($subscription_billing_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $subscription_billing_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->subscriptionBillingModel->update($where, $values)) {
            $db->transRollback();
            $db->close();
            return false;
        } 

        $db->transCommit();
        $db->close();

        return true;
    }

    /**
     * Attempt generate PO
     */
    protected function _attempt_generate_subscription_billing_entries($subscription_billing_id, $db)
    {
        $project_recurring_cost_ids   = $this->request->getVar('project_recurring_cost_ids');
        $project_ids = $this->request->getVar('project_ids');
        $amounts = $this->request->getVar('amounts');
        $subscription_billing_entry_dates      = $this->request->getVar('subscription_billing_entry_dates');
        $due_dates      = $this->request->getVar('due_dates');

        $grand_total = 0;

        foreach ($project_recurring_cost_ids as $key => $project_recurring_cost_id) {
            $data = [
                'subscription_billing_id' => $subscription_billing_id,
                'project_recurring_cost_id' => $project_recurring_cost_id,
                'project_id'     => $project_ids[$key],
                'subscription_billing_entry_date' => $subscription_billing_entry_dates[$key],
                'due_date'     => $due_dates[$key],
                'amount'     => $amounts[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];


            if (!$this->subscriptionBillingEntryModel->insert($data)) {
                return false;
            }
        }

        return true;
    }

    /*
    * Attempt update PO items
    */
    protected function _attempt_update_subscription_billing_entries($subscription_billing, $db)
    {
        $subscription_billing_id = $subscription_billing['id'];
        // // delete all items first
        if (!$this->subscriptionBillingEntryModel->delete_by_subscription_billing_id($subscription_billing['id'], $this->requested_by, $db)) {
            return false;
        }

        $project_recurring_cost_ids   = $this->request->getVar('project_recurring_cost_ids');
        $project_ids = $this->request->getVar('project_ids');
        $amounts = $this->request->getVar('amounts');
        $subscription_billing_entry_dates      = $this->request->getVar('subscription_billing_entry_dates');
        $due_dates      = $this->request->getVar('due_dates');

        $grand_total = 0;

        foreach ($project_recurring_cost_ids as $key => $project_recurring_cost_id) {
            $data = [
                'subscription_billing_id' => $subscription_billing_id,
                'project_recurring_cost_id' => $project_recurring_cost_id,
                'project_id'     => $project_ids[$key],
                'subscription_billing_entry_date' => $subscription_billing_entry_dates[$key],
                'due_date'     => $due_dates[$key],
                'amount'     => $amounts[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];


            if (!$this->subscriptionBillingEntryModel->insert($data)) {
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

        $this->projectModel               = model('App\Models\Project');
        $this->subscriptionBillingModel               = model('App\Models\Subscription_billing');
        $this->subscriptionBillingEntryModel           = model('App\Models\Subscription_billing_entry');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
        
    }
}

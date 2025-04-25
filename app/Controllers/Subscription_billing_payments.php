<?php

namespace App\Controllers;

class Subscription_billing_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get subscription_billing_payment
     */
    public function get_subscription_billing_payment()
    {

        if (($response = $this->_api_verification('subscription_billing_payments', 'get_subscription_billing_payment')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $subscription_billing_payment_id    = $this->request->getVar('subscription_billing_payment_id') ? : null;
        $subscription_billing_payment       = $subscription_billing_payment_id ? $this->subscriptionBillingPaymentModel->get_details_by_id($subscription_billing_payment_id) : null;
        $subscription_billing_payment_entries = $subscription_billing_payment_id ? $this->subscriptionBillingPaymentEntryModel->get_details_by_subscription_billing_payment_id($subscription_billing_payment_id) : null;
        if (!$subscription_billing_payment) {
            $response = $this->failNotFound('No subscription_billing_payment found');
        } else {
            $subscription_billing_payment[0]['subscription_billing_payment_entries'] = $subscription_billing_payment_entries;

            $response = $this->respond([
                'status' => 'success',
                'data' => $subscription_billing_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get subscription_billing_payment by status
     */
    public function filter_subscription_billing_payment_status()
    {
        if (($response = $this->_api_verification('subscription_billing_payments', 'filter_subscription_billing_payment_status')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status    = $this->request->getVar('status') ? : null;
        $subscription_billing_payment = $status ? $this->subscriptionBillingPaymentModel->filter_subscription_billing_payment_status($status) : null;

        if (!$subscription_billing_payment) {
            $response = $this->failNotFound('No subscription_billing_payment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $subscription_billing_payment
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
        if (($response = $this->_api_verification('subscription_billing_payments', 'filter_order_status')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status    = $this->request->getVar('status') ? : null;
        $subscription_billing_payment = $status ? $this->subscriptionBillingPaymentModel->filter_order_status($status) : null;

        if (!$subscription_billing_payment) {
            $response = $this->failNotFound('No subscription_billing_payment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $subscription_billing_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all subscription_billing_payments
     */
    public function get_all_subscription_billing_payment()
    {
        if (($response = $this->_api_verification('subscription_billing_payments', 'get_all_subscription_billing_payment')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $subscription_billing_payments = $this->subscriptionBillingPaymentModel->get_all_subscription_billing_payment();

        if (!$subscription_billing_payments) {
            $response = $this->failNotFound('No subscription_billing_payment found');
        } else {
            foreach ($subscription_billing_payments as $key => $subscription_billing_payment) {
                $subscription_billing_payments[$key]['subscription_billing_payment_entries'] = $this->subscriptionBillingPaymentEntryModel->get_details_by_subscription_billing_payment_id($subscription_billing_payment['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $subscription_billing_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create subscription_billing_payment
     */
    public function create()
    {
        if (($response = $this->_api_verification('subscription_billing_payments', 'create')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$subscription_billing_payment_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else if (!$this->_attempt_generate_subscription_billing_payment_entries($subscription_billing_payment_id, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate PO items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'subscription_billing_payment_id' => $subscription_billing_payment_id,
                'response'    => 'subscription_billing_payment created successfully'
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Give subscription billing
     */
    public function generate_subscription_billing_payment()
    {
        if (($response = $this->_api_verification('subscription_billing_payments', 'create')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $subscription_id = $this->request->getVar('subscription_id');
        $billing_date = $this->request->getVar('billing_date');

        $where = [
            'id' => $subscription_id
        ];
        $subscription = $this->subscriptionModel->select('', $where, 1);
        $subscription_billing_payment_entries = $this->subscriptionModel->get_clients_to_bill($subscription_id, $billing_date);

        $response = $this->respond([
            'subscription' => $subscription,
            'subscription_billing_payment_entries' => $subscription_billing_payment_entries,
            'response'    => 'subscription billing generated successfully'
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update subscription_billing_payment
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('subscription_billing_payments', 'update')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
            
        $subscription_billing_payment_id = $this->request->getVar('subscription_billing_payment_id');
        $where       = ['id' => $subscription_billing_payment_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();
        
        if (!$subscription_billing_payment = $this->subscriptionBillingPaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('subscription_billing_payment not found');
        } elseif (!$this->_attempt_update($subscription_billing_payment)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } elseif (!$this->_attempt_update_subscription_billing_payment_entries($subscription_billing_payment, $db)) {
            $db->transRollback();
            $response = $this->respond([
                'status'  => 'error',
                'message' => 'Failed to generate PO items'
            ]);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'subscription_billing_payment_id' => $subscription_billing_payment_id,
                'response'    => 'subscription_billing_payment updated successfully'
            ]);
        }
        
        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete subscription_billing_payments
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('subscription_billing_payments', 'delete')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
        
        $subscription_billing_payment_id = $this->request->getVar('subscription_billing_payment_id');

        $where = ['id' => $subscription_billing_payment_id, 'is_deleted' => 0];

        if (!$subscription_billing_payment = $this->subscriptionBillingPaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('subscription_billing_payment not found');
        } elseif (!$this->_attempt_delete($subscription_billing_payment_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'subscription_billing_payment deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search subscription_billing_payments based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('subscription_billing_payments', 'search')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $name          = $this->request->getVar('name') ? : null;

        if (!$subscription_billing_payments = $this->subscriptionBillingPaymentModel->search($name, $limit_by, $anything)) {
            $response = $this->failNotFound('No subscription_billing_payment found');
        } else {

            $response = $this->respond([
                'data' => $subscription_billing_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create a subscription_billing_payment
     */
    private function _attempt_create()
    {
        $values = [
            'billing_id'        => $this->request->getVar('billing_id'),
            'payment_type'      => $this->request->getVar('payment_type'),
            'reference_no'      => $this->request->getVar('reference_no'),
            'remarks'           => $this->request->getVar('remarks'),
            'payment_date'      => $this->request->getVar('payment_date'),
            'grand_total'       => $this->request->getVar('grand_total'),
            'added_by'          => $this->requested_by,
            'added_on'          => date('Y-m-d H:i:s'),
        ];

        if (!$subscription_billing_payment_id = $this->subscriptionBillingPaymentModel->insert($values)) {
            return false;
        }

        return $subscription_billing_payment_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($subscription_billing_payment)
    {
        $values = [
            'billing_id'        => $this->request->getVar('billing_id'),
            'payment_type'      => $this->request->getVar('payment_type'),
            'reference_no'      => $this->request->getVar('reference_no'),
            'remarks'           => $this->request->getVar('remarks'),
            'payment_date'      => $this->request->getVar('payment_date'),
            'grand_total'       => $this->request->getVar('grand_total'),
            'updated_by'       => $this->requested_by,
            'updated_on'       => date('Y-m-d H:i:s')
        ];

        if (!$this->subscriptionBillingPaymentModel->update($subscription_billing_payment['id'], $values))
            return false;
    
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($subscription_billing_payment_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $subscription_billing_payment_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->subscriptionBillingPaymentModel->update($where, $values)) {
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
    protected function _attempt_generate_subscription_billing_payment_entries($subscription_billing_payment_id, $db)
    {
        $subscription_billing_entry_ids   = $this->request->getVar('subscription_billing_entry_ids');
        $paid_amounts = $this->request->getVar('paid_amounts');

        foreach ($subscription_billing_entry_ids as $key => $subscription_billing_entry_id) {
            $data = [
                'subscription_billing_payment_id' => $subscription_billing_payment_id,
                'subscription_billing_entry_id' => $subscription_billing_entry_id,
                'paid_amount'     => $paid_amounts[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];


            if (!$this->subscriptionBillingPaymentEntryModel->insert($data)) {
                return false;
            }
        }

        return true;
    }

    /*
    * Attempt update PO items
    */
    protected function _attempt_update_subscription_billing_payment_entries($subscription_billing_payment, $db)
    {
        // // delete all items first
        if (!$this->subscriptionBillingPaymentEntryModel->delete_by_subscription_billing_payment_id($subscription_billing_payment['id'], $this->requested_by, $db)) {
            return false;
        }

        $subscription_billing_entry_ids   = $this->request->getVar('subscription_billing_entry_ids');
        $paid_amounts = $this->request->getVar('paid_amounts');

        foreach ($subscription_billing_entry_ids as $key => $subscription_billing_entry_id) {
            $data = [
                'subscription_billing_payment_id' => $subscription_billing_payment['id'],
                'subscription_billing_entry_id' => $subscription_billing_entry_id,
                'paid_amount'     => $paid_amounts[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];


            if (!$this->subscriptionBillingPaymentEntryModel->insert($data)) {
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

        $this->subscriptionModel               = model('App\Models\Subscription');
        $this->subscriptionBillingPaymentModel               = model('App\Models\Subscription_billing_payment');
        $this->subscriptionBillingPaymentEntryModel           = model('App\Models\Subscription_billing_payment_entry');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
        
    }
}

<?php

namespace App\Controllers;

class Distributor_billing_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key  = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get distributor_billing_payment
     */
    public function get_distributor_billing_payment()
    {

        if (($response = $this->_api_verification('distributor_billing_payments', 'get_distributor_billing_payment')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $distributor_billing_payment_id    = $this->request->getVar('distributor_billing_payment_id') ? : null;
        $distributor_billing_payment       = $distributor_billing_payment_id ? $this->distributorBillingPaymentModel->get_details_by_id($distributor_billing_payment_id) : null;
        $distributor_billing_payment_entries = $distributor_billing_payment_id ? $this->distributorBillingPaymentEntryModel->get_details_by_distributor_billing_payment_id($distributor_billing_payment_id) : null;
        if (!$distributor_billing_payment) {
            $response = $this->failNotFound('No distributor_billing_payment found');
        } else {
            $distributor_billing_payment[0]['distributor_billing_payment_entries'] = $distributor_billing_payment_entries;

            $response = $this->respond([
                'status' => 'success',
                'data' => $distributor_billing_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get distributor_billing_payment by status
     */
    public function filter_distributor_billing_payment_status()
    {
        if (($response = $this->_api_verification('distributor_billing_payments', 'filter_distributor_billing_payment_status')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status    = $this->request->getVar('status') ? : null;
        $distributor_billing_payment = $status ? $this->distributorBillingPaymentModel->filter_distributor_billing_payment_status($status) : null;

        if (!$distributor_billing_payment) {
            $response = $this->failNotFound('No distributor_billing_payment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data' => $distributor_billing_payment
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
        if (($response = $this->_api_verification('distributor_billing_payments', 'filter_order_status')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $status    = $this->request->getVar('status') ? : null;
        $distributor_billing_payment = $status ? $this->distributorBillingPaymentModel->filter_order_status($status) : null;

        if (!$distributor_billing_payment) {
            $response = $this->failNotFound('No distributor_billing_payment found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $distributor_billing_payment
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all distributor_billing_payments
     */
    public function get_all_distributor_billing_payment()
    {
        if (($response = $this->_api_verification('distributor_billing_payments', 'get_all_distributor_billing_payment')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $distributor_billing_payments = $this->distributorBillingPaymentModel->get_all_distributor_billing_payment();

        if (!$distributor_billing_payments) {
            $response = $this->failNotFound('No distributor_billing_payment found');
        } else {
            foreach ($distributor_billing_payments as $key => $distributor_billing_payment) {
                $distributor_billing_payments[$key]['distributor_billing_payment_entries'] = $this->distributorBillingPaymentEntryModel->get_details_by_distributor_billing_payment_id($distributor_billing_payment['id']);
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $distributor_billing_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create distributor_billing_payment
     */
    public function create()
    {
        if (($response = $this->_api_verification('distributor_billing_payments', 'create')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$distributor_billing_payment_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else if (!$this->_attempt_generate_distributor_billing_payment_entries($distributor_billing_payment_id, $db)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate PO items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'distributor_billing_payment_id' => $distributor_billing_payment_id,
                'response'    => 'distributor_billing_payment created successfully'
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Give distributor billing
     */
    public function generate_distributor_billing_payment()
    {
        if (($response = $this->_api_verification('distributor_billing_payments', 'create')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $distributor_id = $this->request->getVar('distributor_id');
        $billing_date = $this->request->getVar('billing_date');

        $where = [
            'id' => $distributor_id
        ];
        $distributor = $this->distributorModel->select('', $where, 1);
        $distributor_billing_payment_entries = $this->distributorModel->get_clients_to_bill($distributor_id, $billing_date);

        $response = $this->respond([
            'distributor' => $distributor,
            'distributor_billing_payment_entries' => $distributor_billing_payment_entries,
            'response'    => 'distributor billing generated successfully'
        ]);

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update distributor_billing_payment
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('distributor_billing_payments', 'update')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
            
        $distributor_billing_payment_id = $this->request->getVar('distributor_billing_payment_id');
        $where       = ['id' => $distributor_billing_payment_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();
        
        if (!$distributor_billing_payment = $this->distributorBillingPaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('distributor_billing_payment not found');
        } elseif (!$this->_attempt_update($distributor_billing_payment)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } elseif (!$this->_attempt_update_distributor_billing_payment_entries($distributor_billing_payment, $db)) {
            $db->transRollback();
            $response = $this->respond([
                'status'  => 'error',
                'message' => 'Failed to generate PO items'
            ]);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'distributor_billing_payment_id' => $distributor_billing_payment_id,
                'response'    => 'distributor_billing_payment updated successfully'
            ]);
        }
        
        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete distributor_billing_payments
     */
    public function delete($id = null)
    {
        if (($response = $this->_api_verification('distributor_billing_payments', 'delete')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }
        
        $distributor_billing_payment_id = $this->request->getVar('distributor_billing_payment_id');

        $where = ['id' => $distributor_billing_payment_id, 'is_deleted' => 0];

        if (!$distributor_billing_payment = $this->distributorBillingPaymentModel->select('', $where, 1)) {
            $response = $this->failNotFound('distributor_billing_payment not found');
        } elseif (!$this->_attempt_delete($distributor_billing_payment_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'distributor_billing_payment deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search distributor_billing_payments based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('distributor_billing_payments', 'search')) !== true) 
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $name          = $this->request->getVar('name') ? : null;

        if (!$distributor_billing_payments = $this->distributorBillingPaymentModel->search($name, $limit_by, $anything)) {
            $response = $this->failNotFound('No distributor_billing_payment found');
        } else {

            $response = $this->respond([
                'data' => $distributor_billing_payments
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt to create a distributor_billing_payment
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

        if (!$distributor_billing_payment_id = $this->distributorBillingPaymentModel->insert($values)) {
            return false;
        }

        return $distributor_billing_payment_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($distributor_billing_payment)
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

        if (!$this->distributorBillingPaymentModel->update($distributor_billing_payment['id'], $values))
            return false;
    
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($distributor_billing_payment_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $distributor_billing_payment_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->distributorBillingPaymentModel->update($where, $values)) {
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
    protected function _attempt_generate_distributor_billing_payment_entries($distributor_billing_payment_id, $db)
    {
        $distributor_billing_entry_ids   = $this->request->getVar('distributor_billing_entry_ids');
        $paid_amounts = $this->request->getVar('paid_amounts');

        foreach ($distributor_billing_entry_ids as $key => $distributor_billing_entry_id) {
            $data = [
                'distributor_billing_payment_id' => $distributor_billing_payment_id,
                'distributor_billing_entry_id' => $distributor_billing_entry_id,
                'paid_amount'     => $paid_amounts[$key],
                'added_by'    => $this->requested_by,
                'added_on'    => date('Y-m-d H:i:s')
            ];


            if (!$this->distributorBillingPaymentEntryModel->insert($data)) {
                return false;
            }
        }

        return true;
    }

    /*
    * Attempt update PO items
    */
    protected function _attempt_update_distributor_billing_payment_entries($distributor_billing_payment, $db)
    {
        // // delete all items first
        if (!$this->distributorBillingPaymentEntryModel->delete_by_distributor_billing_payment_id($distributor_billing_payment['id'], $this->requested_by, $db)) {
            return false;
        }

        $distributor_billing_entry_ids   = $this->request->getVar('distributor_billing_entry_ids');
        $paid_amounts = $this->request->getVar('paid_amounts');

        foreach ($distributor_billing_entry_ids as $key => $distributor_billing_entry_id) {
            $data = [
                'distributor_billing_payment_id'    => $distributor_billing_payment_id,
                'distributor_billing_entry_id'      => $distributor_billing_entry_id,
                'paid_amount'                       => $paid_amounts[$key],
                'added_by'                          => $this->requested_by,
                'added_on'                          => date('Y-m-d H:i:s')
            ];


            if (!$this->distributorBillingPaymentEntryModel->insert($data)) {
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

        $this->distributorModel               = model('App\Models\Distributor');
        $this->distributorBillingPaymentModel               = model('App\Models\Distributor_billing_payment');
        $this->distributorBillingPaymentEntryModel           = model('App\Models\Distributor_billing_payment_entry');
        $this->webappResponseModel         = model('App\Models\Webapp_response');
        
    }
}

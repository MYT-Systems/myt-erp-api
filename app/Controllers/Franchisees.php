<?php

namespace App\Controllers;

use App\Models\Franchisee;
use App\Models\Franchisee_payment;
use App\Models\Webapp_response;

class Franchisees extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get franchisee
     */
    public function get_franchisee()
    {
        if (($response = $this->_api_verification('franchisees', 'get_franchisee')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchisee_id       = $this->request->getVar('franchisee_id') ? : null;
        $franchisee          = $franchisee_id ? $this->franchiseeModel->get_details_by_id($franchisee_id) : null;
        $franchisee_payments = $franchisee_id ? $this->franchiseePaymentModel->get_details_by_franchisee_id($franchisee_id) : null;

        if (!$franchisee) {
            $response = $this->failNotFound('No franchisee found');
        } else {
            $payable_credit = $this->franchiseeModel->get_payable_credit_by_franchisee_name($franchisee[0]['name']);
            $franchisee[0]['payable_credit'] = $payable_credit[0]['payable_credit'];
            $remaining_credit = $this->franchiseeModel->get_remaining_credit_by_franchisee_name($franchisee[0]['name']);
            $franchisee[0]['remaining_credit'] = $remaining_credit[0]['remaining_credit'];
            $franchisee[0]['franchisee_payments'] = $franchisee_payments;
            
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisee
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all franchisees
     */
    public function get_all_franchisee()
    {
        if (($response = $this->_api_verification('franchisees', 'get_all_franchisee')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchisees = $this->franchiseeModel->get_all();

        if (!$franchisees) {
            $response = $this->failNotFound('No franchisee found');
        } else {
            foreach ($franchisees as $key => $franchisee) {
                $franchisee_payment = $this->franchiseePaymentModel->get_details_by_franchisee_id($franchisee['id']);
                $franchisees[$key]['franchisee_payments'] = $franchisee_payment;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisees
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create franchisee
     */
    public function create()
    {
        if (($response = $this->_api_verification('franchisees', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$franchisee_id = $this->_create_franchisee()) {
            $db->transRollback();
            $response = $this->fail('Failed to create franchisee.');
        } else {
            $db->transCommit();
            $response = $this->respond([
                'status'        => 'success',
                'franchisee_id' => $franchisee_id
            ]);
        }


        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update franchisee
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('franchisees', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id'         => $this->request->getVar('franchisee_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$franchisee = $this->franchiseeModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee not found');
        } elseif (!$this->_attempt_update($franchisee)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update franchisee.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'franchisee updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete franchisees
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('franchisees', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('franchisee_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$franchisee = $this->franchiseeModel->select('', $where, 1)) {
            $response = $this->failNotFound('franchisee not found');
        } elseif (!$this->_attempt_delete($franchisee['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete franchisee.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'franchisee deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search franchisees based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('franchisees', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $project_id         = $this->request->getVar('project_id') ?? null;
        $customer_id        = $this->request->getVar('customer_id') ?? null;
        $name               = $this->request->getVar('name') ?? null;
        $type               = $this->request->getVar('type') ?? null;
        $franchisee_fee     = $this->request->getVar('franchisee_fee') ?? null;
        $royalty_fee        = $this->request->getVar('royalty_fee') ?? null;
        $paid_amount        = $this->request->getVar('paid_amount') ?? 0;
        $payment_status     = $this->request->getVar('payment_status') ?? null;
        $franchised_on_from = $this->request->getVar('franchised_on_from') ?? null;
        $franchised_on_to   = $this->request->getVar('franchised_on_to') ?? null;
        $opening_start      = $this->request->getVar('opening_start') ?? null;
        $remarks            = $this->request->getVar('remarks') ?? null;
        $contact_person     = $this->request->getVar('contact_person') ?? null;
        $contact_number     = $this->request->getVar('contact_number') ?? null;
        $phone_no           = $this->request->getVar('phone_no') ?? null;
        $address            = $this->request->getVar('address') ?? null;
        $email              = $this->request->getVar('email') ?? null;
        $contract_status    = $this->request->getVar('contract_status') ?? null;

        if (!$franchisees = $this->franchiseeModel->search($project_id,$customer_id, $name, $type, $franchisee_fee, $royalty_fee, $paid_amount, $payment_status, $franchised_on_from, $franchised_on_to, $opening_start, $remarks, $contact_person, $contact_number, $phone_no, $address, $email, $contract_status)) {
            $response = $this->failNotFound('No franchisee found');
        } else {
            $summary = [
                'total_franchisee_fee' => 0,
                'total_paid_amount' => 0,
                'total_balance' => 0
            ];

            foreach ($franchisees as $key => $franchisee) {
                $payable_credit = $this->franchiseeModel->get_payable_credit_by_franchisee_name($franchisee['name']);
                $remaining_credit = $this->franchiseeModel->get_remaining_credit_by_franchisee_name($franchisee['name']);
                $franchisees[$key]['payable_credit'] = $payable_credit[0]['payable_credit'];
                $franchisees[$key]['remaining_credit'] = $remaining_credit[0]['remaining_credit'];
                $summary['total_franchisee_fee'] += $franchisee['grand_total'];
                $summary['total_paid_amount'] += $franchisee['paid_amount'];
                $summary['total_balance'] += $franchisee['balance'];
            }

            $response = $this->respond([
                'summary' => $summary,
                'data'   => $franchisees,
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function reports()
    {
        if (($response = $this->_api_verification('franchisees', 'reports')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $franchisee_name = $this->request->getVar('franchisee_name') ?? null;
        $franchisee_id   = $this->request->getVar('franchisee_id') ?? null;
        $project_id       = $this->request->getVar('project_id') ?? null;
        $date_from       = $this->request->getVar('date_from') ?? null;
        $date_to         = $this->request->getVar('date_to') ?? null;

        if (!$franchisees = $this->franchiseeModel->reports($franchisee_name, $franchisee_id, $project_id, $date_from, $date_to)) {
            $response = $this->failNotFound('No franchisee found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $franchisees
            ]);
        }


    }

    // --------------------------------------------------------------------
    // Private methods
    // --------------------------------------------------------------------

    /**
     * Create franchisees
     */
    private function _create_franchisee()
    {
        $franchisee_fee     = $this->request->getVar('franchisee_fee') ?? 0;
        $franchisee_package = $this->request->getVar('franchisee_package') ?? 0;
        $royalty_fee        = $this->request->getVar('royalty_fee') ?? 0;
        $marketing_fee      = $this->request->getVar('marketing_fee') ?? 0;
        $other_fee          = $this->request->getVar('other_fee') ?? 0;
        $securtiy_deposit   = $this->request->getVar('security_deposit') ?? 0;
        $taxes              = $this->request->getVar('taxes') ?? 0;
        $grand_total        = $franchisee_fee + $other_fee + $securtiy_deposit + $taxes;
        
        $values = [
            'project_id'             => $this->request->getVar('project_id'),
            'customer_id'            => $this->request->getVar('customer_id'),
            'name'                   => $this->request->getVar('name'),
            'type'                   => $this->request->getVar('type'),
            'grand_total'            => $grand_total,
            'royalty_fee'            => $royalty_fee,
            'marketing_fee'          => $marketing_fee,
            'franchisee_fee'         => $franchisee_fee,
            'franchisee_package'     => $franchisee_package,
            'paid_amount'            => $this->request->getVar('paid_amount') ?? 0,
            'balance'                => $grand_total,
            'payment_status'         => $this->request->getVar('payment_status') ?? 'open_bill',
            'contract_start'         => $this->request->getVar('contract_start'),
            'contract_end'           => $this->request->getVar('contract_end'),
            'franchisee_contact_no'  => $this->request->getVar('franchisee_contact_no'),
            'franchised_on'          => $this->request->getVar('franchised_on'),
            'opening_start'          => $this->request->getVar('opening_start'),
            'remarks'                => $this->request->getVar('remarks'),
            'contact_person'         => $this->request->getVar('contact_person'),
            'contact_number'         => $this->request->getVar('contact_number'),
            'address'                => $this->request->getVar('address'),
            'email'                  => $this->request->getVar('email'),
            'phone_no'               => $this->request->getVar('phone_no'),
            'package_type'           => $this->request->getVar('package_type'),
            'beginning_credit_limit' => $this->request->getVar('beginning_credit_limit'),
            'current_credit_limit'   => $this->request->getVar('beginning_credit_limit'),
            'security_deposit'       => $securtiy_deposit,
            'taxes'                  => $taxes,
            'other_fee'              => $other_fee,
            'added_by'               => $this->requested_by,
            'added_on'               => date('Y-m-d H:i:s'),
        ];

        if (!$franchisee_id = $this->franchiseeModel->insert($values))
           return false;

        return $franchisee_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($franchisee)
    {
        $franchisee_fee     = $this->request->getVar('franchisee_fee') ?? 0;
        $franchisee_package = $this->request->getVar('franchisee_package') ?? 0;
        $royalty_fee        = $this->request->getVar('royalty_fee') ?? 0;
        $marketing_fee      = $this->request->getVar('marketing_fee') ?? 0;
        $other_fee          = $this->request->getVar('other_fee') ?? 0;
        $securtiy_deposit   = $this->request->getVar('security_deposit') ?? 0;
        $taxes              = $this->request->getVar('taxes') ?? 0;
        $grand_total        = $franchisee_fee + $other_fee + $securtiy_deposit + $taxes;

        $credit_difference = $this->request->getVar('beginning_credit_limit') - $franchisee['beginning_credit_limit'];
        $new_current_credit_limit = (float)$franchisee['current_credit_limit'] + $credit_difference;

        $values = [
            'project_id'              => $this->request->getVar('project_id'),
            'customer_id'            => $this->request->getVar('customer_id'),
            'name'                   => $this->request->getVar('name'),
            'type'                   => $this->request->getVar('type'),
            'grand_total'            => $grand_total,
            'royalty_fee'            => $royalty_fee,
            'marketing_fee'          => $marketing_fee,
            'franchisee_fee'         => $franchisee_fee,
            'franchisee_package'     => $franchisee_package,
            'paid_amount'            => $franchisee['paid_amount'],
            'balance'                => $grand_total - $franchisee['paid_amount'],
            'payment_status'         => $grand_total - $franchisee['paid_amount'] > 0 ? 'open_bill' : 'closed_bill',
            'contract_start'         => $this->request->getVar('contract_start'),
            'contract_end'           => $this->request->getVar('contract_end'),
            'franchisee_contact_no'  => $this->request->getVar('franchisee_contact_no'),
            'franchised_on'          => $this->request->getVar('franchised_on'),
            'opening_start'          => $this->request->getVar('opening_start'),
            'remarks'                => $this->request->getVar('remarks'),
            'contact_person'         => $this->request->getVar('contact_person'),
            'contact_number'         => $this->request->getVar('contact_number'),
            'address'                => $this->request->getVar('address'),
            'email'                  => $this->request->getVar('email'),
            'phone_no'               => $this->request->getVar('phone_no'),
            'package_type'           => $this->request->getVar('package_type'),
            'beginning_credit_limit' => $this->request->getVar('beginning_credit_limit'),
            'current_credit_limit'   => $new_current_credit_limit < 0 ? 0 : $new_current_credit_limit,
            'security_deposit'       => $securtiy_deposit,
            'taxes'                  => $taxes,
            'other_fee'              => $other_fee,
            'updated_by'             => $this->requested_by,
            'updated_on'             => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeModel->update($franchisee['id'], $values))
            return false;

        if ($franchisee['opening_start'] != $this->request->getVar('opening_start')) {
            $values = [
                'opening_date' => $this->request->getVar('opening_start'),
                'updated_by'   => $this->requested_by,
                'updated_on'   => date('Y-m-d H:i:s')
            ];

            if (!$this->branchModel->update($franchisee['project_id'], $values))
                return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($franchisee_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->franchiseeModel->update($franchisee_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->franchiseeModel        = model('App\Models\Franchisee');
        $this->franchiseePaymentModel = model('App\Models\Franchisee_payment');
        $this->branchModel            = model('App\Models\Branch');
        $this->webappResponseModel    = model('App\Models\Webapp_response');
    }
}

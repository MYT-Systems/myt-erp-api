<?php

namespace App\Controllers;

use App\Models\Store_deposit;
use App\Models\Store_deposit_attachment;
use App\Models\Webapp_response;

class Store_deposits extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get store deposit
     */
    public function get()
    {
        if (($response = $this->_api_verification('store_deposits', 'get')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $store_deposit_id = $this->request->getVar('store_deposit_id') ? : null;

        $where = ['is_deleted' => 0];
        $limit = null;
        if ($store_deposit_id) {
            $where['id'] = $store_deposit_id;
            $limit = 1;
        }
        $store_deposits = $this->storeDepositModel->select('', $where, $limit);

        if (!$store_deposits) {
            $response = $this->failNotFound('No store deposits found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $store_deposits
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search store deposits
     */
    public function search()
    {
        if (($response = $this->_api_verification('store_deposits', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $transaction_type = $this->request->getVar('transaction_type') ? : null;
        $branch_id = $this->request->getVar('branch_id') ? : null;
        $status = $this->request->getVar('status') ? : null;
        $deposited_to = $this->request->getVar('deposited_to') ? : null;

        $date_from = $this->request->getVar('date_from') ? : null;
        $date_from = $date_from ? date("Y-m-d", strtotime($date_from)) : null;
        $date_to = $this->request->getVar('date_to') ? : null;
        $date_to = $date_to ? date("Y-m-d", strtotime($date_to)) : null;

        $wide_search = $this->request->getVar('wide_search') ? : null;

        if (!$store_deposits = $this->storeDepositModel->search($transaction_type, $branch_id, $status, $deposited_to, $date_from, $date_to, $wide_search)) {
            $response = $this->failNotFound('No store deposits found');
        } else {
            $total_deposited_amount = 0;

            foreach ($store_deposits as $store_deposit)
                $total_deposited_amount += $store_deposit['amount'];

            $response = $this->respond([
                'status' => 'success',
                'data'   => $store_deposits,
                'summary' => ['total_deposited_amount' => $total_deposited_amount]
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update status (mark as done, mark as checked)
     */
    public function update_status()
    {
        if (($response = $this->_api_verification('store_deposits', 'update_status')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $store_deposit_ids = $this->request->getVar('store_deposit_ids');
        $status = $this->request->getVar('status');


        $values = [
            'updated_on'   => date("Y-m-d H:i:s"),
            'updated_by'   => $this->requested_by
        ];

        if ($status == 'posted') {
            $values['status'] = 'posted';
            $values['posted_on'] = date("Y-m-d H:i:s");
            $values['posted_by'] = $this->requested_by;
        } elseif ($status == 'checked') {
            $values['status'] = 'checked';
            $values['checked_on'] = date("Y-m-d H:i:s");
            $values['checked_by'] = $this->requested_by;
        }

        $unknown_ids = [];
        $unupdated_ids = [];
        foreach ($store_deposit_ids as $store_deposit_id) {
            if (!$store_deposit = $this->storeDepositModel->select('', ['id' => $store_deposit_id, 'is_deleted' => 0], 1)) {
                $unknown_ids[] = $store_deposit_id;
            } elseif (!$this->storeDepositModel->update($store_deposit_id, $values)) {
                $unupdated_ids = [];
            }
        }

        $errors = [];
        if ($unknown_ids AND $unupdated_ids) {
            $errors = [
                'NOT.FOUND.ERROR' => 'The following IDs were not found: ' . json_encode($unknown_ids),
                'SERVER.ERROR' => 'The following IDs were not updated: ' . json_encode($unupdated_ids)
            ];
        } elseif ($unknown_ids) {
            $errors = [
                'NOT.FOUND.ERROR' => 'The following IDs were not found: ' . json_encode($unknown_ids)
            ];
        } elseif ($unupdated_ids) {
            $errors = [
                'SERVER.ERROR' => 'The following IDs were not updated: ' . json_encode($unupdated_ids)
            ];
        }

        $response = $errors ? $this->fail($errors) : $this->respond('Status successfully changed');

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete store deposit
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('store_deposits', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $store_deposit_id = $this->request->getVar('store_deposit_id');

        $where = ['id' => $store_deposit_id, 'is_deleted' => 0];

        if (!$store_deposit = $this->storeDepositModel->select('', $where, 1)) {
            $response = $this->failNotFound('Daily sale not found');
        } elseif (!$this->_attempt_delete($store_deposit_id)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'Store deposit deleted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($store_deposit_id)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['store_deposit_id' => $store_deposit_id, 'is_deleted' => 0];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->storeDepositModel->update($store_deposit_id, $values) OR
            !$this->storeDepositAttachmentModel->custom_update($where, $values)
        ) {
            $db->transRollback();
            return false;
        }

        $db->transCommit();

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->storeDepositModel = new Store_deposit();
        $this->storeDepositAttachmentModel = new Store_deposit_attachment();
        $this->webappResponseModel  = new Webapp_response();
    }
}

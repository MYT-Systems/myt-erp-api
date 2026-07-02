<?php

namespace App\Controllers;

use App\Models\Credit_card;
use App\Models\Webapp_response;

class Credit_cards extends MYTController
{
    protected $creditCardModel;
    protected $webappResponseModel;

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get credit card
     */
    public function get_credit_card()
    {
        if (($response = $this->_api_verification('credit_cards', 'get_credit_card')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $credit_card_id = $this->request->getVar('credit_card_id') ? : null;
        $credit_card    = $credit_card_id ? $this->creditCardModel->get_details_by_id($credit_card_id) : null;

        if (!$credit_card) {
            $response = $this->failNotFound('No credit card found');
        } else {
            $response = $this->respond([
                'data'   => $credit_card,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all credit cards
     */
    public function get_all_credit_card()
    {
        if (($response = $this->_api_verification('credit_cards', 'get_all_credit_card')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $credit_cards = $this->creditCardModel->get_all_credit_card();

        if (!$credit_cards) {
            $response = $this->failNotFound('No credit card found');
        } else {
            $response = $this->respond([
                'data'   => $credit_cards,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create credit card
     */
    public function create()
    {
        if (($response = $this->_api_verification('credit_cards', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $name = $this->request->getVar('name');
        if ($response = $this->_is_existing($this->creditCardModel, ['name' => $name]))
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$credit_card_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create credit card.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'response'       => 'Credit card created successfully.',
                'status'         => 'success',
                'credit_card_id' => $credit_card_id
            ]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update credit card
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('credit_cards', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('credit_card_id'),
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$credit_card = $this->creditCardModel->select('', $where, 1)) {
            $response = $this->failNotFound('credit card not found');
        } elseif (!$this->_attempt_update($credit_card['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update credit card.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Credit card updated successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete credit cards
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('credit_cards', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('credit_card_id'),
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$credit_card = $this->creditCardModel->select('', $where, 1)) {
            $response = $this->failNotFound('credit card not found');
        } elseif (!$this->_attempt_delete($credit_card['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete credit card.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Credit card deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search credit cards based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('credit_cards', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $name         = $this->request->getVar('name');
        $card_name    = $this->request->getVar('card_name');
        $card_no      = $this->request->getVar('card_no');
        $issuing_bank = $this->request->getVar('issuing_bank');

        if (!$credit_cards = $this->creditCardModel->search($name, $card_name, $card_no, $issuing_bank)) {
            $response = $this->failNotFound('No credit card found');
        } else {
            $response = [];
            $response['data'] = $credit_cards;
            $response = $this->respond($response);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create credit card
     */
    protected function _attempt_create()
    {
        $values = [
            'name'           => $this->request->getVar('name'),
            'card_name'      => $this->request->getVar('card_name'),
            'card_no'        => $this->request->getVar('card_no'),
            'issuing_bank'   => $this->request->getVar('issuing_bank'),
            'credit_limit'   => $this->request->getVar('credit_limit'),
            'current_bal'    => 0,
            'statement_date' => $this->request->getVar('statement_date'),
            'due_date'       => $this->request->getVar('due_date'),
            'added_by'       => $this->requested_by,
            'added_on'       => date('Y-m-d H:i:s'),
            'is_deleted'     => 0
        ];

        if (!$credit_card_id = $this->creditCardModel->insert($values))
            return false;

        return $credit_card_id;
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($credit_card_id)
    {
        $values = [
            'name'           => $this->request->getVar('name'),
            'card_name'      => $this->request->getVar('card_name'),
            'card_no'        => $this->request->getVar('card_no'),
            'issuing_bank'   => $this->request->getVar('issuing_bank'),
            'credit_limit'   => $this->request->getVar('credit_limit'),
            'statement_date' => $this->request->getVar('statement_date'),
            'due_date'       => $this->request->getVar('due_date'),
            'updated_by'     => $this->requested_by,
            'updated_on'     => date('Y-m-d H:i:s')
        ];

        if (!$this->creditCardModel->update($credit_card_id, $values))
            return false;

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($credit_card_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->creditCardModel->update($credit_card_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->creditCardModel     = new Credit_card();
        $this->webappResponseModel = new Webapp_response();
    }
}

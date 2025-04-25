<?php

namespace App\Controllers;

class Petty_cash_reports extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get petty_cash
     */
    public function get_petty_cash()
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'get_petty_cash')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash_id = $this->request->getVar('petty_cash_id') ? : null;
        $petty_cash    = $petty_cash_id ? $this->pettyCashModel->get_details_by_id($petty_cash_id) : null;
        $petty_cash_details = $petty_cash ? $this->pettyCashDetailModel->get_details_by_petty_cash_id($petty_cash_id) : [];

        foreach ($petty_cash_details as $key => $value) {
            $petty_cash_details[$key]['petty_cash_items'] = $this->pettyCashItemModel->get_details_by_petty_cash_detail_id($value['id']);
        }

        if (!$petty_cash) {
            $response = $this->failNotFound('No petty cash found');
        } else {
            $petty_cash[0]['petty_cash_details'] = $petty_cash_details;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $petty_cash[0]
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_petty_cash_detail()
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'get_petty_cash')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash_detail_id = $this->request->getVar('petty_cash_detail_id') ? : null;
        
        $petty_cash_detail    = $petty_cash_detail_id ? $this->pettyCashDetailModel->get_details_by_id($petty_cash_detail_id) : null;
        $petty_cash_items     = $petty_cash_detail    ? $this->pettyCashItemModel->get_details_by_petty_cash_detail_id($petty_cash_detail_id) : [];

        if (!$petty_cash_detail) {
            $response = $this->failNotFound('No petty cash found');
        } else {
            $petty_cash_detail[0]['petty_cash_items'] = $petty_cash_items;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $petty_cash_detail
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all petty_cash_reports
     */
    public function get_all_petty_cash()
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'get_all_petty_cash')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash = $this->pettyCashModel->get_all_petty_cash();

        if (!$petty_cash) {
            $response = $this->failNotFound('No petty cash found');
        } else {
            foreach($petty_cash as $key => $value) {
                $petty_cash_details = $this->pettyCashDetailModel->get_details_by_petty_cash_id($value['id']);
                $petty_cash_details = $petty_cash_details ? $petty_cash_details : [];
               
                foreach ($petty_cash_details as $key2 => $value2) {
                    $petty_cash_details[$key2]['petty_cash_items'] = $this->pettyCashItemModel->get_details_by_petty_cash_detail_id($value2['id']);
                }
                $petty_cash[$key]['petty_cash_details'] = $petty_cash_details;
            }
            $response = $this->respond([
                'status' => 'success',
                'data'   => $petty_cash
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create petty_cash
     */
    public function create()
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$petty_cash_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response'     => 'petty_cash created successfully.',
                'status'       => 'success',
                'petty_cash_id' => $petty_cash_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create the petty cash details
     */
    public function create_details()
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'create_details')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        $petty_cash_id = $this->request->getVar('petty_cash_id') ? : null;
        
        if (!$petty_cash = $this->pettyCashModel->get_details_by_id($petty_cash_id)) {
            $response = $this->failNotFound('No petty cash found');
        } elseif (!$petty_cash_detail_id = $this->_attempt_create_petty_cash_detail($petty_cash_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_petty_cash_items($petty_cash_id, $petty_cash_detail_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond([
                'response'     => 'Petty cash details created successfully.',
                'status'       => 'success',
                'petty_cash_id' => $petty_cash_id,
                'petty_cash_detail_id' => $petty_cash_detail_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update petty_cash
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash_id = $this->request->getVar('petty_cash_id');
        $where = ['id' => $petty_cash_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$petty_cash = $this->pettyCashModel->select('', $where, 1)) {
            $response = $this->failNotFound('petty_cash not found');
        } elseif (!$this->_attempt_update($petty_cash)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'petty_cash updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update petty cash detail
     */
    public function update_detail() 
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'update_detail')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash_detail_id = $this->request->getVar('petty_cash_detail_id');
        $where = ['id' => $petty_cash_detail_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();
        if (!$petty_cash_detail = $this->pettyCashDetailModel->select('', $where, 1)) {
            $response = $this->failNotFound('petty_cash_detail not found');
        } elseif (!$this->_attempt_update_petty_cash_detail($petty_cash_detail)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Petty cash detail updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete petty_cash_reports
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash_id = $this->request->getVar('petty_cash_id');

        $where = ['id' => $petty_cash_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$petty_cash = $this->pettyCashModel->select('', $where, 1)) {
            $response = $this->failNotFound('petty_cash not found');
        } elseif (!$this->_attempt_delete($petty_cash_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'petty_cash deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete petty_cash_reports
     */
    public function delete_petty_cash_detail($id = '')
    {
        if (($response = $this->_api_verification('delete_petty_cash_detail', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash_detail_id = $this->request->getVar('petty_cash_detail_id');

        $where = ['id' => $petty_cash_detail_id, 'is_deleted' => 0];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$petty_cash_detail = $this->pettyCashDetailModel->select('', $where, 1)) {
            $response = $this->failNotFound('petty_cash not found');
        } elseif (!$this->_attempt_delete_petty_cash_detail($petty_cash_detail)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Petty cash deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search petty_cash_reports based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash_id = $this->request->getVar('petty_cash_id') ?? null;
        $type          = $this->request->getVar('type') ?? null;
        $status = $this->request->getVar('status') ? : null;
        $approved_by = $this->request->getVar('approved_by') ? : null;
        $approved_on = $this->request->getVar('approved_on') ? : null;

        $date_from     = $this->request->getVar('date_from') ?? null;
        $date_to       = $this->request->getVar('date_to') ?? null;

        if (!$petty_cash = $this->pettyCashModel->get_details_by_id($petty_cash_id)) {
            $response = $this->failNotFound('petty cash not found');
        } else {
            $petty_cash_details = $this->pettyCashDetailModel->search($petty_cash_id, null, null, $type, $status, $approved_by, $approved_on) ?? [];
            $current = $petty_cash[0]['beginning_petty_cash'];
            foreach ($petty_cash_details as $key => $value) {
                if ($value['type'] == 'in') {
                    $current += $value['amount'];
                } else {
                    $current -= $value['amount'];
                }
                
                $petty_cash_details[$key]['current'] = $current;
                
                if ($date_from AND $value['date'] < $date_from) {
                    unset($petty_cash_details[$key]);
                    continue;
                }

                if ($date_to AND $value['date'] > $date_to) {
                    unset($petty_cash_details[$key]);
                    continue;
                }
                
                $petty_cash_details[$key]['petty_cash_items'] = $this->pettyCashItemModel->get_details_by_petty_cash_detail_id($value['id']);
            }

            // reverse the petty cash details
            $petty_cash_details = array_values($petty_cash_details);
            $petty_cash_details = array_reverse($petty_cash_details);

            $response = $this->respond([
                'status' => 'success',
                'petty_cash' => $petty_cash[0],
                'history'   => $petty_cash_details
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function approve_cashout()
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'approve_cashout')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $petty_cash_detail_id = $this->request->getVar('petty_cash_id') ?? null;

        if (!$petty_cash_detail = $this->pettyCashDetailModel->get_details_by_id($petty_cash_detail_id)) {
            $response = $this->failNotFound('Petty cash detail not found');
        } elseif (!$petty_cash = $this->pettyCashModel->get_details_by_id($petty_cash_detail[0]['petty_cash_id'])) {
            $response = $this->failNotFound('Petty cash not found');
        } else {
            $petty_cash = $petty_cash[0];
            $petty_cash_detail = $petty_cash_detail[0];

            $petty_cash_subtraction = [
                'current_petty_cash' => $petty_cash['current_petty_cash'] - $petty_cash_detail['amount'],
                'updated_by' => $this->requested_by,
                'updated_on' => date("Y-m-d H:i:s")
            ];

            $values = [
                'status' => 'approved',
                'approved_by' => $this->requested_by,
                'approved_on' => date("Y-m-d H:i:s"),
                'updated_by' => $this->requested_by,
                'updated_on' => date("Y-m-d H:i:s")
            ];
            
            $db = db_connect();
            $db->transBegin();

            if (!$this->pettyCashDetailModel->update($petty_cash_detail_id, $values) OR
                !$this->pettyCashModel->update($petty_cash['id'], $petty_cash_subtraction)
            ) {
                $db->transRollback();
                $response = $this->fail('Unable to approve');
            } else {
                $db->transCommit();
                $response = $this->respond(['response' => 'Cashout approved']);
            }
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    public function get_petty_cash_status_frequency()
    {
        if (($response = $this->_api_verification('petty_cash_reports', 'get_petty_cash_status_frequency')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        if (!$petty_cash_tally = $this->pettyCashDetailModel->get_petty_cash_status_frequency()) {
            $response = $this->failNotFound('No petty cash found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $petty_cash_tally
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create petty_cash
     */
    protected function _attempt_create()
    {
        $values = [
            'name'                 => $this->request->getVar('name'),
            'beginning_petty_cash' => $this->request->getVar('beginning_petty_cash'),
            'current_petty_cash'   => $this->request->getVar('beginning_petty_cash'),
            'details'              => $this->request->getVar('details'),
            'added_by'             => $this->requested_by,
            'added_on'             => date('Y-m-d H:i:s'),
        ];

        if (!$petty_cash_id = $this->pettyCashModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $petty_cash_id;
    }

    /**
     * Attempt generate petty cash details
     */
    private function _attempt_create_petty_cash_detail($petty_cash_id)
    {
        $type = $this->request->getVar('type');
        $status = ($type == 'out') ? 'request' : 'approved';

        $values = [
            'petty_cash_id' => $petty_cash_id,
            'status'        => $status,
            'out_type'      => $this->request->getVar('out_type'),
            'type'          => $this->request->getVar('type'),
            'from'          => $this->request->getVar('from'),
            'requested_by'  => $this->request->getVar('requested_by'),
            'amount'        => $this->request->getVar('amount'),
            'particulars'   => $this->request->getVar('particulars'),
            'invoice_no'    => $this->request->getVar('invoice_no'),
            'date'          => $this->request->getVar('date'),
            'remarks'       => $this->request->getVar('remarks'),
            'added_by'      => $this->requested_by,
            'added_on'      => date('Y-m-d H:i:s')
        ];

        if (!$petty_cash_detail_id = $this->pettyCashDetailModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Update the current petty cash in the petty cash table
        if ($type == 'in' AND !$this->_update_current_petty_cash($petty_cash_id, $values['amount'], $values['type'])) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return $petty_cash_detail_id;
    }

    /**
     * Attempt update petty_cash current petty cash
     */
    private function _update_current_petty_cash($petty_cash_id, $amount, $type)
    {
        if (!$petty_cash = $this->pettyCashModel->get_details_by_id($petty_cash_id)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        $petty_cash = $petty_cash[0];
        $current_petty_cash = $petty_cash['current_petty_cash'];

        if ($type == 'out') {
            $current_petty_cash -= $amount;
        } else {
            $current_petty_cash += $amount;
        }

        $values = [
            'current_petty_cash' => $current_petty_cash,
            'updated_on'         => date('Y-m-d H:i:s'),
            'updated_by'         => $this->requested_by
        ];

        if (!$this->pettyCashModel->update($petty_cash_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt generate petty cash items
     */
    private function _attempt_generate_petty_cash_items($petty_cash_id, $petty_cash_detail_id)
    {
        $names      = $this->request->getVar('names') ?? [];
        $quantities = $this->request->getVar('quantities') ?? [];
        $prices     = $this->request->getVar('prices') ?? [];
        $units      = $this->request->getVar('units') ?? [];
        
        $values = [
            'petty_cash_id'        => $petty_cash_id,
            'petty_cash_detail_id' => $petty_cash_detail_id,
        ];

        foreach ($names as $key => $name) {
            $values['name']  = $name;
            $values['qty']   = $quantities[$key];
            $values['price'] = $prices[$key];
            $values['total'] = (float)$values['qty'] * (float)$values['price'];
            $values['unit']  = $units[$key];

            if (!$this->pettyCashItemModel->insert_on_duplicate($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        return $petty_cash_id;
    }
    

    /**
     * Attempt update
     */
    protected function _attempt_update($petty_cash)
    {
        $new_current_petty_cash = $petty_cash['beginning_petty_cash'] - $this->request->getVar('beginning_petty_cash');

        $values = [
            'name'                 => $this->request->getVar('name'),
            'beginning_petty_cash' => $this->request->getVar('beginning_petty_cash'),
            'current_petty_cash'   => $petty_cash['current_petty_cash'] + $new_current_petty_cash,
            'details'              => $this->request->getVar('details'),
            'updated_by'           => $this->requested_by,
            'updated_on'           => date('Y-m-d H:i:s')
        ];

        if (!$this->pettyCashModel->update($petty_cash_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt update petty cash detail
     */
    private function _attempt_update_petty_cash_detail($petty_cash_detail)
    {
        // Revert the petty cash amount
        if ($petty_cash_detail['status'] == 'approved' AND !$this->_update_current_petty_cash($petty_cash_detail['petty_cash_id'], (float)$petty_cash_detail['amount'] * -1, $petty_cash_detail['type'])) {
            return false;
        }

        $values = [
            'out_type'     => $this->request->getVar('out_type'),
            'type'         => $this->request->getVar('type'),
            'from'         => $this->request->getVar('from'),
            'requested_by' => $this->request->getVar('requested_by'),
            'amount'       => $this->request->getVar('amount'),
            'particulars'  => $this->request->getVar('particulars'),
            'invoice_no'   => $this->request->getVar('invoice_no'),
            'date'         => $this->request->getVar('date'),
            'remarks'      => $this->request->getVar('remarks'),
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s')
        ];

        if (!$this->pettyCashDetailModel->update($petty_cash_detail['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Update the current petty cash in the petty cash table
        if ($petty_cash_detail['status'] == 'approved' AND !$this->_update_current_petty_cash($petty_cash_detail['petty_cash_id'], $values['amount'], $values['type'])) {
            return false;
        }


        // Delete petty_cash_items
        if (!$this->pettyCashItemModel->delete_by_petty_cash_detail_id($petty_cash_detail['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

                
        if (!$this->_attempt_generate_petty_cash_items($petty_cash_detail['petty_cash_id'], $petty_cash_detail['id'])) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($petty_cash_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->pettyCashModel->update($petty_cash_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Delete petty_cash_details
        if (!$this->pettyCashDetailModel->delete_by_petty_cash_id($petty_cash_id, $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Delete petty_cash_items
        if (!$this->pettyCashItemModel->delete_by_petty_cash_id($petty_cash_id, $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        return true;
    }

    /**
     * Attempt delete petty cash detail
     */
    private function _attempt_delete_petty_cash_detail($petty_cash_detail)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->pettyCashDetailModel->update($petty_cash_detail['id'], $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Delete petty_cash_items
        if (!$this->pettyCashItemModel->delete_by_petty_cash_detail_id($petty_cash_detail['id'], $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        // Update the current petty cash in the petty cash table 
        // Reversing the amount to restore the current petty cashitem_remarks[]
        if ($petty_cash_detail['status'] == 'approved' AND !$this->_update_current_petty_cash($petty_cash_detail['petty_cash_id'], (float)$petty_cash_detail['amount'] * -1, $petty_cash_detail['type'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->pettyCashModel       = model('App\Models\Petty_cash');
        $this->pettyCashDetailModel = model('App\Models\Petty_cash_detail');
        $this->pettyCashItemModel   = model('App\Models\Petty_cash_item');
        $this->webappResponseModel  = model('App\Models\Webapp_response');
    }
}

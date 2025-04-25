<?php

namespace App\Controllers;

class Journal_entries extends MYTController
{
    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get journal entry
     */
    public function get_journal_entry()
    {
        if (($response = $this->_api_verification('journal_entries', 'get_journal_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $journal_entry_id       = $this->request->getVar('journal_entry_id') ? : null;
        $journal_entry          = $journal_entry_id ? $this->journalEntryModel->get_details_by_id($journal_entry_id) : null;
        $journal_entry_items    = $journal_entry_id ? $this->journalEntryItemModel->get_details_by_journal_entry_id($journal_entry_id) : [];

        if (!$journal_entry) {
            $response = $this->failNotFound('No journal_entry found');
        } else {
            $journal_entry[0]['journal_entry_items'] = $journal_entry_items;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $journal_entry
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all journal entries
     */
    public function get_all_journal_entry()
    {
        if (($response = $this->_api_verification('journal_entries', 'get_all_journal_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $date_from          = $this->request->getVar('date_from') ? : null;
        $date_to            = $this->request->getVar('date_to') ? : null;

        $journal_entries = $this->journalEntryModel->get_all($date_from, $date_to);

        if (!$journal_entries) {
            $response = $this->failNotFound('No journal_entry found');
        } else {
            $response = $this->respond([
                'status' => 'success',
                'data'   => $journal_entries
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create journal_entry
     */
    public function create()
    {
        if (($response = $this->_api_verification('journal_entries', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$journal_entry_id = $this->_attempt_create()) {
            $db->transRollback();
            $response = $this->fail('Failed to create journal entry.');
        } elseif (!$this->_attempt_generate_journal_entry_items($journal_entry_id)) {
            $db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $db->transCommit();
            $response = $this->respond([
                'status'        => 'success',
                'journal_entry_id' => $journal_entry_id
            ]);
        }

        $db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update journal_entry
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('journal_entries', 'update')) !== true)
            return $response;
    
        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id'         => $this->request->getVar('journal_entry_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        $journal_entry = $this->journalEntryModel->select('', $where, 1);
        if (!$journal_entry) {
            $response = $this->failNotFound('journal_entry not found');
        } elseif (!$this->_attempt_update($journal_entry['id'])) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update journal_entry.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_journal_entry_items($journal_entry)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to update journal_entry items.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'journal entry updated successfully.', 'status' => 'success']);
        }

        $db->close();

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Post journal_entry
     */
    public function post_journal_entry()
    {
        if (($response = $this->_api_verification('journal_entries', 'post_journal_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('journal_entry_id'), 
            'is_deleted' => 0
        ];

        $is_posted = $this->request->getVar('is_posted');

        if (!$journal_entry = $this->journalEntryModel->select('', $where, 1)) {
            $response = $this->respond(['response' => 'Journal entry not found']);
        } elseif (!$this->_attempt_post_journal_entry($journal_entry, $is_posted)) {
            $response = $this->fail('Server error');
        } else {
            $response = $this->respond(['response' => 'Journal entry posted successfully']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete journal_entry
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('journal_entries', 'delete')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $where = [
            'id' => $this->request->getVar('journal_entry_id'), 
            'is_deleted' => 0
        ];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$journal_entry = $this->journalEntryModel->select('', $where, 1)) {
            $response = $this->failNotFound('journal_entry not found');
        } elseif (!$this->_attempt_delete($journal_entry['id'])) { // Use $journal_entry['id'] instead of $journal_entry_id
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete journal_entry.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'journal_entry deleted successfully.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search project_invoices based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('project_invoices', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $project_invoice_id = $this->request->getVar('project_invoice_id');
        $project_id = $this->request->getVar('project_id');
        $invoice_date = $this->request->getVar('invoice_date');
        $address = $this->request->getVar('address');
        $company = $this->request->getVar('company');
        $remarks = $this->request->getVar('remarks');
        $payment_status = $this->request->getVar('payment_status');
        $status = $this->request->getVar('status');
        $fully_paid_on = $this->request->getVar('fully_paid_on');
        $anything = $this->request->getVar('anything') ?? null;
        $date_from = $this->request->getVar('date_from') ?? null;
        $date_to = $this->request->getVar('date_to') ?? null;

        if (!$project_invoices = $this->projectInvoiceModel->search($project_invoice_id, $project_id, $invoice_date, $address, $company, $remarks, $payment_status, $status, $fully_paid_on, $anything, $date_from, $date_to)) {
            $response = $this->failNotFound('No project_invoice found');
        } else {
            $summary = [
                'total' => 0,
                'total_paid_amount' => 0,
                'total_balance' => 0,
            ];

            foreach ($project_invoices as $key => $project_invoice) {
                $project_invoice_payments = $this->projectInvoicePaymentModel->get_details_by_project_invoices_id($project_invoice['id']);
                $project_invoices[$key]['project_invoice_payments'] = $project_invoice_payments;
                $summary['total'] += $project_invoice['grand_total'];
                $summary['total_paid_amount'] += $project_invoice['paid_amount'];
                $summary['total_balance'] += $project_invoice['balance'];
            }

            $response = $this->respond([
                'summary' => $summary,
                'data'    => $project_invoices,
                'status'  => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // --------------------------------------------------------------------
    // Private methods
    // --------------------------------------------------------------------

    /**
     * Create journal entry
     */
    private function _attempt_create()
    {
        $values = [
            'date'                  => $this->request->getVar('date'),
            'remarks'               => $this->request->getVar('remarks'),
            'total_debit'          => $this->request->getVar('total_debit'),
            'total_credit'         => $this->request->getVar('total_credit'),
            'is_posted'            => 0,
            'added_by'             => $this->requested_by,
            'added_on'             => date('Y-m-d H:i:s'),
        ];

        if (!$journal_entry_id = $this->journalEntryModel->insert($values))
            return false;

        return $journal_entry_id;
    }

    /**
     * Generate journal entry items
     */
    protected function _attempt_generate_journal_entry_items($journal_entry_id)
    {
        $project_ids             = $this->request->getVar('project_ids');
        $expense_type_ids        = $this->request->getVar('expense_type_ids');
        $debits                  = $this->request->getVar('debits');
        $credits                 = $this->request->getVar('credits');
        $remarks                 = $this->request->getVar('item_remarks');

        foreach ($project_ids as $key => $project_id) {
            $values = [
                'journal_entry_id' => $journal_entry_id,
                'project_id'       => $project_id,
                'expense_type_id'  => $expense_type_ids[$key],
                'debit'            => $debits[$key],
                'credit'           => $credits[$key],
                'remarks'          => $remarks[$key],
                'added_by'        => $this->requested_by,
                'added_on'        => date('Y-m-d H:i:s'),
            ];

            if (!$this->journalEntryItemModel->insert($values)) {
                return false; 
            }
        }

        return true; 
    }

    /**
     * Attempt update
     */
    protected function _attempt_update($journal_entry_id)
    {
        $values = [
            'date'                  => $this->request->getVar('date'),
            'remarks'               => $this->request->getVar('remarks'),
            'total_debit'          => $this->request->getVar('total_debit'),
            'total_credit'         => $this->request->getVar('total_credit'),
            'updated_by'           => $this->requested_by,
            'updated_on'           => date('Y-m-d H:i:s')
        ];

        if (!$this->journalEntryModel->update($journal_entry_id, $values)) {
            return false;            
        }

        return true;
    }

    /**
     * Attempt post journal entry
     */
    protected function _attempt_post_journal_entry($journal_entry, $is_posted)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $where = ['id' => $journal_entry['id']];

        $values = [
            'is_posted' => $is_posted,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->journalEntryModel->update($where, $values)) {
            $db->transRollback();
            return false;
        }

        $db->transCommit();
        $db->close();
        return true;
    }

    /**
     * Attempt generate journal entry items
     */
    protected function _attempt_update_journal_entry_items($journal_entry)
    {
        if (!$this->journalEntryItemModel->delete_by_journal_entry_id($journal_entry['id'], $this->requested_by)) {
            return false;
        }

        // insert new journal entry items
        if (!$this->_attempt_generate_journal_entry_items($journal_entry['id'])) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($journal_entry_id)
    {
        if (!$this->journalEntryItemModel->delete_by_journal_entry_id($journal_entry_id, $this->requested_by)) {
            return false;
        }
        
        $where = ['id' => $journal_entry_id];
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->journalEntryModel->update($where, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->journalEntryModel        = model('App\Models\Journal_entry');
        $this->journalEntryItemModel    = model('App\Models\Journal_entry_item');
        $this->itemUnitModel            = model('App\Models\Item_unit');
        $this->inventoryModel           = model('App\Models\Inventory');
        $this->webappResponseModel      = model('App\Models\Webapp_response');
    }
}

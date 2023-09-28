<?php

namespace App\Controllers;

class Expenses extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Change status expense item
     */
    public function change_status()
    {
        if (($response = $this->_api_verification('expenses', 'change_status')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('expense_id'),
            'is_deleted' => 0
        ];

        $values = [
            'status' => $this->request->getVar('status'),
            'updated_by' => $this->requested_by,
            'updated_on' => date("Y-m-d H:i:s")
        ];

        if (!$expense = $this->expenseModel->select('', $where, 1)) {
            $response = $this->failNotFound('Expense not found');
        } elseif (!$this->expenseModel->update($expense['id'], $values)) {
            $response = $this->fail(['response' => 'Failed to update expense status.']);
        } else {
            $response = $this->respond(['response' => 'Expense status updated successfully.']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get expense
     */
    public function get_expense()
    {
        if (($response = $this->_api_verification('expenses', 'get_expense')) !== true)
            return $response;

        $expense_id         = $this->request->getVar('expense_id') ? : null;
        $expense            = $expense_id ? $this->expenseModel->get_details_by_id($expense_id) : null;
        $expense_attachment = $expense_id ? $this->expenseAttachmentModel->get_details_by_expense_id($expense_id) : null;
        
        $item_name          = $this->request->getVar('item_name') ? : null;
        $expense_item       = $expense_id ? $this->expenseItemModel->get_all_expense_item_by_expense_id($expense_id, $item_name) : null;

        if (!$expense) {
            $response = $this->failNotFound('No expense found');
        } else {
            $expense[0]['attachment']   = $expense_attachment;
            $expense[0]['expense_item'] = $expense_item;

            $response = $this->respond([
                'data'   => $expense,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all expenses
     */
    public function get_all_expense()
    {
        if (($response = $this->_api_verification('expenses', 'get_all_expense')) !== true)
            return $response;

        $expenses = $this->expenseModel->get_all_expense();

        if (!$expenses) {
            $response = $this->failNotFound('No expense found');
        } else {
            foreach ($expenses as $key => $expense) {
                $expenses[$key]['attachment']   = $this->expenseAttachmentModel->get_details_by_expense_id($expense['id']);
                $expenses[$key]['expense_item'] = $this->expenseItemModel->get_all_expense_item_by_expense_id($expense['id']);
            }

            $response = $this->respond([
                'data'   => $expenses,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create expense
     */
    public function create()
    {
        if (($response = $this->_api_verification('expenses', 'create')) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$expense_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_generate_expense_item($expense_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {     
            $this->db->transCommit();
            $response = $this->respond([
                'response'   => 'Expense created successfully.',
                'status'     => 'success',
                'expense_id' => $expense_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Bulk create
     */
    public function bulk_create()
    {
        if (($response = $this->_api_verification('expenses', 'bulk_create')) !== true)
            return $response;

        $data = $this->request->getVar();
        unset($data['requester']);
        unset($data['token']);
        $filename = $this->_write_json('expenses', $data);

        if ($filename === false) {
            $response = $this->fail('File not created.');
        }

        $response = $this->respond([
            'status' => 'success',
            'sync_time' => date("Y-m-d H:i:s")
        ]);
        
        // elseif (!$this->_attempt_bulk_create($filename)) {
        //     $response = $this->fail($this->errorMessage);
        // } else {
        //     $response = $this->respond([
        //         'status' => 'success'
        //     ]);
        // }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update expense
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('expenses', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('expense_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$expense = $this->expenseModel->select('', $where, 1))
            $response = $this->failNotFound('expense not found');
        elseif (!$this->_attempt_update($expense['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } elseif (!$this->_attempt_update_expense_item($expense['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Expense updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete expenses
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('expenses', 'delete')) !== true)
            return $response;

        $where = [
            'id'         => $this->request->getVar('expense_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$expense = $this->expenseModel->select('', $where, 1)) {
            $response = $this->failNotFound('expense not found');
        } elseif (!$this->_attempt_delete($expense['id'])) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'Expense deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search expenses based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('expenses', 'search')) !== true)
            return $response;

        $branch_id         = $this->request->getVar('branch_id') ? : null;
        $branch_name       = $this->request->getVar('branch_name') ? : null;
        $expense_date      = $this->request->getVar('expense_date') ? : null;
        $description       = $this->request->getVar('description') ? : null;
        $amount            = $this->request->getVar('amount') ? : null;
        $expense_date_from = $this->request->getVar('expense_date_from') ? : null;
        $expense_date_to   = $this->request->getVar('expense_date_to') ? : null;
        $by_branch   = $this->request->getVar('by_branch') ? : false;
        $grand_total = 0;

        if (!$expenses = $this->expenseModel->search($branch_id, $branch_name, $expense_date, $description, $amount, $expense_date_from, $expense_date_to, $by_branch)) {
            $response = $this->failNotFound('No expense found');
        } else {
            foreach ($expenses as $key => $expense) {
                $grand_total += $expenses[$key]['grand_total'];
                $expenses[$key]['attachment']   = $this->expenseAttachmentModel->get_details_by_expense_id($expense['id']);
                $expenses[$key]['expense_item'] = $this->expenseItemModel->get_all_expense_item_by_expense_id($expense['id']);
            }
            
            $response = $this->respond([
                'data' => $expenses,
                'status' => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get grand total
     */
    public function get_total_expense()
    {
        if (($response = $this->_api_verification('expenses', 'get_total_expense')) !== true)
            return $response;
        
        $branch_id    = $this->request->getVar('branch_id');
        $expense_date = $this->request->getVar('expense_date');

        if (!$grand_total = $this->expenseModel->get_total_expense($branch_id, $expense_date)) {
            $response = $this->failNotFound('No expense found');
        } else {
            $response = $this->respond([
                'branch_id'    => $branch_id ? $branch_id : 'all',
                'expense_date' => $expense_date ? $expense_date : 'all',
                'total'        => $grand_total,
                'status'       => 'success',
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }


    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt bulk create
     */
    protected function _attempt_bulk_create($filename)
    {
        $upload_path = FCPATH . 'public/expenses/' . $filename;
        $json = file_get_contents($upload_path);
        $expenses = json_decode($json);
        $expenses = (array) $expenses;

        $unsaved_expenses = [];

        $this->db = \Config\Database::connect();
        
        foreach ($expenses['expenses'] as $expense) {
            $expense = json_decode($expense);
            $expense = (array) $expense;

            $this->db->transBegin();

            if (!$expense_id = $this->_attempt_create($expense)) {
                $unsaved_expenses[] = $expense;
                $this->errorMessage = $this->db->error()['message'];
                $this->db->transRollback();
            } elseif (!$this->_attempt_generate_expense_item_from_sync($expense_id, $expense['expense_items'])) {
                $unsaved_expenses[] = $expense;
                $this->db->transRollback();
            }
        }

        $this->db->transCommit();

        if ($unsaved_expenses) {
            $write_response = $this->_write_json('expenses', $unsaved_expenses);
            
            $old_file_path = FCPATH . 'public/expenses/' . $filename;
            unlink($old_file_path);
        
            return false;
        }
        
        $old_file_path = FCPATH . 'public/expenses/' . $filename;
        unlink($old_file_path);

        return true;
    }

    /**
     * Attempt create expense
     */
    private function _attempt_create($data = null)
    {
        $values = [
            'branch_id'    => $this->_get_payload_value('branch_id', $data),
            'expense_date' => $this->_get_payload_value('expense_date', $data),
            'store_name'   => $this->_get_payload_value('store_name', $data),
            'invoice_no'   => $this->_get_payload_value('invoice_no', $data, null),
            'encoded_by'   => $this->_get_payload_value('encoded_by', $data, null),
            'remarks'      => $this->_get_payload_value('remarks', $data, null),
            'added_by'     => $this->requested_by,
            'added_on'     => date('Y-m-d H:i:s')
        ];

        if (!$expense_id = $this->expenseModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        } elseif ($this->request->getFile('file') AND 
                !$this->_attempt_upload_file_base64($this->expenseAttachmentModel, ['expense_id' => $expense_id])) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        } elseif ($data AND !$this->_save_attachment_from_sync($data, $expense_id)) {
            return false;
        }

        return $expense_id;
    }

    protected function _save_attachment_from_sync($data, $expense_id)
    {

        // DELETE THIS AFTER CLIENT MEETING: LINE BEGIN
        // $values = [
        //     'expense_id' => $expense_id,
        //     'base_64' => $data['expense_attachments'],
        //     'added_by' => $this->requested_by,
        //     'added_on' => date('Y-m-d H:i:s')
        // ];

        // if (!$this->expenseAttachmentModel->insert($values))
        //     return false;
        // return true;
        // DELETE THIS AFTER CLIENT MEETING: LINE END

        $values = [];
        foreach ($data['expense_attachments'] as $attachment) {
            $values[] = [
                'expense_id' => $expense_id,
                'base_64' => $attachment,
                'added_by' => $this->requested_by,
                'added_on' => date('Y-m-d H:i:s')
            ];
        }

        if (count($values) > 0 AND !$this->expenseAttachmentModel->insertBatch($values))
            return false;
        return true;
    }

    /**
     * Attempt generate expense item from sync
     */
    protected function _attempt_generate_expense_item_from_sync($expense_id, $expense_items)
    {
        $grand_total = 0;
        foreach ($expense_items as $index => $expense_item) {
            $expense_item = (array) $expense_item;

            $price = $this->_get_payload_value('price', $expense_item);
            $qty = $this->_get_payload_value('qty', $expense_item);

            $current_total = $price * $qty;
            $grand_total  += $current_total;

            $values = [
                'expense_id' => $expense_id,
                'name'  => $this->_get_payload_value('name', $expense_item),
                'unit'  => $this->_get_payload_value('unit', $expense_item),
                'price' => $price,
                'qty'   => $qty,
                'total' => $current_total,
                'added_by'   => $this->_get_payload_value('added_by', $expense_item),
                'added_on'   => $this->_get_payload_value('added_on', $expense_item)
            ];

            if (!$this->expenseItemModel->insert_on_duplicate_key_update($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        
        }

        $values = [
            'grand_total' => $grand_total,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$this->expenseModel->update($expense_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }

    /**
     * Attempt generate expense item
     */
    protected function _attempt_generate_expense_item($expense_id)
    {
        $names      = $this->request->getVar('names');
        $units      = $this->request->getVar('units');
        $prices     = $this->request->getVar('prices');
        $quantities = $this->request->getVar('quantities');

        $values = [
            'expense_id' => $expense_id,
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
        ];

        $grand_total = 0;
        foreach ($names as $key => $name) {
            $current_total = $prices[$key] * $quantities[$key];
            $grand_total  += $current_total;

            $values['name']  = $name;
            $values['unit']  = $units[$key];
            $values['price'] = $prices[$key];
            $values['qty']   = $quantities[$key];
            $values['total'] = $current_total;

            if (!$this->expenseItemModel->insert_on_duplicate_key_update($values, $this->requested_by, $this->db)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        
        }

        $values = [
            'grand_total' => $grand_total,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s'),
        ];

        if (!$this->expenseModel->update($expense_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }

        return true;
    }


    /**
     * Attempt update
     */
    protected function _attempt_update($expense_id)
    {
        $values = [
            'branch_id'    => $this->request->getVar('branch_id'),
            'expense_date' => $this->request->getVar('expense_date'),
            'store_name'   => $this->request->getVar('store_name'),
            'invoice_no'   => $this->request->getVar('invoice_no'),
            'encoded_by'   => $this->request->getVar('encoded_by'),
            'remarks'      => $this->request->getVar('remarks'),
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s')
        ];

        if (!$this->expenseModel->update($expense_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        } elseif ($this->request->getFile('file') AND
                  $this->expenseAttachmentModel->delete_attachments_by_expense_id($expense_id, $this->requested_by, $this->db)
        ) {
            if (!$this->_attempt_upload_file_base64($this->expenseAttachmentModel, ['expense_id' => $expense_id])){
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt update expense item
     */
    protected function _attempt_update_expense_item($expense_id)
    {
        if (!$this->expenseItemModel->delete_expense_item_by_expense_id($expense_id,  $this->requested_by, $this->db)) {
            $this->errorMessage = $this->db->error()['message'];    
            return false;
        }
        
        if (!$this->_attempt_generate_expense_item($expense_id))
            return false;
        
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($expense_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->expenseModel->update($expense_id, $values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        if (!$this->expenseItemModel->delete_expense_item_by_expense_id($expense_id, $this->requested_by)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        return true;
    }

    protected function _get_payload_value($parameter, $data = null, $default_value = null)
    {
        $final_value = null;
        if ($data)
            $final_value = array_key_exists($parameter, $data) ? $data[$parameter] : $default_value;
        else
            $final_value = $this->request->getVar($parameter) ? : $default_value;
        
        return $final_value;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->expenseModel           = model('App\Models\Expense');
        $this->expenseItemModel       = model('App\Models\Expense_item');
        $this->expenseAttachmentModel = model('App\Models\Expense_attachment');
        $this->webappResponseModel    = model('App\Models\Webapp_response');
    }
}

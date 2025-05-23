<?php

namespace App\Controllers;

use App\Models\SE_bank_entry;
use App\Models\SE_bank_slip;
use App\Models\SE_bank_slip_attachment;
use App\Models\Supplies_receive;
use App\Models\Webapp_response;

class Se_bank_payments extends MYTController
{

    public function __construct()
    {
        helper('filesystem');
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get se_bank_entry
     */
    public function get_entry()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_bank_entry_id = $this->request->getVar('entry_id') ? : null;
        $se_bank_entry    = $se_bank_entry_id ? $this->bankEntryModel->get_details_by_id($se_bank_entry_id) : null;
        $se_bank_slip     = $se_bank_entry ? $this->bankSlipModel->get_details_by_id($se_bank_entry[0]['id']) : null;

        if (!$se_bank_entry) {
            $response = $this->failNotFound('No bank invoice found');
        } else {
            $se_bank_entry[0]['se_bank_slip'] = $se_bank_slip;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_bank_entry
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get se_bank_entry
     */
    public function get_slip()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_bank_slip_id = $this->request->getVar('slip_id') ? : null;
        $se_bank_slip    = $se_bank_slip_id ? $this->bankSlipModel->get_details_by_id($se_bank_slip_id) : null;
        $bank_entries = $se_bank_slip ? $this->bankEntryModel->get_details_by_slip_id($se_bank_slip[0]['id']) : null;
        $bank_slip_attachments = $bank_entries ? $this->bankSlipAttachmentModel->get_details_by_se_bank_slip_id($se_bank_slip[0]['id']) : null;

        if (!$se_bank_slip) {
            $response = $this->failNotFound('No bank invoice found');
        } else {
            $se_bank_slip[0]['bank_entries'] = $bank_entries;
            $se_bank_slip[0]['bank_slip_attachments'] = $bank_slip_attachments ? $bank_slip_attachments : [];
            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_bank_slip
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all se_bank_entry
     */
    public function get_all_entry()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_all_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_entries = $this->bankEntryModel->get_all_entry();

        if (!$bank_entries) {
            $response = $this->failNotFound('No se_bank_entry found');
        } else {
            foreach($bank_entries as $key => $se_bank_entry) {
                $se_bank_slip = $this->bankSlipModel->get_details_by_id($se_bank_entry['se_bank_slip_id']);
                $bank_entries[$key]['se_bank_slip'] = $se_bank_slip;
            }

            $response = $this->respond([
                'data'   => $bank_entries,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all se_bank_entry
     */
    public function get_all_slip()
    {
        if (($response = $this->_api_verification('bank_payments', 'get_all_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_bank_slips = $this->bankSlipModel->get_all_slip();

        if (!$se_bank_slips) {
            $response = $this->failNotFound('No se_bank_entry found');
        } else {
            foreach($se_bank_slips as $key => $se_bank_slip) {
                $bank_entries = $this->bankEntryModel->get_details_by_slip_id($se_bank_slip['id']);
                $se_bank_slips[$key]['bank_entries'] = $bank_entries;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_bank_slips
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete receive attachment
     */
    public function delete_attachment()
    {
        if (($response = $this->_api_verification('receives', 'delete_attachment')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_bank_slip_id = $this->request->getVar('se_bank_slip_id');
        $attachment_id = $this->request->getVar('attachment_id');
        $where = ['id' => $se_bank_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$attachment = $this->bankSlipAttachmentModel->select('', $where, 1)) {
            $response = $this->failNotFound('Bank Slip attachment not found.');
        } elseif (!$this->_attempt_delete_attachments($se_bank_slip_id, $attachment)) {
            $db->transRollback();
            $response = $this->respond(['response' => 'Attachment not deleted successfully.']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Attachment deleted successfully.']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /** 
     * Attempt delete attachments
     */
    function _attempt_delete_attachments($se_bank_slip_id, $attachment = null)
    {
        $where = [
            'se_bank_slip_id' => $se_bank_slip_id,
            'is_deleted' => 0
        ];

        if($attachment) {
            $where['id'] = $attachment['id'];
        }

        $unique_name = substr("abcd", mt_rand(0, 4), 1).substr(md5(time()), 1);
        $oldFileName = $attachment['file_path'] . '/' . $attachment['file_name'];
        $newFileName = $attachment['file_path'] . '/' . 'deleted_' . $unique_name . '_' . $attachment['file_name'];
        rename($oldFileName, $newFileName);

        // unlink($attachment['file_path'] . '/' . $attachment['file_name'])
        $values = [
            'file_name'     => 'deleted_' . $unique_name . '_' . $attachment['file_name'],
            'file_url'      => $newFileName,
            'is_deleted'    => 1,
            'updated_by'    => $this->requested_by,
            'updated_on'    => date('Y-m-d H:i:s'),
        ];

        if (!$this->bankSlipAttachmentModel->custom_update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Upload Attachments
     */
    public function _upload_attachments($se_bank_slip_id, $path)
    {
        $files = $this->request->getFileMultiple('attachments');
        $file_path = $path.$se_bank_slip_id;
        // Uncomment this to add randomize naming
        // $unique_name = substr("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", mt_rand(0, 51), 1).substr(md5(time()), 1);
        $unique_name = "";

        if (!empty($files)) {
            $path = $file_path . '/';

            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                write_file($path . 'index.html', 'Directory access is forbidden.');
            }

            foreach ($files as $i => $file) {
                $sourcePath = $file->getPath();
                $destinationPath = $path;

                if ($file->isValid() && !in_array($file->getExtension(), ['png', 'jpeg', 'jpg'])) {
                    return false;
                }

                $original_name = $file->getName();
                $max_file_size = 5 * 1024 * 1024; // 5 MB in bytes
            
                if ($file->getSize() > $max_file_size) {
                    return false;
                }

                if ($file->isValid() && !$file->hasMoved()) {
                    $extension = $file->getExtension();
                    $random_str = bin2hex(random_bytes(4)); // generates 8-character random string
                    $file_name = pathinfo($original_name, PATHINFO_FILENAME) . '_' . $random_str . '.' . $extension;
                    $mime_type = $file->getMimeType();

                    $where = [
                        'se_bank_slip_id' => $se_bank_slip_id,
                        'file_name' => $file_name,
                        'is_deleted' => 0
                    ];

                    if(empty($this->bankSlipAttachmentModel->select('', $where))) {
                        $file->move($path, $file_name);
                        $data = [
                            'se_bank_slip_id' => $se_bank_slip_id,
                            'file_name' => $file_name,
                            'file_path' => $file_path,
                            'file_url' => base_url($file_path . '/' . $file_name),
                            'mime' => $mime_type,
                            'added_by' => $this->requested_by,
                            'added_on' => date('Y-m-d H:i:s')
                        ];

                        if (!$this->bankSlipAttachmentModel->insert($data)) {
                            return false;
                        }
                    }
                }

            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Create se_bank_entry
     */
    public function create()
    {
        if (($response = $this->_api_verification('bank_payments', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_bank_slip_id = $this->_attempt_create_slip()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create slip.', 'status' => 'error']);
        } elseif (($error_message = $this->_attempt_generate_entry($se_bank_slip_id)) !== true) {
            $db->transRollback();
            $response = $this->respond([
                "response" => $error_message,  
                "status" => "error"            
            ]);
        } else if(($this->request->getFileMultiple('attachments')?true:false) && !$this->_upload_attachments($se_bank_slip_id, 'assets/se_bank_payments/')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to upload attachments. Make sure you have the correct file type, and file does not exceed 5 megabytes.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully created slip.', 'status' => 'success', 'slip_id' => $se_bank_slip_id]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update bank slip and se_bank_entry
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('bank_payements', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_bank_slip_id = $this->request->getVar('se_bank_slip_id');
        $where         = ['id' => $se_bank_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_bank_slip = $this->bankSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense bank slip not found');
        } elseif (!$this->_attempt_update_slip($se_bank_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Supplies expense bank entry updated unsuccessfully', 'status' => 'error']);
        } elseif (!$this->_attempt_update_entry($se_bank_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Supplies expense bank entry updated unsuccessfully', 'status' => 'error']);
        }  else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Supplies expense bank entry updated successfully', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete se_bank_entry
     */
    public function delete_entry($id = '')
    {
        if (($response = $this->_api_verification('bank_payements', 'delete_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_bank_entry_id = $this->request->getVar('se_bank_entry_id');

        $where = ['id' => $se_bank_entry_id, 'is_deleted' => 0];


        if (!$se_bank_entry = $this->bankEntryModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense bank entry not found');
        } elseif (!$this->_attempt_delete_entry($se_bank_entry)) {
            $response = $this->fail(['response' => 'Failed to delete Supplies expense bank entry.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Successfully deleted Supplies expense bank entry.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete bank slip
     */
    public function delete_slip($id = '')
    {
        if (($response = $this->_api_verification('bank_payements', 'delete_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_bank_slip_id = $this->request->getVar('se_bank_slip_id');

        $where = ['id' => $se_bank_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_bank_slip = $this->bankSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense bank slip not found');
        } elseif (!$this->_attempt_delete_slip($se_bank_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete Supplies expense bank slip.', 'status' => 'error']);
        } elseif (!$this->bankEntryModel->delete_by_slip_id($se_bank_slip_id, $this->requested_by)){
            $db->transCommit();
            $response = $this->fail(['response' => 'Successfully deleted Supplies expense bank slip.', 'status' => 'success']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted Supplies expense bank slip.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search se_bank_entry based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('bank_payments', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_id     = $this->request->getVar('bank_id') ?? null;
        $bank_no    = $this->request->getVar('bank_no') ?? null;
        $bank_date  = $this->request->getVar('bank_date') ?? null;
        $amount      = $this->request->getVar('amount') ?? null;
        $supplier_id = $this->request->getVar('supplier_id') ?? null;
        $payee       = $this->request->getVar('payee') ?? null;
        $particulars = $this->request->getVar('particulars') ?? null;

        if (!$se_bank_slip = $this->bankSlipModel->search($bank_id, $bank_no, $bank_date, $amount, $supplier_id, $payee, $particulars)) {
            $response = $this->failNotFound('No se_bank_entry found');
        } else {
            $bank_entries = $this->bankEntryModel->get_details_by_slip_id($se_bank_slip[0]['id']);
            $se_bank_slip[0]['bank_entries'] = $bank_entries;
            $response = $this->respond(['data' => $se_bank_slip, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record the action of the user
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('bank_payments', 'record_action')) !== true)
            return $response;
    
        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_bank_slip_id = $this->request->getVar('se_bank_slip_id');
        $action        = $this->request->getVar('action');

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_bank_slip = $this->bankSlipModel->select('', ['id' => $se_bank_slip_id, 'is_deleted' => 0], 1)) {
            $response = $this->respond(['response' => 'Supplies expense bank slip not found']);
        } elseif (!$this->_attempt_record_action($se_bank_slip, $action)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'se_bank_slip status changed successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create bank slip
     */
    protected function _attempt_create_slip()
    {
        $data = [
            'payment_date'      => $this->request->getVar('payment_date'),
            'bank_from'         => $this->request->getVar('bank_from'),
            'from_account_no'   => $this->request->getVar('from_account_no'),
            'from_account_name' => $this->request->getVar('from_account_name'),
            'bank_to'           => $this->request->getVar('bank_to'),
            'to_account_no'     => $this->request->getVar('to_account_no'),
            'to_account_name'   => $this->request->getVar('to_account_name'),
            'transaction_fee'   => $this->request->getVar('transaction_fee'),
            'reference_no'      => $this->request->getVar('reference_no'),
            'amount'            => $this->request->getVar('amount'),
            'supplier_id'       => $this->request->getVar('supplier_id'),
            'vendor_id'         => $this->request->getVar('vendor_id'),
            'payee'             => $this->request->getVar('payee'),
            'particulars'       => $this->request->getVar('particulars'),
            'acknowledged_by'   => $this->request->getVar('acknowledged_by'),
            'added_by'          => $this->requested_by,
            'added_on'          => date('Y-m-d H:i:s')
        ];

        if (!$se_bank_slip_id = $this->bankSlipModel->insert($data)) {
            return false;
        }

        return $se_bank_slip_id;
    }

    /**
     * Attempt generate bank entry
     */
    protected function _attempt_generate_entry($se_bank_slip_id)
    {
        $se_ids = $this->request->getVar('se_ids');
        $amounts = $this->request->getVar('amounts');
        $types = $this->request->getVar('types');
        $bank_from = $this->request->getVar('bank_from');
        $transaction_fee = $this->request->getVar('transaction_fee');

        $bank = $this->bankModel->get_details_by_id($bank_from);
        if (!$bank) {
            return "Bank account not found.";
        }

        $bank_balance = $bank[0]['current_bal'];
        $total = array_sum($amounts) + $transaction_fee; // Calculate total amount to be deducted

        if ($total > $bank_balance) {
            return "Insufficient funds in the Bank Account.";
        }

        // Process each supplies expense
        foreach ($se_ids as $key => $se_id) {
            $amount = $amounts[$key];
            $type = $types[$key];

            // Insert bank entry
            $data = [
                'se_bank_slip_id' => $se_bank_slip_id,
                'se_id'           => $se_id,
                'type'            => $type,
                'amount'          => $amount,
                'added_by'        => $this->requested_by,
                'added_on'        => date('Y-m-d H:i:s')
            ];

            if (!$this->bankEntryModel->insert($data)) {
                return "Failed to insert bank entry for se_id: {$se_id}";
            }

            $supplies_expense = $this->suppliesExpenseModel->get_details_by_id($se_id);
            if ($supplies_expense) {
                $new_balance = $supplies_expense[0]['balance'] - $amount;
                $order_status = ($new_balance <= 0) ? 'complete' : 'incomplete';

                $supplies_expense_data = [
                    'paid_amount'  => $supplies_expense[0]['paid_amount'] + $amount,
                    'balance'      => $new_balance,
                    'order_status' => $order_status,
                    'updated_on'   => date('Y-m-d H:i:s'),
                    'updated_by'   => $this->requested_by
                ];

                if (!$this->suppliesExpenseModel->update($se_id, $supplies_expense_data)) {
                    return "Failed to update supplies expense for se_id: {$se_id}";
                }
            } else {
                return "Supplies Expense with id {$se_id} not found.";
            }
        }

        $new_bank_balance = $bank_balance - $total;

        $bank_data = [
            'current_bal' => $new_bank_balance,
            'updated_on'  => date('Y-m-d H:i:s'),
            'updated_by'  => $this->requested_by
        ];

        if (!$this->bankModel->update($bank_from, $bank_data)) {
            return "Failed to update bank balance.";
        }

        $values = [
            'amount'     => $total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankSlipModel->update($se_bank_slip_id, $values)) {
            return "Failed to update bank slip.";
        }

        return true;
    }

    /**
     * Attempt update bank slip
     */
    protected function _attempt_update_slip($se_bank_slip_id)
    {
        $data = [
            'payment_date'      => $this->request->getVar('payment_date'),
            'bank_from'         => $this->request->getVar('bank_from'),
            'from_account_no'   => $this->request->getVar('from_account_no'),
            'from_account_name' => $this->request->getVar('from_account_name'),
            'bank_to'           => $this->request->getVar('bank_to'),
            'to_account_no'     => $this->request->getVar('to_account_no'),
            'to_account_name'   => $this->request->getVar('to_account_name'),
            'transaction_fee'   => $this->request->getVar('transaction_fee'),
            'reference_no'      => $this->request->getVar('reference_no'),
            'amount'            => $this->request->getVar('amount'),
            'supplier_id'       => $this->request->getVar('supplier_id'),
            'vendor_id'         => $this->request->getVar('vendor_id'),
            'payee'             => $this->request->getVar('payee'),
            'particulars'       => $this->request->getVar('particulars'),
            'acknowledged_by'   => $this->request->getVar('acknowledged_by'),
            'updated_by'        => $this->requested_by,
            'updated_on'        => date('Y-m-d H:i:s')
        ];

        if (!$this->bankSlipModel->update($se_bank_slip_id, $data)) {
            return false;
        }

        if (!$this->bankSlipAttachmentModel->delete_attachment_by_se_bank_slip_id($se_bank_slip_id, $this->requested_by)) {
            return false;
        } elseif(($this->request->getFileMultiple('attachments')?true:false) && !$this->_upload_attachments($se_bank_slip_id, 'assets/se_bank_payments/')) {
            // $db->transRollback();
            // $response = $this->fail(['response' => 'Failed to upload attachments. Make sure you have the correct file type, and file does not exceed 5 megabytes.', 'status' => 'error']);
            return false;
        }

        return true;
    }

    /**
     * Attempt update bank entry
     */
    protected function _attempt_update_entry($se_bank_slip_id)
    {
        $this->bankEntryModel->delete_by_slip_id($se_bank_slip_id, $this->requested_by);
        if (!$this->_attempt_generate_entry($se_bank_slip_id)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete entry
     */
    protected function _attempt_delete_entry($se_bank_entry)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankEntryModel->update($se_bank_entry['id'], $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        if (!$se_bank_slip = $this->bankSlipModel->select('', ['id' => $se_bank_entry['se_bank_slip_id']], 1)) {
            $db->close();
            return false;
        }

        $values = [
            'amount' => $se_bank_slip['amount'] - $se_bank_entry['amount'],
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankSlipModel->update($se_bank_slip['id'], $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        $db->transCommit();
        $db->close();

        return true;
    }

    /**
     * Attempt delete slip
     */

    protected function _attempt_delete_slip($se_bank_slip)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->bankSlipModel->update($se_bank_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($se_bank_slip, $action)
    {
        $current_status = $se_bank_slip['status'];

        $where = ['id' => $se_bank_slip['id']];

        $values = [
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        switch ($action) {
            case 'pending':
                $values['status'] = 'pending';
                break;
            case 'approved':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'approved';
                break;
            case 'disapproved':
                $values['disapproved_by'] = $this->requested_by;
                $values['disapproved_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'disapproved';
                break;
            case 'print':
                $values['printed_by'] = $this->requested_by;
                $values['printed_on'] = date('Y-m-d H:i:s');
                $values['status'] = 'printed';
                break;
            case 'completed':
                $values['completed_by'] = $this->requested_by;
                $values['completed_on'] = date('Y-m-d H:i:s');
                $values['status']       = 'completed';
                break;
            default:
                return false;
        }

        if (!$this->bankSlipModel->update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->bankEntryModel           = new SE_bank_entry();
        $this->bankSlipModel            = new SE_bank_slip();
        $this->bankModel                = model('App\Models\Bank');
        $this->suppliesExpenseModel     = model('App\Models\Supplies_expense');
        $this->bankSlipAttachmentModel  = new SE_bank_slip_attachment();
        $this->seReceiveModel           = new Supplies_receive();
        $this->webappResponseModel      = new Webapp_response();
    }
}

<?php

namespace App\Controllers;

use App\Models\SE_cash_entry;
use App\Models\SE_cash_slip;
use App\Models\SE_cash_slip_attachment;
use App\Models\Supplies_receive;
use App\Models\Project_expense;
use App\Models\Webapp_response;

class Se_cash_payments extends MYTController
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
     * Get se_cash_entry
     */
    public function get_entry()
    {
        if (($response = $this->_api_verification('cash_payments', 'get_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_cash_entry_id = $this->request->getVar('entry_id') ? : null;
        $se_cash_entry    = $se_cash_entry_id ? $this->cashEntryModel->get_details_by_id($se_cash_entry_id) : null;
        $se_cash_slip     = $se_cash_entry ? $this->cashSlipModel->get_details_by_id($se_cash_entry[0]['id']) : null;

        if (!$se_cash_entry) {
            $response = $this->failNotFound('No cash invoice found');
        } else {
            $se_cash_entry[0]['se_cash_slip'] = $se_cash_slip;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_cash_entry
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get se_cash_entry
     */
    public function get_slip()
    {
        if (($response = $this->_api_verification('cash_payments', 'get_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_cash_slip_id = $this->request->getVar('slip_id') ? : null;
        $se_cash_slip    = $se_cash_slip_id ? $this->cashSlipModel->get_details_by_id($se_cash_slip_id) : null;
        $cash_entries = $se_cash_slip ? $this->cashEntryModel->get_details_by_slip_id($se_cash_slip[0]['id']) : null;
        $cash_slip_attachments = $cash_entries ? $this->cashSlipAttachmentModel->get_details_by_se_cash_slip_id($se_cash_slip[0]['id']) : null;

        if (!$se_cash_slip) {
            $response = $this->failNotFound('No cash invoice found');
        } else {
            $se_cash_slip[0]['cash_entries'] = $cash_entries;
            $se_cash_slip[0]['cash_slip_attachments'] = $cash_slip_attachments ? $cash_slip_attachments : [];
            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_cash_slip
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

        $se_cash_slip_id = $this->request->getVar('se_cash_slip_id');
        $attachment_id = $this->request->getVar('attachment_id');
        $where = ['id' => $se_cash_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$attachment = $this->cashSlipAttachmentModel->select('', $where, 1)) {
            $response = $this->failNotFound('Cash Slip attachment not found.');
        } elseif (!$this->_attempt_delete_attachments($se_cash_slip_id, $attachment)) {
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
    function _attempt_delete_attachments($se_cash_slip_id, $attachment = null)
    {
        $where = [
            'se_cash_slip_id' => $se_cash_slip_id,
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

        if (!$this->cashSlipAttachmentModel->custom_update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Get all se_cash_entry
     */
    public function get_all_entry()
    {
        if (($response = $this->_api_verification('cash_payments', 'get_all_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $cash_entries = $this->cashEntryModel->get_all_entry();

        if (!$cash_entries) {
            $response = $this->failNotFound('No se_cash_entry found');
        } else {
            foreach($cash_entries as $key => $se_cash_entry) {
                $se_cash_slip = $this->cashSlipModel->get_details_by_id($se_cash_entry['se_cash_slip_id']);
                $cash_entries[$key]['se_cash_slip'] = $se_cash_slip;
            }

            $response = $this->respond([
                'data'   => $cash_entries,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all se_cash_entry
     */
    public function get_all_slip()
    {
        if (($response = $this->_api_verification('cash_payments', 'get_all_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_cash_slips = $this->cashSlipModel->get_all_slip();

        if (!$se_cash_slips) {
            $response = $this->failNotFound('No se_cash_entry found');
        } else {
            foreach($se_cash_slips as $key => $se_cash_slip) {
                $cash_entries = $this->cashEntryModel->get_details_by_slip_id($se_cash_slip['id']);
                $se_cash_slips[$key]['cash_entries'] = $cash_entries;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_cash_slips
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create se_cash_entry
     */
    public function create()
    {
        if (($response = $this->_api_verification('cash_payments', 'create')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_cash_slip_id = $this->_attempt_create_slip()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create slip.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_entry($se_cash_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate cash entry.', 'status' => 'error']);
        } else if(($this->request->getFileMultiple('attachments')?true:false) && !$this->_upload_attachments($se_cash_slip_id, 'assets/se_cash_payments/')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to upload attachments. Make sure you have the correct file type, and file does not exceed 5 megabytes.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully created slip.', 'status' => 'success', 'slip_id' => $se_cash_slip_id]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update cash slip and se_cash_entry
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('cash_payements', 'update')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_cash_slip_id = $this->request->getVar('se_cash_slip_id');
        $where         = ['id' => $se_cash_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_cash_slip = $this->cashSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense cash slip not found');
        } elseif (!$this->_attempt_update_slip($se_cash_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Supplies expense cash entry updated unsuccessfully', 'status' => 'error']);
        } elseif (!$this->_attempt_update_entry($se_cash_slip_id)) {
            $db->transRollback();
            $response = $this->respond(['response' => 'Supplies expense cash entry updated unsuccessfully', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Supplies expense cash entry updated successfully', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Upload Attachments
     */
    public function _upload_attachments($se_cash_slip_id, $path)
    {
        $files = $this->request->getFileMultiple('attachments');
        $file_path = $path.$se_cash_slip_id;
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
                        'se_cash_slip_id' => $se_cash_slip_id,
                        'file_name' => $file_name,
                        'is_deleted' => 0
                    ];

                    if(empty($this->cashSlipAttachmentModel->select('', $where))) {
                        $file->move($path, $file_name);
                        $data = [
                            'se_cash_slip_id' => $se_cash_slip_id,
                            'file_name' => $file_name,
                            'file_path' => $file_path,
                            'file_url' => base_url($file_path . '/' . $file_name),
                            'mime' => $mime_type,
                            'added_by' => $this->requested_by,
                            'added_on' => date('Y-m-d H:i:s')
                        ];

                        if (!$this->cashSlipAttachmentModel->insert($data)) {
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
     * Delete se_cash_entry
     */
    public function delete_entry($id = '')
    {
        if (($response = $this->_api_verification('cash_payements', 'delete_entry')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_cash_entry_id = $this->request->getVar('se_cash_entry_id');

        $where = ['id' => $se_cash_entry_id, 'is_deleted' => 0];


        if (!$se_cash_entry = $this->cashEntryModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense cash entry not found');
        } elseif (!$this->_attempt_delete_entry($se_cash_entry)) {
            $response = $this->fail(['response' => 'Failed to delete Supplies expense cash entry.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Successfully deleted Supplies expense cash entry.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete cash slip
     */
    public function delete_slip($id = '')
    {
        if (($response = $this->_api_verification('cash_payements', 'delete_slip')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_cash_slip_id = $this->request->getVar('se_cash_slip_id');

        $where = ['id' => $se_cash_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_cash_slip = $this->cashSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense cash slip not found');
        } elseif (!$this->_attempt_delete_slip($se_cash_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete Supplies expense cash slip.', 'status' => 'error']);
        } elseif (!$this->cashEntryModel->delete_by_slip_id($se_cash_slip_id, $this->requested_by)){
            $db->transCommit();
            $response = $this->fail(['response' => 'Successfully deleted Supplies expense cash slip.', 'status' => 'success']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted Supplies expense cash slip.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search se_cash_entry based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('cash_payments', 'search')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $bank_id     = $this->request->getVar('bank_id') ?? null;
        $cash_no    = $this->request->getVar('cash_no') ?? null;
        $cash_date  = $this->request->getVar('cash_date') ?? null;
        $amount      = $this->request->getVar('amount') ?? null;
        $supplier_id = $this->request->getVar('supplier_id') ?? null;
        $payee       = $this->request->getVar('payee') ?? null;
        $particulars = $this->request->getVar('particulars') ?? null;

        if (!$se_cash_slip = $this->cashSlipModel->search($bank_id, $cash_no, $cash_date, $amount, $supplier_id, $payee, $particulars)) {
            $response = $this->failNotFound('No se_cash_entry found');
        } else {
            $cash_entries = $this->cashEntryModel->get_details_by_slip_id($se_cash_slip[0]['id']);
            $se_cash_slip[0]['cash_entries'] = $cash_entries;
            $response = $this->respond(['data' => $se_cash_slip, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record the action of the user
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('cash_payments', 'record_action')) !== true)
            return $response;

        $token = $this->request->getVar('token');
        if (($response = $this->_verify_requester($token)) !== true) {
            return $response;
        }

        $se_cash_slip_id = $this->request->getVar('se_cash_slip_id');
        $action        = $this->request->getVar('action');

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_cash_slip = $this->cashSlipModel->select('', ['id' => $se_cash_slip_id, 'is_deleted' => 0], 1)) {
            $response = $this->respond(['response' => 'Supplies expense cash slip not found']);
        } elseif (!$this->_attempt_record_action($se_cash_slip, $action)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'se_cash_slip status changed successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create cash slip
     */
    protected function _attempt_create_slip()
    {
        $data = [
            'payment_date'    => $this->request->getVar('payment_date'),
            'supplier_id'     => $this->request->getVar('supplier_id'),
            'vendor_id'       => $this->request->getVar('vendor_id'),
            'payee'           => $this->request->getVar('payee'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'particulars'     => $this->request->getVar('particulars'),
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s')
        ];

        if (!$se_cash_slip_id = $this->cashSlipModel->insert($data)) {
            return false;
        }

        return $se_cash_slip_id;
    }

    /**
     * Attempt generate cash entry
     */
    protected function _attempt_generate_entry($se_cash_slip_id)
    {
        $se_ids = $this->request->getVar('se_ids');
        $amounts     = $this->request->getVar('amounts');
        $types = $this->request->getVar('types');

        $total = 0;
        foreach ($se_ids as $key => $se_id) {
            $total += $amounts[$key];
            $type = $types[$key];
            $data = [
                'se_cash_slip_id' => $se_cash_slip_id,
                'se_id'            => $se_id,
                'type'            => $type,
                'amount'           => $amounts[$key],
                'added_by'         => $this->requested_by,
                'added_on'         => date('Y-m-d H:i:s')
            ];

            if (!$this->cashEntryModel->insert($data)) {
                return false;
            }

            if ($type == 'supplies_expense') {
                if ($supplies_expense = $this->suppliesExpenseModel->get_details_by_id($se_id)) {
                    $new_balance = $supplies_expense[0]['balance'] - $amounts[$key];

                    $order_status = ($new_balance <= 0) ? 'complete' : 'incomplete';

                    $supplies_expense_data = [
                        'paid_amount' => $supplies_expense[0]['paid_amount'] + $amounts[$key],
                        'balance'     => $new_balance,
                        'order_status' => $order_status,
                        'updated_on' => date('Y-m-d H:i:s'),
                        'updated_by' => $this->requested_by
                    ];

                    if (!$this->suppliesExpenseModel->update($se_id, $supplies_expense_data)) {
                        return false;
                    }
                } else {
                    var_dump("Supplies expense id {$se_id} not found");
                }
            } elseif ($type == 'project_expense') {
                if ($project_expense = $this->projectExpenseModel->get_details_by_id($se_id)) {

                    $supplies_expense_data = [
                        'paid_amount' => $project_expense[0]['paid_amount'] + $amounts[$key],
                        'balance'     => $new_balance,
                        'updated_on' => date('Y-m-d H:i:s'),
                        'updated_by' => $this->requested_by
                    ];

                    if (!$this->projectExpenseModel->update($se_id, $supplies_expense_data)) {
                        return false;
                    }
                } else {
                    var_dump("Project expense id {$se_id} not found");
                }
            }
        }

        $values = [
            'amount'     => $total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        if (!$this->cashSlipModel->update($se_cash_slip_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt update cash slip
     */
    protected function _attempt_update_slip($se_cash_slip_id)
    {
        $data = [
            'bank_id'         => $this->request->getVar('bank_id'),
            'cash_no'         => $this->request->getVar('cash_no'),
            'cash_date'       => $this->request->getVar('cash_date'),
            'issued_date'     => $this->request->getVar('issued_date'),
            'supplier_id'     => $this->request->getVar('supplier_id'),
            'vendor_id'       => $this->request->getVar('vendor_id'),
            'payee'           => $this->request->getVar('payee'),
            'particulars'     => $this->request->getVar('particulars'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'updated_by'      => $this->requested_by,
            'updated_on'      => date('Y-m-d H:i:s')
        ];

        if (!$this->cashSlipModel->update($se_cash_slip_id, $data)) {
            return false;
        }

        if (!$this->cashSlipAttachmentModel->delete_attachment_by_se_cash_slip_id($se_cash_slip_id, $this->requested_by)) {
            return false;
        } elseif(($this->request->getFileMultiple('attachments')?true:false) && !$this->_upload_attachments($se_cash_slip_id, 'assets/se_cash_payments/')) {
            // $db->transRollback();
            // $response = $this->fail(['response' => 'Failed to upload attachments. Make sure you have the correct file type, and file does not exceed 5 megabytes.', 'status' => 'error']);
            return false;
        }

        return true;
    }

    /**
     * Attempt update cash entry
     */
    protected function _attempt_update_entry($se_cash_slip_id)
    {
        $this->cashEntryModel->delete_by_slip_id($se_cash_slip_id, $this->requested_by);
        if (!$this->_attempt_generate_entry($se_cash_slip_id)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete entry
     */
    protected function _attempt_delete_entry($se_cash_entry)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashEntryModel->update($se_cash_entry['id'], $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        if (!$se_cash_slip = $this->cashSlipModel->select('', ['id' => $se_cash_entry['se_cash_slip_id']], 1)) {
            $db->close();
            return false;
        }

        $values = [
            'amount' => $se_cash_slip['amount'] - $se_cash_entry['amount'],
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashSlipModel->update($se_cash_slip['id'], $values)) {
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

    protected function _attempt_delete_slip($se_cash_slip)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->cashSlipModel->update($se_cash_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($se_cash_slip, $action)
    {
        $current_status = $se_cash_slip['status'];

        $where = ['id' => $se_cash_slip['id']];

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

        if (!$this->cashSlipModel->update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->cashEntryModel      = new SE_cash_entry();
        $this->cashSlipModel       = new SE_cash_slip();
        $this->suppliesExpenseModel = model('App\Models\Supplies_expense');
        $this->cashSlipAttachmentModel       = new SE_cash_slip_attachment();
        $this->seReceiveModel      = new Supplies_receive();
        $this->projectExpenseModel = new Project_expense();
        $this->webappResponseModel = new Webapp_response();
    }
}

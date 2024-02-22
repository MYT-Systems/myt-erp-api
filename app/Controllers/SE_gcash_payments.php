<?php

namespace App\Controllers;

use App\Models\SE_gcash_entry;
use App\Models\SE_gcash_slip;
use App\Models\SE_gcash_slip_attachment;
use App\Models\Supplies_receive;
use App\Models\Webapp_response;

class Se_gcash_payments extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get se_gcash_entry
     */
    public function get_entry()
    {
        if (($response = $this->_api_verification('gcash_payments', 'get_entry')) !== true)
            return $response;

        $se_gcash_entry_id = $this->request->getVar('entry_id') ? : null;
        $se_gcash_entry    = $se_gcash_entry_id ? $this->gcashEntryModel->get_details_by_id($se_gcash_entry_id) : null;
        $se_gcash_slip     = $se_gcash_entry ? $this->gcashSlipModel->get_details_by_id($se_gcash_entry[0]['id']) : null;

        if (!$se_gcash_entry) {
            $response = $this->failNotFound('No gcash invoice found');
        } else {
            $se_gcash_entry[0]['se_gcash_slip'] = $se_gcash_slip;
            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_gcash_entry
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get se_gcash_entry
     */
    public function get_slip()
    {
        if (($response = $this->_api_verification('gcash_payments', 'get_slip')) !== true)
            return $response;

        $se_gcash_slip_id = $this->request->getVar('slip_id') ? : null;
        $se_gcash_slip    = $se_gcash_slip_id ? $this->gcashSlipModel->get_details_by_id($se_gcash_slip_id) : null;
        $gcash_entries = $se_gcash_slip ? $this->gcashEntryModel->get_details_by_slip_id($se_gcash_slip[0]['id']) : null;
        $gcash_slip_attachments = $gcash_entries ? $this->gcashSlipAttachmentModel->get_details_by_se_gcash_slip_id($se_gcash_slip[0]['id']) : null;

        if (!$se_gcash_slip) {
            $response = $this->failNotFound('No gcash invoice found');
        } else {
            $se_gcash_slip[0]['gcash_entries'] = $gcash_entries;
            $se_gcash_slip[0]['gcash_slip_attachments'] = $gcash_slip_attachments ? $gcash_slip_attachments : [];
            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_gcash_slip
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

        $se_gcash_slip_id = $this->request->getVar('se_gcash_slip_id');
        $attachment_id = $this->request->getVar('attachment_id');
        $where = ['id' => $se_gcash_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$attachment = $this->gcashSlipAttachmentModel->select('', $where, 1)) {
            $response = $this->failNotFound('gcash Slip attachment not found.');
        } elseif (!$this->_attempt_delete_attachments($se_gcash_slip_id, $attachment)) {
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
    function _attempt_delete_attachments($se_gcash_slip_id, $attachment = null)
    {
        $where = [
            'se_gcash_slip_id' => $se_gcash_slip_id,
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

        if (!$this->gcashSlipAttachmentModel->custom_update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Get all se_gcash_entry
     */
    public function get_all_entry()
    {
        if (($response = $this->_api_verification('gcash_payments', 'get_all_entry')) !== true)
            return $response;

        $gcash_entries = $this->gcashEntryModel->get_all_entry();

        if (!$gcash_entries) {
            $response = $this->failNotFound('No se_gcash_entry found');
        } else {
            foreach($gcash_entries as $key => $se_gcash_entry) {
                $se_gcash_slip = $this->gcashSlipModel->get_details_by_id($se_gcash_entry['se_gcash_slip_id']);
                $gcash_entries[$key]['se_gcash_slip'] = $se_gcash_slip;
            }

            $response = $this->respond([
                'data'   => $gcash_entries,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all se_gcash_entry
     */
    public function get_all_slip()
    {
        if (($response = $this->_api_verification('gcash_payments', 'get_all_slip')) !== true)
            return $response;

        $se_gcash_slips = $this->gcashSlipModel->get_all_slip();

        if (!$se_gcash_slips) {
            $response = $this->failNotFound('No se_gcash_entry found');
        } else {
            foreach($se_gcash_slips as $key => $se_gcash_slip) {
                $gcash_entries = $this->gcashEntryModel->get_details_by_slip_id($se_gcash_slip['id']);
                $se_gcash_slips[$key]['gcash_entries'] = $gcash_entries;
            }

            $response = $this->respond([
                'status' => 'success',
                'data'   => $se_gcash_slips
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Create se_gcash_entry
     */
    public function create()
    {
        if (($response = $this->_api_verification('gcash_payments', 'create')) !== true)
            return $response;

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_gcash_slip_id = $this->_attempt_create_slip()) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to create slip.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_entry($se_gcash_slip_id)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to generate gcash entry.', 'status' => 'error']);
        } else if(($this->request->getFileMultiple('attachments')?true:false) && !$this->_upload_attachments($se_gcash_slip_id, 'assets/se_gcash_payments/')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to upload attachments. Make sure you have the correct file type, and file does not exceed 5 megabytes.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully created slip.', 'status' => 'success', 'slip_id' => $se_gcash_slip_id]);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update gcash slip and se_gcash_entry
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('gcash_payements', 'update')) !== true)
            return $response;

        $se_gcash_slip_id = $this->request->getVar('se_gcash_slip_id');
        $where         = ['id' => $se_gcash_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_gcash_slip = $this->gcashSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense gcash slip not found');
        } elseif (!$this->_attempt_update_slip($se_gcash_slip_id)) {
            $db->transRollback();
            $response = $this->respond(['response' => 'Supplies expense gcashentry updated unsuccessfully']);
        } elseif (!$this->_attempt_update_entry($se_gcash_slip_id)) {
            $db->transRollback();
            $response = $this->respond(['response' => 'Supplies expense gcash entry updated unsuccessfully']);
        } else if(($this->request->getFileMultiple('attachments')?true:false) && !$this->_upload_attachments($se_gcash_slip_id, 'assets/se_gcash_payments/')) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to upload attachments. Make sure you have the correct file type, and file does not exceed 5 megabytes.', 'status' => 'error']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Supplies expense gcash entry updated successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Upload Attachments
     */
    public function _upload_attachments($se_gcash_slip_id, $path)
    {
        $files = $this->request->getFileMultiple('attachments');
        $file_path = $path.$se_gcash_slip_id;
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

                $file_name = $file->getName();
                $max_file_size = 5 * 1024 * 1024; // 5 MB in bytes
            
                if ($file->getSize() > $max_file_size) {
                    return false;
                }

                if ($file->isValid() && !$file->hasMoved()) {
                    $file_name = $file->getName();
                    $mime_type = $file->getMimeType();

                    $where = [
                        'se_gcash_slip_id' => $se_gcash_slip_id,
                        'file_name' => $file_name,
                        'is_deleted' => 0
                    ];

                    if(empty($this->gcashSlipAttachmentModel->select('', $where))) {
                        $file->move($path, $file_name);
                        $data = [
                            'se_gcash_slip_id' => $se_gcash_slip_id,
                            'file_name' => $file_name,
                            'file_path' => $file_path,
                            'file_url' => base_url($file_path . '/' . $file_name),
                            'mime' => $mime_type,
                            'added_by' => $this->requested_by,
                            'added_on' => date('Y-m-d H:i:s')
                        ];

                        if (!$this->gcashSlipAttachmentModel->insert($data)) {
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
     * Delete se_gcash_entry
     */
    public function delete_entry($id = '')
    {
        if (($response = $this->_api_verification('gcash_payements', 'delete_entry')) !== true)
            return $response;

        $se_gcash_entry_id = $this->request->getVar('se_gcash_entry_id');

        $where = ['id' => $se_gcash_entry_id, 'is_deleted' => 0];


        if (!$se_gcash_entry = $this->gcashEntryModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense gcash entry not found');
        } elseif (!$this->_attempt_delete_entry($se_gcash_entry)) {
            $response = $this->fail(['response' => 'Failed to delete Supplies expense gcash entry.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Successfully deleted Supplies expense gcash entry.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete gcash slip
     */
    public function delete_slip($id = '')
    {
        if (($response = $this->_api_verification('gcash_payements', 'delete_slip')) !== true)
            return $response;

        $se_gcash_slip_id = $this->request->getVar('se_gcash_slip_id');

        $where = ['id' => $se_gcash_slip_id, 'is_deleted' => 0];

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_gcash_slip = $this->gcashSlipModel->select('', $where, 1)) {
            $response = $this->failNotFound('Supplies expense gcash slip not found');
        } elseif (!$this->_attempt_delete_slip($se_gcash_slip)) {
            $db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete Supplies expense gcash slip.', 'status' => 'error']);
        } elseif (!$this->gcashEntryModel->delete_by_slip_id($se_gcash_slip_id, $this->requested_by)){
            $db->transCommit();
            $response = $this->fail(['response' => 'Successfully deleted Supplies expense gcash slip.', 'status' => 'success']);
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'Successfully deleted Supplies expense gcash slip.', 'status' => 'success']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search se_gcash_entry based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('gcash_payments', 'search')) !== true)
            return $response;

        $bank_id     = $this->request->getVar('bank_id') ?? null;
        $gcash_no    = $this->request->getVar('gcash_no') ?? null;
        $gcash_date  = $this->request->getVar('gcash_date') ?? null;
        $amount      = $this->request->getVar('amount') ?? null;
        $supplier_id = $this->request->getVar('supplier_id') ?? null;
        $payee       = $this->request->getVar('payee') ?? null;
        $particulars = $this->request->getVar('particulars') ?? null;

        if (!$se_gcash_slip = $this->gcashSlipModel->search($bank_id, $gcash_no, $gcash_date, $amount, $supplier_id, $payee, $particulars)) {
            $response = $this->failNotFound('No se_gcash_entry found');
        } else {
            $gcash_entries = $this->gcashEntryModel->get_details_by_slip_id($se_gcash_slip[0]['id']);
            $se_gcash_slip[0]['gcash_entries'] = $gcash_entries;
            $response = $this->respond(['data' => $se_gcash_slip, 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record the action of the user
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('gcash_payments', 'record_action')) !== true)
            return $response;

        $se_gcash_slip_id = $this->request->getVar('se_gcash_slip_id');
        $action        = $this->request->getVar('action');

        $db = \Config\Database::connect();
        $db->transBegin();

        if (!$se_gcash_slip = $this->gcashSlipModel->select('', ['id' => $se_gcash_slip_id, 'is_deleted' => 0], 1)) {
            $response = $this->respond(['response' => 'Supplies expense gcash slip not found']);
        } elseif (!$this->_attempt_record_action($se_gcash_slip, $action)) {
            $db->transRollback();
            $response = $this->fail('Server error');
        } else {
            $db->transCommit();
            $response = $this->respond(['response' => 'se_gcash_slip status changed successfully']);
        }

        $db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create gcash slip
     */
    protected function _attempt_create_slip()
    {
        $data = [
            'payment_date'    => $this->request->getVar('payment_date'),
            'reference_no'    => $this->request->getVar('reference_no'),
            'account_name'    => $this->request->getVar('account_name'),
            'account_no'      => $this->request->getVar('account_no'),
            'supplier_id'     => $this->request->getVar('supplier_id'),
            'vendor_id'       => $this->request->getVar('vendor_id'),
            'payee'           => $this->request->getVar('payee'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'particulars'     => $this->request->getVar('particulars'),
            'added_by'        => $this->requested_by,
            'added_on'        => date('Y-m-d H:i:s')
        ];

        if (!$se_gcash_slip_id = $this->gcashSlipModel->insert($data)) {
            return false;
        }

        return $se_gcash_slip_id;
    }

    /**
     * Attempt generate gcash entry
     */
    protected function _attempt_generate_entry($se_gcash_slip_id)
    {
        $se_ids = $this->request->getVar('se_ids');
        $amounts     = $this->request->getVar('amounts');

        $total = 0;
        foreach ($se_ids as $key => $se_id) {
            $total += $amounts[$key];
            $data = [
                'se_gcash_slip_id' => $se_gcash_slip_id,
                'se_id'            => $se_id,
                'amount'           => $amounts[$key],
                'added_by'         => $this->requested_by,
                'added_on'         => date('Y-m-d H:i:s')
            ];

            if (!$this->gcashEntryModel->insert($data)) {
                return false;
            }

            if ($receive = $this->seReceiveModel->get_details_by_id($se_id)) {
                $receive_data = [
                    'paid_amount' => $receive[0]['paid_amount'] + $amounts[$key],
                    'balance'     => $receive[0]['balance'] - $amounts[$key],
                    'updated_on' => date('Y-m-d H:i:s'),
                    'updated_by' => $this->requested_by
                ];

                if (!$this->seReceiveModel->update($se_id, $receive_data)) {
                    return false;
                }
            } else {
                var_dump("Supplies expense receive id {$se_id} not found");
            }
        }

        $values = [
            'amount'     => $total,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        if (!$this->gcashSlipModel->update($se_gcash_slip_id, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt update gcash slip
     */
    protected function _attempt_update_slip($se_gcash_slip_id)
    {
        $data = [
            'bank_id'         => $this->request->getVar('bank_id'),
            'gcash_no'         => $this->request->getVar('gcash_no'),
            'gcash_date'       => $this->request->getVar('gcash_date'),
            'issued_date'     => $this->request->getVar('issued_date'),
            'supplier_id'     => $this->request->getVar('supplier_id'),
            'vendor_id'       => $this->request->getVar('vendor_id'),
            'payee'           => $this->request->getVar('payee'),
            'particulars'     => $this->request->getVar('particulars'),
            'acknowledged_by' => $this->request->getVar('acknowledged_by'),
            'updated_by'      => $this->requested_by,
            'updated_on'      => date('Y-m-d H:i:s')
        ];

        return $this->gcashSlipModel->update($se_gcash_slip_id, $data);
    }

    /**
     * Attempt update gcash entry
     */
    protected function _attempt_update_entry($se_gcash_slip_id)
    {
        $this->gcashEntryModel->delete_by_slip_id($se_gcash_slip_id, $this->requested_by);
        if (!$this->_attempt_generate_entry($se_gcash_slip_id)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt delete entry
     */
    protected function _attempt_delete_entry($se_gcash_entry)
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->gcashEntryModel->update($se_gcash_entry['id'], $values)) {
            $db->transRollback();
            $db->close();
            return false;
        }

        if (!$se_gcash_slip = $this->gcashSlipModel->select('', ['id' => $se_gcash_entry['se_gcash_slip_id']], 1)) {
            $db->close();
            return false;
        }

        $values = [
            'amount' => $se_gcash_slip['amount'] - $se_gcash_entry['amount'],
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->gcashSlipModel->update($se_gcash_slip['id'], $values)) {
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

    protected function _attempt_delete_slip($se_gcash_slip)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->gcashSlipModel->update($se_gcash_slip['id'], $values)) {
            return false;
        }

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($se_gcash_slip, $action)
    {
        $current_status = $se_gcash_slip['status'];

        $where = ['id' => $se_gcash_slip['id']];

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

        if (!$this->gcashSlipModel->update($where, $values)) {
            return false;
        }

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->gcashEntryModel      = new SE_gcash_entry();
        $this->gcashSlipModel       = new SE_gcash_slip();
        $this->gcashSlipAttachmentModel       = new SE_gcash_slip_attachment();
        $this->seReceiveModel      = new Supplies_receive();
        $this->webappResponseModel = new Webapp_response();
    }
}

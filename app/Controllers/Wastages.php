<?php

namespace App\Controllers;

class Wastages extends MYTController
{

    public function __construct()
    {
        // Headers
        $this->api_key = $_SERVER['HTTP_API_KEY'];
        $this->user_key = $_SERVER['HTTP_USER_KEY'];

        $this->_load_essentials();
    }

    /**
     * Get wastage
     */
    public function get_wastage()
    {
        if (($response = $this->_api_verification('wastages', 'get_wastage')) !== true)
            return $response;

        $wastage_id    = $this->request->getVar('wastage_id') ? : null;
        $wastage       = $wastage_id ? $this->wastageModel->get_details_by_id($wastage_id) : null;
        $wastage_item  = $wastage_id ? $this->wastageItemModel->get_all_wastage_item_by_wastage_id($wastage_id) : null;

        if (!$wastage) {
            $response = $this->failNotFound('No wastage found');
        } else {
            $wastage[0]['wastage_item'] = $wastage_item;
            $response = $this->respond([
                'data'   => $wastage,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Get all wastages
     */
    public function get_all_wastage()
    {
        if (($response = $this->_api_verification('wastages', 'get_all_wastage')) !== true)
            return $response;

        $wastages = $this->wastageModel->get_all_wastage();

        if (!$wastages) {
            $response = $this->failNotFound('No wastage found');
        } else {
            foreach ($wastages as $key => $wastage) {
                $wastages[$key]['wastage_item'] = $this->wastageItemModel->get_all_wastage_item_by_wastage_id($wastage['id']);
            }

            $response = $this->respond([
                'data'   => $wastages,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Insert wastage items
     */
    public function insert_wastage_items()
    {
        if (($response = $this->_api_verification('wastages', 'insert_wastage_items')) !== true)
            return $response;

        $data = $this->request->getVar();
        unset($data['requester']);
        unset($data['token']);
        $filename = $this->_write_json('wastage_items', $data);

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

    protected function _attempt_bulk_create($filename)
    {
        $upload_path = FCPATH . 'public/wastage_items/' . $filename;
        $json = file_get_contents($upload_path);
        $wastage_parent = json_decode($json);
        $wastage_parent = (array) $wastage_parent;

        $unsaved_wastage_items = [];

        $current_date = date("Y-m-d");
        $where = [
            'branch_id' => $wastage_parent['branch_id'],
            'wastage_date' => $current_date,
            'is_deleted' => 0
        ];

        $wastage_id = null;

        $this->db = \Config\Database::connect();

        foreach ($wastage_parent['wastage_items'] as $wastage_item) {
            $wastage_item = json_decode($wastage_item);
            $wastage_item = (array) $wastage_item;
            
            $this->db->transBegin();

            if (!$wastage_id AND
                !$wastage = $this->wastageModel->select('', $where, 1) AND
                !$wastage_id = $this->_attempt_create_from_sync($wastage_parent)
            ) {
                $this->errorMessage = $this->db->error()['message'];
            }

            $wastage_id = $wastage ? $wastage['id'] : $wastage_id;

            if (!$this->_attempt_generate_wastage_item_from_sync($wastage_id, $wastage_item)) {
                $this->db->transRollback();
                $unsaved_wastage_items[] = $this->wastage;
                continue;
            }

            $this->db->transCommit();
            $this->wastage = null;
        }

        if ($unsaved_wastage_items) {
            $wastage_parent['wastage_items'] = $unsaved_wastage_items;
            $write_response = $this->_write_json('wastage_items', $wastage_parent);
            
            $old_file_path = FCPATH . 'public/wastage_items/' . $filename;
            unlink($old_file_path);
        
            return false;
        }
        
        $old_file_path = FCPATH . 'public/wastage_items/' . $filename;
        unlink($old_file_path);

        return true;
    }

    /**
     * Attempt create wastage
     */
    private function _attempt_create_from_sync($wastage_parent)
    {
        $values = [
            'branch_id'    => array_key_exists('branch_id', $wastage_parent) ? $wastage_parent['branch_id'] : null,
            'wastage_date' => date("Y-m-d"),
            'description'  => array_key_exists('description', $wastage_parent) ? $wastage_parent['description'] : null,
            'remarks'      => array_key_exists('remarks', $wastage_parent) ? $wastage_parent['remarks'] : null,
            'added_by'     => $this->requested_by,
            'added_on'     => date('Y-m-d H:i:s')
        ];

        if (!$wastage_id = $this->wastageModel->insert($values))
            return false;

        return $wastage_id;
    }

    /**
     * Attempt generate wastage item
     */
    protected function _attempt_generate_wastage_item_from_sync($wastage_id, $wastage_item)
    {
        $item_id = array_key_exists('item_id', $wastage_item) ? $wastage_item['item_id'] : null;
        $values = [
            'wastage_id' => $wastage_id,
            'name' => array_key_exists('item_name', $wastage_item) ? $wastage_item['item_name'] : null,
            'item_id' => $item_id,
            'unit' => array_key_exists('unit', $wastage_item) ? $wastage_item['unit'] : null,
            'qty' => array_key_exists('quantity', $wastage_item) ? $wastage_item['quantity'] : null,
            'remarks' => array_key_exists('remarks', $wastage_item) ? $wastage_item['remarks'] : null,
            'wasted_by' => array_key_exists('wasted_by', $wastage_item) ? $wastage_item['wasted_by'] : null,
            'reason' => array_key_exists('reason', $wastage_item) ? $wastage_item['reason'] : null,
            'status' => ($item_id === SAGO_ITEM_ID ? 'approved' : 'pending'),
            'status_change_by' => ($item_id === SAGO_ITEM_ID ? $this->requested_by : null),
            'status_change_on' => ($item_id === SAGO_ITEM_ID ? date("Y-m-d H:i:s") : null),
            'added_by' => $this->requested_by,
            'added_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->wastageItemModel->insert($values)) {
            $this->errorMessage = $this->db->error()['message'];
            return false;
        }
        
        return true;
    }

    /**
     * Create wastage
     */
    public function create()
    {
        if (($response = $this->_api_verification('wastages', 'create')) !== true)
            return $response;

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$wastage_id = $this->_attempt_create()) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to create wastage.', 'status' => 'error']);
        } elseif (!$this->_attempt_generate_wastage_item($wastage_id)) {
            $this->db->transRollback();
            $response = $this->fail($this->errorMessage);
        } else {     
            $this->db->transCommit();
            $response = $this->respond([
                'response'   => 'wastage created successfully.',
                'status'     => 'success',
                'wastage_id' => $wastage_id
            ]);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Update wastage
     */
    public function update($id = null)
    {
        if (($response = $this->_api_verification('wastages', 'update')) !== true)
            return $response;

        $where = [
            'id' => $this->request->getVar('wastage_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$wastage = $this->wastageModel->select('', $where, 1))
            $response = $this->failNotFound('wastage not found');
        elseif (!$this->_attempt_update($wastage['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to update wastage.', 'status' => 'error']);
        } elseif (!$this->_attempt_update_wastage_item($wastage['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to update wastage item.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'wastage updated successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Delete wastages
     */
    public function delete($id = '')
    {
        if (($response = $this->_api_verification('wastages', 'delete')) !== true)
            return $response;

        $where = [
            'id'  => $this->request->getVar('wastage_id'), 
            'is_deleted' => 0
        ];

        $this->db = \Config\Database::connect();
        $this->db->transBegin();

        if (!$wastage = $this->wastageModel->select('', $where, 1)) {
            $response = $this->failNotFound('wastage not found');
        } elseif (!$this->_attempt_delete($wastage['id'])) {
            $this->db->transRollback();
            $response = $this->fail(['response' => 'Failed to delete wastage.', 'status' => 'error']);
        } else {
            $this->db->transCommit();
            $response = $this->respond(['response' => 'wastage deleted successfully.', 'status' => 'success']);
        }

        $this->db->close();
        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Search wastages based on parameters passed
     */
    public function search()
    {
        if (($response = $this->_api_verification('wastages', 'search')) !== true)
            return $response;

        $branch_id         = $this->request->getVar('branch_id') ? : null;
        $item_id           = $this->request->getVar('item_id') ? : null;
        $wastage_date_from = $this->request->getVar('wastage_date_from') ? : null;
        $wastage_date_from = date("Y-m-d", strtotime($wastage_date_from));
        $wastage_date_to   = $this->request->getVar('wastage_date_to') ? : null;
        $wastage_date_to   = date("Y-m-d", strtotime($wastage_date_to));
        $description       = $this->request->getVar('description') ? : null;
        $remarks           = $this->request->getVar('remarks') ? : null;
        $branch_name       = $this->request->getVar('branch_name') ? : null;
        $is_mobile         = $this->request->getVar('is_mobile') ? : false;
        $summary           = ['total_quantity' => 0];

        if (!$wastages = $this->wastageModel->search($branch_id, $wastage_date_from, $wastage_date_to, $description, $remarks, $branch_name)) {
            $response = $this->failNotFound('No wastage found');
        } else {
            $new_wastage_format = [];
            foreach ($wastages as $index => $wastage) {
                $wastages[$index]['wastage_items'] = $this->wastageItemModel->get_all_wastage_item_by_wastage_id($wastage['id'], $item_id);
                foreach ($wastages[$index]['wastage_items'] as $wastage_index => $wastage_item) {
                    
                    $wastages[$index]['wastage_items'][$wastage_index]['branch_id'] = $wastage['branch_id'];

                    $where = ['item_id' => $wastage_item['item_id']];
                    if (!$item_unit_details = $this->itemUnitModel->select('', $where, 1)) {
                        $this->fail('Item unit not found for item ' . $wastage_item['item_id']);
                        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
                        return $response;
                    }

                    $item_qty = $wastage_item['qty'];

                    if ($item_unit_details['inventory_unit'] != $wastage_item['unit']) {
                        $item_qty = ($item_qty/$item_unit_details['breakdown_value']) * $item_unit_details['inventory_value'];
                        $item_qty = round($item_qty, 2);
                    }

                    $summary['total_quantity'] += $item_qty;
                }

                if ($is_mobile) {
                    if (array_key_exists($wastage['branch_id'], $new_wastage_format)) {
                        $new_wastage_format[$wastage['branch_id']]['wastages'][] = $wastages[$index];
                    } else {
                        $new_wastage_format[$wastage['branch_id']] = [
                            'branch_name' => $wastage['branch_name'],
                            'branch_id' => $wastage['branch_id'],
                            'wastages' => [$wastages[$index]]
                        ];
                    }
                }
            }

            $response = $this->respond([
                'summary' => $summary,
                'data' => $is_mobile ? array_values($new_wastage_format) : $wastages,
                'status' => 'success'
            ]);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    /**
     * Record Action
     */
    public function record_action()
    {
        if (($response = $this->_api_verification('wastages', 'record_action')) !== true)
            return $response;

        $wastage_id = $this->request->getVar('wastage_id');
        $action     = $this->request->getVar('action');

        if (!$wastage = $this->wastageModel->get_details_by_id($wastage_id)) {
            $response = $this->failNotFound('wastage not found');
        } elseif (!$this->_attempt_record_action($wastage_id, $action)) {
            $response = $this->fail(['response' => 'Failed to record action.', 'status' => 'error']);
        } else {
            $response = $this->respond(['response' => 'Action recorded successfully.', 'status' => 'success']);
        }

        $this->webappResponseModel->record_response($this->webapp_log_id, $response);
        return $response;
    }

    // ------------------------------------------------------------------------
    // Private Functions
    // ------------------------------------------------------------------------

    /**
     * Attempt create wastage
     */
    private function _attempt_create()
    {
        $values = [
            'branch_id'    => $this->request->getVar('branch_id'),
            'wastage_date' => date("Y-m-d"),
            'description'  => $this->request->getVar('description'),
            'remarks'      => $this->request->getVar('remarks'),
            'added_by'     => $this->requested_by,
            'added_on'     => date('Y-m-d H:i:s'),
        ];

        if (!$wastage_id = $this->wastageModel->insert($values))
            return false;

        return $wastage_id;
    }

    /**
     * Attempt generate wastage item
     */
    protected function _attempt_generate_wastage_item($wastage_id)
    {
        $names      = $this->request->getVar('names') ?? [];
        $item_ids   = $this->request->getVar('item_ids') ?? [];
        $units      = $this->request->getVar('units') ?? [];
        $quantities = $this->request->getVar('quantities') ?? [];
        $remarks    = $this->request->getVar('remarks') ?? [];
        $reasons    = $this->request->getVar('reasons') ?? [];
        $wasted_by  = $this->request->getVar('wasted_by') ?? [];

        $values = [
            'wastage_id' => $wastage_id,
            'added_by'   => $this->requested_by,
            'added_on'   => date('Y-m-d H:i:s'),
        ];

        foreach ($names as $key => $name) {
            $values['name'] = $name;
            $values['item_id'] = $item_ids[$key];
            $values['unit'] = $units[$key];
            $values['qty'] = $quantities[$key];
            $values['remarks'] = $remarkss[$key];
            $values['wasted_by'] = $wasted_by[$key];
            $values['reason'] = $reasons[$key];

            if (!$this->wastageItemModel->insert($values)) {
                $this->errorMessage = $this->db->error()['message'];
                return false;
            }
        }
        
        return true;
    }


    /**
     * Attempt update
     */
    protected function _attempt_update($wastage_id)
    {
        $values = [
            'branch_id'    => $this->request->getVar('branch_id'),
            'wastage_date' => $this->request->getVar('wastage_date'),
            'description'  => $this->request->getVar('description'),
            'remarks'      => $this->request->getVar('remarks'),
            'updated_by'   => $this->requested_by,
            'updated_on'   => date('Y-m-d H:i:s')
        ];

        if (!$this->wastageModel->update($wastage_id, $values))
            return false;
        
        return true;
    }

    /**
     * Attempt update wastage item
     */
    protected function _attempt_update_wastage_item($wastage_id)
    {
        if (!$this->wastageItemModel->delete_wastage_item_by_wastage_id($wastage_id, $this->requested_by, $this->db))
            return false;
        
        if (!$this->_attempt_generate_wastage_item($wastage_id))
            return false;
        
        return true;
    }

    /**
     * Attempt delete
     */
    protected function _attempt_delete($wastage_id)
    {
        $values = [
            'is_deleted' => 1,
            'updated_by' => $this->requested_by,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        if (!$this->wastageModel->update($wastage_id, $values))
            return false;

        if (!$this->wastageItemModel->delete_wastage_item_by_wastage_id($wastage_id, $this->requested_by))
            return false;

        return true;
    }

    /**
     * Attempt record action
     */
    protected function _attempt_record_action($wastage_id, $action)
    {
        $values = [
            'action'      => $action,
            'updated_by'  => $this->requested_by,
            'updated_on'  => date('Y-m-d H:i:s')
        ];

        switch ($action) {
            case 'approved':
                $values['approved_by'] = $this->requested_by;
                $values['approved_on'] = date('Y-m-d H:i:s');
                break;
            case 'rejected':
                $values['rejected_by'] = $this->requested_by;
                $values['rejected_on'] = date('Y-m-d H:i:s');
                break;
        }

        if (!$this->wastageModel->update($wastage_id, $values))
            return false;

        return true;
    }

    /**
     * Load all essential models and helpers
     */
    protected function _load_essentials()
    {
        $this->itemUnitModel          = model('App\Models\Item_unit');
        $this->wastageModel           = model('App\Models\Wastage');
        $this->wastageItemModel       = model('App\Models\Wastage_item');
        $this->webappResponseModel    = model('App\Models\Webapp_response');

        $this->wastage = null;
    }
}

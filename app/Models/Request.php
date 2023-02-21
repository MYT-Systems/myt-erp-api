<?php

namespace App\Models;

class Request extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_from',
        'branch_to',
        'request_number',
        'transfer_number',
        'request_date',
        'remarks',
        'grand_total',
        'status',
        'encoded_by',
        'delivery_date',
        'completed_by',
        'completed_on',
        'rejection_remarks',
        'rejected_by',
        'rejected_on',
        'approved_by',
        'approved_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'request';
    }

    /**
     * Get request details by ID
     */
    public function get_details_by_id($request_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT
    source_branch.name AS branch_from_name, 
    target_branch.name AS branch_to_name, 
    CONCAT(encoder.first_name, " ", encoder.last_name) AS encoded_by_name, 
    CONCAT(approver.first_name, " ", approver.last_name) AS approved_by_name, 
    request.*
FROM request
LEFT JOIN branch AS source_branch ON source_branch.id = request.branch_from
LEFT JOIN branch AS target_branch ON target_branch.id = request.branch_to
LEFT JOIN employee AS encoder ON encoder.id = request.encoded_by
LEFT JOIN user AS approver ON approver.id = request.approved_by
WHERE request.is_deleted = 0
EOT;
        $binds = [];
        if ($request_id) {
            $sql .= " AND request.id = ?";
            $binds[] = $request_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get request details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM request
WHERE request.is_deleted = 0
EOT;
        $binds = [];
        if ($status) {
            $sql .= " AND request.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all requests
     */
    public function get_all_request()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM request
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get requests based on request name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($branch_from = null, $branch_to = null, $request_number = null, $transfer_number = null, $request_date_from = null, $request_date_to = null, $remarks = null, $grand_total = null, $status = null, $limit_by = null)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT 
    source_branch.name AS branch_from_name, 
    target_branch.name AS branch_to_name, 
    CONCAT(encoder.first_name, " ", encoder.last_name) AS encoded_by_name, 
    CONCAT(approver.first_name, " ", approver.last_name) AS approved_by_name,
    CONCAT(rejecter.first_name, " ", rejecter.last_name) AS rejected_by_name,
    request.*
FROM request
LEFT JOIN branch AS source_branch ON source_branch.id = request.branch_from
LEFT JOIN branch AS target_branch ON target_branch.id = request.branch_to
LEFT JOIN employee AS encoder ON encoder.id = request.encoded_by
LEFT JOIN user AS approver ON approver.id = request.approved_by
LEFT JOIN user AS rejecter ON rejecter.id = request.rejected_by
WHERE request.is_deleted = 0
EOT;
        $binds = [];
        if ($branch_from) {
            $branch_from = explode(',', $branch_from);
            $sql .= " AND request.branch_from IN (";
            foreach ($branch_from as $key => $value) {
                $sql .= "?,";
                $binds[] = $value;
            }
            $sql = rtrim($sql, ',');
            $sql .= ")";
        }
        
        if ($branch_to) {
            $branch_to = explode(',', $branch_to);
            $sql .= " AND request.branch_to IN (";
            foreach ($branch_to as $key => $value) {
                $sql .= "?,";
                $binds[] = $value;
            }
            $sql = rtrim($sql, ',');
            $sql .= ")";
        }
        
        if ($request_number) {
            $sql .= " AND request.id LIKE ?";
            $binds[] = $request_number . "%";
        }
        
        if ($transfer_number) {
            $sql .= " AND request.transfer_number = ?";
            $binds[] = $transfer_number;
        }
        
        if ($request_date_from AND $request_date_to) {
            $sql .= " AND request.request_date BETWEEN ? AND ?";
            $binds[] = $request_date_from;
            $binds[] = $request_date_to;
        }
        
        if ($remarks) {
            $sql .= " AND request.remarks = ?";
            $binds[] = $remarks;
        }
        
        if ($grand_total) {
            $sql .= " AND request.grand_total = ?";
            $binds[] = $grand_total;
        }
        
        if ($status) {
            $status = explode(',', $status);
            $sql .= " AND request.status IN (";
            foreach ($status as $key => $value) {
                $sql .= "?,";
                $binds[] = $value;
            }
            $sql = rtrim($sql, ',');
            $sql .= ")";
        }

        $sql .= " ORDER BY request.added_on DESC";
        
        if ($limit_by) {
            $sql .= <<< EOT

LIMIT ?
EOT;
            $binds[] = (int)$limit_by;
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
   }

    /**
     * Get processors of request
     */
    public function get_processors_by_request_id($request_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT
    CONCAT(processor_user.first_name, " ", processor_user.last_name) AS processor_name
FROM request
LEFT JOIN transfer AS processor ON processor.request_id = request.id AND processor.is_deleted = 0
LEFT JOIN user AS processor_user ON processor_user.id = processor.added_by
WHERE request.is_deleted = 0
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : [];
    }    

}
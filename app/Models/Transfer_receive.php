<?php

namespace App\Models;

class Transfer_receive extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'transfer_id',
        'branch_from',
        'branch_to',
        'transfer_receive_number',
        'transfer_receive_date',
        'remarks',
        'grand_total',
        'status',
        'completed_by',
        'completed_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'transfer_receive';
    }

    /**
     * Get transfer_receive details by ID
     */
    public function get_details_by_id($transfer_receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_receive
WHERE is_deleted = 0
EOT;
        $binds = [];
        if ($transfer_receive_id) {
            $sql .= " AND id = ?";
            $binds[] = $transfer_receive_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get transfer_receive details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_receive
WHERE transfer_receive.is_deleted = 0
EOT;
        $binds = [];
        if ($status) {
            $sql .= " AND transfer_receive.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all transfer_receives
     */
    public function get_all_transfer_receive()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_receive
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get transfer_receives based on transfer_receive name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($branch_from = null, $branch_to = null, $transfer_receive_number = null, $transfer_receive_date = null, $remarks = null, $grand_total = null, $status = null, $transfer_id)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT transfer_receive.*, 
    transfer.transfer_number,
    branch.name AS branch_from_name,
    transfer.transfer_date
FROM transfer_receive
LEFT JOIN transfer ON transfer_receive.transfer_id = transfer.id
LEFT JOIN branch ON branch.id = transfer_receive.branch_from
WHERE transfer_receive.is_deleted = 0
EOT;
        $binds = [];
        if ($branch_from) {
            $sql .= " AND transfer_receive.branch_from = ?";
            $binds[] = $branch_from;
        }
        if ($branch_to) {
            $sql .= " AND transfer_receive.branch_to = ?";
            $binds[] = $branch_to;
        }
        if ($transfer_receive_number) {
            $sql .= " AND transfer_receive.transfer_receive_number = ?";
            $binds[] = $transfer_receive_number;
        }
        if ($transfer_receive_date) {
            $sql .= " AND transfer_receive.transfer_receive_date = ?";
            $binds[] = $transfer_receive_date;
        }
        if ($remarks) {
            $sql .= " AND transfer_receive.remarks = ?";
            $binds[] = $remarks;
        }
        if ($grand_total) {
            $sql .= " AND transfer_receive.grand_total = ?";
            $binds[] = $grand_total;
        }
        if ($status) {
            $sql .= " AND transfer_receive.status = ?";
            $binds[] = $status;
        }
        if ($transfer_id) {
            $sql .= " AND transfer_receive.transfer_id = ?";
            $binds[] = $transfer_id;
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
   }

}
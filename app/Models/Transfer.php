<?php

namespace App\Models;

class Transfer extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'request_id',
        'branch_from',
        'branch_to',
        'dispatcher',
        'transfer_number',
        'transfer_date',
        'remarks',
        'grand_total',
        'status',
        'transfer_status',
        'completed_by',
        'completed_on',
        'approved_by',
        'approved_on',
        'rejected_by',
        'rejected_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'transfer';
    }

    /**
     * Get transfer report
     */
    public function transfer_report($item_id, $date_from, $date_to, $branch_from, $branch_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT COUNT(transfer.id) AS total_transfer, item.name, transfer_item.unit, SUM(transfer_item.qty) AS total_qty, source_branch.name AS source_branch, destination_branch.name AS destination_branch
FROM transfer
LEFT JOIN transfer_item ON transfer.id = transfer_item.transfer_id
LEFT JOIN item ON item.id = transfer_item.item_id
LEFT JOIN branch AS source_branch ON source_branch.id = transfer.branch_from
LEFT JOIN branch AS destination_branch ON destination_branch.id = transfer.branch_to
WHERE transfer.is_deleted = 0
    AND transfer.transfer_date BETWEEN ? AND ?
EOT;
        $binds = [];

        if (!$date_from AND !$date_to) {
            $date_to = date("Y-m-d");
            $date_from = date("Y-m-d", strtotime($date_to . "-1 week"));
        } elseif (!$date_from) {
            $date_from = date("Y-m-d", strtotime($date_to . "-1 week"));
        } elseif (!$date_to) {
            $date_to = date("Y-m-d");
        }

        $binds[] = $date_from;
        $binds[] = $date_to;

        if ($item_id) {
            $sql .= " AND transfer_item.item_id = ?";
            $binds[] = $item_id;
        }

        if ($branch_from) {
            $sql .= " AND transfer.branch_from = ?";
            $binds[] = $branch_from;
        }

        if ($branch_to) {
            $sql .= " AND transfer.branch_to = ?";
            $binds[] = $branch_to;
        }

        $sql .= " GROUP BY transfer_item.item_id, transfer_item.unit, transfer.branch_from, transfer.branch_to";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get transfer details by ID
     */
    public function get_details_by_id($transfer_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM branch WHERE id = branch_from) AS branch_from_name,
    (SELECT name FROM branch WHERE id = branch_to) AS branch_to_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = completed_by) AS completed_by_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = added_by) AS added_by_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM employee WHERE id = dispatcher) AS dispatcher_name
FROM transfer
WHERE is_deleted = 0
EOT;
        $binds = [];
        if ($transfer_id) {
            $sql .= " AND id = ?";
            $binds[] = $transfer_id;
        }

        $sql .= " ORDER BY added_on DESC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get transfer details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM branch WHERE id = branch_from) AS branch_from_name,
    (SELECT name FROM branch WHERE id = branch_to) AS branch_to_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = completed_by) AS completed_by_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = added_by) AS added_by_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM employee WHERE id = dispatcher) AS dispatcher_name
FROM transfer
WHERE transfer.id > 0
EOT;
        $binds = [];
        if ($status) {
            $sql .= " AND transfer.status = ?";
            $binds[] = $status;
        }

        $sql .= " ORDER BY added_on DESC";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all transfers
     */
    public function get_all_transfer()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM branch WHERE id = branch_from) AS branch_from_name,
    (SELECT name FROM branch WHERE id = branch_to) AS branch_to_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = completed_by) AS completed_by_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = added_by) AS added_by_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM employee WHERE id = dispatcher) AS dispatcher_name
FROM transfer
WHERE is_deleted = 0
ORDER BY added_on DESC
EOT;
        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get transfers based on transfer name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search($transfer_id, $branch_from, $branch_to, $transfer_number, $transfer_date_to, $transfer_date_from, $remarks, $grand_total, $status, $limit_by)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *,
    (SELECT name FROM branch WHERE id = branch_from) AS branch_from_name,
    (SELECT name FROM branch WHERE id = branch_to) AS branch_to_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = transfer.completed_by) AS completed_by_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = transfer.added_by) AS added_by_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM employee WHERE id = transfer.dispatcher) AS dispatcher_name,
    (SELECT CONCAT(last_name, ', ', first_name, ' ', middle_name) FROM user WHERE id = transfer.approved_by) AS approved_by_name
FROM transfer
WHERE transfer.is_deleted = 0
EOT;
        $binds = [];
        if ($transfer_id) {
            $sql .= " AND transfer.id LIKE ?";
            $binds[] = $transfer_id . "%";
        }
        
        if ($branch_from) {
            $branch_from = explode(',', $branch_from);
            $sql .= " AND transfer.branch_from IN (";
            foreach ($branch_from as $key => $value) {
                $sql .= "?,";
                $binds[] = $value;
            }
            $sql = rtrim($sql, ',');
            $sql .= ")";
        }
        
        if ($branch_to) {
            $branch_to = explode(',', $branch_to);
            $sql .= " AND transfer.branch_to IN (";
            foreach ($branch_to as $key => $value) {
                $sql .= "?,";
                $binds[] = $value;
            }
            $sql = rtrim($sql, ',');
            $sql .= ")";
        }
        

        if ($transfer_number) {
            $sql .= " AND transfer.transfer_number LIKE ?";
            $binds[] = "%{$transfer_number}%";
        }
        if ($transfer_date_to) {
            $sql .= " AND transfer.transfer_date <= ?";
            $binds[] = $transfer_date_to;
        }
        if ($transfer_date_from) {
            $sql .= " AND transfer.transfer_date >= ?";
            $binds[] = $transfer_date_from;
        }
        if ($remarks) {
            $sql .= " AND transfer.remarks = ?";
            $binds[] = $remarks;
        }
        if ($grand_total) {
            $sql .= " AND transfer.grand_total = ?";
            $binds[] = $grand_total;
        }
        
        if ($status) {
            $statuses = explode(',', $status);
            $sql .= " AND transfer.status IN (";
            foreach ($statuses as $key => $value) {
                $sql .= "?,";
                $binds[] = $value;
            }
            $sql = rtrim($sql, ',');
            $sql .= ")";
        }

        $sql .= " ORDER BY transfer.added_on DESC";
        
        if ($limit_by) {
            $sql .= " LIMIT ?";
            $binds[] = (int)$limit_by;
        }
        
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
   }

    /**
     * Get Receive items
     */
    public function get_received_items($item_id, $item_name, $transfer_date_from, $transfer_date_to, $branch_from_id, $branch_to_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT
    COUNT(transfer.id) AS no_of_transfer,
    branch_from.name AS branch_from_name,
    branch_to.name AS branch_to_name,
    SUM(transfer_item.qty) AS quantity,
    SUM(transfer_item.received_qty) AS received_qty,
    SUM(total) AS total_amount,
    item.name AS item_name,
    transfer_item.unit AS item_unit,
    item.id AS item_id
FROM transfer_item
LEFT JOIN transfer ON transfer.id = transfer_item.transfer_id
LEFT JOIN item ON item.id = transfer_item.item_id
LEFT JOIN branch AS branch_from ON branch_from.id = transfer.branch_from
LEFT JOIN branch AS branch_to ON branch_to.id = transfer.branch_to
WHERE transfer.is_deleted = 0
EOT;

        $binds = [];
        if ($item_id) {
            $sql .= " AND transfer_item.item_id = ?";
            $binds[] = $item_id;
        }
        
        if ($item_name) {
            $sql .= " AND item.name LIKE ?";
            $binds[] = "%{$item_name}%";
        }

        if ($transfer_date_from) {
            $sql .= " AND transfer.transfer_date >= ?";
            $binds[] = $transfer_date_from;
        }

        if ($transfer_date_to) {
            $sql .= " AND transfer.transfer_date <= ?";
            $binds[] = $transfer_date_to;
        }

        if ($branch_from_id) {
            $sql .= " AND transfer.branch_from = ?";
            $binds[] = $branch_from_id;
        }

        if ($branch_to_id) {
            $sql .= " AND transfer.branch_to = ?";
            $binds[] = $branch_to_id;
        }

        $sql .= " GROUP BY transfer_item.item_id, transfer_item.unit, transfer.branch_from, transfer.branch_to";
        $sql .= " ORDER BY transfer.transfer_date DESC";


        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}
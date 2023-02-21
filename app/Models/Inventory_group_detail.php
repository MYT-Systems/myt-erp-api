<?php

namespace App\Models;

class Inventory_group_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'inventory_group_id',
        'branch_id',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'inventory_group_detail';
    }

    /**
     * Get details by inventory group id
     */
    public function get_details_by_inventory_group_id($inventory_group_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT inventory_group_detail.*,
    branch.name AS branch_name
FROM inventory_group_detail
LEFT JOIN branch ON branch.id = inventory_group_detail.branch_id
WHERE inventory_group_detail.is_deleted = 0
EOT;
        $binds = [];
        if ($inventory_group_id) {
            $sql .= " AND inventory_group_detail.inventory_group_id = ?";
            $binds[] = $inventory_group_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete by inventory group id
     */
    public function delete_by_inventory_group_id($inventory_group_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE inventory_group_detail
SET is_deleted = 1,
    updated_by = ?,
    updated_on = ?
WHERE inventory_group_id = ?
EOT;
        $binds = [$requested_by, $date_now, $inventory_group_id];

        return $database->query($sql, $binds);
    }
   
    /*
    * insert_on_duplicate
    */
    public function insert_on_duplicate($data = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO inventory_group_detail
(
    inventory_group_id,
    branch_id,
    added_by,
    added_on,
    updated_by,
    updated_on,
    is_deleted
)
VALUES
(
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    0
)
ON DUPLICATE KEY UPDATE
    updated_by = ?,
    updated_on = ?,
    is_deleted = 0
EOT;
        $binds = [
            $data['inventory_group_id'],
            $data['branch_id'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now,
            $requested_by,
            $date_now
        ];

        return $database->query($sql, $binds);
    }

    /**
     * Get branches by inventory group id
     */
    public function get_branches_by_inventory_group_id($inventory_group_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT branch_id
FROM inventory_group_detail
WHERE is_deleted = 0
    AND inventory_group_id = ?
EOT;
        $binds = [$inventory_group_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : [];
    }

    
    /**
     * Get branch group details by branch id, branch group id
     */
    public function get_details_by_branch_id_and_branch_group_id($branch_id = null, $inventory_group_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT id
FROM inventory_group_detail
WHERE is_deleted = 0
EOT;
        $binds = [];
        if ($branch_id) {
            $sql .= " AND branch_id = ?";
            $binds[] = $branch_id;
        }
        if ($inventory_group_id) {
            $sql .= " AND inventory_group_id = ?";
            $binds[] = $inventory_group_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getRowArray() : false;
    }
}
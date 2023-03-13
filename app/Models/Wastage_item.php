<?php

namespace App\Models;

class Wastage_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'wastage_id',
        'name',
        'item_id',
        'qty',
        'unit',
        'reason',
        'remarks',
        'wasted_by',
        'wastage_cost',
        'status',
        'status_change_by',
        'status_change_on',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'wastage_item';
    }

    /**
     * Get wastage_item details by ID
     */
    public function get_details_by_id($wastage_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM wastage_item
WHERE wastage_item.is_deleted = 0
    AND wastage_item.id = ?
EOT;
        $binds = [$wastage_item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all wastage_items
     */
    public function get_all_wastage_item()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM wastage_item
WHERE wastage_item.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

  
    /**
     * Get all wastage_items by wastage_id
     */
    public function get_all_wastage_item_by_wastage_id($wastage_id = null, $item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT wastage_item.*, CONCAT(employee.first_name, " ", employee.last_name) AS wasted_by_name
FROM wastage_item
LEFT JOIN employee
    ON employee.id = wastage_item.wasted_by
WHERE wastage_item.is_deleted = 0
    AND wastage_item.wastage_id = ?
EOT;
        $binds = [$wastage_id];

        if ($item_id) {
            $sql .= " AND wastage_item.item_id = ?";
            $binds[] = $item_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete wastage_item by wastage_id
     */
    public function delete_wastage_item_by_wastage_id($wastage_id = null, $requested_by = null, $db = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE wastage_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE wastage_item.is_deleted = 0
    AND wastage_item.wastage_id = ?
EOT;
        $binds = [$requested_by, $date_now, $wastage_id];
        return $database->query($sql, $binds);
    }

    /**
     * Get request details by ID
     */
    public function get_by_status($status = null, $branches = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT wastage_item.*
FROM wastage_item
LEFT JOIN wastage ON wastage_item.wastage_id = wastage.id
WHERE wastage_item.is_deleted = 0
EOT;
        $binds = [];
        if ($status) {
            $sql .= " AND wastage_item.status = ?";
            $binds[] = $status;
        }

        if ($branches) {
            $sql .= " AND wastage.branch_id IN ?";
            $binds[] = $branches;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Wastage cost total per employee
     */
    public function get_cost_per_employee($employee_id, $date_from, $date_to)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT SUM(wastage_item.cost * wastage_item.qty) AS total_cost
FROM wastage_item
WHERE wastage_item.is_deleted = 0
    AND wastage_item.status = "approved"
    AND wastage_item.wasted_by = ?
    AND DATE(wastage_item.added_on) BETWEEN ? AND ?
EOT;

        $binds = [$employee_id, $date_from, $date_to];

        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }
}
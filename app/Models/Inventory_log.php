<?php

namespace App\Models;

class Inventory_log extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'inventory_id',
        'item_id',
        'item_unit_id',
        'type',
        'source_id',
        'table_name',
        'supplier_id',
        'doc_no',
        'origin',
        'qty_in',
        'qty_out',
        'current_qty',
        'unit',
        'status',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'inventory_log';
    }

    /**
     * Get inventory_log by ID
     */
    public function get_inventory_log_by_id($inventory_log_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT percentage
FROM inventory_log
WHERE inventory_log.is_deleted = 0
    AND inventory_log.id = ?
EOT;
        $binds = [$inventory_log_id];

        $query = $database->query($sql, $binds);
        return $query ? (float)$query->getResultArray()[0]['percentage'] : false;
    }

    /**
     * Get inventory_log details by ID
     */
    public function get_details_by_id($inventory_log_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM inventory_log
WHERE inventory_log.is_deleted = 0
    AND inventory_log.id = ?
EOT;
        $binds = [$inventory_log_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all inventory_logs
     */
    public function get_all_inventory_log()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM inventory_log
WHERE inventory_log.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get inventory_logess based on transaction_type_id, branch_id, commission
     */
    public function search($branch_id = null, $item_id = null, $beginning_qty = null, $current_qty = null, $unit = null, $status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM inventory_log
WHERE inventory_log.is_deleted = 0
EOT;
        $binds = [];

        if ($branch_id) {
            $sql .= ' AND inventory_log.branch_id = ?';
            $binds[] = $branch_id;
        }

        if ($item_id) {
            $sql .= ' AND inventory_log.item_id = ?';
            $binds[] = $item_id;
        }

        if ($beginning_qty) {
            $sql .= ' AND inventory_log.beginning_qty = ?';
            $binds[] = $beginning_qty;
        }

        if ($current_qty) {
            $sql .= ' AND inventory_log.current_qty = ?';
            $binds[] = $current_qty;
        }

        if ($unit) {
            $sql .= ' AND inventory_log.unit = ?';
            $binds[] = $unit;
        }

        if ($status) {
            $sql .= ' AND inventory_log.status = ?';
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}
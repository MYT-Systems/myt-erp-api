<?php

namespace App\Models;

class wastage_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'wastage_id',
        'name',
        'qty',
        'unit',
        'reason',
        'type',
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
    public function get_all_wastage_item_by_wastage_id($wastage_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM wastage_item
WHERE wastage_item.is_deleted = 0
    AND wastage_item.wastage_id = ?
EOT;
        $binds = [$wastage_id];

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
}
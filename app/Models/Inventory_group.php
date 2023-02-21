<?php

namespace App\Models;

class Inventory_group extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'details',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'inventory_group';
    }

    /**
     * Get details by id
     */
    public function get_details_by_id($id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM inventory_group
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all inventory group
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM inventory_group
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search function
     */
    public function search($name = null, $min = null, $max = null, $acceptable_variance = null, $details = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM inventory_group
WHERE inventory_group.is_deleted = 0
EOT;
        $binds = [];
        if ($name) {
            $sql .= " AND name LIKE ?";
            $binds[] = "%$name%";
        }

        if ($min) {
            $sql .= " AND min = ?";
            $binds[] = $min;
        }

        if ($max) {
            $sql .= " AND max = ?";
            $binds[] = $max;
        }

        if ($acceptable_variance) {
            $sql .= " AND acceptable_variance = ?";
            $binds[] = $acceptable_variance;
        }

        if ($details) {
            $sql .= " AND details LIKE ?";
            $binds[] = "%$details%";
        } 

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}
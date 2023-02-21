<?php

namespace App\Models;

class Price_level extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'price_level';
    }

    /**
     * Get details by ID
     */
    public function get_details_by_id($id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT
    price_level.id,
    price_level.name
FROM price_level
WHERE is_deleted = 0
    AND id = ?
EOT;
        $bind = [$id];

        $query = $database->query($sql, $bind);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all details
     */
    public function get_all_price_levels()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM price_level
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search
     */
    public function search($name = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM price_level
WHERE is_deleted = 0
EOT;
        $bind = [];

        if ($name) {
            $sql .= ' AND name LIKE ?';
            $bind[] = '%' . $name . '%';
        }

        $query = $database->query($sql, $bind);
        return $query ? $query->getResultArray() : false;
    }
    
    public function get_price($addon_id, $price_level_type_id, $price_level_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT price
FROM price_level_type_detail
WHERE is_deleted = 0
    AND product_id = ?
    AND price_level_type_id = ?
    AND price_level_id = ?
EOT;
        $binds = [$addon_id, $price_level_type_id, $price_level_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_commission($price_level_type_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT commission_rate
FROM price_level_type
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$price_level_type_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}
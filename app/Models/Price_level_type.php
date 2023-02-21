<?php

namespace App\Models;

class Price_level_type extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'price_level_id',
        'name',
        'commission_rate',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'price_level_type';
    }

    /**
     * Get details by ID
     */
    public function get_details_by_id($id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT
    price_level_type.id,
    price_level_type.name,
    price_level_type.commission_rate
FROM price_level_type
WHERE is_deleted = 0
    AND id = ?
EOT;
        $bind = [$id];
        $query = $database->query($sql, $bind);
        
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details by ID
     */
    public function get_details_by_price_level_id($price_level_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT 
    price_level_type.id,
    price_level_type.price_level_id,
    price_level_type.name,
    price_level_type.commission_rate
FROM price_level_type
WHERE is_deleted = 0
    AND price_level_id = ?
EOT;
        $bind = [$price_level_id];

        $query = $database->query($sql, $bind);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete price level type by price level id
     */
    public function delete_by_price_level_id($price_level_id, $requested_by)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE price_level_type
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE price_level_id = ?
EOT;
        $bind = [$requested_by, $date_now, $price_level_id];
        return $database->query($sql, $bind);
    }
}
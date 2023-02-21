<?php

namespace App\Models;

class Price_level_type_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'price_level_id',
        'price_level_type_id',
        'product_id',
        'price',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'price_level_type_detail';
    }

    /**
     * Get details by ID
     */
    public function get_details_by_price_level_type_id($price_level_type_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT 
    price_level_type_detail.price_level_type_id,
    price_level_type_detail.product_id,
    price_level_type_detail.price,
    product.name AS product_name,
    product.details AS product_details,
    product.is_addon
FROM price_level_type_detail
LEFT JOIN product ON product.id = price_level_type_detail.product_id
WHERE price_level_type_detail.is_deleted = 0
    AND price_level_type_detail.price_level_type_id = ?
EOT;
        $bind = [$price_level_type_id];

        $query = $database->query($sql, $bind);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete price level type detail by price level type id
     */
    public function delete_by_price_level_type_id($price_level_type_id  = null, $requested_by  = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        
        $sql = <<<EOT
UPDATE price_level_type_detail
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE price_level_type_id = ?
EOT;
        $bind = [$requested_by, $date_now, $price_level_type_id];
        return $database->query($sql, $bind);
    }
}
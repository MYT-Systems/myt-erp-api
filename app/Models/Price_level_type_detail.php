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
     * Search price level type detail
     */
    public function search($price_level_id, $price_level_type_id, $product_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT price_level_type_detail.*,
    price_level.name AS price_level_name,
    price_level_type.name AS price_level_type_name,
    product.name AS product_name
FROM price_level_type_detail
LEFT JOIN product ON product.id = price_level_type_detail.product_id
LEFT JOIN price_level ON price_level.id = price_level_type_detail.price_level_id
LEFT JOIN price_level_type ON price_level_type.id = price_level_type_detail.price_level_type_id
WHERE price_level_type_detail.is_deleted = 0
EOT;
        $binds = [];

        if ($price_level_id) {
            $sql .= " AND price_level_type_detail.price_level_id = ?";
            $binds[] = $price_level_id;
        }

        if ($price_level_type_id) {
            $sql .= " AND price_level_type_detail.price_level_type_id = ?";
            $binds[] = $price_level_type_id;
        }

        if ($product_id) {
            $sql .= " AND price_level_type_detail.product_id = ?";
            $binds[] = $product_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
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
    AND product.is_deleted = 0
    AND price_level_type_detail.price_level_type_id = ?
ORDER BY product.priority
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

    /**
     * Get price levels by product
     */
    public function get_price_level_by_product($product_id)
    {
        $database = \Config\Database::connect();
        
        $sql = <<<EOT
SELECT DISTINCT price_level_id
FROM price_level_type_detail
WHERE product_id = ?
    AND is_deleted = 0
EOT;
        $binds = [$product_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete price level type detail by price level and product
     */
    public function delete_by_product_and_price_level($product_id, $price_level_ids, $requested_by)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        
        $sql = <<<EOT
UPDATE price_level_type_detail
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE price_level_id IN ? AND product_id = ?
EOT;
        $binds = [$requested_by, $date_now, $price_level_ids, $product_id];
        return $database->query($sql, $binds);
    }
}
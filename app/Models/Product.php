<?php

namespace App\Models;

class Product extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'is_addon',
        'details',
        'image64',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'product';
    }
    
    /**
     * Get product details by ID
     */
    public function get_details_by_id($product_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM product
WHERE product.is_deleted = 0
EOT;
        $binds = [];
        if (isset($product_id)) {
            $sql .= " AND product.id = ?";
            $binds[] = $product_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all products
     */
    public function get_all_product($product_name = null, $is_addon = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM product
WHERE product.is_deleted = 0
EOT;
        $binds = [];

        if (isset($product_name)) {
            $sql .= " AND product.name LIKE ?";
            $binds[] = "%$product_name%";
        }

        if ($is_addon !== null) {
            $sql .= " AND product.is_addon = ?";
            $binds[] = $is_addon;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get product details by product name
     */
    public function get_details_by_product_name($product_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM product
WHERE product.is_deleted = 0
    AND product.name = ?
EOT;
        $binds = [$product_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult();
    }

    /**
     * Get products based on product name, contact_person, phone_no, tin_no, bir_no, email
     */
    public function search($name = null, $is_addon = null, $details = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM product
WHERE product.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND product.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($is_addon) {
            $sql .= " AND is_addon = ?";
            $binds[] = $is_addon;
        }

        if ($details) {
            $sql .= " AND details REGEXP ?";
            $details = str_replace(' ', '|', $details);
            $binds[]        = $details;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function get_price_level_by_product($product_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT price_level.id, price_level.name
FROM price_level_type_detail
LEFT JOIN price_level ON price_level.id = price_level_type_detail.price_level_id
WHERE product_id = ?
    AND price_level_type_detail.is_deleted = 0
GROUP BY price_level.id
EOT;
        $binds = [$product_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}
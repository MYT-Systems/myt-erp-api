<?php

namespace App\Models;

class Product_price extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'branch_id',
        'product_id',
        'transaction_type_id',
        'price',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'product_price';
    }
    
    /**
     * Get product_price details by ID
     */
    public function get_details_by_id($product_price_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM product_price
WHERE product_price.is_deleted = 0
EOT;
        $binds = [];
        if (isset($product_price_id)) {
            $sql .= " AND product_price.id = ?";
            $binds[] = $product_price_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all product_prices
     */
    public function get_all_product_price()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM product_price
WHERE product_price.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get product's price by branch_id and transaction id
     */
    public function get_price($branch_id = null, $product_id = null, $transaction_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT price
FROM product_price
WHERE product_price.is_deleted = 0
    AND product_price.product_id = ?
    AND product_price.branch_id = ?
    AND product_price.transaction_type_id = ?
EOT;

        $binds = [$product_id, $branch_id, $transaction_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}
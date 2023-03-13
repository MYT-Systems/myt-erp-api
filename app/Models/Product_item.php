<?php

namespace App\Models;

class Product_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'product_id',
        'item_id',
        'type',
        'qty',
        'unit',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'product_item';
    }

    public function delete_by_product_id($product_id = null, $requester = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
UPDATE product_item
SET is_deleted = 1, updated_by = ?
WHERE is_deleted = 0
    AND product_id = ?
EOT;
        $binds = [$requester, $product_id];
        return $database->query($sql, $binds);
    }

    public function get_details_by_product_id($product_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE item.id = product_item.item_id) AS item_name
FROM product_item
WHERE is_deleted = 0
    AND product_id = ?
EOT;
        $binds = [$product_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    public function search($product_id = null, $type = null, $include_both = true)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE item.id = product_item.item_id) AS item_name
FROM product_item
WHERE is_deleted = 0
EOT;

        $binds = [];

        if ($product_id) {
            $sql .= " AND product_id = ?";
            $binds[] = $product_id;
        }

        if ($type) {
            if ($include_both)
                $sql .= ' AND type IN (?, "both")';
            else
                $sql .= ' AND type IN (?)';
            $binds[] = $type;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}
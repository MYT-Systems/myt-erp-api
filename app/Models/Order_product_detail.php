<?php

namespace App\Models;

class Order_product_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'order_detail_id',
        'addon_id',
        'qty',
        'price',
        'subtotal',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'order_product_detail';
    }
    
    /**
     * Get order_product_detail details by ID
     */
    public function get_details_by_id($order_product_detail_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM order_product_detail
WHERE order_product_detail.is_deleted = 0
EOT;
        $binds = [];
        if (isset($order_product_detail_id)) {
            $sql .= " AND order_product_detail.id = ?";
            $binds[] = $order_product_detail_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all order_product_details
     */
    public function get_all_order_product_detail()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM order_product_detail
WHERE order_product_detail.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get order_product_detail details by order_product_detail name
     */
    public function get_details_by_order_product_detail_name($order_product_detail_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM order_product_detail
WHERE order_product_detail.is_deleted = 0
    AND order_product_detail.name = ?
EOT;
        $binds = [$order_product_detail_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult()[0];
    }

    /**
     * Get order_product_detailess based on order_product_detail name, address, contact_person, contact_person_no, tin_no, bir_no
     */
    public function search()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM order_product_detail
WHERE order_product_detail.is_deleted = 0
EOT;
        $binds = [];


        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get order_product_detail details by order_detail_id
     */
    public function get_details_by_order_detail_id($order_detail_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT order_product_detail.*,
    product.name AS product_name
FROM order_product_detail
LEFT JOIN product ON product.id = order_product_detail.addon_id
WHERE order_product_detail.is_deleted = 0
    AND order_product_detail.order_detail_id = ?
EOT;
        $binds = [$order_detail_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Insert on duplicate key update
     */
    public function insert_on_duplicate_key_update($data = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO order_product_detail (
    order_detail_id,
    addon_id,
    qty,
    price,
    subtotal,
    added_by,
    added_on,
    updated_by,
    updated_on,
    is_deleted
) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
ON DUPLICATE KEY UPDATE
    qty = VALUES(qty),
    price = VALUES(price),
    subtotal = VALUES(subtotal),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;
        $binds = [
            $data['order_detail_id'],
            $data['addon_id'],
            $data['qty'],
            $data['price'],
            $data['subtotal'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now,
            0
        ];



        $query = $database->query($sql, $binds);
        return $query ? $database->insertID() : false;
    }
}
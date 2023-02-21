<?php

namespace App\Models;

class Order_detail extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'order_id',
        'product_id',
        'price',
        'qty',
        'subtotal',
        'remarks',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'order_detail';
    }
    
    /**
     * Get order_detail details by ID
     */
    public function get_details_by_id($order_detail_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT product.name AS product_name, order_detail.*
FROM order_detail
LEFT JOIN product ON product.id = order_detail.product_id
WHERE order_detail.is_deleted = 0
    AND order_detail.id = ?
EOT;
        $binds = [$order_detail_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get order_detail details by order ID
     */
    public function get_details_by_order_id($order_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT product.name AS product_name, order_detail.*
FROM order_detail
LEFT JOIN product ON product.id = order_detail.product_id
WHERE order_detail.is_deleted = 0
    AND order_detail.order_id = ?
EOT;
        $binds = [$order_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }



    /**
     * Get all order_details
     */
    public function get_all_order_detail()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM order_detail
WHERE order_detail.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get order_detail details by order_detail name
     */
    public function get_details_by_order_detail_name($order_detail_name)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM order_detail
WHERE order_detail.is_deleted = 0
    AND order_detail.name = ?
EOT;
        $binds = [$order_detail_name];
        $query = $database->query($sql, $binds);

        return !$query->getResult() ? false : $query->getResult();
    }

    /**
     * Get order_detailess based on order_detail name, address, contact_person, contact_person_no, tin_no, bir_no
     */
    public function search($name = null, $address = null, $contact_person = null, $contact_person_no = null, 
                           $franchisee_name = null, $franchisee_contact_no = null, $tin_no = null, $bir_no = null,
                           $contract_start = null, $contract_end = null, $opening_date = null)
    {
        $database = \Config\Database::connect();
        
        $sql = <<<EOT
SELECT *
FROM order_detail
WHERE order_detail.is_deleted = 0
EOT;
        $binds = [];

        if ($name) {
            $sql .= " AND order_detail.name REGEXP ?";
            $name    = str_replace(' ', '|', $name);
            $binds[] = $name;
        }

        if ($address) {
            $sql .= " AND incharge REGEXP ?";
            $address = str_replace(' ', '|', $address);
            $binds[] = $address;
        }

        if ($contact_person) {
            $sql .= " AND contact_person REGEXP ?";
            $contact_person = str_replace(' ', '|', $contact_person);
            $binds[]        = $contact_person;
        }

        if ($contact_person_no) {
            $sql .= " AND contact_person_no = ?";
            $binds[] = $contact_person_no;
        }

        if ($franchisee_name) {
            $sql .= " AND franchisee_name REGEXP ?";
            $franchisee_name = str_replace(' ', '|', $franchisee_name);
            $binds[]         = $franchisee_name;
        }

        if ($franchisee_contact_no) {
            $sql .= " AND franchisee_contact_no = ?";
            $binds[] = $franchisee_contact_no;
        }

        if ($tin_no) {
            $sql .= " AND tin_no = ?";
            $binds[] = $tin_no;
        }

        if ($bir_no) {
            $sql .= " AND bir_no = ?";
            $binds[] = $bir_no;
        }

        if ($contract_start) {
            $sql .= " AND contract_start = ?";
            $binds[] = $contract_start;
        }

        if ($contract_end) {
            $sql .= " AND contract_end = ?";
            $binds[] = $contract_end;
        }

        if ($opening_date) {
            $sql .= " AND opening_date = ?";
            $binds[] = $opening_date;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Insert order detail on update duplicate
     */
    public function insert_on_duplicate_update($data = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
INSERT INTO order_detail (
    order_id,
    product_id,
    price,
    qty,
    subtotal,
    remarks,
    added_by,
    added_on,
    updated_by,
    updated_on,
    is_deleted
) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
ON DUPLICATE KEY UPDATE
    order_id = VALUES(order_id),
    product_id = VALUES(product_id),
    price = VALUES(price),
    qty = VALUES(qty),
    subtotal = VALUES(subtotal),
    remarks = VALUES(remarks),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;

        $binds = [
            $data['order_id'],
            $data['product_id'],
            $data['price'],
            $data['qty'],
            $data['subtotal'],
            $data['remarks'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now,
            0
        ];

        $query = $database->query($sql, $binds);
        return $query ? $database->insertID() : false;
    }

    public function get_system_inventory_sales($daily = false)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
SELECT SUM(inventory_sales.grand_total) AS grand_total
FROM (
    SELECT SUM(IFNULL(subtotal, 0)) AS grand_total
    FROM order_detail
    WHERE is_deleted = 0
    ADDITIONAL_CONDITION

    UNION

    SELECT SUM(IFNULL(subtotal, 0)) AS grand_total
    FROM order_product_detail
    WHERE is_deleted = 0
    ADDITIONAL_CONDITION
) AS inventory_sales
EOT;
        $binds = [];

        if ($daily) {
            $condition = "AND DATE(added_on) = ?";
            $sql = str_replace("ADDITIONAL_CONDITION", $condition, $sql);
            $binds = [$date_now, $date_now];
        } else {
            $sql = str_replace("ADDITIONAL_CONDITION", "", $sql);
        }

        $query = $database->query($sql, $binds);
        return ($query AND $query->getResultArray()) ? $query->getResultArray()[0] : false;
    }

}
<?php

namespace App\Models;

class Supplies_receive_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'se_receive_id',
        'se_item_id',
        'name',
        'qty',
        'unit',
        'price',
        'total',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'supplies_receive_item';
    }

    /**
     * Get supplies_receive_item details by receive ID
     */
    public function get_details_by_receive_id($se_receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT supplies_receive_item.qty 
    FROM supplies_receive_item 
    WHERE supplies_receive_item.se_receive_id = supplies_receive_item.se_receive_id
        AND supplies_receive_item.name = supplies_receive_item.name
        AND supplies_receive_item.is_deleted = 0
        ORDER BY supplies_receive_item.id DESC LIMIT 1 OFFSET 1) AS prev_received_qty,
    (SELECT se_item.qty - se_item.received_qty
        FROM se_item
        WHERE se_item.id = supplies_receive_item.se_item_id
            AND se_item.is_deleted = 0
            LIMIT 1) AS remaining_qty,
    (SELECT se_item.received_qty
        FROM se_item
        WHERE se_item.id = supplies_receive_item.se_item_id
            AND se_item.is_deleted = 0
            LIMIT 1) AS received_qty
FROM supplies_receive_item
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($se_receive_id)) {
            $sql .= " AND se_receive_id = ?";
            $binds[] = $se_receive_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    
    /**
     * Get supplies_receive_item details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplies_receive_item
WHERE supplies_receive_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND supplies_receive_item.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all supplies_receive_items
     */

    public function get_supplies_receive_items_by_receive_id($se_receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplies_receive_item
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($se_receive_id)) {
            $sql .= " AND se_receive_id = ?";
            $binds[] = $se_receive_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all receives
     */
    public function get_all_receive()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplies_receive_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get items based on item name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search(
    $supplier_id = null, $receive_date = null, $location = null, $ship_via = null, $grand_total = null, $remarks = null, 
    $requisitioner = null, $status = null, $authorized_by = null, $recommended_by = null, $approved_by = null, $disapproved_by = null, $printed_by = null)
   {
       $database = \Config\Database::connect();
       $sql = <<<EOT
SELECT *
FROM item
WHERE item.is_deleted = 0
EOT;
       $binds = [];

       if ($name) {
           $sql .= " AND item.name REGEXP ?";
           $name    = str_replace(' ', '|', $name);
           $binds[] = $name;
       }

       if ($unit) {
           $sql .= " AND item.unit REGEXP ?";
           $name    = str_replace(' ', '|', $unit);
           $binds[] = $unit;
       }

       if ($price) {
           $sql .= " AND price = ?";
           $binds[] = $price;
       }

       if ($category) {
           $sql .= " AND item.category REGEXP ?";
           $name    = str_replace(' ', '|', $category);
           $binds[] = $category;
       }

       $query = $database->query($sql, $binds);
       return $query ? $query->getResultArray() : false;
   }

    /**
     * Delete receive item by receive ID
     */
    public function delete_by_receive_id($se_receive_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE supplies_receive_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE se_receive_id = ?
EOT;
        $binds = [$requested_by, $date_now, $se_receive_id];
        return $database->query($sql, $binds);
    }

    /**
     * Get by receive ID
     */
    public function get_by_receive_id($se_receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM supplies_receive_item
WHERE is_deleted = 0
    AND se_receive_id = ?
EOT;
        $binds = [$se_receive_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

}
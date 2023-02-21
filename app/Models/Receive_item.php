<?php

namespace App\Models;

class Receive_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'receive_id',
        'inventory_id',
        'po_item_id',
        'item_id',
        'item_unit_id',
        'qty',
        'unit',
        'price',
        'type',
        'total',
        'inventory_qty',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'receive_item';
    }

    /**
     * Get receive_item details by receive ID
     */
    public function get_details_by_receive_id($receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT receive_item.*,
    (SELECT item.name FROM item WHERE item.id = receive_item.item_id) AS item_name,
    (SELECT inventory.current_qty 
        FROM inventory 
        LEFT JOIN item_unit ON inventory.item_unit_id = item_unit.id 
        WHERE item_unit.item_id = receive_item.item_id
            AND item_unit.inventory_unit = receive_item.unit
            AND inventory.branch_id = receive.branch_id 
            AND inventory.is_deleted = 0
            LIMIT 1) AS inventory_qty,
    (SELECT SUM(receive_item.qty) 
        FROM receive_item as RI
        WHERE RI.receive_id = receive_item.receive_id
            AND RI.item_id = receive_item.item_id
            AND RI.is_deleted = 0) AS prev_received_qty,
    (SELECT SUM(receive_item.qty) 
        FROM receive_item as RI
        WHERE RI.receive_id = receive_item.receive_id
            AND RI.item_id = receive_item.item_id
            AND RI.is_deleted = 0) AS received_qty,
    (SELECT purchase_item.qty - purchase_item.received_qty 
        FROM purchase_item 
        WHERE purchase_item.id = receive_item.po_item_id
            AND purchase_item.is_deleted = 0) AS remaining_qty
FROM receive_item
LEFT JOIN receive ON receive.id = receive_item.receive_id AND receive.is_deleted = 0
WHERE receive_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($receive_id)) {
            $sql .= " AND receive_id = ?";
            $binds[] = $receive_id;
        }
        
        $sql .= " GROUP BY receive_item.id";

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    
    /**
     * Get receive_item details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT item.name FROM item WHERE item.id = receive_item.item_id) AS item_name
FROM receive_item
WHERE receive_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND receive_item.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all receive_items
     */

    public function get_receive_items_by_receive_id($receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT item.name FROM item WHERE item.id = receive_item.item_id) AS item_name
FROM receive_item
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($receive_id)) {
            $sql .= " AND receive_id = ?";
            $binds[] = $receive_id;
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
SELECT *,
    (SELECT item.name FROM item WHERE item.id = receive_item.item_id) AS item_name
FROM receive_item
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
SELECT *,
    (SELECT item.name FROM item WHERE item.id = receive_item.item_id) AS item_name
FROM receive_item
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
    public function delete_by_receive_id($receive_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE receive_item
SET is_deleted = 1, updated_by = ?, 
    updated_on = ?
WHERE receive_id = ?
EOT;
        $binds = [$requested_by, $date_now, $receive_id];

        $query = $database->query($sql, $binds);
        return $query ? true : false;
    }
}
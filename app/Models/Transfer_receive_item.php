<?php

namespace App\Models;

class Transfer_receive_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'transfer_receive_id',
        'to_iventory_id',
        'from_inventory_id',
        'item_unit_id',
        'transfer_item_id',
        'item_id',
        'unit',
        'qty',
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
        $this->table = 'transfer_receive_item';
    }

    /**
     * Get transfer_receive_item details by transfer_receive ID
     */
    public function get_details_by_transfer_receive_id($transfer_receive_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT transfer_receive_item.*, item.name AS item_name, transfer_item.qty AS transfer_qty, IFNULL(transfer_item.received_qty, transfer_receive_item.qty) AS received_qty
FROM transfer_receive_item
LEFT JOIN transfer_item ON transfer_item.id = transfer_receive_item.transfer_item_id
LEFT JOIN item ON item.id = transfer_receive_item.item_id
WHERE transfer_receive_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($transfer_receive_id)) {
            $sql .= " AND transfer_receive_item.transfer_receive_id = ?";
            $binds[] = $transfer_receive_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get transfer_receive_item details by transfer_receive ID
     */
    public function get_details_by_transfer_id($transfer_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT transfer_receive_item.id, ? AS transfer_id, transfer_receive_item.item_id,
    transfer_receive_item.unit, transfer_receive_item.qty, transfer_receive_item.price,
    transfer_receive_item.total, transfer_receive_item.qty AS received_qty, "completed" AS status,
    transfer_receive_item.added_by, transfer_receive_item.added_on,
    transfer_receive_item.updated_by, transfer_receive_item.updated_on,
    transfer_receive_item.is_deleted,
    (SELECT name FROM item WHERE id = transfer_receive_item.item_id) AS item_name
FROM transfer_receive
LEFT JOIN transfer_receive_item
    ON transfer_receive.id = transfer_receive_item.transfer_receive_id
WHERE transfer_receive.is_deleted = 0
    AND transfer_receive_item.is_deleted = 0
    AND transfer_receive_item.transfer_item_id = 0
    AND transfer_receive.transfer_id = ?
EOT;
        $binds = [$transfer_id, $transfer_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    
    /**
     * Get transfer_receive_item details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_receive_item
WHERE transfer_receive_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND transfer_receive_item.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all transfer_receives
     */
    public function get_all_transfer_receive()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_receive_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get items based on item name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search(
    $supplier_id = null, $transfer_receive_date = null, $location = null, $ship_via = null, $grand_total = null, $remarks = null, 
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
    * Delete by transfer_receive ID
    */
    public function delete_by_transfer_receive_id($transfer_receive_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE transfer_receive_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE transfer_receive_id = ?
EOT;
        $binds = [$requested_by, $date_now, $transfer_receive_id];

        return $database->query($sql, $binds);
    }

    /**
     * Insert on duplicate
     */
    public function insert_on_duplicate($data = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
INSERT INTO transfer_receive_item (
    transfer_receive_id, 
    to_inventory_id,
    from_inventory_id, 
    transfer_item_id,
    item_unit_id, 
    item_id, 
    qty,
    unit, 
    price, 
    total, 
    added_by,
    added_on,
    updated_by,
    updated_on,
    is_deleted
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 0 
) ON DUPLICATE KEY UPDATE
    qty = VALUES(qty),
    unit = VALUES(unit),
    item_unit_id = VALUES(item_unit_id),
    price = VALUES(price),
    total = VALUES(total),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;
        $binds = [
            $data['transfer_receive_id'],
            $data['to_inventory_id'],
            $data['from_inventory_id'],
            $data['transfer_item_id'],
            $data['item_unit_id'],
            $data['item_id'],
            $data['qty'],
            $data['unit'],
            0,
            0,
            $requested_by,
            $date_now
        ];

        return $database->query($sql, $binds);
    }

    /**
     * Get all items that were transferred to a branch during the day
     */
    public function get_all_items_received_today($branch_id = null)
    {
        $database = \Config\Database::connect();
        $date_now = date('Y-m-d');
        $sql = <<<EOT
SELECT
    transfer_receive_item.item_id,
    item.name,
    SUM(transfer_receive_item.qty) AS qty,
    transfer_receive.transfer_receive_date
FROM transfer_receive_item
LEFT JOIN item ON item.id = transfer_receive_item.item_id
LEFT JOIN transfer_receive ON transfer_receive.id = transfer_receive_item.transfer_receive_id
WHERE transfer_receive_item.is_deleted = 0
    AND transfer_receive.branch_to = ?
    AND transfer_receive.transfer_receive_date = ?
GROUP BY transfer_receive_item.item_id
EOT;
        $binds = [$branch_id, $date_now];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
}
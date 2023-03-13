<?php

namespace App\Models;

class Transfer_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'transfer_id',
        'item_id',
        'unit',
        'qty',
        'price',
        'total',
        'received_qty',
        'status',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'transfer_item';
    }

    /**
     * Get transfer item details by ID
     */
    public function get_details_by_id($transfer_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_item
WHERE is_deleted = 0
    AND id = ?
EOT;
        $binds = [$transfer_item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get transfer_item details by transfer ID
     */
    public function get_details_by_transfer_id($transfer_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE id = transfer_item.item_id) AS item_name
FROM transfer_item
WHERE is_deleted = 0
    AND transfer_id = ?
EOT;
        $binds = [$transfer_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    
    /**
     * Get transfer_item details by ID
     */
    public function get_by_status($status = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_item
WHERE transfer_item.is_deleted = 0
EOT;
        $binds = [];
        if (isset($status)) {
            $sql .= " AND transfer_item.status = ?";
            $binds[] = $status;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all transfer_items
     */

    public function get_transfer_items_by_transfer_id($transfer_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_item
WHERE is_deleted = 0
EOT;
        $binds = [];
        if (isset($transfer_id)) {
            $sql .= " AND transfer_id = ?";
            $binds[] = $transfer_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all transfers
     */
    public function get_all_transfer()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM transfer_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get items based on item name, contact_person, phone_no, tin_no, bir_no, email
     */
   public function search(
    $supplier_id = null, $transfer_date = null, $location = null, $ship_via = null, $grand_total = null, $remarks = null, 
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
    * Delete by transfer ID
    */
    public function delete_by_transfer_id($transfer_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE transfer_item
SET is_deleted = 1, status = 'deleted', updated_by = ?, updated_on = ?
WHERE transfer_id = ?
EOT;
        $binds = [$requested_by, $date_now, $transfer_id];
        return $database->query($sql, $binds);
    }

    /**
     * Update status by transfer ID
     */
    public function update_status_by_transfer_id($transfer_id = null, $requested_by = null, $status = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE transfer_item
SET status = ?, updated_on = ?, updated_by = ?
WHERE transfer_id = ?
EOT;
        $binds = [$status, $date_now, $requested_by, $transfer_id];
        return $database->query($sql, $binds);
    }

    /**
     * Update received by where
     */
    public function update_received($where = null, $data = null, $replace_receive_qty = false, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $receive_qty_query = $replace_receive_qty ? 'received_qty = ?' : 'received_qty = received_qty + ?';

        $sql = <<<EOT
UPDATE transfer_item
SET $receive_qty_query, status = ?, updated_on = ?, updated_by = ?
WHERE item_id = ?
   AND transfer_id = ?
   AND unit = ?
EOT;
        $binds = [$data['received_qty'], $data['status'], $date_now, $data['updated_by'], $where['item_id'], $where['transfer_id'], $where['unit']];
        return $database->query($sql, $binds);
    }

    /**
     * Check if all tranfer items are received already by transfer id
     */
    public function is_all_received_by_transfer_id($transfer_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT * 
FROM transfer_item
WHERE transfer_item.is_deleted = 0
    AND transfer_id = ?
    AND received_qty < qty
EOT;
        $binds = [$transfer_id];
        $query = $database->query($sql, $binds);
        return $query ? ($query->getResultArray() ? false : true) : false;
    }

    /**
     * Insert transfer item on duplicate key update
     */
    public function insert_on_duplicate($data = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
INSERT INTO transfer_item (transfer_id, item_id, unit, qty, price, total, status, added_by, added_on)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    qty = VALUES(qty),
    price = VALUES(price),
    total = VALUES(total),
    status = VALUES(status),
    updated_by = VALUES(added_by),
    updated_on = VALUES(added_on),
    is_deleted = 0
EOT;

        $binds = [$data['transfer_id'], $data['item_id'], $data['unit'], $data['qty'], $data['price'], $data['total'], $data['status'], $requested_by, $date_now];
        return $database->query($sql, $binds);
    }

    /**
     * Get all for adjustment transfer items
     */
    public function get_all_for_adjustment()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT transfer_item.id, 
    transfer_item.transfer_id,
    transfer_item.item_id,
    item.name,
    transfer_item.received_qty,
    transfer_item.qty,
    transfer.branch_from,
    transfer.branch_to
FROM transfer_item
LEFT JOIN item ON item.id = transfer_item.item_id
LEFT JOIN transfer ON transfer.id = transfer_item.transfer_id
WHERE transfer_item.is_deleted = 0
    AND transfer_item.qty != transfer_item.received_qty
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }
}
<?php

namespace App\Models;

class Purchase_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'purchase_id',
        'item_id',
        'current_qty',
        'qty',
        'unit',
        'price',
        'amount',
        'status',
        'remarks',
        'received_qty',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'purchase_item';
    }

    /**
     * Get purchase details by ID
     */
    public function get_details_by_purchase_id($purchase_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase_item.*, 
    (SELECT item.name FROM item WHERE item.id = purchase_item.item_id) AS item_name,
    (SELECT inventory.current_qty 
        FROM inventory 
        LEFT JOIN item_unit ON inventory.item_unit_id = item_unit.id 
        WHERE item_unit.item_id = purchase_item.item_id
            AND item_unit.inventory_unit = purchase_item.unit
            AND inventory.branch_id = purchase.branch_id 
            AND inventory.item_id = purchase_item.item_id
            AND inventory.is_deleted = 0 LIMIT 1) AS inventory_qty,
    SUM(receive_item.qty) AS received_qty,
    purchase_item.qty - purchase_item.received_qty AS remaining_qty,
    (SELECT receive_item.price
        FROM receive_item
        WHERE receive_item.po_item_id = purchase_item.id
            AND receive_item.is_deleted = 0
            ORDER BY receive_item.id DESC LIMIT 1) AS previous_item_price,
    IFNULL(receive_item.updated_on, receive_item.added_on) AS last_received_date,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) 
        FROM user 
        WHERE user.id = IFNULL(receive_item.updated_by, receive_item.added_by)) AS last_received_by
FROM purchase_item
LEFT JOIN purchase ON purchase.id = purchase_item.purchase_id 
    AND purchase.is_deleted = 0
LEFT JOIN receive_item ON receive_item.po_item_id = purchase_item.id 
    AND receive_item.is_deleted = 0
WHERE purchase_id = ? 
    AND purchase_item.is_deleted = 0
GROUP BY purchase_item.id
EOT;
        $binds = [$purchase_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

        /**
     * Get purchase details by ID
     */
    public function get_details_by_id($id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase_item.*, 
    (SELECT item.name FROM item WHERE item.id = purchase_item.item_id) AS item_name,
    (SELECT inventory.current_qty 
        FROM inventory 
        LEFT JOIN item_unit ON inventory.item_unit_id = item_unit.id 
        WHERE item_unit.item_id = purchase_item.item_id
            AND item_unit.inventory_unit = purchase_item.unit
            AND inventory.branch_id = purchase.branch_id 
            AND inventory.is_deleted = 0) AS inventory_qty,
    SUM(receive_item.qty) AS received_qty,
    purchase_item.qty - purchase_item.received_qty AS remaining_qty,
    (SELECT receive_item.price
        FROM receive_item
        WHERE receive_item.po_item_id = purchase_item.id
            AND receive_item.is_deleted = 0
            ORDER BY receive_item.id DESC LIMIT 1) AS previous_item_price,
    IFNULL(receive_item.updated_on, receive_item.added_on) AS last_received_date,
    (SELECT CONCAT(user.first_name, ' ', user.last_name) 
        FROM user 
        WHERE user.id = IFNULL(receive_item.updated_by, receive_item.added_by)) AS last_received_by
FROM purchase_item
LEFT JOIN purchase ON purchase.id = purchase_item.purchase_id 
    AND purchase.is_deleted = 0
LEFT JOIN receive_item ON receive_item.po_item_id = purchase_item.id 
    AND receive_item.is_deleted = 0
WHERE purchase_id = ? 
    AND purchase_item.is_deleted = 0
GROUP BY purchase_item.id
EOT;
        $binds = [$id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Update status by purchase ID
     */
    public function update_status_by_purchase_id($purchase_id = null, $requested_by = null, $status = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE purchase_item
SET status = ?, updated_on = ?, updated_by = ?
WHERE purchase_id = ?
EOT;
        $binds = [$status, $date_now, $requested_by, $purchase_id];
        return $database->query($sql, $binds);
    }

    /**
    * Delete all purchase_items by purchase ID
    */
    public function delete_by_purchase_id($purchase_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');
        $sql = <<<EOT
UPDATE purchase_item
SET purchase_item.is_deleted = 1, updated_by = ?, updated_on = ?
WHERE purchase_id = ?
EOT;
        $binds = [$requested_by, $date_now, $purchase_id];      
        return $database->query($sql, $binds);
    }

    /**
     * Update receive qty by purchase item ID
     */
    public function update_receive_qty_by_id($id = null, $received_qty = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE purchase_item
SET purchase_item.received_qty = purchase_item.received_qty + ?, 
    purchase_item.updated_by = ?, 
    purchase_item.updated_on = ?
WHERE purchase_item.id = ?
    AND purchase_item.is_deleted = 0
EOT;
        $binds = [$received_qty, $requested_by, $date_now, $id];
        return $database->query($sql, $binds);
    }

    /**
     * Check if all purchase items are received already by purchase id
     */
    public function is_all_received_by_purchase_id($purchase_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT * 
FROM purchase_item
WHERE purchase_item.is_deleted = 0
    AND purchase_id = ?
    AND received_qty < qty
EOT;
        $binds = [$purchase_id];
        $query = $database->query($sql, $binds);
        return $query ? ($query->getResultArray() ? false : true) : false;
    }

    /**
     * Get all ongoing
     */
    public function get_all_ongoing_by_inventory($item_id, $branch_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT purchase.*
FROM purchase_item
LEFT JOIN purchase ON purchase.id = purchase_item.purchase_id AND purchase.is_deleted = 0
WHERE purchase_item.is_deleted = 0
    AND purchase_item.item_id = ?
    AND purchase.branch_id = ?
    AND purchase.order_status IN ('incomplete', 'pending')
GROUP BY purchase.id
EOT;
        $binds = [$item_id, $branch_id];
        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Insert on duplicate
     */
    public function insert_on_duplicate($data = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
INSERT INTO purchase_item (purchase_id, item_id, unit, price, qty, amount, received_qty, status, remarks, added_by, added_on, updated_by, updated_on)
VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NULL, NULL)
ON DUPLICATE KEY UPDATE
    qty = VALUES(qty),
    price = VALUES(price),
    amount = VALUES(amount),
    received_qty = VALUES(received_qty),
    status = VALUES(status),
    remarks = VALUES(remarks),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;
        $binds = [$data['purchase_id'], $data['item_id'], $data['unit'], $data['price'], $data['qty'], $data['amount'], $data['status'], $data['remarks'], $requested_by, $date_now];
        return $database->query($sql, $binds);
    }
}
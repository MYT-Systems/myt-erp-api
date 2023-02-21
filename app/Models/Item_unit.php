<?php

namespace App\Models;

class Item_unit extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'item_id',
        'price',
        'breakdown_unit',
        'breakdown_value',
        'inventory_unit',
        'inventory_value',
        'min',
        'max',
        'acceptable_variance',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'item_unit';
    }

    /**
     * Get item_unit details by ID
     */
    public function get_details_by_id($item_unit_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *
FROM item_unit
WHERE item_unit.is_deleted = 0
EOT;
        $binds = [];
        if (isset($item_unit_id)) {
            $sql .= " AND item_unit.id = ?";
            $binds[] = $item_unit_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }
    
    /**
     * Get item details by ID
     */
    public function get_details_by_item_id($item_id = null, $branch_id = null)
    {
        $database = \Config\Database::connect();

        if ($branch_id) {
            $sql = <<<EOT
    SELECT item_unit.*,
        (SELECT max - current_qty 
            FROM inventory 
            WHERE item_unit_id = item_unit.id AND branch_id = ? 
                AND item_id = ?  LIMIT 1) AS order_qty,
        (SELECT IF(current_qty IS NULL, 0, current_qty)
            FROM inventory 
            WHERE item_unit_id = item_unit.id 
            AND inventory.branch_id = ? AND inventory.item_id = ? LIMIT 1) AS current_qty,
        (SELECT receive_item.price
            FROM receive_item
            WHERE receive_item.item_id = item_unit.item_id
                AND receive_item.unit = item_unit.inventory_unit
                AND receive_item.is_deleted = 0
            ORDER BY receive_item.id DESC LIMIT 1) AS previous_item_price
    FROM item_unit
    WHERE item_unit.is_deleted = 0
        AND item_unit.item_id = ?
    EOT;
            $binds = [$branch_id, $item_id, $branch_id, $item_id, $item_id];
        } else {
            $sql = <<<EOT
    SELECT item_unit.*,
    (SELECT receive_item.price
        FROM receive_item
        WHERE receive_item.item_id = item_unit.item_id
            AND receive_item.unit = item_unit.inventory_unit
            AND receive_item.is_deleted = 0
        ORDER BY receive_item.id DESC LIMIT 1) AS previous_item_price
    FROM item_unit
    WHERE item_unit.is_deleted = 0
        AND item_unit.item_id = ?
    EOT;
            $binds = [$item_id];
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get item details by item_id and unit
     */
    public function get_details_by_item_id_and_unit($branch_id, $item_id = null, $inventory_unit = null, $breakdown_unit = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT item_unit.*,
    (SELECT max - current_qty FROM inventory WHERE item_unit_id = item_unit.id AND branch_id = ? AND item_id = ? LIMIT 1) AS order_qty,
    (SELECT current_qty FROM inventory WHERE item_unit_id = item_unit.id AND branch_id = ? AND item_id = ? LIMIT 1) AS current_qty
FROM item_unit
WHERE item_unit.is_deleted = 0
    AND item_unit.item_id = ?
EOT;
        $binds = [$branch_id, $item_id, $branch_id, $item_id, $item_id];

        if ($inventory_unit) {
            $sql .= ' AND item_unit.inventory_unit = ?';
            $binds[] = $inventory_unit;
        }

        if ($breakdown_unit) {
            $sql .= ' AND item_unit.breakdown_unit = ?';
            $binds[] = $breakdown_unit;
        }

        $query = $database->query($sql, $binds);

        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get item details by item_id
     */
    public function get_item_unit_by_item_id($item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT item_unit.*
FROM item_unit
WHERE item_unit.is_deleted = 0
    AND item_unit.item_id = ?
EOT;
        $binds = [$item_id];

        $query = $database->query($sql, $binds);

        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete item unit by item_id
     */
    public function delete_by_item_id($item_id = null, $requester = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE item_unit
SET is_deleted = 1,
    updated_by = ?,
    updated_on = ?
WHERE item_id = ?
EOT;
        $binds = [$requester, $date_now, $item_id];

        return $database->query($sql, $binds);
    }

    /**
     * Insert on duplicate key update
     */
    public function insert_on_duplicate_key_update($data = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
INSERT INTO item_unit (item_id, price, breakdown_unit, breakdown_value, inventory_unit, inventory_value, min, max, acceptable_variance, added_by, added_on, updated_by, updated_on, is_deleted)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    price = VALUES(price),
    breakdown_unit = VALUES(breakdown_unit),
    breakdown_value = VALUES(breakdown_value),
    inventory_unit = VALUES(inventory_unit),
    inventory_value = VALUES(inventory_value),
    min = VALUES(min),
    max = VALUES(max),
    acceptable_variance = VALUES(acceptable_variance),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;

        $binds = [
            $data['item_id'],
            isset($data['price']) ? $data['price'] : 0,
            $data['breakdown_unit'],
            $data['breakdown_value'],
            $data['inventory_unit'],
            $data['inventory_value'],
            $data['min'],
            $data['max'],
            $data['acceptable_variance'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now,
            0
        ];

        $database->query($sql, $binds);
        return $database->insertID();
    }

    /*
    * Get item unit by item_id
    */
    public function get_by_item_id($item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT item_unit.*
FROM item_unit
WHERE item_unit.is_deleted = 0
    AND item_unit.item_id = ?
EOT;
        $binds = [$item_id];

        $query = $database->query($sql, $binds);

        return $query ? $query->getResultArray() : false;
    }
}
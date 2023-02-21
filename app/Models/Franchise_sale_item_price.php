<?php

namespace App\Models;

class Franchise_sale_item_price extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'item_id',
        'item_unit_id',
        'unit',
        'type',
        'price_1',
        'price_2',
        'price_3',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'franchise_sale_item_price';
    }
    
    /**
     * Get franchise_sale_item_price details by ID
     */
    public function get_details_by_id($franchise_sale_item_price_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE id = item_id) AS item_name
FROM franchise_sale_item_price
WHERE franchise_sale_item_price.is_deleted = 0
EOT;
        $binds = [];
        if (isset($franchise_sale_item_price_id)) {
            $sql .= " AND franchise_sale_item_price.id = ?";
            $binds[] = $franchise_sale_item_price_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }


    /**
     * Get all franchise_sale_item_prices
     */
    public function get_all_franchise_sale_item_price()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE id = item_id) AS item_name
FROM franchise_sale_item_price
WHERE franchise_sale_item_price.is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Search: Franchise Sale Item Price
     */
    public function search($item_id, $type, $item_name, $branch_id)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE id = item_id) AS item_name,
    IFNULL((SELECT current_qty
        FROM inventory
        WHERE item_id = franchise_sale_item_price.item_id
        AND branch_id = ?
        AND item_unit_id = franchise_sale_item_price.item_unit_id
        AND is_deleted = 0
        LIMIT 1), 0) AS current_qty
FROM franchise_sale_item_price 
WHERE franchise_sale_item_price.is_deleted = 0
EOT;
        $binds = [$branch_id];
        if ($item_id) {
            $sql .= " AND item_id = ?";
            $binds[] = $item_id;
        }
        if ($type) {
            $sql .= " AND type = ?";
            $binds[] = $type;
        }
        if ($item_name) {
            $sql .= " AND item_id IN (SELECT id FROM item WHERE name LIKE ?)";
            $binds[] = "%$item_name%";
        }
        if ($branch_id) {
            $sql .= " AND item_id IN (SELECT item_id FROM inventory WHERE branch_id = ?)";
            $binds[] = $branch_id;
        }

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete by item id
     */
    public function delete_by_item_id($item_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE franchise_sale_item_price
SET is_deleted = 1,
    updated_by = ?,
    updated_on = ?
WHERE item_id = ?
EOT;

        $binds = [
            $requested_by,
            $date_now,
            $item_id
        ];

        return $database->query($sql, $binds);
    }

    /**
     * Insert on duplicate
     */
    public function insert_on_duplicate($values = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();
        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
INSERT INTO franchise_sale_item_price (
    item_id, 
    item_unit_id, 
    unit, 
    type, 
    price_1, 
    price_2, 
    price_3, 
    added_by, 
    added_on, 
    updated_by, 
    updated_on, 
    is_deleted
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
ON DUPLICATE KEY UPDATE
    price_1 = VALUES(price_1),
    price_2 = VALUES(price_2),
    price_3 = VALUES(price_3),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;

        $binds = [
            $values['item_id'],
            $values['item_unit_id'],
            $values['unit'],
            $values['type'],
            $values['price_1'],
            $values['price_2'],
            $values['price_3'],
            $requested_by,
            $date_now,
            $requested_by,
            $date_now
        ];

        $query = $database->query($sql, $binds);
        // Get the id of the inserted row
        $insert_id = $database->insertID();
        return $insert_id;
    }

}
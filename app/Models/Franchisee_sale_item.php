<?php

namespace App\Models;

class Franchisee_sale_item extends MYTModel
{
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'franchisee_sale_id',
        'item_id',
        'item_name',
        'item_unit_id',
        'unit',
        'price',
        'qty',
        'discount',
        'subtotal',
        'added_by',
        'added_on',
        'updated_by',
        'updated_on',
        'is_deleted'
    ];

    public function __construct()
    {
        $this->table = 'franchisee_sale_item';
    }

    /**
     * Get franchisee_sale_item by ID
     */
    public function get_details_by_id($franchisee_sale_item_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE item.id = franchisee_sale_item.item_id) AS item_name
FROM franchisee_sale_item
WHERE franchisee_sale_item.is_deleted = 0
    AND franchisee_sale_item.id = ?
EOT;
        $binds = [$franchisee_sale_item_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get all franchisee_sale_item
     */
    public function get_all()
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT *,
    (SELECT name FROM item WHERE item.id = franchisee_sale_item.item_id) AS item_name
FROM franchisee_sale_item
WHERE is_deleted = 0
EOT;

        $query = $database->query($sql);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Get details by franchisee sales ID
     */
    public function get_details_by_franchisee_sales_id($franchisee_sales_id = null)
    {
        $database = \Config\Database::connect();
        $sql = <<<EOT
SELECT franchisee_sale_item.*,
    IF(franchisee_sale_item.item_id IS NULL, franchisee_sale_item.item_name, item.name) AS item_name
FROM franchisee_sale_item
LEFT JOIN item ON item.id = franchisee_sale_item.item_id
WHERE franchisee_sale_item.is_deleted = 0
    AND franchisee_sale_item.franchisee_sale_id = ?
EOT;
        $binds = [$franchisee_sales_id];

        $query = $database->query($sql, $binds);
        return $query ? $query->getResultArray() : false;
    }

    /**
     * Delete franchisee_sale_item by franchisee_sale_id
     */
    public function delete_by_franchisee_sale_id($franchisee_sale_id = null, $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_now = date('Y-m-d H:i:s');

        $sql = <<<EOT
UPDATE franchisee_sale_item
SET is_deleted = 1, updated_by = ?, updated_on = ?
WHERE franchisee_sale_item.is_deleted = 0
    AND franchisee_sale_item.franchisee_sale_id = ?
EOT;
        $binds = [$requested_by, $date_now, $franchisee_sale_id];

        return $database->query($sql, $binds);
    }

    /**
     * Insert franchisee_sale_item
     */
    public function insert_on_duplicate($values = [], $requested_by = null, $db = null)
    {
        $database = $db ? $db : \Config\Database::connect();

        $date_today = date('Y-m-d H:i:s');
        $sql = <<<EOT
INSERT INTO franchisee_sale_item (franchisee_sale_id, item_id, item_name, item_unit_id, unit, price, qty, discount, subtotal, added_by, added_on, updated_by, updated_on, is_deleted)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 0)
ON DUPLICATE KEY UPDATE
    franchisee_sale_id = VALUES(franchisee_sale_id),
    item_id = VALUES(item_id),
    item_name = VALUES(item_name),
    item_unit_id = VALUES(item_unit_id),
    unit = VALUES(unit),
    price = VALUES(price),
    qty = VALUES(qty),
    discount = VALUES(discount),
    subtotal = VALUES(subtotal),
    updated_by = VALUES(updated_by),
    updated_on = VALUES(updated_on),
    is_deleted = 0
EOT;

        $binds = [
            $values['franchisee_sale_id'],
            $values['item_id'],
            $values['item_name'],
            $values['item_unit_id'],
            $values['unit'],
            $values['price'],
            $values['qty'],
            $values['discount'],
            $values['subtotal'],
            $requested_by,
            $date_today
        ];

        return $database->query($sql, $binds);
    }
}